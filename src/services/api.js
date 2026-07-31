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

function queryFrom(filters = {}) {
  const query = new URLSearchParams();
  Object.entries(filters).forEach(([key, value]) => {
    if (value === "" || value === null || value === undefined) return;
    query.set(
      key,
      typeof value === "boolean" ? (value ? "1" : "0") : String(value),
    );
  });
  return query;
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

  const isFormData = body instanceof FormData;
  const headers = isFormData
    ? {
        Accept: "application/json",
        "X-Requested-With": "XMLHttpRequest",
      }
    : { ...JSON_HEADERS };
  const xsrfToken = getCookie("XSRF-TOKEN");
  if (xsrfToken) headers["X-XSRF-TOKEN"] = xsrfToken;

  let response;

  try {
    response = await fetch(path, {
      method,
      credentials: "include",
      headers,
      body:
        body === undefined
          ? undefined
          : isFormData
            ? body
            : JSON.stringify(body),
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

export const runtimeConfigurationApi = {
  async show() {
    const data = await request("/api/runtime-configuration");
    return data?.configuration ?? {};
  },
};

export const workflowApi = {
  async list({ includeArchived = false, includeCompleted = true } = {}) {
    const query = queryFrom({
      include_archived: includeArchived,
      include_completed: includeCompleted,
    });
    return request(`/api/workflows?${query.toString()}`);
  },
  async create(payload) {
    const data = await request("/api/workflows", {
      method: "POST",
      body: payload,
      csrf: true,
    });
    return data?.workflow ?? null;
  },
  async update(id, payload) {
    const data = await request(`/api/workflows/${id}`, {
      method: "PUT",
      body: payload,
      csrf: true,
    });
    return data?.workflow ?? null;
  },
  async publish(id) {
    const data = await request(`/api/workflows/${id}/publish`, {
      method: "POST",
      csrf: true,
    });
    return data?.workflow ?? null;
  },
  async createRevision(id) {
    const data = await request(`/api/workflows/${id}/revisions`, {
      method: "POST",
      csrf: true,
    });
    return data?.workflow ?? null;
  },
  async archive(id) {
    await request(`/api/workflows/${id}`, {
      method: "DELETE",
      csrf: true,
    });
  },
  async restore(id) {
    const data = await request(`/api/workflows/${id}/restore`, {
      method: "POST",
      csrf: true,
    });
    return data?.workflow ?? null;
  },
  async start(payload) {
    const data = await request("/api/workflow-instances", {
      method: "POST",
      body: payload,
      csrf: true,
    });
    return data?.instance ?? null;
  },
  async showInstance(id) {
    const data = await request(`/api/workflow-instances/${id}`);
    return data?.instance ?? null;
  },
  async transition(id, action, payload) {
    const data = await request(
      `/api/workflow-instances/${id}/transitions/${action}`,
      { method: "POST", body: payload, csrf: true },
    );
    return data?.instance ?? null;
  },
  async cancel(id, payload) {
    const data = await request(`/api/workflow-instances/${id}/cancel`, {
      method: "POST",
      body: payload,
      csrf: true,
    });
    return data?.instance ?? null;
  },
};

export const notificationApi = {
  async list(filters = {}) {
    const query = queryFrom(filters);
    return request(
      `/api/notifications${query.size ? `?${query.toString()}` : ""}`,
    );
  },
  async recent() {
    return request("/api/notifications/recent");
  },
  async markRead(id) {
    const data = await request(`/api/notifications/${id}/read`, {
      method: "POST",
      csrf: true,
    });
    return data?.notification ?? null;
  },
  async markUnread(id) {
    const data = await request(`/api/notifications/${id}/unread`, {
      method: "POST",
      csrf: true,
    });
    return data?.notification ?? null;
  },
  async markAllRead() {
    return request("/api/notifications/read-all", {
      method: "POST",
      csrf: true,
    });
  },
  async archive(id) {
    await request(`/api/notifications/${id}`, {
      method: "DELETE",
      csrf: true,
    });
  },
  async restore(id) {
    const data = await request(`/api/notifications/${id}/restore`, {
      method: "POST",
      csrf: true,
    });
    return data?.notification ?? null;
  },
  async updatePreferences(payload) {
    const data = await request("/api/notifications/preferences", {
      method: "PUT",
      body: payload,
      csrf: true,
    });
    return data?.preferences ?? null;
  },
  async deliver(payload) {
    return request("/api/notifications", {
      method: "POST",
      body: payload,
      csrf: true,
    });
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
  async show(id) {
    const data = await request(`/api/users/${id}`);
    return data?.user ?? null;
  },
  async activate(id) {
    const data = await request(`/api/users/${id}/activate`, {
      method: "POST",
      csrf: true,
    });
    return data?.user ?? null;
  },
  async disable(id) {
    const data = await request(`/api/users/${id}/disable`, {
      method: "POST",
      csrf: true,
    });
    return data?.user ?? null;
  },
  async lock(id) {
    const data = await request(`/api/users/${id}/lock`, {
      method: "POST",
      csrf: true,
    });
    return data?.user ?? null;
  },
  async unlock(id) {
    const data = await request(`/api/users/${id}/unlock`, {
      method: "POST",
      csrf: true,
    });
    return data?.user ?? null;
  },
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
  async list({ includeArchived = false } = {}) {
    const data = await request(
      `/api/roles${includeArchived ? "?include_archived=1" : ""}`,
    );
    return Array.isArray(data?.roles) ? data.roles : [];
  },
  async create(payload) {
    const data = await request("/api/roles", {
      method: "POST",
      body: payload,
      csrf: true,
    });
    return data?.role ?? null;
  },
  async update(id, payload) {
    const data = await request(`/api/roles/${id}`, {
      method: "PUT",
      body: payload,
      csrf: true,
    });
    return data?.role ?? null;
  },
  async clone(id, payload) {
    const data = await request(`/api/roles/${id}/clone`, {
      method: "POST",
      body: payload,
      csrf: true,
    });
    return data?.role ?? null;
  },
  async remove(id) {
    await request(`/api/roles/${id}`, {
      method: "DELETE",
      csrf: true,
    });
  },
  async restore(id) {
    const data = await request(`/api/roles/${id}/restore`, {
      method: "POST",
      csrf: true,
    });
    return data?.role ?? null;
  },
};

export const permissionApi = {
  async list() {
    const data = await request("/api/permissions");
    return Array.isArray(data?.permissions) ? data.permissions : [];
  },
};

export const documentApi = {
  async list({ includeArchived = false } = {}) {
    const data = await request(
      `/api/documents${includeArchived ? "?include_archived=1" : ""}`,
    );
    return {
      documents: Array.isArray(data?.documents) ? data.documents : [],
      documentTypes: Array.isArray(data?.documentTypes)
        ? data.documentTypes
        : [],
      confidentialityLevels: Array.isArray(data?.confidentialityLevels)
        ? data.confidentialityLevels
        : [],
      linkOptions: Array.isArray(data?.linkOptions) ? data.linkOptions : [],
      linkModules: data?.linkModules ?? {},
    };
  },
  async create(formData) {
    const data = await request("/api/documents", {
      method: "POST",
      body: formData,
      csrf: true,
    });
    return data?.document ?? null;
  },
  async update(id, formData) {
    formData.set("_method", "PUT");
    const data = await request(`/api/documents/${id}`, {
      method: "POST",
      body: formData,
      csrf: true,
    });
    return data?.document ?? null;
  },
  async createVersion(id, formData) {
    const data = await request(`/api/documents/${id}/versions`, {
      method: "POST",
      body: formData,
      csrf: true,
    });
    return data?.document ?? null;
  },
  async remove(id) {
    await request(`/api/documents/${id}`, {
      method: "DELETE",
      csrf: true,
    });
  },
  async restore(id) {
    const data = await request(`/api/documents/${id}/restore`, {
      method: "POST",
      csrf: true,
    });
    return data?.document ?? null;
  },
  async download(document) {
    return this.downloadFile(
      `/api/documents/${document.id}/download`,
      document.fileName,
    );
  },
  async downloadVersion(document, version) {
    return this.downloadFile(
      `/api/documents/${document.id}/versions/${version.id}/download`,
      version.fileName,
    );
  },
  async downloadFile(urlPath, fileName) {
    let response;
    try {
      response = await fetch(urlPath, {
        credentials: "include",
        headers: {
          Accept: "application/octet-stream",
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

    const blob = await response.blob();
    const url = URL.createObjectURL(blob);
    const link = window.document.createElement("a");
    link.href = url;
    link.download = fileName;
    window.document.body.appendChild(link);
    link.click();
    link.remove();
    URL.revokeObjectURL(url);
  },
};

export const masterListApi = {
  async list({ configurableOnly = false } = {}) {
    const data = await request(
      `/api/master-lists${configurableOnly ? "?configurableOnly=1" : ""}`,
    );
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
      const data = await request("/api/system-configurations", {
        method: "PUT",
        body: { configurations },
        csrf: true,
      });
      return data?.configuration ?? null;
    },
    async uploadLogo(file) {
      const body = new FormData();
      body.set("logo", file);
      const data = await request("/api/system-configurations/logo", {
        method: "POST",
        body,
        csrf: true,
      });
      return data?.configuration ?? null;
    },
    async testEmail(recipient) {
      await request("/api/system-configurations/test-email", {
        method: "POST",
        body: { recipient },
        csrf: true,
      });
    },
  };

export const activityLogApi = {
  async list(filters = {}) {
    const query = queryFrom(filters);
    return request(`/api/activity-logs${query.size ? `?${query}` : ""}`);
  },
};

export const viewActivityApi = {
  async record(payload) {
    return request("/api/record-views", {
      method: "POST",
      body: payload,
      csrf: true,
    });
  },
};

export const logApi = {
  async list(mode, filters = {}) {
    const query = queryFrom(filters);
    const path = mode === "audit" ? "audit-logs" : "activity-logs";
    return request(`/api/${path}${query.size ? `?${query}` : ""}`);
  },
  exportUrl(mode, filters = {}) {
    const query = queryFrom(filters);
    const path = mode === "audit" ? "audit-logs" : "activity-logs";
    return `/api/${path}/export?${query.toString()}`;
  },
};

export const iapApi = {
  async list(filters = {}) {
    const query = queryFrom(filters);
    const data = await request(
      `/api/iap/plans${query.size ? `?${query.toString()}` : ""}`,
    );
    return {
      plans: Array.isArray(data?.plans) ? data.plans : [],
      pagination: data?.pagination ?? {
        currentPage: 1,
        lastPage: 1,
        perPage: 10,
        total: 0,
      },
    };
  },
  async show(id) {
    const data = await request(`/api/iap/plans/${id}`);
    return data?.plan ?? null;
  },
  async create(payload) {
    const data = await request("/api/iap/plans", {
      method: "POST",
      body: payload,
      csrf: true,
    });
    return data?.plan ?? null;
  },
  async update(id, payload) {
    const data = await request(`/api/iap/plans/${id}`, {
      method: "PUT",
      body: payload,
      csrf: true,
    });
    return data?.plan ?? null;
  },
  async archive(id) {
    await request(`/api/iap/plans/${id}`, {
      method: "DELETE",
      csrf: true,
    });
  },
  async restore(id) {
    const data = await request(`/api/iap/plans/${id}/restore`, {
      method: "POST",
      csrf: true,
    });
    return data?.plan ?? null;
  },
  async completeness(id) {
    const data = await request(`/api/iap/plans/${id}/completeness`);
    return data?.completeness ?? { complete: false, errors: [] };
  },
  async transition(id, action, payload) {
    const data = await request(`/api/iap/plans/${id}/transitions/${action}`, {
      method: "POST",
      body: payload,
      csrf: true,
    });
    return data?.plan ?? null;
  },
  async createRevision(id, payload) {
    const data = await request(`/api/iap/plans/${id}/revisions`, {
      method: "POST",
      body: payload,
      csrf: true,
    });
    return data?.plan ?? null;
  },
  async connectPrioritization(id, payload) {
    const data = await request(`/api/iap/plans/${id}/prioritization`, {
      method: "PUT",
      body: payload,
      csrf: true,
    });
    return data?.plan ?? null;
  },
  async createEngagement(planId, payload) {
    const data = await request(`/api/iap/plans/${planId}/engagements`, {
      method: "POST",
      body: payload,
      csrf: true,
    });
    return data?.engagement ?? null;
  },
  async listRiskAssessments(planId, { includeArchived = false } = {}) {
    const data = await request(
      `/api/iap/plans/${planId}/risk-assessments${
        includeArchived ? "?includeArchived=1" : ""
      }`,
    );
    return Array.isArray(data?.riskAssessments)
      ? data.riskAssessments
      : [];
  },
  async createRiskAssessment(planId, payload) {
    const data = await request(`/api/iap/plans/${planId}/risk-assessments`, {
      method: "POST",
      body: payload,
      csrf: true,
    });
    return data?.riskAssessment ?? null;
  },
  async updateRiskAssessment(planId, assessmentId, payload) {
    const data = await request(
      `/api/iap/plans/${planId}/risk-assessments/${assessmentId}`,
      {
        method: "PUT",
        body: payload,
        csrf: true,
      },
    );
    return data?.riskAssessment ?? null;
  },
  async archiveRiskAssessment(planId, assessmentId) {
    await request(
      `/api/iap/plans/${planId}/risk-assessments/${assessmentId}`,
      {
        method: "DELETE",
        csrf: true,
      },
    );
  },
  async restoreRiskAssessment(planId, assessmentId) {
    const data = await request(
      `/api/iap/plans/${planId}/risk-assessments/${assessmentId}/restore`,
      {
        method: "POST",
        csrf: true,
      },
    );
    return data?.riskAssessment ?? null;
  },
};

export const iapDashboardApi = {
  async show() {
    return request("/api/iap/dashboard");
  },
};

function reportQuery(filters = {}) {
  return queryFrom(filters);
}

export const iapReportApi = {
  async catalog() {
    return request("/api/iap/reports");
  },
  async preview(reportCode, filters = {}) {
    const query = reportQuery(filters);
    const data = await request(
      `/api/iap/reports/${reportCode}${
        query.size ? `?${query.toString()}` : ""
      }`,
    );
    return data?.report ?? null;
  },
  async download(reportCode, format, filters = {}) {
    const query = reportQuery({ ...filters, format });
    const response = await fetch(
      `/api/iap/reports/${reportCode}/export?${query.toString()}`,
      {
        credentials: "include",
        headers: {
          Accept: "application/octet-stream",
          "X-Requested-With": "XMLHttpRequest",
        },
      },
    );
    if (!response.ok) {
      const payload = await parseResponse(response);
      throw errorFromResponse(payload, response.status);
    }
    const blob = await response.blob();
    const disposition = response.headers.get("content-disposition") ?? "";
    const encodedName = disposition.match(/filename\*=UTF-8''([^;]+)/i)?.[1];
    const simpleName = disposition.match(/filename="?([^";]+)"?/i)?.[1];
    const fileName = encodedName
      ? decodeURIComponent(encodedName)
      : simpleName || `${reportCode}.${format === "excel" ? "xls" : format}`;
    const url = URL.createObjectURL(blob);
    const link = document.createElement("a");
    link.href = url;
    link.download = fileName;
    document.body.appendChild(link);
    link.click();
    link.remove();
    URL.revokeObjectURL(url);
  },
  printUrl(reportCode, filters = {}) {
    const query = reportQuery({ ...filters, format: "print", autoprint: 1 });
    return `/api/iap/reports/${reportCode}/export?${query.toString()}`;
  },
};

export const iapSupportingRecordsApi = {
  async show(planId, { includeArchived = false } = {}) {
    return (
      (await request(
        `/api/iap/plans/${planId}/supporting-records${
          includeArchived ? "?includeArchived=1" : ""
        }`,
      )) ?? {
        attachments: [],
        comments: [],
        attachmentTypes: [],
        riskAssessments: [],
        engagements: [],
        capabilities: {},
      }
    );
  },
  async upload(planId, payload) {
    const body = new FormData();
    Object.entries(payload).forEach(([key, value]) => {
      if (value !== "" && value !== null && value !== undefined) {
        body.append(key, value);
      }
    });
    const data = await request(`/api/iap/plans/${planId}/attachments`, {
      method: "POST",
      body,
      csrf: true,
    });
    return data?.attachment ?? null;
  },
  async download(planId, attachment) {
    const response = await fetch(
      `/api/iap/plans/${planId}/attachments/${attachment.id}/download`,
      {
        credentials: "include",
        headers: {
          Accept: "application/octet-stream",
          "X-Requested-With": "XMLHttpRequest",
        },
      },
    );
    if (!response.ok) {
      const payload = await parseResponse(response);
      throw errorFromResponse(payload, response.status);
    }
    const blob = await response.blob();
    const url = URL.createObjectURL(blob);
    const link = document.createElement("a");
    link.href = url;
    link.download = attachment.fileName || attachment.displayName;
    document.body.appendChild(link);
    link.click();
    link.remove();
    URL.revokeObjectURL(url);
  },
  async archive(planId, attachmentId) {
    await request(`/api/iap/plans/${planId}/attachments/${attachmentId}`, {
      method: "DELETE",
      csrf: true,
    });
  },
  async restore(planId, attachmentId) {
    await request(
      `/api/iap/plans/${planId}/attachments/${attachmentId}/restore`,
      {
        method: "POST",
        csrf: true,
      },
    );
  },
  async addComment(planId, payload) {
    const data = await request(`/api/iap/plans/${planId}/comments`, {
      method: "POST",
      body: payload,
      csrf: true,
    });
    return data?.comment ?? null;
  },
};

export const siapApi = {
  async list(filters = {}) {
    const query = queryFrom(filters);
    const data = await request(
      `/api/iap/strategic-plans${query.size ? `?${query.toString()}` : ""}`,
    );
    return {
      strategicPlans: Array.isArray(data?.strategicPlans)
        ? data.strategicPlans
        : [],
      pagination: data?.pagination ?? {
        currentPage: 1,
        lastPage: 1,
        perPage: 10,
        total: 0,
      },
    };
  },
  async show(id) {
    const data = await request(`/api/iap/strategic-plans/${id}`);
    return data?.strategicPlan ?? null;
  },
  async create(payload) {
    const data = await request("/api/iap/strategic-plans", {
      method: "POST",
      body: payload,
      csrf: true,
    });
    return data?.strategicPlan ?? null;
  },
  async update(id, payload) {
    const data = await request(`/api/iap/strategic-plans/${id}`, {
      method: "PUT",
      body: payload,
      csrf: true,
    });
    return data?.strategicPlan ?? null;
  },
  async archive(id) {
    await request(`/api/iap/strategic-plans/${id}`, {
      method: "DELETE",
      csrf: true,
    });
  },
  async restore(id) {
    const data = await request(`/api/iap/strategic-plans/${id}/restore`, {
      method: "POST",
      csrf: true,
    });
    return data?.strategicPlan ?? null;
  },
  async transition(id, action, payload) {
    const data = await request(
      `/api/iap/strategic-plans/${id}/transitions/${action}`,
      {
        method: "POST",
        body: payload,
        csrf: true,
      },
    );
    return data?.strategicPlan ?? null;
  },
  async createRevision(id, payload) {
    const data = await request(`/api/iap/strategic-plans/${id}/revisions`, {
      method: "POST",
      body: payload,
      csrf: true,
    });
    return data?.strategicPlan ?? null;
  },
};

export const auditUniverseApi = {
  async list(filters = {}) {
    const query = queryFrom(filters);
    const data = await request(
      `/api/iap/audit-universe${query.size ? `?${query.toString()}` : ""}`,
    );
    return {
      items: Array.isArray(data?.auditUniverse) ? data.auditUniverse : [],
      pagination: data?.pagination ?? {
        currentPage: 1,
        lastPage: 1,
        perPage: 10,
        total: 0,
      },
    };
  },
  async create(payload) {
    const data = await request("/api/iap/audit-universe", {
      method: "POST",
      body: payload,
      csrf: true,
    });
    return data?.auditUniverseItem ?? null;
  },
  async update(id, payload) {
    const data = await request(`/api/iap/audit-universe/${id}`, {
      method: "PUT",
      body: payload,
      csrf: true,
    });
    return data?.auditUniverseItem ?? null;
  },
  async archive(id) {
    await request(`/api/iap/audit-universe/${id}`, {
      method: "DELETE",
      csrf: true,
    });
  },
  async restore(id) {
    const data = await request(`/api/iap/audit-universe/${id}/restore`, {
      method: "POST",
      csrf: true,
    });
    return data?.auditUniverseItem ?? null;
  },
};

export const riskPeriodApi = {
  async list(filters = {}) {
    const query = queryFrom(filters);
    const data = await request(
      `/api/iap/risk-periods${query.size ? `?${query.toString()}` : ""}`,
    );
    return {
      riskPeriods: Array.isArray(data?.riskPeriods) ? data.riskPeriods : [],
      pagination: data?.pagination ?? {
        currentPage: 1,
        lastPage: 1,
        perPage: 10,
        total: 0,
      },
    };
  },
  async show(id) {
    const data = await request(`/api/iap/risk-periods/${id}`);
    return data?.riskPeriod ?? null;
  },
  async create(payload) {
    const data = await request("/api/iap/risk-periods", {
      method: "POST",
      body: payload,
      csrf: true,
    });
    return data?.riskPeriod ?? null;
  },
  async update(id, payload) {
    const data = await request(`/api/iap/risk-periods/${id}`, {
      method: "PUT",
      body: payload,
      csrf: true,
    });
    return data?.riskPeriod ?? null;
  },
  async archive(id) {
    await request(`/api/iap/risk-periods/${id}`, {
      method: "DELETE",
      csrf: true,
    });
  },
  async restore(id) {
    const data = await request(`/api/iap/risk-periods/${id}/restore`, {
      method: "POST",
      csrf: true,
    });
    return data?.riskPeriod ?? null;
  },
  async transition(id, action, payload) {
    const data = await request(
      `/api/iap/risk-periods/${id}/transitions/${action}`,
      { method: "POST", body: payload, csrf: true },
    );
    return data?.riskPeriod ?? null;
  },
  async createAssessment(periodId, payload) {
    const data = await request(
      `/api/iap/risk-periods/${periodId}/assessments`,
      { method: "POST", body: payload, csrf: true },
    );
    return data?.riskPeriod ?? null;
  },
  async updateAssessment(periodId, assessmentId, payload) {
    const data = await request(
      `/api/iap/risk-periods/${periodId}/assessments/${assessmentId}`,
      { method: "PUT", body: payload, csrf: true },
    );
    return data?.riskPeriod ?? null;
  },
  async archiveAssessment(periodId, assessmentId) {
    await request(
      `/api/iap/risk-periods/${periodId}/assessments/${assessmentId}`,
      { method: "DELETE", csrf: true },
    );
  },
  async restoreAssessment(periodId, assessmentId) {
    const data = await request(
      `/api/iap/risk-periods/${periodId}/assessments/${assessmentId}/restore`,
      { method: "POST", csrf: true },
    );
    return data?.riskPeriod ?? null;
  },
  async uploadEvidence(periodId, assessmentId, file) {
    const formData = new FormData();
    formData.append("file", file);
    await request(
      `/api/iap/risk-periods/${periodId}/assessments/${assessmentId}/evidence`,
      { method: "POST", body: formData, csrf: true },
    );
  },
  async removeEvidence(periodId, assessmentId, evidenceId) {
    await request(
      `/api/iap/risk-periods/${periodId}/assessments/${assessmentId}/evidence/${evidenceId}`,
      { method: "DELETE", csrf: true },
    );
  },
  async downloadEvidence(periodId, assessmentId, evidence) {
    const response = await fetch(
      `/api/iap/risk-periods/${periodId}/assessments/${assessmentId}/evidence/${evidence.id}`,
      {
        credentials: "include",
        headers: {
          Accept: "application/octet-stream",
          "X-Requested-With": "XMLHttpRequest",
        },
      },
    );
    if (!response.ok) {
      const payload = await parseResponse(response);
      throw errorFromResponse(payload, response.status);
    }
    const url = URL.createObjectURL(await response.blob());
    const link = window.document.createElement("a");
    link.href = url;
    link.download = evidence.fileName;
    window.document.body.appendChild(link);
    link.click();
    link.remove();
    URL.revokeObjectURL(url);
  },
};

export const prioritizationApi = {
  async list(filters = {}) {
    const query = queryFrom(filters);
    const data = await request(
      `/api/iap/prioritizations${query.size ? `?${query.toString()}` : ""}`,
    );
    return {
      prioritizations: Array.isArray(data?.prioritizations)
        ? data.prioritizations
        : [],
      pagination: data?.pagination ?? {
        currentPage: 1,
        lastPage: 1,
        perPage: 10,
        total: 0,
      },
    };
  },
  async show(id) {
    const data = await request(`/api/iap/prioritizations/${id}`);
    return data?.prioritization ?? null;
  },
  async create(payload) {
    const data = await request("/api/iap/prioritizations", {
      method: "POST",
      body: payload,
      csrf: true,
    });
    return data?.prioritization ?? null;
  },
  async update(id, payload) {
    const data = await request(`/api/iap/prioritizations/${id}`, {
      method: "PUT",
      body: payload,
      csrf: true,
    });
    return data?.prioritization ?? null;
  },
  async updateItem(runId, itemId, payload) {
    const data = await request(
      `/api/iap/prioritizations/${runId}/items/${itemId}`,
      { method: "PUT", body: payload, csrf: true },
    );
    return data?.prioritization ?? null;
  },
  async transition(id, action, payload) {
    const data = await request(
      `/api/iap/prioritizations/${id}/transitions/${action}`,
      { method: "POST", body: payload, csrf: true },
    );
    return data?.prioritization ?? null;
  },
  async archive(id) {
    await request(`/api/iap/prioritizations/${id}`, {
      method: "DELETE",
      csrf: true,
    });
  },
  async restore(id) {
    const data = await request(`/api/iap/prioritizations/${id}/restore`, {
      method: "POST",
      csrf: true,
    });
    return data?.prioritization ?? null;
  },
};

export const schedulingApi = {
  async list(filters = {}) {
    const query = queryFrom(filters);
    const data = await request(
      `/api/iap/schedules${query.size ? `?${query.toString()}` : ""}`,
    );
    return {
      schedules: Array.isArray(data?.schedules) ? data.schedules : [],
      plans: Array.isArray(data?.plans) ? data.plans : [],
      auditors: Array.isArray(data?.auditors) ? data.auditors : [],
      teamRoles: Array.isArray(data?.teamRoles) ? data.teamRoles : [],
      capacities: Array.isArray(data?.capacities) ? data.capacities : [],
    };
  },
  async checkConflicts(engagementId, payload) {
    const data = await request(
      `/api/iap/schedules/${engagementId}/conflicts`,
      { method: "POST", body: payload, csrf: true },
    );
    return Array.isArray(data?.conflicts) ? data.conflicts : [];
  },
  async update(engagementId, payload) {
    const data = await request(`/api/iap/schedules/${engagementId}`, {
      method: "PUT",
      body: payload,
      csrf: true,
    });
    return {
      schedule: data?.schedule ?? null,
      conflicts: Array.isArray(data?.conflicts) ? data.conflicts : [],
    };
  },
  async cancel(engagementId, payload) {
    await request(`/api/iap/schedules/${engagementId}/cancel`, {
      method: "POST",
      body: payload,
      csrf: true,
    });
  },
  async updateCapacity(userId, payload) {
    await request(`/api/iap/schedules/capacities/${userId}`, {
      method: "PUT",
      body: payload,
      csrf: true,
    });
  },
};

export const resourceCapacityApi = {
  async show(fiscalYear = "") {
    const query = fiscalYear ? `?fiscalYear=${fiscalYear}` : "";
    const data = await request(`/api/iap/resources${query}`);
    return {
      fiscalYear: data?.fiscalYear ?? new Date().getFullYear(),
      years: Array.isArray(data?.years) ? data.years : [],
      auditors: Array.isArray(data?.auditors) ? data.auditors : [],
      engagements: Array.isArray(data?.engagements) ? data.engagements : [],
      specializations: Array.isArray(data?.specializations)
        ? data.specializations
        : [],
      unavailabilityTypes: Array.isArray(data?.unavailabilityTypes)
        ? data.unavailabilityTypes
        : [],
      proficiencyLevels: Array.isArray(data?.proficiencyLevels)
        ? data.proficiencyLevels
        : [],
      summary: data?.summary ?? {},
    };
  },
  async updateCapacity(userId, payload) {
    await request(`/api/iap/resources/auditors/${userId}/capacity`, {
      method: "PUT",
      body: payload,
      csrf: true,
    });
  },
  async createUnavailability(userId, payload) {
    await request(`/api/iap/resources/auditors/${userId}/unavailability`, {
      method: "POST",
      body: payload,
      csrf: true,
    });
  },
  async updateUnavailability(id, payload) {
    await request(`/api/iap/resources/unavailability/${id}`, {
      method: "PUT",
      body: payload,
      csrf: true,
    });
  },
  async archiveUnavailability(id) {
    await request(`/api/iap/resources/unavailability/${id}`, {
      method: "DELETE",
      csrf: true,
    });
  },
  async restoreUnavailability(id) {
    await request(`/api/iap/resources/unavailability/${id}/restore`, {
      method: "POST",
      csrf: true,
    });
  },
  async syncSkills(userId, skills) {
    await request(`/api/iap/resources/auditors/${userId}/skills`, {
      method: "PUT",
      body: { skills },
      csrf: true,
    });
  },
  async syncRequirements(engagementId, requirements) {
    await request(
      `/api/iap/resources/engagements/${engagementId}/requirements`,
      {
        method: "PUT",
        body: { requirements },
        csrf: true,
      },
    );
  },
};

export const aemsDashboardApi = {
  async show(filters = {}) {
    const query = queryFrom(filters);
    return request(
      `/api/aems/dashboard${query.size ? `?${query.toString()}` : ""}`,
    );
  },
  async export(filters = {}) {
    const query = queryFrom(filters);
    const response = await fetch(
      `/api/aems/dashboard/export${query.size ? `?${query.toString()}` : ""}`,
      {
        credentials: "include",
        headers: {
          Accept: "text/csv",
          "X-Requested-With": "XMLHttpRequest",
        },
      },
    );
    if (!response.ok) {
      const payload = await parseResponse(response);
      throw errorFromResponse(payload, response.status);
    }
    const blob = await response.blob();
    const disposition = response.headers.get("content-disposition") ?? "";
    const encodedName = disposition.match(/filename\*=UTF-8''([^;]+)/i)?.[1];
    const simpleName = disposition.match(/filename="?([^";]+)"?/i)?.[1];
    const fileName = encodedName
      ? decodeURIComponent(encodedName)
      : simpleName || "aems-engagement-progress.csv";
    const url = URL.createObjectURL(blob);
    const link = document.createElement("a");
    link.href = url;
    link.download = fileName;
    document.body.appendChild(link);
    link.click();
    link.remove();
    URL.revokeObjectURL(url);
  },
};

export const aemsEngagementApi = {
  async list(filters = {}) {
    const query = queryFrom(filters);
    const data = await request(
      `/api/aems/engagements${query.size ? `?${query.toString()}` : ""}`,
    );
    return {
      engagements: Array.isArray(data?.engagements) ? data.engagements : [],
      summary: data?.summary ?? {
        total: 0,
        planned: 0,
        special: 0,
        ongoing: 0,
        archived: 0,
      },
      pagination: data?.pagination ?? {
        currentPage: 1,
        lastPage: 1,
        perPage: 10,
        total: 0,
      },
    };
  },
  async show(id) {
    const data = await request(`/api/aems/engagements/${id}`);
    return data?.engagement ?? null;
  },
  async importOptions() {
    const data = await request("/api/aems/engagements/import-options");
    return Array.isArray(data?.iapEngagements) ? data.iapEngagements : [];
  },
  async importFromIap(payload) {
    const data = await request("/api/aems/engagements/import", {
      method: "POST",
      body: payload,
      csrf: true,
    });
    return data?.engagement ?? null;
  },
  async createSpecial(payload) {
    const data = await request("/api/aems/engagements", {
      method: "POST",
      body: payload,
      csrf: true,
    });
    return data?.engagement ?? null;
  },
  async update(id, payload) {
    const data = await request(`/api/aems/engagements/${id}`, {
      method: "PUT",
      body: payload,
      csrf: true,
    });
    return data?.engagement ?? null;
  },
  async archive(id) {
    await request(`/api/aems/engagements/${id}`, {
      method: "DELETE",
      csrf: true,
    });
  },
  async restore(id) {
    const data = await request(`/api/aems/engagements/${id}/restore`, {
      method: "POST",
      csrf: true,
    });
    return data?.engagement ?? null;
  },
};

export const aemsTeamApi = {
  async show(engagementId) {
    return request(`/api/aems/engagements/${engagementId}/team`);
  },
  async assign(engagementId, payload) {
    const data = await request(`/api/aems/engagements/${engagementId}/team`, {
      method: "POST",
      body: payload,
      csrf: true,
    });
    return data?.teamMember ?? null;
  },
  async update(engagementId, memberId, payload) {
    const data = await request(
      `/api/aems/engagements/${engagementId}/team/${memberId}`,
      { method: "PUT", body: payload, csrf: true },
    );
    return data?.teamMember ?? null;
  },
  async reassign(engagementId, memberId, payload) {
    const data = await request(
      `/api/aems/engagements/${engagementId}/team/${memberId}/reassign`,
      { method: "POST", body: payload, csrf: true },
    );
    return data?.teamMember ?? null;
  },
  async end(engagementId, memberId, reason) {
    await request(
      `/api/aems/engagements/${engagementId}/team/${memberId}`,
      { method: "DELETE", body: { reason }, csrf: true },
    );
  },
};

export const aemsAeoApi = {
  async show(engagementId) {
    return request(`/api/aems/engagements/${engagementId}/aeo`);
  },
  async create(engagementId, payload) {
    const data = await request(`/api/aems/engagements/${engagementId}/aeo`, {
      method: "POST",
      body: payload,
      csrf: true,
    });
    return data?.order ?? null;
  },
  async update(engagementId, orderId, payload) {
    const data = await request(
      `/api/aems/engagements/${engagementId}/aeo/${orderId}`,
      { method: "PUT", body: payload, csrf: true },
    );
    return data?.order ?? null;
  },
  async transition(engagementId, orderId, payload) {
    const data = await request(
      `/api/aems/engagements/${engagementId}/aeo/${orderId}/transition`,
      { method: "POST", body: payload, csrf: true },
    );
    return data?.order ?? null;
  },
  async revise(engagementId, orderId, payload) {
    const data = await request(
      `/api/aems/engagements/${engagementId}/aeo/${orderId}/revise`,
      { method: "POST", body: payload, csrf: true },
    );
    return data?.order ?? null;
  },
  async downloadPdf(engagementId, order) {
    const response = await fetch(
      `/api/aems/engagements/${engagementId}/aeo/${order.id}/pdf`,
      {
        credentials: "include",
        headers: {
          Accept: "application/pdf",
          "X-Requested-With": "XMLHttpRequest",
        },
      },
    );
    if (!response.ok) {
      const payload = await parseResponse(response);
      throw errorFromResponse(payload, response.status);
    }
    const url = URL.createObjectURL(await response.blob());
    const link = document.createElement("a");
    link.href = url;
    link.download = `${order.orderCode}-approved.pdf`;
    document.body.appendChild(link);
    link.click();
    link.remove();
    URL.revokeObjectURL(url);
  },
};

export const aemsAepApi = {
  async show(engagementId) {
    return request(`/api/aems/engagements/${engagementId}/aep`);
  },
  async create(engagementId, payload) {
    const data = await request(`/api/aems/engagements/${engagementId}/aep`, {
      method: "POST",
      body: payload,
      csrf: true,
    });
    return data?.plan ?? null;
  },
  async update(engagementId, planId, payload) {
    const data = await request(
      `/api/aems/engagements/${engagementId}/aep/${planId}`,
      { method: "PUT", body: payload, csrf: true },
    );
    return data?.plan ?? null;
  },
  async transition(engagementId, planId, payload) {
    const data = await request(
      `/api/aems/engagements/${engagementId}/aep/${planId}/transition`,
      { method: "POST", body: payload, csrf: true },
    );
    return data?.plan ?? null;
  },
  async revise(engagementId, planId, payload) {
    const data = await request(
      `/api/aems/engagements/${engagementId}/aep/${planId}/revise`,
      { method: "POST", body: payload, csrf: true },
    );
    return data?.plan ?? null;
  },
};

export const aemsProgramApi = {
  async show(engagementId) {
    return request(`/api/aems/engagements/${engagementId}/programs`);
  },
  async create(engagementId, payload) {
    const data = await request(
      `/api/aems/engagements/${engagementId}/programs`,
      { method: "POST", body: payload, csrf: true },
    );
    return data?.program ?? null;
  },
  async update(engagementId, programId, payload) {
    const data = await request(
      `/api/aems/engagements/${engagementId}/programs/${programId}`,
      { method: "PUT", body: payload, csrf: true },
    );
    return data?.program ?? null;
  },
  async transition(engagementId, programId, payload) {
    const data = await request(
      `/api/aems/engagements/${engagementId}/programs/${programId}/transition`,
      { method: "POST", body: payload, csrf: true },
    );
    return data?.program ?? null;
  },
  async revise(engagementId, programId, payload) {
    const data = await request(
      `/api/aems/engagements/${engagementId}/programs/${programId}/revise`,
      { method: "POST", body: payload, csrf: true },
    );
    return data?.program ?? null;
  },
  async addProcedure(engagementId, programId, payload) {
    const data = await request(
      `/api/aems/engagements/${engagementId}/programs/${programId}/procedures`,
      { method: "POST", body: payload, csrf: true },
    );
    return data?.procedure ?? null;
  },
  async updateProcedure(engagementId, programId, procedureId, payload) {
    const data = await request(
      `/api/aems/engagements/${engagementId}/programs/${programId}/procedures/${procedureId}`,
      { method: "PUT", body: payload, csrf: true },
    );
    return data?.procedure ?? null;
  },
  async removeProcedure(engagementId, programId, procedureId, payload) {
    await request(
      `/api/aems/engagements/${engagementId}/programs/${programId}/procedures/${procedureId}`,
      { method: "DELETE", body: payload, csrf: true },
    );
  },
  async progressProcedure(engagementId, programId, procedureId, payload) {
    const data = await request(
      `/api/aems/engagements/${engagementId}/programs/${programId}/procedures/${procedureId}/progress`,
      { method: "POST", body: payload, csrf: true },
    );
    return data?.procedure ?? null;
  },
  async reviewProcedure(engagementId, programId, procedureId, payload) {
    const data = await request(
      `/api/aems/engagements/${engagementId}/programs/${programId}/procedures/${procedureId}/review`,
      { method: "POST", body: payload, csrf: true },
    );
    return data?.procedure ?? null;
  },
};

export const aemsWorkingPaperApi = {
  async show(engagementId) {
    return request(`/api/aems/engagements/${engagementId}/working-papers`);
  },
  async create(engagementId, payload) {
    const data = await request(
      `/api/aems/engagements/${engagementId}/working-papers`,
      { method: "POST", body: payload, csrf: true },
    );
    return data?.workingPaper ?? null;
  },
  async update(engagementId, paperId, payload) {
    const data = await request(
      `/api/aems/engagements/${engagementId}/working-papers/${paperId}`,
      { method: "PUT", body: payload, csrf: true },
    );
    return data?.workingPaper ?? null;
  },
  async transition(engagementId, paperId, payload) {
    const data = await request(
      `/api/aems/engagements/${engagementId}/working-papers/${paperId}/transition`,
      { method: "POST", body: payload, csrf: true },
    );
    return data?.workingPaper ?? null;
  },
  async revise(engagementId, paperId, payload) {
    const data = await request(
      `/api/aems/engagements/${engagementId}/working-papers/${paperId}/revise`,
      { method: "POST", body: payload, csrf: true },
    );
    return data?.workingPaper ?? null;
  },
};

function evidenceForm(payload) {
  const body = new FormData();
  Object.entries(payload).forEach(([key, value]) => {
    if (value === undefined || value === null || value === "") return;
    body.append(
      key,
      Array.isArray(value) ? JSON.stringify(value) : value,
    );
  });
  return body;
}

export const aemsEvidenceApi = {
  async upload(engagementId, payload) {
    const data = await request(
      `/api/aems/engagements/${engagementId}/evidence`,
      { method: "POST", body: evidenceForm(payload), csrf: true },
    );
    return data?.evidence ?? null;
  },
  async replace(engagementId, evidenceId, payload) {
    const data = await request(
      `/api/aems/engagements/${engagementId}/evidence/${evidenceId}/revisions`,
      { method: "POST", body: evidenceForm(payload), csrf: true },
    );
    return data?.evidence ?? null;
  },
  async transition(engagementId, evidenceId, payload) {
    const data = await request(
      `/api/aems/engagements/${engagementId}/evidence/${evidenceId}/transition`,
      { method: "POST", body: payload, csrf: true },
    );
    return data?.evidence ?? null;
  },
  async download(engagementId, evidence) {
    const response = await fetch(
      `/api/aems/engagements/${engagementId}/evidence/${evidence.id}/download`,
      {
        credentials: "include",
        headers: {
          Accept: "application/octet-stream",
          "X-Requested-With": "XMLHttpRequest",
        },
      },
    );
    if (!response.ok) {
      const payload = await parseResponse(response);
      throw errorFromResponse(payload, response.status);
    }
    const url = URL.createObjectURL(await response.blob());
    const link = document.createElement("a");
    link.href = url;
    link.download = evidence.fileName || `${evidence.evidenceCode}.bin`;
    document.body.appendChild(link);
    link.click();
    link.remove();
    URL.revokeObjectURL(url);
  },
};

export const aemsFindingApi = {
  async engagements() {
    const data = await request("/api/aems/findings-workspaces");
    return data?.engagements ?? [];
  },
  async show(engagementId) {
    return request(`/api/aems/engagements/${engagementId}/findings-workspace`);
  },
  async createIssue(engagementId, payload) {
    const data = await request(`/api/aems/engagements/${engagementId}/issues`, {
      method: "POST",
      body: payload,
      csrf: true,
    });
    return data?.issue ?? null;
  },
  async updateIssue(engagementId, issueId, payload) {
    const data = await request(
      `/api/aems/engagements/${engagementId}/issues/${issueId}`,
      { method: "PUT", body: payload, csrf: true },
    );
    return data?.issue ?? null;
  },
  async transitionIssue(engagementId, issueId, payload) {
    return request(
      `/api/aems/engagements/${engagementId}/issues/${issueId}/transition`,
      { method: "POST", body: payload, csrf: true },
    );
  },
  async createFinding(engagementId, payload) {
    const data = await request(
      `/api/aems/engagements/${engagementId}/findings`,
      { method: "POST", body: payload, csrf: true },
    );
    return data?.finding ?? null;
  },
  async updateFinding(engagementId, findingId, payload) {
    const data = await request(
      `/api/aems/engagements/${engagementId}/findings/${findingId}`,
      { method: "PUT", body: payload, csrf: true },
    );
    return data?.finding ?? null;
  },
  async transitionFinding(engagementId, findingId, payload) {
    const data = await request(
      `/api/aems/engagements/${engagementId}/findings/${findingId}/transition`,
      { method: "POST", body: payload, csrf: true },
    );
    return data?.finding ?? null;
  },
  async saveRecommendation(engagementId, findingId, recommendationId, payload) {
    const suffix = recommendationId ? `/${recommendationId}` : "";
    const data = await request(
      `/api/aems/engagements/${engagementId}/findings/${findingId}/recommendations${suffix}`,
      {
        method: recommendationId ? "PUT" : "POST",
        body: payload,
        csrf: true,
      },
    );
    return data?.recommendation ?? null;
  },
  async removeRecommendation(engagementId, findingId, recommendationId, payload) {
    await request(
      `/api/aems/engagements/${engagementId}/findings/${findingId}/recommendations/${recommendationId}`,
      { method: "DELETE", body: payload, csrf: true },
    );
  },
  async createResponse(engagementId, findingId, payload) {
    const data = await request(
      `/api/aems/engagements/${engagementId}/findings/${findingId}/responses`,
      { method: "POST", body: payload, csrf: true },
    );
    return data?.response ?? null;
  },
  async updateResponse(engagementId, findingId, responseId, payload) {
    const data = await request(
      `/api/aems/engagements/${engagementId}/findings/${findingId}/responses/${responseId}`,
      { method: "PUT", body: payload, csrf: true },
    );
    return data?.response ?? null;
  },
  async transitionResponse(engagementId, findingId, responseId, payload) {
    const data = await request(
      `/api/aems/engagements/${engagementId}/findings/${findingId}/responses/${responseId}/transition`,
      { method: "POST", body: payload, csrf: true },
    );
    return data?.response ?? null;
  },
  async reviseResponse(engagementId, findingId, responseId, payload) {
    const data = await request(
      `/api/aems/engagements/${engagementId}/findings/${findingId}/responses/${responseId}/revisions`,
      { method: "POST", body: payload, csrf: true },
    );
    return data?.response ?? null;
  },
  async uploadResponseAttachment(
    engagementId,
    findingId,
    responseId,
    payload,
  ) {
    const data = await request(
      `/api/aems/engagements/${engagementId}/findings/${findingId}/responses/${responseId}/attachments`,
      { method: "POST", body: evidenceForm(payload), csrf: true },
    );
    return data?.attachment ?? null;
  },
  async saveRejoinder(
    engagementId,
    findingId,
    responseId,
    rejoinderId,
    payload,
  ) {
    const suffix = rejoinderId ? `/${rejoinderId}` : "";
    const data = await request(
      `/api/aems/engagements/${engagementId}/findings/${findingId}/responses/${responseId}/rejoinders${suffix}`,
      {
        method: rejoinderId ? "PUT" : "POST",
        body: payload,
        csrf: true,
      },
    );
    return data?.rejoinder ?? null;
  },
  async finalizeRejoinder(
    engagementId,
    findingId,
    responseId,
    rejoinderId,
    payload,
  ) {
    const data = await request(
      `/api/aems/engagements/${engagementId}/findings/${findingId}/responses/${responseId}/rejoinders/${rejoinderId}/finalize`,
      { method: "POST", body: payload, csrf: true },
    );
    return data?.rejoinder ?? null;
  },
  async uploadRejoinderAttachment(
    engagementId,
    findingId,
    responseId,
    rejoinderId,
    payload,
  ) {
    const data = await request(
      `/api/aems/engagements/${engagementId}/findings/${findingId}/responses/${responseId}/rejoinders/${rejoinderId}/attachments`,
      { method: "POST", body: evidenceForm(payload), csrf: true },
    );
    return data?.attachment ?? null;
  },
  async downloadAttachment(engagementId, findingId, attachment) {
    const response = await fetch(
      `/api/aems/engagements/${engagementId}/findings/${findingId}/dialogue-attachments/${attachment.id}/download`,
      {
        credentials: "include",
        headers: {
          Accept: "application/octet-stream",
          "X-Requested-With": "XMLHttpRequest",
        },
      },
    );
    if (!response.ok) {
      const payload = await parseResponse(response);
      throw errorFromResponse(payload, response.status);
    }
    const url = URL.createObjectURL(await response.blob());
    const link = document.createElement("a");
    link.href = url;
    link.download = attachment.fileName || `${attachment.attachmentCode}.bin`;
    document.body.appendChild(link);
    link.click();
    link.remove();
    URL.revokeObjectURL(url);
  },
};

export const aemsExitConferenceApi = {
  async engagements() {
    const data = await request("/api/aems/exit-conference-workspaces");
    return data?.engagements ?? [];
  },
  async show(engagementId) {
    return request(`/api/aems/engagements/${engagementId}/exit-conferences`);
  },
  async create(engagementId, payload) {
    const data = await request(
      `/api/aems/engagements/${engagementId}/exit-conferences`,
      { method: "POST", body: payload, csrf: true },
    );
    return data?.conference ?? null;
  },
  async update(engagementId, conferenceId, payload) {
    const data = await request(
      `/api/aems/engagements/${engagementId}/exit-conferences/${conferenceId}`,
      { method: "PUT", body: payload, csrf: true },
    );
    return data?.conference ?? null;
  },
  async complete(engagementId, conferenceId, payload) {
    const data = await request(
      `/api/aems/engagements/${engagementId}/exit-conferences/${conferenceId}/complete`,
      { method: "POST", body: payload, csrf: true },
    );
    return data?.conference ?? null;
  },
  async transition(engagementId, conferenceId, payload) {
    const data = await request(
      `/api/aems/engagements/${engagementId}/exit-conferences/${conferenceId}/transition`,
      { method: "POST", body: payload, csrf: true },
    );
    return data?.conference ?? null;
  },
  async uploadAttachment(engagementId, conferenceId, payload) {
    const data = await request(
      `/api/aems/engagements/${engagementId}/exit-conferences/${conferenceId}/attachments`,
      { method: "POST", body: evidenceForm(payload), csrf: true },
    );
    return data?.attachment ?? null;
  },
  async acknowledge(engagementId, conferenceId, payload) {
    const data = await request(
      `/api/aems/engagements/${engagementId}/exit-conferences/${conferenceId}/acknowledgements`,
      { method: "POST", body: payload, csrf: true },
    );
    return data?.acknowledgement ?? null;
  },
  async downloadAttachment(engagementId, conferenceId, attachment) {
    const response = await fetch(
      `/api/aems/engagements/${engagementId}/exit-conferences/${conferenceId}/attachments/${attachment.id}/download`,
      {
        credentials: "include",
        headers: {
          Accept: "application/octet-stream",
          "X-Requested-With": "XMLHttpRequest",
        },
      },
    );
    if (!response.ok) {
      const payload = await parseResponse(response);
      throw errorFromResponse(payload, response.status);
    }
    const url = URL.createObjectURL(await response.blob());
    const link = document.createElement("a");
    link.href = url;
    link.download = attachment.fileName || `${attachment.attachmentCode}.bin`;
    document.body.appendChild(link);
    link.click();
    link.remove();
    URL.revokeObjectURL(url);
  },
};

export const aemsLifecycleApi = {
  async show(engagementId) {
    return request(`/api/aems/engagements/${engagementId}/lifecycle`);
  },
  async transition(engagementId, action, payload) {
    return request(
      `/api/aems/engagements/${engagementId}/transitions/${action}`,
      { method: "POST", body: payload, csrf: true },
    );
  },
};

export const aemsEntryConferenceApi = {
  async engagements() {
    const data = await request("/api/aems/entry-conference-workspaces");
    return data?.engagements ?? [];
  },
  async show(engagementId) {
    return request(`/api/aems/engagements/${engagementId}/entry-conference`);
  },
  async create(engagementId, payload) {
    const data = await request(
      `/api/aems/engagements/${engagementId}/entry-conference`,
      { method: "POST", body: payload, csrf: true },
    );
    return data?.conference ?? null;
  },
  async update(engagementId, conferenceId, payload) {
    const data = await request(
      `/api/aems/engagements/${engagementId}/entry-conference/${conferenceId}`,
      { method: "PUT", body: payload, csrf: true },
    );
    return data?.conference ?? null;
  },
  async transition(engagementId, conferenceId, action, payload) {
    const data = await request(
      `/api/aems/engagements/${engagementId}/entry-conference/${conferenceId}/transitions/${action}`,
      { method: "POST", body: payload, csrf: true },
    );
    return data?.conference ?? null;
  },
  async acknowledge(engagementId, conferenceId, payload) {
    const data = await request(
      `/api/aems/engagements/${engagementId}/entry-conference/${conferenceId}/acknowledgements`,
      { method: "POST", body: payload, csrf: true },
    );
    return data?.acknowledgement ?? null;
  },
  async uploadAttachment(engagementId, conferenceId, payload) {
    const data = await request(
      `/api/aems/engagements/${engagementId}/entry-conference/${conferenceId}/attachments`,
      { method: "POST", body: evidenceForm(payload), csrf: true },
    );
    return data?.attachment ?? null;
  },
  async downloadAttachment(engagementId, conferenceId, attachment) {
    const response = await fetch(
      `/api/aems/engagements/${engagementId}/entry-conference/${conferenceId}/attachments/${attachment.id}/download`,
      {
        credentials: "include",
        headers: {
          Accept: "application/octet-stream",
          "X-Requested-With": "XMLHttpRequest",
        },
      },
    );
    if (!response.ok) {
      const payload = await parseResponse(response);
      throw errorFromResponse(payload, response.status);
    }
    const url = URL.createObjectURL(await response.blob());
    const link = document.createElement("a");
    link.href = url;
    link.download = attachment.fileName || `${attachment.attachmentCode}.bin`;
    document.body.appendChild(link);
    link.click();
    link.remove();
    URL.revokeObjectURL(url);
  },
};

export const aemsCompletionAssessmentApi = {
  async show(engagementId) {
    return request(
      `/api/aems/engagements/${engagementId}/completion-assessments`,
    );
  },
  async create(engagementId, payload) {
    const data = await request(
      `/api/aems/engagements/${engagementId}/completion-assessments`,
      { method: "POST", body: payload, csrf: true },
    );
    return data?.assessment ?? null;
  },
  async update(engagementId, assessmentId, payload) {
    const data = await request(
      `/api/aems/engagements/${engagementId}/completion-assessments/${assessmentId}`,
      { method: "PUT", body: payload, csrf: true },
    );
    return data?.assessment ?? null;
  },
  async transition(engagementId, assessmentId, action, payload) {
    const data = await request(
      `/api/aems/engagements/${engagementId}/completion-assessments/${assessmentId}/transitions/${action}`,
      { method: "POST", body: payload, csrf: true },
    );
    return data?.assessment ?? null;
  },
  async acceptBlocker(
    engagementId,
    assessmentId,
    itemId,
    payload,
  ) {
    const data = await request(
      `/api/aems/engagements/${engagementId}/completion-assessments/${assessmentId}/items/${itemId}/accept-blocker`,
      { method: "POST", body: payload, csrf: true },
    );
    return data?.assessment ?? null;
  },
  async revise(engagementId, assessmentId, payload) {
    const data = await request(
      `/api/aems/engagements/${engagementId}/completion-assessments/${assessmentId}/revisions`,
      { method: "POST", body: payload, csrf: true },
    );
    return data?.assessment ?? null;
  },
};

export const aemsClosureApi = {
  async show(engagementId) {
    return request(`/api/aems/engagements/${engagementId}/closure`);
  },
  async create(engagementId, payload) {
    return request(`/api/aems/engagements/${engagementId}/closure`, {
      method: "POST",
      body: payload,
      csrf: true,
    });
  },
  async update(engagementId, closureId, payload) {
    return request(
      `/api/aems/engagements/${engagementId}/closures/${closureId}`,
      { method: "PUT", body: payload, csrf: true },
    );
  },
  async refreshChecklist(engagementId, closureId) {
    return request(
      `/api/aems/engagements/${engagementId}/closures/${closureId}/refresh-checklist`,
      { method: "POST", csrf: true },
    );
  },
  async transition(engagementId, closureId, action, payload) {
    return request(
      `/api/aems/engagements/${engagementId}/closures/${closureId}/transitions/${action}`,
      { method: "POST", body: payload, csrf: true },
    );
  },
  async saveRetention(engagementId, payload) {
    const data = await request(
      `/api/aems/engagements/${engagementId}/retention`,
      { method: "PUT", body: payload, csrf: true },
    );
    return data?.retention ?? null;
  },
  async approveRetention(engagementId, retentionId, payload) {
    const data = await request(
      `/api/aems/engagements/${engagementId}/retention/${retentionId}/approve`,
      { method: "POST", body: payload, csrf: true },
    );
    return data?.retention ?? null;
  },
  async addLesson(engagementId, payload) {
    const data = await request(
      `/api/aems/engagements/${engagementId}/lessons-learned`,
      { method: "POST", body: payload, csrf: true },
    );
    return data?.lesson ?? null;
  },
  async excludeRecommendation(engagementId, recommendationId, payload) {
    const data = await request(
      `/api/aems/engagements/${engagementId}/recommendations/${recommendationId}/cms-exclusion`,
      { method: "POST", body: payload, csrf: true },
    );
    return data?.recommendation ?? null;
  },
};

export const aemsDocumentIndexApi = {
  async show(engagementId) {
    return request(`/api/aems/engagements/${engagementId}/document-index`);
  },
  async refresh(engagementId) {
    return request(
      `/api/aems/engagements/${engagementId}/document-index/refresh`,
      { method: "POST", csrf: true },
    );
  },
  async add(engagementId, payload) {
    const data = await request(
      `/api/aems/engagements/${engagementId}/document-index`,
      { method: "POST", body: payload, csrf: true },
    );
    return data?.item ?? null;
  },
  async exclude(engagementId, itemId, payload) {
    const data = await request(
      `/api/aems/engagements/${engagementId}/document-index/${itemId}/exclude`,
      { method: "POST", body: payload, csrf: true },
    );
    return data?.item ?? null;
  },
  exportUrl(engagementId) {
    return `/api/aems/engagements/${engagementId}/document-index/export`;
  },
};

export const aemsReopenApi = {
  async list(engagementId) {
    const data = await request(
      `/api/aems/engagements/${engagementId}/reopen-requests`,
    );
    return data?.requests ?? [];
  },
  async create(engagementId, payload) {
    const data = await request(
      `/api/aems/engagements/${engagementId}/reopen-requests`,
      { method: "POST", body: payload, csrf: true },
    );
    return data?.request ?? null;
  },
  async transition(engagementId, requestId, action, payload) {
    const data = await request(
      `/api/aems/engagements/${engagementId}/reopen-requests/${requestId}/transitions/${action}`,
      { method: "POST", body: payload, csrf: true },
    );
    return data?.request ?? null;
  },
};

export const aemsReportApi = {
  async engagements() {
    const data = await request("/api/aems/report-workspaces");
    return data?.engagements ?? [];
  },
  async show(engagementId) {
    return request(`/api/aems/engagements/${engagementId}/reports`);
  },
  async create(engagementId, payload) {
    const data = await request(`/api/aems/engagements/${engagementId}/reports`, {
      method: "POST",
      body: payload,
      csrf: true,
    });
    return data?.report ?? null;
  },
  async revise(engagementId, reportId, payload) {
    const data = await request(
      `/api/aems/engagements/${engagementId}/reports/${reportId}/versions`,
      { method: "POST", body: payload, csrf: true },
    );
    return data?.report ?? null;
  },
  async createFinal(engagementId, reportId, payload) {
    const data = await request(
      `/api/aems/engagements/${engagementId}/reports/${reportId}/final`,
      { method: "POST", body: payload, csrf: true },
    );
    return data?.report ?? null;
  },
  async transition(engagementId, reportId, payload) {
    const data = await request(
      `/api/aems/engagements/${engagementId}/reports/${reportId}/transition`,
      { method: "POST", body: payload, csrf: true },
    );
    return data?.report ?? null;
  },
  async transferRecommendations(engagementId, reportId, payload) {
    const data = await request(
      `/api/aems/engagements/${engagementId}/reports/${reportId}/cms-transfer`,
      { method: "POST", body: payload, csrf: true },
    );
    return data?.transfers ?? [];
  },
  async download(engagementId, reportId, version) {
    const response = await fetch(
      `/api/aems/engagements/${engagementId}/reports/${reportId}/versions/${version.id}/download`,
      {
        credentials: "include",
        headers: {
          Accept: "application/pdf",
          "X-Requested-With": "XMLHttpRequest",
        },
      },
    );
    if (!response.ok) {
      const payload = await parseResponse(response);
      throw errorFromResponse(payload, response.status);
    }
    const url = URL.createObjectURL(await response.blob());
    const link = document.createElement("a");
    link.href = url;
    link.download =
      version.pdfFileName ||
      `audit-report-v${version.versionNumber}.pdf`;
    document.body.appendChild(link);
    link.click();
    link.remove();
    URL.revokeObjectURL(url);
  },
};

export const cmsApi = {
  async getDashboard(filters = {}) {
    const query = queryFrom(filters).toString();
    return request(`/api/cms/dashboard${query ? `?${query}` : ""}`);
  },
  async getRecommendations(filters = {}) {
    const query = queryFrom(filters).toString();
    return request(`/api/cms/recommendations${query ? `?${query}` : ""}`);
  },
  async getRecommendation(recommendationId) {
    const data = await request(
      `/api/cms/recommendations/${recommendationId}`,
    );
    return data?.recommendation ?? null;
  },
  async getAssignments(recommendationId) {
    return request(
      `/api/cms/recommendations/${recommendationId}/assignments`,
    );
  },
  async assignMonitor(recommendationId, payload) {
    return request(`/api/cms/recommendations/${recommendationId}/assignments`, {
      method: "POST",
      body: payload,
      csrf: true,
    });
  },
  async endMonitorAssignment(recommendationId, assignmentId, payload) {
    return request(
      `/api/cms/recommendations/${recommendationId}/assignments/${assignmentId}/end`,
      {
        method: "POST",
        body: payload,
        csrf: true,
      },
    );
  },
  async getActionPlanForRecommendation(recommendationId) {
    return request(
      `/api/cms/recommendations/${recommendationId}/action-plan`,
    );
  },
  async createActionPlan(recommendationId, payload) {
    const data = await request(
      `/api/cms/recommendations/${recommendationId}/action-plans`,
      {
        method: "POST",
        body: payload,
        csrf: true,
      },
    );
    return data?.actionPlan ?? null;
  },
  async getActionPlan(actionPlanId) {
    const data = await request(`/api/cms/action-plans/${actionPlanId}`);
    return data?.actionPlan ?? null;
  },
  async updateActionPlan(actionPlanId, versionId, payload) {
    const data = await request(
      `/api/cms/action-plans/${actionPlanId}/versions/${versionId}`,
      {
        method: "PUT",
        body: payload,
        csrf: true,
      },
    );
    return data?.actionPlan ?? null;
  },
  async submitActionPlan(actionPlanId, versionId, payload) {
    const data = await request(
      `/api/cms/action-plans/${actionPlanId}/versions/${versionId}/transitions/submit`,
      {
        method: "POST",
        body: payload,
        csrf: true,
      },
    );
    return data?.actionPlan ?? null;
  },
  async startActionPlanReview(actionPlanId, versionId, payload) {
    const data = await request(
      `/api/cms/action-plans/${actionPlanId}/versions/${versionId}/transitions/start-review`,
      {
        method: "POST",
        body: payload,
        csrf: true,
      },
    );
    return data?.actionPlan ?? null;
  },
  async returnActionPlan(actionPlanId, versionId, payload) {
    const data = await request(
      `/api/cms/action-plans/${actionPlanId}/versions/${versionId}/transitions/return`,
      {
        method: "POST",
        body: payload,
        csrf: true,
      },
    );
    return data?.actionPlan ?? null;
  },
  async acceptActionPlan(actionPlanId, versionId, payload) {
    const data = await request(
      `/api/cms/action-plans/${actionPlanId}/versions/${versionId}/transitions/accept`,
      {
        method: "POST",
        body: payload,
        csrf: true,
      },
    );
    return data?.actionPlan ?? null;
  },
  async reviseActionPlan(actionPlanId, versionId, payload) {
    const data = await request(
      `/api/cms/action-plans/${actionPlanId}/versions/${versionId}/revisions`,
      {
        method: "POST",
        body: payload,
        csrf: true,
      },
    );
    return data?.actionPlan ?? null;
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
