import { useEffect, useMemo, useState } from "react";
import {
  Archive,
  KeyRound,
  Pencil,
  Plus,
  RotateCcw,
  Search,
  ShieldCheck,
  UserCheck,
  UserRound,
  UsersRound,
  UserX,
  X,
} from "lucide-react";
import { useAuth } from "../auth/auth-context";
import DataTable from "../components/ui/DataTable";
import ConfirmDialog from "../components/ui/ConfirmDialog";
import FormField from "../components/ui/FormField";
import Modal from "../components/ui/Modal";
import RegistryHeader from "../components/ui/RegistryHeader";
import SearchableSelect from "../components/ui/SearchableSelect";
import StatusBadge from "../components/ui/StatusBadge";
import { hasPermission } from "../config/navigation";
import SummaryCard from "../components/ui/SummaryCard";
import {
  ApiError,
  masterListApi,
  officeApi,
  roleApi,
  userApi,
} from "../services/api";
import { useToast } from "../ui/toast-context";

const emptyForm = {
  employeeId: "",
  email: "",
  firstName: "",
  middleName: "",
  lastName: "",
  extension: "",
  position: "",
  employmentType: "Permanent",
  contactNumber: "",
  birthDate: "",
  isOfficeHead: false,
  isActive: true,
  officeId: "",
  roleId: "",
  password: "lala",
};
const inputClass =
  "h-11 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm text-slate-800 outline-none transition placeholder:text-slate-400 focus:border-sky-500 focus:ring-2 focus:ring-sky-100";

const roleTone = {
  platform_admin: "border-violet-200 bg-violet-50 text-violet-700",
  administrator: "border-violet-200 bg-violet-50 text-violet-700",
  audit_manager: "border-cyan-200 bg-cyan-50 text-cyan-700",
  auditor: "border-blue-200 bg-blue-50 text-blue-700",
  auditee_representative: "border-emerald-200 bg-emerald-50 text-emerald-700",
};

function SubtleBadge({ children, className = "" }) {
  return (
    <span
      className={`inline-flex max-w-full items-center rounded-full border px-2.5 py-1 text-xs font-semibold leading-none ${className}`}
    >
      <span className="truncate">{children}</span>
    </span>
  );
}

