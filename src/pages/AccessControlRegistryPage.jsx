import { useEffect, useMemo, useState } from "react";
import { KeyRound, Pencil, Search, ShieldCheck } from "lucide-react";
import { useAuth } from "../auth/auth-context";
import DataTable from "../components/ui/DataTable";
import ConfirmDialog from "../components/ui/ConfirmDialog";
import FormField from "../components/ui/FormField";
import Modal from "../components/ui/Modal";
import RegistryHeader from "../components/ui/RegistryHeader";
import StatusBadge from "../components/ui/StatusBadge";
import { hasPermission } from "../config/navigation";
import { permissionApi, roleApi } from "../services/api";
import { useToast } from "../ui/toast-context";

export default function AccessControlRegistryPage({ mode }) {
  const { user } = useAuth();
  const toast = useToast();
  const [roles, setRoles] = useState([]);
  const [permissions, setPermissions] = useState([]);
  const [loading, setLoading] = useState(true);
  const [search, setSearch] = useState("");
  const [editing, setEditing] = useState(null);
  const [form, setForm] = useState(null);
  const [saving, setSaving] = useState(false);
  const [confirmOpen, setConfirmOpen] = useState(false);
  const [selectedRole, setSelectedRole] = useState(null);
  const isRoles = mode === "roles";
  const canUpdate = isRoles && hasPermission(user, "roles.update");

  useEffect(() => {
    let active = true;
    Promise.all([
      isRoles ? roleApi.list() : Promise.resolve([]),
      permissionApi.list(),
    ])
      .then(([roleRecords, permissionRecords]) => {
        if (!active) return;
        setRoles(roleRecords);
        setPermissions(permissionRecords);
      })
      .catch((error) => active && toast.error(error.message))
      .finally(() => active && setLoading(false));
    return () => {
      active = false;
    };
  }, [isRoles, toast]);

  const rows = useMemo(() => {
    const source = isRoles ? roles : permissions;
    const query = search.trim().toLowerCase();
    if (!query) return source;
    return source.filter((item) =>
      [item.code, item.name, item.description, item.module].some((value) =>
        value?.toLowerCase().includes(query),
      ),
    );
  }, [isRoles, permissions, roles, search]);

  function editRole(role) {
    setEditing(role);
    setForm({
      name: role.name,
      description: role.description ?? "",
      isActive: role.isActive,
      permissionIds: role.permissionIds,
    });
  }

  function saveRole(event) {
    event.preventDefault();
    setConfirmOpen(true);
  }

  async function persistRole() {
    setSaving(true);
    try {
      await roleApi.update(editing.id, form);
      setRoles(await roleApi.list());
      setConfirmOpen(false);
      setEditing(null);
      toast.success("Access role updated successfully.");
    } catch (error) {
      toast.error(error.message);
    } finally {
      setSaving(false);
    }
  }

  const columns = isRoles
    ? [
        {
          key: "name",
          label: "Access Role",
          render: (role) => (
            <div className="min-w-64">
              <strong className="text-slate-800">{role.name}</strong>
              <p className="mt-1 text-xs text-slate-500">{role.description}</p>
            </div>
          ),
        },
        { key: "code", label: "Role Code" },
        { key: "usersCount", label: "Users" },
        {
          key: "permissions",
          label: "Permissions",
          render: (role) => `${role.permissions.length} assigned`,
        },
        {
          key: "status",
          label: "Status",
          render: (role) => (
            <StatusBadge tone={role.isActive ? "active" : "inactive"}>
              {role.isActive ? "Active" : "Inactive"}
            </StatusBadge>
          ),
        },
        ...(canUpdate
          ? [
              {
                key: "actions",
                label: "Actions",
                className: "text-right",
                render: (role) =>
                  (role.code !== "platform_admin" ||
                    user.roleCode === "platform_admin") && (
                    <button
                      className="grid h-9 w-9 place-items-center rounded-lg text-blue-700 hover:bg-blue-100"
                      onClick={() => editRole(role)}
                      type="button"
                    >
                      <Pencil size={17} />
                    </button>
                  ),
              },
            ]
          : []),
      ]
    : [
        {
          key: "name",
          label: "Permission",
          render: (permission) => (
            <div className="min-w-60">
              <strong className="text-slate-800">{permission.name}</strong>
              <p className="mt-1 text-xs text-slate-500">
                {permission.description}
              </p>
            </div>
          ),
        },
        { key: "code", label: "Permission Code" },
        {
          key: "module",
          label: "Module",
          render: (permission) => (
            <StatusBadge>{permission.module.replaceAll("_", " ")}</StatusBadge>
          ),
        },
        { key: "action", label: "Action" },
      ];

  const groupedPermissions = permissions.reduce((groups, permission) => {
    const modulePermissions = groups[permission.module] ?? [];
    return {
      ...groups,
      [permission.module]: [...modulePermissions, permission],
    };
  }, {});

  return (
    <div className="p-4 sm:p-6">
      <RegistryHeader
        description={
          isRoles
            ? "Review the six standard AGIS roles and assign their permitted capabilities."
            : "Review the stable module.action permission catalogue used by server authorization."
        }
        icon={isRoles ? KeyRound : ShieldCheck}
        readOnly={!canUpdate}
        title={isRoles ? "Access Role Registry" : "Permission Registry"}
      />
      <section className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <header className="flex flex-wrap items-center justify-between gap-3 border-b p-4">
          <strong>{rows.length} records</strong>
          <label className="flex h-10 w-full max-w-sm items-center gap-2 rounded-lg border px-3 text-slate-500">
            <Search size={17} />
            <input
              className="min-w-0 flex-1 outline-none"
              onChange={(event) => setSearch(event.target.value)}
              placeholder="Search access controls..."
              value={search}
            />
          </label>
        </header>
        <DataTable
          columns={columns}
          loading={loading}
          onRowClick={isRoles ? setSelectedRole : undefined}
          rows={rows}
        />
      </section>

      <Modal
        onClose={() => setSelectedRole(null)}
        open={Boolean(selectedRole)}
        size="xl"
        title={selectedRole?.name ?? "Access role details"}
      >
        {selectedRole && (
          <div className="grid gap-5">
            <div className="rounded-xl bg-slate-50 p-4">
              <div className="flex flex-wrap items-center gap-2">
                <StatusBadge>{selectedRole.code}</StatusBadge>
                <StatusBadge
                  tone={selectedRole.isActive ? "active" : "inactive"}
                >
                  {selectedRole.isActive ? "Active" : "Inactive"}
                </StatusBadge>
                {selectedRole.isSystem && (
                  <StatusBadge tone="warning">System role</StatusBadge>
                )}
              </div>
              <p className="mt-3 text-sm leading-6 text-slate-600">
                {selectedRole.description || "No description provided."}
              </p>
              <p className="mt-2 text-xs font-semibold text-slate-500">
                {selectedRole.usersCount} assigned{" "}
                {selectedRole.usersCount === 1 ? "user" : "users"} ·{" "}
                {selectedRole.permissionIds.length} permissions
              </p>
            </div>
            <div className="grid gap-3 md:grid-cols-2">
              {Object.entries(
                permissions
                  .filter((permission) =>
                    selectedRole.permissionIds.includes(permission.id),
                  )
                  .reduce(
                    (groups, permission) => ({
                      ...groups,
                      [permission.module]: [
                        ...(groups[permission.module] ?? []),
                        permission,
                      ],
                    }),
                    {},
                  ),
              ).map(([module, modulePermissions]) => (
                <section
                  className="rounded-xl border border-slate-200 p-4"
                  key={module}
                >
                  <h3 className="text-xs font-bold uppercase tracking-wide text-slate-500">
                    {module.replaceAll("_", " ")}
                  </h3>
                  <div className="mt-2 flex flex-wrap gap-2">
                    {modulePermissions.map((permission) => (
                      <span
                        className="rounded-md bg-sky-50 px-2.5 py-1.5 text-xs font-semibold text-sky-800"
                        key={permission.id}
                        title={permission.description}
                      >
                        {permission.action.replaceAll("_", " ")}
                      </span>
                    ))}
                  </div>
                </section>
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
              onClick={() => setEditing(null)}
              type="button"
            >
              Cancel
            </button>
            <button
              className="h-10 rounded-lg bg-sky-700 px-5 text-sm font-bold text-white"
              form="role-form"
              type="submit"
            >
              {saving ? "Saving..." : "Save role"}
            </button>
          </>
        }
        onClose={() => !saving && setEditing(null)}
        open={Boolean(editing)}
        size="lg"
        title={`Edit ${editing?.name ?? "access role"}`}
      >
        {form && (
          <form className="grid gap-4" id="role-form" onSubmit={saveRole}>
            <FormField htmlFor="role-name" label="Role name">
              <input
                className="h-11 w-full rounded-lg border px-3"
                id="role-name"
                onChange={(event) =>
                  setForm({ ...form, name: event.target.value })
                }
                value={form.name}
              />
            </FormField>
            <FormField htmlFor="role-description" label="Description">
              <textarea
                className="min-h-24 w-full rounded-lg border p-3"
                id="role-description"
                onChange={(event) =>
                  setForm({ ...form, description: event.target.value })
                }
                value={form.description}
              />
            </FormField>
            <div className="max-h-80 overflow-y-auto rounded-lg border p-3">
              {Object.entries(groupedPermissions).map(
                ([module, modulePermissions]) => (
                  <fieldset className="mb-4" key={module}>
                    <legend className="mb-2 text-xs font-bold uppercase text-slate-500">
                      {module.replaceAll("_", " ")}
                    </legend>
                    <div className="grid gap-2 sm:grid-cols-2">
                      {modulePermissions.map((permission) => (
                        <label
                          className="flex gap-2 rounded-md bg-slate-50 px-2 py-2 text-xs"
                          key={permission.id}
                        >
                          <input
                            checked={form.permissionIds.includes(permission.id)}
                            onChange={() =>
                              setForm((current) => ({
                                ...current,
                                permissionIds: current.permissionIds.includes(
                                  permission.id,
                                )
                                  ? current.permissionIds.filter(
                                      (id) => id !== permission.id,
                                    )
                                  : [
                                      ...current.permissionIds,
                                      permission.id,
                                    ],
                              }))
                            }
                            type="checkbox"
                          />
                          {permission.name}
                        </label>
                      ))}
                    </div>
                  </fieldset>
                ),
              )}
            </div>
            <label className="flex items-center gap-2 text-sm font-semibold">
              <input
                checked={form.isActive}
                onChange={(event) =>
                  setForm({ ...form, isActive: event.target.checked })
                }
                type="checkbox"
              />
              Active role
            </label>
          </form>
        )}
      </Modal>

      <ConfirmDialog
        busy={saving}
        confirmLabel="Update access role"
        description={`Permission changes affect every user assigned to ${editing?.name ?? "this role"}.`}
        onCancel={() => setConfirmOpen(false)}
        onConfirm={persistRole}
        open={confirmOpen}
        title="Confirm access-role update"
        tone="warning"
      />
    </div>
  );
}
