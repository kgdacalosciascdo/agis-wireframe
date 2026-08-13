import { useMemo, useState } from "react";
import FormField from "../ui/FormField";
import SearchableSelect from "../ui/SearchableSelect";

const inputClass =
  "min-h-11 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm text-slate-800 outline-none transition focus:border-sky-500 focus:ring-2 focus:ring-sky-100";
const textareaClass = `${inputClass} min-h-28 py-3`;

function items(masterLists, code) {
  return (
    masterLists.find((list) => list.code === code)?.items?.filter(
      (item) => item.isActive && !item.isArchived,
    ) ?? []
  );
}

/**
 * Captures separately approved special/unplanned engagements. Workflow status
 * is server controlled; the form only records the external authority.
 */
export default function AemsSpecialEngagementForm({
  formId,
  offices,
  auditAreas,
  auditFocuses,
  users,
  masterLists,
  errors = {},
  onSubmit,
}) {
  const [form, setForm] = useState({
    engagementCode: "",
    title: "",
    specialAuthorityReference: "",
    specialAuthorityTypeCode: "",
    specialAuthorityClass: "SPECIAL",
    specialAuthorityDate: "",
    specialAuthorityApprovedBy: "",
    auditTypeId: "",
    engagementApproachId: "",
    background: "",
    objectives: "",
    scope: "",
    exclusions: "",
    plannedStartDate: "",
    plannedEndDate: "",
    expectedReportDate: "",
    plannedPersonDays: "",
    officeIds: [],
    auditAreaIds: [],
    auditFocusIds: [],
  });
  const selectedAreas = useMemo(
    () => new Set(form.auditAreaIds.map(String)),
    [form.auditAreaIds],
  );
  const focusOptions = auditFocuses
    .filter((focus) => selectedAreas.has(String(focus.auditAreaId)))
    .map((focus) => ({
      value: focus.id,
      label: `${focus.code} — ${focus.name}`,
      keywords: focus.auditArea?.name,
    }));
  const set = (key, value) =>
    setForm((current) => ({ ...current, [key]: value }));

  return (
    <form
      className="space-y-5"
      id={formId}
      onSubmit={(event) => {
        event.preventDefault();
        onSubmit({
          ...form,
          engagementCode: form.engagementCode || null,
          auditTypeId: form.auditTypeId || null,
          engagementApproachId: form.engagementApproachId || null,
          specialAuthorityTypeCode:
            form.specialAuthorityTypeCode || null,
          specialAuthorityClass: form.specialAuthorityClass,
          plannedPersonDays: Number(form.plannedPersonDays),
        });
      }}
    >
      <section className="rounded-xl border border-amber-200 bg-amber-50/60 p-4">
        <h3 className="text-sm font-bold text-amber-900">
          Separate authorization
        </h3>
        <p className="mt-1 text-xs leading-5 text-amber-800">
          Special engagements require an authority reference and an approving
          authority different from the registry creator.
        </p>
      </section>

      <div className="grid gap-4 md:grid-cols-2">
        <FormField
          error={errors.engagementCode?.[0]}
          htmlFor="aems-code"
          hint="Leave blank to generate the next AEMS number."
          label="Engagement code"
        >
          <input
            className={inputClass}
            id="aems-code"
            onChange={(event) => set("engagementCode", event.target.value)}
            placeholder="Auto-generated"
            value={form.engagementCode}
          />
        </FormField>
        <FormField
          error={errors.title?.[0]}
          htmlFor="aems-title"
          label="Engagement title"
          required
        >
          <input
            className={inputClass}
            id="aems-title"
            onChange={(event) => set("title", event.target.value)}
            value={form.title}
          />
        </FormField>
        <FormField
          error={errors.specialAuthorityReference?.[0]}
          htmlFor="aems-authority-reference"
          label="Authority reference"
          required
        >
          <input
            className={inputClass}
            id="aems-authority-reference"
            onChange={(event) =>
              set("specialAuthorityReference", event.target.value)
            }
            placeholder="e.g. OCM-MEMO-2026-014"
            value={form.specialAuthorityReference}
          />
        </FormField>
        <FormField
          error={errors.specialAuthorityTypeCode?.[0]}
          htmlFor="aems-authority-type"
          label="Authority type"
        >
          <input
            className={inputClass}
            id="aems-authority-type"
            onChange={(event) =>
              set("specialAuthorityTypeCode", event.target.value)
            }
            placeholder="e.g. MAYOR_DIRECTIVE"
            value={form.specialAuthorityTypeCode}
          />
        </FormField>
        <FormField
          error={errors.specialAuthorityClass?.[0]}
          htmlFor="aems-authority-class"
          label="Authorization class"
          required
        >
          <select
            className={inputClass}
            id="aems-authority-class"
            onChange={(event) => set("specialAuthorityClass", event.target.value)}
            value={form.specialAuthorityClass}
          >
            <option value="SPECIAL">Special engagement</option>
            <option value="EMERGENCY">Emergency engagement</option>
          </select>
        </FormField>
        <FormField
          error={errors.specialAuthorityDate?.[0]}
          htmlFor="aems-authority-date"
          label="Authority date"
          required
        >
          <input
            className={inputClass}
            id="aems-authority-date"
            onChange={(event) =>
              set("specialAuthorityDate", event.target.value)
            }
            type="date"
            value={form.specialAuthorityDate}
          />
        </FormField>
        <FormField
          error={errors.specialAuthorityApprovedBy?.[0]}
          label="Approving authority"
          required
        >
          <SearchableSelect
            onChange={(value) => set("specialAuthorityApprovedBy", value)}
            options={users.map((user) => ({
              value: user.id,
              label: user.name,
              description: `${user.employeeId} · ${user.office?.name ?? "No office"}`,
            }))}
            placeholder="Select approving authority"
            searchPlaceholder="Search employee ID or name..."
            value={form.specialAuthorityApprovedBy}
          />
        </FormField>
        <FormField error={errors.auditTypeId?.[0]} label="Audit type">
          <SearchableSelect
            onChange={(value) => set("auditTypeId", value)}
            options={items(masterLists, "IAP_ENGAGEMENT_TYPE").map((item) => ({
              value: item.id,
              label: item.label,
              keywords: item.code,
            }))}
            placeholder="Select audit type"
            value={form.auditTypeId}
          />
        </FormField>
        <FormField
          error={errors.engagementApproachId?.[0]}
          label="Audit approach"
        >
          <SearchableSelect
            onChange={(value) => set("engagementApproachId", value)}
            options={items(masterLists, "IAP_AUDIT_APPROACH").map((item) => ({
              value: item.id,
              label: item.label,
              keywords: item.code,
            }))}
            placeholder="Select audit approach"
            value={form.engagementApproachId}
          />
        </FormField>
      </div>

      <div className="grid gap-4 md:grid-cols-2">
        <FormField
          error={errors.officeIds?.[0]}
          hint="Exactly one office is required by the foundation rule."
          label="Engagement Office"
          required
        >
          <SearchableSelect
            onChange={(value) => set("officeIds", value ? [value] : [])}
            options={offices.map((office) => ({
              value: office.id,
              label: `${office.code} — ${office.name}`,
            }))}
            placeholder="Select one Engagement Office"
            value={form.officeIds[0] ?? ""}
          />
        </FormField>
        <FormField
          error={errors.auditAreaIds?.[0]}
          label="Audit areas"
          required
        >
          <SearchableSelect
            multiple
            onChange={(value) => {
              setForm((current) => ({
                ...current,
                auditAreaIds: value,
                auditFocusIds: current.auditFocusIds.filter((focusId) => {
                  const focus = auditFocuses.find(
                    (candidate) => String(candidate.id) === String(focusId),
                  );
                  return value.map(String).includes(
                    String(focus?.auditAreaId),
                  );
                }),
              }));
            }}
            options={auditAreas.map((area) => ({
              value: area.id,
              label: `${area.code} — ${area.name}`,
            }))}
            placeholder="Select one or more audit areas"
            value={form.auditAreaIds}
          />
        </FormField>
      </div>
      <FormField
        error={errors.auditFocusIds?.[0]}
        label="Audit focuses"
        hint="Focus options are limited to the selected audit areas."
      >
        <SearchableSelect
          disabled={form.auditAreaIds.length === 0}
          multiple
          onChange={(value) => set("auditFocusIds", value)}
          options={focusOptions}
          placeholder="Select relevant audit focuses"
          value={form.auditFocusIds}
        />
      </FormField>

      <div className="grid gap-4 md:grid-cols-3">
        <FormField
          error={errors.plannedStartDate?.[0]}
          htmlFor="aems-start"
          label="Planned start"
          required
        >
          <input
            className={inputClass}
            id="aems-start"
            onChange={(event) => set("plannedStartDate", event.target.value)}
            type="date"
            value={form.plannedStartDate}
          />
        </FormField>
        <FormField
          error={errors.plannedEndDate?.[0]}
          htmlFor="aems-end"
          label="Planned end"
          required
        >
          <input
            className={inputClass}
            id="aems-end"
            onChange={(event) => set("plannedEndDate", event.target.value)}
            type="date"
            value={form.plannedEndDate}
          />
        </FormField>
        <FormField
          error={errors.expectedReportDate?.[0]}
          htmlFor="aems-report-date"
          label="Expected report"
        >
          <input
            className={inputClass}
            id="aems-report-date"
            onChange={(event) =>
              set("expectedReportDate", event.target.value)
            }
            type="date"
            value={form.expectedReportDate}
          />
        </FormField>
      </div>
      <FormField
        error={errors.plannedPersonDays?.[0]}
        htmlFor="aems-person-days"
        label="Planned person-days"
        required
      >
        <input
          className={inputClass}
          id="aems-person-days"
          min="0.5"
          onChange={(event) => set("plannedPersonDays", event.target.value)}
          step="0.5"
          type="number"
          value={form.plannedPersonDays}
        />
      </FormField>

      {[
        ["background", "Background", false],
        ["objectives", "Objectives", true],
        ["scope", "Scope", true],
        ["exclusions", "Exclusions", false],
      ].map(([key, label, required]) => (
        <FormField
          error={errors[key]?.[0]}
          htmlFor={`aems-${key}`}
          key={key}
          label={label}
          required={required}
        >
          <textarea
            className={textareaClass}
            id={`aems-${key}`}
            onChange={(event) => set(key, event.target.value)}
            value={form[key]}
          />
        </FormField>
      ))}
    </form>
  );
}
