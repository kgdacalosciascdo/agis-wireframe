import { useEffect, useMemo, useState } from "react";
import {
  Archive,
  CircleCheckBig,
  CirclePause,
  Network,
  Pencil,
  Plus,
  RotateCcw,
  Search,
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
import SummaryCard from "../components/ui/SummaryCard";
import { hasPermission } from "../config/navigation";
import { ApiError, auditAreaApi, officeApi } from "../services/api";
import { useToast } from "../ui/toast-context";

const emptyForm = {
  code: "",
  name: "",
  description: "",
  isActive: true,
  officeIds: [],
};
const inputClass =
  "h-11 w-full rounded-lg border border-slate-300 px-3 text-sm outline-none transition focus:border-sky-500 focus:ring-2 focus:ring-sky-100";

function firstError(errors, field) {
  const value = errors?.[field];
  return Array.isArray(value) ? value[0] : value;
}

export default function AuditAreaRegistryPage() {
  const { user } = useAuth();
  const toast = useToast();
  const [areas, setAreas] = useState([]);
  const [offices, setOffices] = useState([]);
  const [loading, setLoading] = useState(true);
  const [search, setSearch] = useState("");
  const [officeFilter, setOfficeFilter] = useState("");
  const [statusFilter, setStatusFilter] = useState("");
  const [form, setForm] = useState(emptyForm);
  const [editing, setEditing] = useState(null);
  const [editorOpen, setEditorOpen] = useState(false);
  const [deleteTarget, setDeleteTarget] = useState(null);
  const [restoreTarget, setRestoreTarget] = useState(null);
  const [saveConfirmOpen, setSaveConfirmOpen] = useState(false);
  const [selectedArea, setSelectedArea] = useState(null);
  const [saving, setSaving] = useState(false);
  const [errors, setErrors] = useState({});
  const canCreate = hasPermission(user, "audit_areas.create");
  const canUpdate = hasPermission(user, "audit_areas.update");
  const canDelete = hasPermission(user, "audit_areas.delete");
  const canRestore = hasPermission(user, "audit_areas.restore");

  useEffect(() => {
    let active = true;

    Promise.all([
      auditAreaApi.list({ includeArchived: true }),
      officeApi.list(),
    ])
      .then(([areaRecords, officeRecords]) => {
        if (!active) return;
        setAreas(areaRecords);
        setOffices(
          officeRecords.filter((office) => office.code !== "AGIS-SYS"),
        );
      })
      .catch((error) => {
        if (active) toast.error(error.message);
      })
      .finally(() => {
        if (active) setLoading(false);
      });

    return () => {
      active = false;
    };
  }, [toast]);

  const filtered = useMemo(() => {
    const query = search.trim().toLowerCase();
    return areas.filter(
      (area) =>
        (!query ||
          [
            area.code,
            area.name,
            area.description,
            ...area.offices.flatMap((office) => [
              office.code,
              office.name,
              office.sector,
            ]),
          ].some((value) => value?.toLowerCase().includes(query))) &&
        (!officeFilter ||
          area.offices.some(
            (office) => String(office.id) === String(officeFilter),
          )) &&
        (!statusFilter ||
          (statusFilter === "archived"
            ? area.isArchived
            : statusFilter === "active"
              ? area.isActive && !area.isArchived
              : !area.isActive && !area.isArchived)),
    );
  }, [areas, officeFilter, search, statusFilter]);

  const areaStats = useMemo(() => {
    const active = areas.filter(
      (area) => area.isActive && !area.isArchived,
    ).length;
    const archived = areas.filter((area) => area.isArchived).length;
    const inactive = areas.length - active - archived;

    return {
      total: areas.length,
      active,
      inactive,
      archived,
    };
  }, [areas]);

  const hasActiveFilters = Boolean(search || officeFilter || statusFilter);

  const officeOptions = useMemo(
    () =>
      offices.map((office) => ({
        value: office.id,
        label: `${office.code} — ${office.name}`,
        keywords: `${office.sector ?? ""} ${office.headName ?? ""}`,
      })),
    [offices],
  );

  function openCreate() {
    setEditing(null);
    setForm(emptyForm);
    setErrors({});
    setEditorOpen(true);
  }

  function openEdit(area) {
    setEditing(area);
    setForm({
      code: area.code,
      name: area.name,
      description: area.description ?? "",
      isActive: area.isActive,
      officeIds: area.offices.map((office) => office.id),
    });
    setErrors({});
    setEditorOpen(true);
  }

  function save(event) {
    event.preventDefault();
    setSaveConfirmOpen(true);
  }

  async function persistArea() {
    setSaving(true);
    setErrors({});

    try {
      if (editing) await auditAreaApi.update(editing.id, form);
      else await auditAreaApi.create(form);
      setAreas(await auditAreaApi.list({ includeArchived: true }));
      setSaveConfirmOpen(false);
      setEditorOpen(false);
      toast.success(
        editing
          ? "Audit area updated successfully."
          : "Audit area created successfully.",
      );
    } catch (error) {
      if (error instanceof ApiError && error.status === 422)
        setErrors(error.errors);
      toast.error(error.message);
    } finally {
      setSaving(false);
    }
  }

  async function archiveArea() {
    setSaving(true);
    try {
      await auditAreaApi.remove(deleteTarget.id);
      setAreas((current) =>
        current.map((area) =>
          area.id === deleteTarget.id
            ? { ...area, isActive: false, isArchived: true }
            : area,
        ),
      );
      setDeleteTarget(null);
      toast.success("Audit area archived successfully.");
    } catch (error) {
      toast.error(error.message);
    } finally {
      setSaving(false);
    }
  }

  async function restoreArea() {
    if (!restoreTarget) return;
    setSaving(true);
    try {
      const restored = await auditAreaApi.restore(restoreTarget.id);
      setAreas((current) =>
        current.map((area) => (area.id === restored.id ? restored : area)),
      );
      setRestoreTarget(null);
      toast.success("Audit area restored successfully.");
    } catch (error) {
      toast.error(error.message);
    } finally {
      setSaving(false);
    }
  }

  const columns = [
    {
      key: "code",
      label: "Code",
      render: (area) => (
        <strong className="whitespace-nowrap text-slate-800">
          {area.code}
        </strong>
      ),
    },
    {
      key: "name",
      label: "Audit Area",
      render: (area) => (
        <div className="min-w-72">
          <strong className="block text-slate-800">{area.name}</strong>
          <p className="mt-1 line-clamp-2 text-xs leading-5 text-slate-500">
            {area.description}
          </p>
        </div>
      ),
    },
    {
      key: "coverage",
      label: "Office Coverage",
      render: (area) => (
        <span className="font-semibold text-slate-700">
          {area.offices.length} offices
        </span>
      ),
    },
    {
      key: "focuses",
      label: "Audit Focuses",
      render: (area) => (
        <span className="font-semibold text-slate-700">
          {area.focuses.length} focuses
        </span>
      ),
    },
    {
      key: "status",
      label: "Status",
      render: (area) => (
        <StatusBadge
          tone={
            area.isArchived ? "inactive" : area.isActive ? "active" : "warning"
          }
        >
          {area.isArchived ? "Archived" : area.isActive ? "Active" : "Inactive"}
        </StatusBadge>
      ),
    },
  ];

  if (canUpdate || canDelete || canRestore) {
    columns.push({
      key: "actions",
      label: "Actions",
      className: "text-right",
      headerClassName: "text-right",
      render: (area) => (
        <div className="flex justify-end gap-1">
          {canUpdate && !area.isArchived && (
            <button
              className="grid h-9 w-9 place-items-center rounded-lg text-blue-700 hover:bg-blue-100"
              onClick={(event) => {
                event.stopPropagation();
                openEdit(area);
              }}
              title="Edit audit area"
              type="button"
            >
              <Pencil size={17} />
            </button>
          )}
          {canDelete && !area.isArchived && (
            <button
              className="grid h-9 w-9 place-items-center rounded-lg text-red-600 hover:bg-red-100"
              onClick={(event) => {
                event.stopPropagation();
                setDeleteTarget(area);
              }}
              title="Archive audit area"
              type="button"
            >
              <Archive size={17} />
            </button>
          )}
          {canRestore && area.isArchived && (
            <button
              className="grid h-9 w-9 place-items-center rounded-lg text-emerald-700 hover:bg-emerald-100"
              onClick={(event) => {
                event.stopPropagation();
                setRestoreTarget(area);
              }}
              title="Restore audit area"
              type="button"
            >
              <RotateCcw size={17} />
            </button>
          )}
        </div>
      ),
    });
  }

  return (
    <div className="p-4 sm:p-6">
      <RegistryHeader
        actions={
          canCreate && (
            <button
              className="flex h-11 items-center gap-2 rounded-lg bg-sky-700 px-4 text-sm font-bold text-white shadow-sm transition hover:-translate-y-0.5 hover:bg-sky-800"
              onClick={openCreate}
              type="button"
            >
              <Plus size={18} /> Add audit area
            </button>
          )
        }
        description="Define auditable subject areas and link each area to one or more city offices."
        icon={Network}
        readOnly={!canCreate && !canUpdate}
        title="Audit Area Registry"
      />

      <section className="mb-3 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <SummaryCard
          icon={Network}
          label="Total audit areas"
          tone="sky"
          value={areaStats.total}
        />
        <SummaryCard
          icon={CircleCheckBig}
          label="Active audit areas"
          tone="emerald"
          value={areaStats.active}
        />
        <SummaryCard
          icon={CirclePause}
          label="Inactive audit areas"
          tone="amber"
          value={areaStats.inactive}
        />
        <SummaryCard
          icon={Archive}
          label="Archived audit areas"
          tone="red"
          value={areaStats.archived}
        />
      </section>

      <section className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <header className="border-b border-slate-200 bg-white p-4">
          <div className="grid w-full gap-2 md:grid-cols-[minmax(16rem,1fr)_16rem_11rem_auto]">
            <label className="flex h-11 items-center gap-2 rounded-lg border border-slate-300 bg-white px-3 text-slate-500 transition focus-within:border-sky-500 focus-within:ring-2 focus-within:ring-sky-100">
              <Search className="shrink-0" size={17} />
              <input
                className="min-w-0 flex-1 bg-transparent text-sm text-slate-800 outline-none placeholder:text-slate-400"
                onChange={(event) => setSearch(event.target.value)}
                placeholder="Search audit area, code, office..."
                type="search"
                value={search}
              />
            </label>

            <SearchableSelect
              onChange={setOfficeFilter}
              options={[
                { value: "", label: "All offices" },
                ...officeOptions,
              ]}
              placeholder="Filter by office"
              searchPlaceholder="Search offices..."
              value={officeFilter}
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

            <button
              className="flex h-11 items-center justify-center gap-2 rounded-lg border border-slate-300 bg-white px-3 text-sm font-semibold text-slate-600 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:bg-slate-50 disabled:text-slate-400"
              disabled={!hasActiveFilters}
              onClick={() => {
                setSearch("");
                setOfficeFilter("");
                setStatusFilter("");
              }}
              type="button"
            >
              <X size={16} />
              Clear filters
            </button>
          </div>
        </header>

        <div className="[&_tbody_tr]:transition-colors [&_tbody_tr:hover]:bg-sky-50/60 [&_thead]:bg-slate-50/90">
          <DataTable
            columns={columns}
            emptyMessage="No audit areas match your filters."
            initialPageSize={8}
            key={`${search}|${officeFilter}|${statusFilter}`}
            loading={loading}
            onRowClick={setSelectedArea}
            pageSizeOptions={[8, 10, 25, 50]}
            rows={filtered}
          />
        </div>
      </section>

      <Modal
        footer={
          <>
            <button
              className="h-10 rounded-lg border border-slate-300 px-4 text-sm font-bold"
              disabled={saving}
              onClick={() => setEditorOpen(false)}
              type="button"
            >
              Cancel
            </button>
            <button
              className="h-10 rounded-lg bg-sky-700 px-5 text-sm font-bold text-white disabled:opacity-60"
              disabled={saving}
              form="audit-area-form"
              type="submit"
            >
              {saving ? "Saving..." : "Save audit area"}
            </button>
          </>
        }
        onClose={() => !saving && setEditorOpen(false)}
        open={editorOpen}
        size="lg"
        title={editing ? "Edit audit area" : "Add audit area"}
      >
        <form className="grid gap-4" id="audit-area-form" onSubmit={save}>
          <div className="grid gap-4 sm:grid-cols-2">
            <FormField
              error={firstError(errors, "code")}
              htmlFor="area-code"
              label="Code"
              required
            >
              <input
                className={inputClass}
                id="area-code"
                onChange={(event) =>
                  setForm({ ...form, code: event.target.value })
                }
                value={form.code}
              />
            </FormField>
            <FormField
              error={firstError(errors, "name")}
              htmlFor="area-name"
              label="Audit area name"
              required
            >
              <input
                className={inputClass}
                id="area-name"
                onChange={(event) =>
                  setForm({ ...form, name: event.target.value })
                }
                value={form.name}
              />
            </FormField>
          </div>
          <FormField
            error={firstError(errors, "description")}
            htmlFor="area-description"
            label="Description"
          >
            <textarea
              className={`${inputClass} min-h-28 py-3`}
              id="area-description"
              onChange={(event) =>
                setForm({ ...form, description: event.target.value })
              }
              value={form.description}
            />
          </FormField>
          <FormField
            error={firstError(errors, "officeIds")}
            label={`Office coverage (${form.officeIds.length} selected)`}
            required
          >
            <SearchableSelect
              multiple
              multipleDisplay="summary"
              onChange={(officeIds) =>
                setForm((current) => ({ ...current, officeIds }))
              }
              options={officeOptions}
              placeholder="Select one or more offices"
              searchPlaceholder="Search offices by name, code, sector, or head..."
              value={form.officeIds}
            />
            {form.officeIds.length > 0 && (
              <div className="mt-2 max-h-52 space-y-2 overflow-y-auto rounded-xl border border-slate-200 bg-slate-50 p-2">
                {form.officeIds.map((officeId) => {
                  const office = offices.find(
                    (candidate) => String(candidate.id) === String(officeId),
                  );
                  if (!office) return null;

                  return (
                    <div
                      className="flex items-start gap-3 rounded-lg border border-slate-200 bg-white px-3 py-2.5 shadow-sm"
                      key={office.id}
                    >
                      <span className="min-w-0 flex-1">
                        <strong className="block text-sm text-sky-800">
                          {office.code}
                        </strong>
                        <span className="block text-xs leading-5 text-slate-600">
                          {office.name}
                        </span>
                      </span>
                      <button
                        aria-label={`Remove ${office.name}`}
                        className="grid h-8 w-8 shrink-0 place-items-center rounded-lg text-slate-500 transition hover:bg-red-50 hover:text-red-600"
                        onClick={() =>
                          setForm((current) => ({
                            ...current,
                            officeIds: current.officeIds.filter(
                              (selectedId) =>
                                String(selectedId) !== String(office.id),
                            ),
                          }))
                        }
                        type="button"
                      >
                        <X size={16} />
                      </button>
                    </div>
                  );
                })}
              </div>
            )}
          </FormField>
          <label className="flex items-center gap-2 text-sm font-semibold">
            <input
              checked={form.isActive}
              onChange={(event) =>
                setForm({ ...form, isActive: event.target.checked })
              }
              type="checkbox"
            />
            Active audit area
          </label>
        </form>
      </Modal>

      <Modal
        onClose={() => setSelectedArea(null)}
        open={Boolean(selectedArea)}
        size="lg"
        title={selectedArea?.name ?? "Audit area details"}
      >
        {selectedArea && (
          <div className="grid gap-5">
            <div className="rounded-xl bg-slate-50 p-4">
              <div className="flex flex-wrap items-center gap-3">
                <StatusBadge>{selectedArea.code}</StatusBadge>
                <StatusBadge
                  tone={
                    selectedArea.isArchived
                      ? "inactive"
                      : selectedArea.isActive
                        ? "active"
                        : "warning"
                  }
                >
                  {selectedArea.isArchived
                    ? "Archived"
                    : selectedArea.isActive
                      ? "Active"
                      : "Inactive"}
                </StatusBadge>
              </div>
              <p className="mt-3 text-sm leading-6 text-slate-600">
                {selectedArea.description}
              </p>
            </div>
            <section>
              <h3 className="text-sm font-bold text-slate-800">
                Connected offices ({selectedArea.offices.length})
              </h3>
              <div className="mt-2 grid gap-2 sm:grid-cols-2">
                {selectedArea.offices.map((office) => (
                  <div
                    className="rounded-lg border border-slate-200 p-3"
                    key={office.id}
                  >
                    <strong className="text-sm text-sky-700">
                      {office.code}
                    </strong>
                    <span className="ml-2 text-sm text-slate-600">
                      {office.name}
                    </span>
                  </div>
                ))}
              </div>
            </section>
            <section>
              <h3 className="text-sm font-bold text-slate-800">
                Audit focuses ({selectedArea.focuses.length})
              </h3>
              <div className="mt-2 grid gap-2 sm:grid-cols-2">
                {selectedArea.focuses.map((focus) => (
                  <article
                    className="rounded-lg border border-slate-200 p-3"
                    key={focus.id}
                  >
                    <strong className="text-sm text-slate-700">
                      {focus.code} — {focus.name}
                    </strong>
                    <p className="mt-1 text-xs leading-5 text-slate-500">
                      {focus.description}
                    </p>
                  </article>
                ))}
              </div>
            </section>
          </div>
        )}
      </Modal>

      <ConfirmDialog
        busy={saving}
        confirmLabel={editing ? "Update audit area" : "Add audit area"}
        description={
          editing
            ? `Apply these changes to ${editing.name}?`
            : `Add ${form.name || "this audit area"} to the registry?`
        }
        onCancel={() => setSaveConfirmOpen(false)}
        onConfirm={persistArea}
        open={saveConfirmOpen}
        title={editing ? "Confirm audit area update" : "Confirm audit area"}
      />

      <ConfirmDialog
        busy={saving}
        confirmLabel="Archive audit area"
        description={`${deleteTarget?.name ?? "This audit area"} and its focuses will be archived but remain recoverable.`}
        onCancel={() => setDeleteTarget(null)}
        onConfirm={archiveArea}
        open={Boolean(deleteTarget)}
        title="Archive audit area?"
        tone="danger"
      />

      <ConfirmDialog
        busy={saving}
        confirmLabel="Restore audit area"
        description={`${restoreTarget?.name ?? "This audit area"} and its archived focuses will be restored.`}
        onCancel={() => setRestoreTarget(null)}
        onConfirm={restoreArea}
        open={Boolean(restoreTarget)}
        title="Restore audit area?"
      />
    </div>
  );
}
