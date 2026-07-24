const JSON_HEADERS = {
  Accept: "application/json",
  "Content-Type": "application/json",
  "X-Requested-With": "XMLHttpRequest",
};

export class ApiError extends Error {
  constructor(message, { status = 0, errors = {} } = {}) {
    super(message);
    this.name = "ApiError";
    this.status = status;
    this.errors = errors;
  }
}

function getCookie(name) {
  const prefix = `${name}=`;
  const cookie = document.cookie
    .split(";")
    .map((value) => value.trim())
    .find((value) => value.startsWith(prefix));

  return cookie ? decodeURIComponent(cookie.slice(prefix.length)) : "";
}

function errorFromResponse(payload, status) {
  const errors = payload?.errors ?? {};
  const firstValidationError = Object.values(errors)
    .flat()
    .find((message) => typeof message === "string");
  const message =
    firstValidationError ||
    payload?.message ||
    (status === 401
      ? "Your session has expired. Please sign in again."
      : "The request could not be completed.");

  return new ApiError(message, { status, errors });
}

async function parseResponse(response) {
  if (response.status === 204) return null;

  const contentType = response.headers.get("content-type") || "";
  if (!contentType.includes("application/json")) return null;

  try {
    return await response.json();
  } catch {
    return null;
  }
}

async function ensureCsrfCookie() {
  let response;

  try {
    response = await fetch("/sanctum/csrf-cookie", {
      credentials: "include",
      headers: {
        Accept: "application/json",
        "X-Requested-With": "XMLHttpRequest",
      },
    });
  } catch {
    throw new ApiError(
      "Unable to connect to AGIS. Please check that the server is running.",
    );
  }

  if (!response.ok) {
    const payload = await parseResponse(response);
    throw errorFromResponse(payload, response.status);
  }
}

async function request(path, { method = "GET", body, csrf = false } = {}) {
  if (csrf) await ensureCsrfCookie();

  const headers = { ...JSON_HEADERS };
  const xsrfToken = getCookie("XSRF-TOKEN");
  if (xsrfToken) headers["X-XSRF-TOKEN"] = xsrfToken;

  let response;

  try {
    response = await fetch(path, {
      method,
      credentials: "include",
      headers,
      body: body === undefined ? undefined : JSON.stringify(body),
    });
  } catch {
    throw new ApiError(
      "Unable to connect to AGIS. Please check that the server is running.",
    );
  }

  const payload = await parseResponse(response);
  if (!response.ok || payload?.success === false) {
    throw errorFromResponse(payload, response.status);
  }

  return payload?.data ?? null;
}

export const authApi = {
  async demoAccounts() {
    const data = await request("/api/demo-accounts");
    return Array.isArray(data) ? data : [];
  },

  async login(credentials) {
    const data = await request("/api/login", {
      method: "POST",
      body: credentials,
      csrf: true,
    });
    return data?.user ?? null;
  },

  async me() {
    const data = await request("/api/me");
    return data?.user ?? null;
  },

  async logout() {
    await request("/api/logout", { method: "POST", csrf: true });
  },
};

export const officeApi = {
  async list({ includeArchived = false } = {}) {
    const data = await request(
      `/api/offices${includeArchived ? "?include_archived=1" : ""}`,
    );
    return Array.isArray(data?.offices) ? data.offices : [];
  },

  async create(office) {
    const data = await request("/api/offices", {
      method: "POST",
      body: office,
      csrf: true,
    });
    return data?.office ?? null;
  },

  async update(id, office) {
    const data = await request(`/api/offices/${id}`, {
      method: "PUT",
      body: office,
      csrf: true,
    });
    return data?.office ?? null;
  },

  async remove(id) {
    await request(`/api/offices/${id}`, {
      method: "DELETE",
      csrf: true,
    });
  },
  async restore(id) {
    const data = await request(`/api/offices/${id}/restore`, {
      method: "POST",
      csrf: true,
    });
    return data?.office ?? null;
  },
};

export const demoApi = {
  async reset() {
    return request("/api/demo/reset", {
      method: "POST",
      csrf: true,
    });
  },
};

function crudApi(path, collectionKey, itemKey) {
  return {
    async list({ includeArchived = false } = {}) {
      const data = await request(
        `${path}${includeArchived ? "?include_archived=1" : ""}`,
      );
      return Array.isArray(data?.[collectionKey]) ? data[collectionKey] : [];
    },
    async create(payload) {
      const data = await request(path, {
        method: "POST",
        body: payload,
        csrf: true,
      });
      return data?.[itemKey] ?? null;
    },
    async update(id, payload) {
      const data = await request(`${path}/${id}`, {
        method: "PUT",
        body: payload,
        csrf: true,
      });
      return data?.[itemKey] ?? null;
    },
    async remove(id) {
      await request(`${path}/${id}`, { method: "DELETE", csrf: true });
    },
    async restore(id) {
      const data = await request(`${path}/${id}/restore`, {
        method: "POST",
        csrf: true,
      });
      return data?.[itemKey] ?? null;
    },
  };
}

export const auditAreaApi = crudApi(
  "/api/audit-areas",
  "auditAreas",
  "auditArea",
);

export const auditFocusApi = crudApi(
  "/api/audit-focuses",
  "auditFocuses",
  "auditFocus",
);

export const userApi = {
  ...crudApi("/api/users", "users", "user"),
  async resetPassword(id, password) {
    await request(`/api/users/${id}/password`, {
      method: "PUT",
      body: {
        password,
        password_confirmation: password,
      },
      csrf: true,
    });
  },
};

export const roleApi = {
  async list() {
    const data = await request("/api/roles");
    return Array.isArray(data?.roles) ? data.roles : [];
  },
  async update(id, payload) {
    await request(`/api/roles/${id}`, {
      method: "PUT",
      body: payload,
      csrf: true,
    });
  },
};

export const permissionApi = {
  async list() {
    const data = await request("/api/permissions");
    return Array.isArray(data?.permissions) ? data.permissions : [];
  },
};

export const masterListApi = {
  async list() {
    const data = await request("/api/master-lists");
    return Array.isArray(data?.masterLists) ? data.masterLists : [];
  },
  async create(payload) {
    const data = await request("/api/master-lists", {
      method: "POST",
      body: payload,
      csrf: true,
    });
    return data?.masterList ?? null;
  },
  async update(id, payload) {
    await request(`/api/master-lists/${id}`, {
      method: "PUT",
      body: payload,
      csrf: true,
    });
  },
};

export const configurationApi = {
  async list() {
    const data = await request("/api/system-configurations");
    return Array.isArray(data?.configurations) ? data.configurations : [];
  },
  async update(configurations) {
    await request("/api/system-configurations", {
      method: "PUT",
      body: { configurations },
      csrf: true,
    });
  },
};

export const activityLogApi = {
  async list() {
    const data = await request("/api/activity-logs");
    return Array.isArray(data?.activityLogs) ? data.activityLogs : [];
  },
};

export const profileApi = {
  async show() {
    const data = await request("/api/profile");
    return data?.profile ?? null;
  },
  async update(profile) {
    return request("/api/profile", {
      method: "PUT",
      body: profile,
      csrf: true,
    });
  },
  async changePassword(passwords) {
    await request("/api/profile/password", {
      method: "PUT",
      body: passwords,
      csrf: true,
    });
  },
};