export default function UserRegistryPage() {
  const { user: currentUser } = useAuth();
  const toast = useToast();
  const [users, setUsers] = useState([]);
  const [offices, setOffices] = useState([]);
  const [roles, setRoles] = useState([]);
  const [positions, setPositions] = useState([]);
  const [employmentTypes, setEmploymentTypes] = useState([]);
  const [loading, setLoading] = useState(true);
  const [search, setSearch] = useState("");
  const [roleFilter, setRoleFilter] = useState("");
  const [statusFilter, setStatusFilter] = useState("");
  const [editing, setEditing] = useState(null);
  const [form, setForm] = useState(emptyForm);
  const [open, setOpen] = useState(false);
  const [errors, setErrors] = useState({});
  const [saving, setSaving] = useState(false);
  const [saveConfirmOpen, setSaveConfirmOpen] = useState(false);
  const [archiveTarget, setArchiveTarget] = useState(null);
  const [restoreTarget, setRestoreTarget] = useState(null);
  const [resetTarget, setResetTarget] = useState(null);
  const [selectedUser, setSelectedUser] = useState(null);
  const [resetPassword, setResetPassword] = useState("lala");
  const canCreate = hasPermission(currentUser, "users.create");
  const canUpdate = hasPermission(currentUser, "users.update");
  const canDeactivate = hasPermission(currentUser, "users.deactivate");
  const canRestore = hasPermission(currentUser, "users.restore");
  const canResetPassword = hasPermission(currentUser, "users.reset_password");
  const canManage = canCreate || canUpdate;
  const assignableRoles =
    currentUser.roleCode === "platform_admin"
      ? roles
      : roles.filter((role) => role.code !== "platform_admin");

  useEffect(() => {
    let active = true;
    const requests = canManage
      ? [
          userApi.list({ includeArchived: true }),
          officeApi.list(),
          roleApi.list(),
          masterListApi.list(),
        ]
      : [
          userApi.list(),
          Promise.resolve([]),
          Promise.resolve([]),
          Promise.resolve([]),
        ];

    Promise.all(requests)
      .then(([userRecords, officeRecords, roleRecords, masterLists]) => {
        if (!active) return;
        setUsers(userRecords);
        setOffices(officeRecords);
        setRoles(roleRecords);
        setPositions(
          masterLists.find((list) => list.code === "POSITION")?.items ?? [],
        );
        setEmploymentTypes(
          masterLists.find((list) => list.code === "GOVERNMENT_EMPLOYMENT_TYPE")
            ?.items ?? [],
        );
      })
      .catch((error) => active && toast.error(error.message))
      .finally(() => active && setLoading(false));

    return () => {
      active = false;
    };
  }, [canManage, toast]);

  const filtered = useMemo(() => {
    const query = search.trim().toLowerCase();
    return users.filter(
      (user) =>
        (!query ||
          [
            user.employeeId,
            user.name,
            user.office,
            user.role,
            user.position,
            user.employmentType,
          ].some((value) => value?.toLowerCase().includes(query))) &&
        (!roleFilter || user.roleCode === roleFilter) &&
        (!statusFilter ||
          (statusFilter === "archived"
            ? user.isArchived
            : statusFilter === "active"
              ? user.isActive && !user.isArchived
              : !user.isActive && !user.isArchived)),
    );
  }, [roleFilter, search, statusFilter, users]);

  const userStats = useMemo(() => {
    const active = users.filter(
      (user) => user.isActive && !user.isArchived,
    ).length;
    const archived = users.filter((user) => user.isArchived).length;
    const inactive = users.length - active - archived;

    return {
      total: users.length,
      active,
      inactive,
      archived,
    };
  }, [users]);

  const hasActiveFilters = Boolean(search || roleFilter || statusFilter);

  const officeOptions = useMemo(
    () =>
      offices.map((office) => ({
        value: office.id,
        label: `${office.code} — ${office.name}`,
        keywords: `${office.sector ?? ""} ${office.headName ?? ""}`,
      })),
    [offices],
  );
  const roleOptions = useMemo(
    () =>
      assignableRoles.map((role) => ({
        value: role.id,
        label: role.name,
        description: role.description,
      })),
    [assignableRoles],
  );
  const roleFilterOptions = useMemo(
    () =>
      [
        ...new Map(
          users.map((user) => [
            user.roleCode,
            { value: user.roleCode, label: user.role },
          ]),
        ).values(),
      ].sort((left, right) => left.label.localeCompare(right.label)),
    [users],
  );
  const positionOptions = useMemo(
    () =>
      positions
        .filter((position) => position.isActive)
        .map((position) => ({
          value: position.label,
          label: position.label,
          keywords: position.code,
        })),
    [positions],
  );
  const employmentTypeOptions = useMemo(
    () =>
      employmentTypes
        .filter((type) => type.isActive)
        .map((type) => ({
          value: type.label,
          label: type.label,
          description: type.description,
          keywords: type.code,
        })),
    [employmentTypes],
  );

  function showEditor(user = null) {
    setEditing(user);
    setErrors({});
    setForm(
      user
        ? {
            employeeId: user.employeeId ?? "",
            email: user.email ?? "",
            firstName: user.firstName,
            middleName: user.middleName ?? "",
            lastName: user.lastName ?? "",
            extension: user.extension ?? "",
            position: user.position ?? "",
            employmentType: user.employmentType ?? "",
            contactNumber: user.contactNumber ?? "",
            birthDate: user.birthDate ?? "",
            isOfficeHead: user.isOfficeHead,
            isActive: user.isActive,
            officeId: user.officeId,
            roleId: user.roleId,
            password: "",
          }
        : {
            ...emptyForm,
            officeId: offices[0]?.id ?? "",
            roleId:
              assignableRoles.find(
                (role) => role.code === "auditee_representative",
              )?.id ??
              assignableRoles[0]?.id ??
              "",
          },
    );
    setOpen(true);
  }

  function change(event) {
    const { name, type, checked, value } = event.target;
    setForm((current) => ({
      ...current,
      [name]: type === "checkbox" ? checked : value,
    }));
    setErrors((current) => ({ ...current, [name]: undefined }));
  }

  function save(event) {
    event.preventDefault();
    setSaveConfirmOpen(true);
  }

  async function persistUser() {
    setSaving(true);
    setErrors({});
    const payload = {
      ...form,
      officeId: Number(form.officeId),
      roleId: Number(form.roleId),
    };

    try {
      if (editing) await userApi.update(editing.id, payload);
      else await userApi.create(payload);
      if (
        payload.position &&
        !positions.some(
          (position) =>
            position.label.toLowerCase() === payload.position.toLowerCase(),
        )
      ) {
        setPositions((current) => [
          ...current,
          {
            id: `custom-${payload.position}`,
            code: payload.position.toUpperCase().replaceAll(/\W+/g, "_"),
            label: payload.position,
            isActive: true,
          },
        ]);
      }
      setUsers(await userApi.list({ includeArchived: true }));
      setSaveConfirmOpen(false);
      setOpen(false);
      toast.success(
        editing ? "User updated successfully." : "User created successfully.",
      );
    } catch (error) {
      if (error instanceof ApiError && error.status === 422)
        setErrors(error.errors);
      toast.error(error.message);
    } finally {
      setSaving(false);
    }
  }

  async function archiveUser() {
    if (!archiveTarget) return;
    setSaving(true);
    try {
      await userApi.remove(archiveTarget.id);
      setUsers((current) =>
        current.map((item) =>
          item.id === archiveTarget.id
            ? { ...item, isActive: false, isArchived: true }
            : item,
        ),
      );
      setArchiveTarget(null);
      toast.success("User archived successfully.");
    } catch (error) {
      toast.error(error.message);
    } finally {
      setSaving(false);
    }
  }

  async function restoreUser() {
    if (!restoreTarget) return;
    setSaving(true);
    try {
      const restored = await userApi.restore(restoreTarget.id);
      setUsers((current) =>
        current.map((item) => (item.id === restored.id ? restored : item)),
      );
      setRestoreTarget(null);
      toast.success("User restored successfully.");
    } catch (error) {
      toast.error(error.message);
    } finally {
      setSaving(false);
    }
  }

  async function resetUserPassword() {
    if (!resetTarget) return;
    setSaving(true);
    try {
      await userApi.resetPassword(resetTarget.id, resetPassword);
      setResetTarget(null);
      setResetPassword("lala");
      toast.success(`Password reset successfully for ${resetTarget.name}.`);
    } catch (error) {
      toast.error(error.message);
    } finally {
      setSaving(false);
    }
  }

  const columns = [
    {
      key: "name",
      label: "User",
      render: (user) => (
        <div className="flex min-w-60 items-center gap-3 py-0.5">
          <span className="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-sky-100 text-xs font-bold text-sky-700 ring-2 ring-white">
            {user.initials}
          </span>
          <span className="min-w-0">
            <strong className="block truncate text-sm font-bold text-slate-800">
              {user.name}
            </strong>
            <small className="mt-0.5 block truncate text-xs font-medium text-slate-500">
              {user.employeeId}
            </small>
          </span>
        </div>
      ),
    },
    {
      key: "office",
      label: "Office",
      render: (user) => (
        <div className="min-w-56 max-w-72">
          <strong className="block text-sm font-bold text-slate-700">
            {user.officeCode}
          </strong>
          <span className="mt-0.5 block truncate text-xs text-slate-500">
            {user.office}
          </span>
        </div>
      ),
    },
    {
      key: "role",
      label: "Access Role",
      render: (user) => (
        <SubtleBadge
          className={
            roleTone[user.roleCode] ??
            "border-slate-200 bg-slate-50 text-slate-600"
          }
        >
          {user.role}
        </SubtleBadge>
      ),
    },
    {
      key: "position",
      label: "Position",
      render: (user) => (
        <div className="min-w-40">
          <span className="block text-sm font-medium text-slate-700">
            {user.position || "—"}
          </span>
          {user.isOfficeHead && (
            <span className="mt-1 inline-flex items-center gap-1 text-[11px] font-semibold text-sky-700">
              <ShieldCheck size={12} /> Office head
            </span>
          )}
        </div>
      ),
    },
    {
      key: "employmentType",
      label: "Employment Type",
      render: (user) => (
        <SubtleBadge className="border-slate-200 bg-slate-50 text-slate-600">
          {user.employmentType || "—"}
        </SubtleBadge>
      ),
    },
    {
      key: "status",
      label: "Status",
      render: (user) => (
        <StatusBadge
          tone={
            user.isArchived ? "inactive" : user.isActive ? "active" : "warning"
          }
        >
          {user.isArchived ? "Archived" : user.isActive ? "Active" : "Inactive"}
        </StatusBadge>
      ),
    },
  ];

  if (canUpdate || canDeactivate || canRestore || canResetPassword) {
    columns.push({
      key: "actions",
      label: "Actions",
      className: "text-right",
      render: (user) => (
        <div className="flex min-w-36 justify-end gap-1.5">
          {canUpdate && !user.isArchived && (
            <button
              aria-label={`Edit ${user.name}`}
              className="grid h-9 w-9 place-items-center rounded-lg border border-slate-200 bg-white text-sky-700 shadow-sm transition hover:border-sky-200 hover:bg-sky-50"
              onClick={(event) => {
                event.stopPropagation();
                showEditor(user);
              }}
              title="Edit user"
              type="button"
            >
              <Pencil size={16} />
            </button>
          )}
          {canResetPassword && !user.isArchived && (
            <button
              aria-label={`Reset password for ${user.name}`}
              className="grid h-9 w-9 place-items-center rounded-lg border border-slate-200 bg-white text-amber-700 shadow-sm transition hover:border-amber-200 hover:bg-amber-50"
              onClick={(event) => {
                event.stopPropagation();
                setResetTarget(user);
                setResetPassword("lala");
              }}
              title="Reset password"
              type="button"
            >
              <KeyRound size={16} />
            </button>
          )}
          {canDeactivate && !user.isArchived && user.id !== currentUser.id && (
            <button
              aria-label={`Archive ${user.name}`}
              className="grid h-9 w-9 place-items-center rounded-lg border border-slate-200 bg-white text-red-600 shadow-sm transition hover:border-red-200 hover:bg-red-50"
              onClick={(event) => {
                event.stopPropagation();
                setArchiveTarget(user);
              }}
              title="Archive user"
              type="button"
            >
              <Archive size={16} />
            </button>
          )}
          {canRestore && user.isArchived && (
            <button
              aria-label={`Restore ${user.name}`}
              className="grid h-9 w-9 place-items-center rounded-lg border border-slate-200 bg-white text-emerald-700 shadow-sm transition hover:border-emerald-200 hover:bg-emerald-50"
              onClick={(event) => {
                event.stopPropagation();
                setRestoreTarget(user);
              }}
              title="Restore user"
              type="button"
            >
              <RotateCcw size={16} />
            </button>
          )}
        </div>
      ),
    });
  }

  return (
    <div className="min-h-full bg-sky-50/40 p-4 sm:p-5">
      <RegistryHeader
        actions={
          canCreate && (
            <button
              className="flex h-11 items-center gap-2 rounded-lg bg-sky-700 px-4 text-sm font-bold text-white shadow-sm transition hover:bg-sky-800 focus:outline-none focus:ring-2 focus:ring-sky-300"
              onClick={() => showEditor()}
              type="button"
            >
              <Plus size={18} /> Add user
            </button>
          )
        }
        description="Manage employee identities, office assignments, access roles, and account status."
        icon={UserRound}
        readOnly={!canManage}
        title="User Registry"
      />

      <section className="mb-3 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <SummaryCard
          icon={UsersRound}
          label="Total users"
          tone="sky"
          value={userStats.total}
        />
        <SummaryCard
          icon={UserCheck}
          label="Active accounts"
          tone="emerald"
          value={userStats.active}
        />
        <SummaryCard
          icon={UserX}
          label="Inactive accounts"
          tone="amber"
          value={userStats.inactive}
        />
        <SummaryCard
          icon={Archive}
          label="Archived accounts"
          tone="slate"
          value={userStats.archived}
        />
      </section>

      <section className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <header className="border-b border-slate-200 bg-white px-4 py-4">
          <div className="flex flex-col gap-3 xl:flex-row xl:items-center xl:justify-between">
            <div>
              <h2 className="text-sm font-bold text-slate-800">
                Employee accounts
              </h2>
              <p className="mt-0.5 text-xs text-slate-500">
                Showing {filtered.length} of {users.length} users
              </p>
            </div>

            <div className="grid w-full gap-2 md:grid-cols-[minmax(16rem,1fr)_13rem_11rem_auto] xl:w-auto">
              <label className="flex h-11 items-center gap-2 rounded-lg border border-slate-300 bg-white px-3 text-slate-500 transition focus-within:border-sky-500 focus-within:ring-2 focus-within:ring-sky-100">
                <Search size={17} className="shrink-0" />
                <input
                  className="min-w-0 flex-1 bg-transparent text-sm text-slate-800 outline-none placeholder:text-slate-400"
                  onChange={(event) => setSearch(event.target.value)}
                  placeholder="Search name, ID, office..."
                  value={search}
                />
              </label>

              <SearchableSelect
                onChange={setRoleFilter}
                options={[
                  { value: "", label: "All roles" },
                  ...roleFilterOptions,
                ]}
                placeholder="Filter by role"
                searchPlaceholder="Search roles..."
                value={roleFilter}
              />

              <SearchableSelect
                onChange={setStatusFilter}
                options={[
                  { value: "", label: "All statuses" },
                  { value: "active", label: "Active" },
                  { value: "inactive", label: "Inactive" },
                  { value: "archived", label: "Archived" },
                ]}
                placeholder="Filter by status"
                searchPlaceholder="Search statuses..."
                value={statusFilter}
              />

              {hasActiveFilters && (
                <button
                  className="flex h-11 items-center justify-center gap-2 rounded-lg border border-slate-300 bg-white px-3 text-sm font-semibold text-slate-600 transition hover:bg-slate-50"
                  onClick={() => {
                    setSearch("");
                    setRoleFilter("");
                    setStatusFilter("");
                  }}
                  type="button"
                >
                  <X size={16} /> Clear
                </button>
              )}
            </div>
          </div>
        </header>

        <div className="[&_tbody_tr]:transition-colors [&_tbody_tr:hover]:bg-sky-50/60 [&_thead]:bg-slate-50/90">
          <DataTable
            columns={columns}
            emptyMessage="No users match your search."
            initialPageSize={8}
            loading={loading}
            onRowClick={setSelectedUser}
            pageSizeOptions={[8, 10, 25, 50]}
            rows={filtered}
          />
        </div>
      </section>

      <Modal
        onClose={() => setSelectedUser(null)}
        open={Boolean(selectedUser)}
        size="lg"
        title={selectedUser?.name ?? "User details"}
      >
        {selectedUser && (
          <div className="grid gap-5">
            <div className="flex flex-wrap items-center gap-4 rounded-xl bg-slate-50 p-4">
              <span className="grid h-14 w-14 place-items-center rounded-full bg-sky-700 text-lg font-bold text-white">
                {selectedUser.initials}
              </span>
              <div className="min-w-0 flex-1">
                <h3 className="font-bold text-slate-800">
                  {selectedUser.name}
                </h3>
                <p className="text-sm text-slate-500">
                  {selectedUser.employeeId} · {selectedUser.role}
                </p>
              </div>
              <StatusBadge
                tone={
                  selectedUser.isArchived
                    ? "inactive"
                    : selectedUser.isActive
                      ? "active"
                      : "warning"
                }
              >
                {selectedUser.isArchived
                  ? "Archived"
                  : selectedUser.isActive
                    ? "Active"
                    : "Inactive"}
              </StatusBadge>
            </div>
            <div className="grid gap-3 sm:grid-cols-2">
              {[
                ["First name", selectedUser.firstName],
                ["Middle name", selectedUser.middleName || "—"],
                ["Last name", selectedUser.lastName],
                ["Extension", selectedUser.extension || "—"],
                [
                  "Office",
                  `${selectedUser.officeCode} — ${selectedUser.office}`,
                ],
                ["Position", selectedUser.position || "—"],
                ["Employment type", selectedUser.employmentType || "—"],
                ["Email", selectedUser.email || "—"],
                ["Contact number", selectedUser.contactNumber || "—"],
                ["Birth date", selectedUser.birthDate || "—"],
                [
                  "Office responsibility",
                  selectedUser.isOfficeHead
                    ? "Office head"
                    : "Office personnel",
                ],
                [
                  "Last sign-in",
                  selectedUser.lastLoginAt || "No sign-in recorded",
                ],
              ].map(([label, value]) => (
                <div
                  className="rounded-lg border border-slate-200 bg-white p-3"
                  key={label}
                >
                  <span className="block text-[11px] font-bold uppercase tracking-wide text-slate-400">
                    {label}
                  </span>
                  <strong className="mt-1 block break-words text-sm text-slate-700">
                    {value}
                  </strong>
                </div>
              ))}
            </div>
          </div>
        )}
      </Modal>

      <Modal
        footer={
          <>
            <button
              className="h-10 rounded-lg border px-4 text-sm font-bold"
              onClick={() => setOpen(false)}
              type="button"
            >
              Cancel
            </button>
            <button
              className="h-10 rounded-lg bg-sky-700 px-5 text-sm font-bold text-white disabled:opacity-60"
              disabled={saving}
              form="user-form"
              type="submit"
            >
              {saving ? "Saving..." : "Save user"}
            </button>
          </>
        }
        onClose={() => !saving && setOpen(false)}
        open={open}
        size="lg"
        title={editing ? "Edit user" : "Add user"}
      >
        <form className="grid gap-4" id="user-form" onSubmit={save}>
          <div className="grid gap-4 sm:grid-cols-2">
            {[
              ["employeeId", "Employee ID", true],
              ["firstName", "First name", true],
              ["middleName", "Middle name", false],
              ["lastName", "Last name", true],
              ["extension", "Name extension", false],
              ["email", "Email", false],
              ["contactNumber", "Contact number", false],
              ["birthDate", "Birth date", false, "date"],
            ].map(([name, label, required, type = "text"]) => (
              <FormField
                error={errors[name]?.[0]}
                htmlFor={`user-${name}`}
                key={name}
                label={label}
                required={required}
              >
                <input
                  className={inputClass}
                  id={`user-${name}`}
                  name={name}
                  onChange={change}
                  type={type}
                  value={form[name]}
                />
              </FormField>
            ))}
            <FormField
              error={errors.position?.[0]}
              label="Position"
              hint="Search the government position catalogue or type an authorized custom title."
            >
              <SearchableSelect
                allowCustom
                onChange={(position) =>
                  setForm((current) => ({ ...current, position }))
                }
                options={positionOptions}
                placeholder="Select or type a position"
                searchPlaceholder="Search or type a position..."
                value={form.position}
              />
            </FormField>
            <FormField
              error={errors.employmentType?.[0]}
              label="Government employment type"
              hint="Appointment or engagement category maintained in Master Lists."
            >
              <SearchableSelect
                onChange={(employmentType) =>
                  setForm((current) => ({ ...current, employmentType }))
                }
                options={employmentTypeOptions}
                placeholder="Select employment type"
                searchPlaceholder="Search employment types..."
                value={form.employmentType}
              />
            </FormField>
            <FormField
              error={errors.officeId?.[0]}
              htmlFor="user-office"
              label="Office"
              required
            >
              <SearchableSelect
                onChange={(officeId) =>
                  setForm((current) => ({ ...current, officeId }))
                }
                options={officeOptions}
                placeholder="Select an office"
                searchPlaceholder="Search offices..."
                value={form.officeId}
              />
            </FormField>
            <FormField
              error={errors.roleId?.[0]}
              htmlFor="user-role"
              label="Access role"
              required
            >
              <SearchableSelect
                onChange={(roleId) =>
                  setForm((current) => ({ ...current, roleId }))
                }
                options={roleOptions}
                placeholder="Select an access role"
                searchPlaceholder="Search roles..."
                value={form.roleId}
              />
            </FormField>
            {!editing && (
              <FormField
                error={errors.password?.[0]}
                htmlFor="user-password"
                label="Temporary password"
                required
              >
                <input
                  className={inputClass}
                  id="user-password"
                  name="password"
                  onChange={change}
                  type="text"
                  value={form.password}
                />
              </FormField>
            )}
          </div>
          <div className="flex flex-wrap gap-5 rounded-lg bg-slate-50 p-3 text-sm font-semibold">
            <label className="flex items-center gap-2">
              <input
                checked={form.isOfficeHead}
                name="isOfficeHead"
                onChange={change}
                type="checkbox"
              />
              Office head
            </label>
            <label className="flex items-center gap-2">
              <input
                checked={form.isActive}
                name="isActive"
                onChange={change}
                type="checkbox"
              />
              Active account
            </label>
          </div>
        </form>
      </Modal>

      <ConfirmDialog
        busy={saving}
        confirmLabel={editing ? "Update user" : "Add user"}
        description={
          editing
            ? `Apply these account changes to ${editing.name}?`
            : `Create the account for ${[form.firstName, form.middleName, form.lastName, form.extension].filter(Boolean).join(" ") || "this user"}?`
        }
        onCancel={() => setSaveConfirmOpen(false)}
        onConfirm={persistUser}
        open={saveConfirmOpen}
        title={editing ? "Confirm user update" : "Confirm new user"}
      />

      <ConfirmDialog
        busy={saving}
        confirmLabel="Archive user"
        description={`${archiveTarget?.name ?? "This user"} will no longer be able to sign in. The account and history remain recoverable.`}
        onCancel={() => setArchiveTarget(null)}
        onConfirm={archiveUser}
        open={Boolean(archiveTarget)}
        title="Archive this user?"
        tone="danger"
      />

      <ConfirmDialog
        busy={saving}
        confirmLabel="Restore user"
        description={`${restoreTarget?.name ?? "This user"} will be restored as an active account.`}
        onCancel={() => setRestoreTarget(null)}
        onConfirm={restoreUser}
        open={Boolean(restoreTarget)}
        title="Restore this user?"
      />

      <Modal
        description={`Set a temporary password for ${resetTarget?.name ?? "this user"}. Their failed login lock will also be cleared.`}
        footer={
          <>
            <button
              className="h-10 rounded-lg border border-slate-300 bg-white px-4 text-sm font-bold text-slate-700 hover:bg-slate-100"
              disabled={saving}
              onClick={() => setResetTarget(null)}
              type="button"
            >
              Cancel
            </button>
            <button
              className="h-10 rounded-lg bg-amber-600 px-5 text-sm font-bold text-white hover:bg-amber-700 disabled:opacity-60"
              disabled={saving || resetPassword.length < 4}
              onClick={resetUserPassword}
              type="button"
            >
              {saving ? "Resetting..." : "Reset password"}
            </button>
          </>
        }
        onClose={() => !saving && setResetTarget(null)}
        open={Boolean(resetTarget)}
        size="sm"
        title="Reset user password?"
      >
        <FormField
          htmlFor="reset-user-password"
          label="Temporary password"
          required
        >
          <input
            autoComplete="new-password"
            className={inputClass}
            id="reset-user-password"
            minLength={4}
            onChange={(event) => setResetPassword(event.target.value)}
            type="text"
            value={resetPassword}
          />
        </FormField>
        <p className="mt-3 rounded-lg bg-amber-50 px-3 py-2 text-xs leading-5 text-amber-800">
          The demo default is <strong>lala</strong>. Share temporary passwords
          through an approved secure channel.
        </p>
      </Modal>
    </div>
  );
}
