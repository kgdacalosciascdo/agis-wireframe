import { useCallback, useEffect, useMemo, useState } from "react";
import {
  ArrowDown,
  ArrowUp,
  BadgeCheck,
  Download,
  FileCheck2,
  FileClock,
  FilePlus2,
  FileText,
  LockKeyhole,
  Plus,
  RefreshCw,
  Send,
  Undo2,
} from "lucide-react";
import { useSearchParams } from "react-router";
import { useAuth } from "../auth/auth-context";
import Modal from "../components/ui/Modal";
import RegistryHeader from "../components/ui/RegistryHeader";
import SearchableSelect from "../components/ui/SearchableSelect";
import StatusBadge from "../components/ui/StatusBadge";
import SummaryCard from "../components/ui/SummaryCard";
import { hasPermission } from "../config/navigation";
import { aemsReportApi, ApiError } from "../services/api";
import { useToast } from "../ui/toast-context";

const inputClass =
  "h-11 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm text-slate-800 outline-none transition focus:border-sky-500 focus:ring-2 focus:ring-sky-100";
const textAreaClass = `${inputClass} min-h-28 resize-y py-2.5`;

function label(value) {
  return String(value ?? "")
    .replaceAll("_", " ")
    .replace(/\b\w/g, (character) => character.toUpperCase());
}

function dateTime(value) {
  if (!value) return "—";
  return new Intl.DateTimeFormat("en-PH", {
    dateStyle: "medium",
    timeStyle: "short",
  }).format(new Date(value));
}

function bytes(value) {
  const size = Number(value || 0);
  if (size < 1024) return `${size} B`;
  if (size < 1024 ** 2) return `${(size / 1024).toFixed(1)} KB`;
  return `${(size / 1024 ** 2).toFixed(1)} MB`;
}

function statusTone(status) {
  return {
    DRAFT: "inactive",
    PENDING_REVIEW: "warning",
    RETURNED_FOR_REVISION: "danger",
    RESUBMITTED: "warning",
    APPROVED: "success",
    ISSUED: "active",
  }[status] ?? "inactive";
}

function Field({ title, error, children, wide = false }) {
  return (
    <label
      className={`text-sm font-semibold text-slate-700 ${wide ? "sm:col-span-2" : ""}`}
    >
      {title}
      <span className="mt-1.5 block">{children}</span>
      {error && <small className="mt-1 block text-red-600">{error[0]}</small>}
    </label>
  );
}

function ActionButton({ children, onClick, tone = "sky", disabled = false }) {
  const tones = {
    sky: "border-sky-300 text-sky-700 hover:bg-sky-50",
    green: "border-emerald-300 text-emerald-700 hover:bg-emerald-50",
    red: "border-red-300 text-red-700 hover:bg-red-50",
    amber: "border-amber-300 text-amber-700 hover:bg-amber-50",
  };
  return (
    <button
      className={`inline-flex min-h-9 items-center justify-center gap-1.5 rounded-lg border bg-white px-3 text-xs font-bold transition disabled:cursor-not-allowed disabled:opacity-50 ${tones[tone]}`}
      disabled={disabled}
      onClick={onClick}
      type="button"
    >
      {children}
    </button>
  );
}

function emptyContent(defaultConfidentiality = "") {
  return {
    title: "",
    executiveSummary: "",
    sections: [
      { title: "Background and Scope", content: "" },
      { title: "Overall Conclusion", content: "" },
    ],
    findingIds: [],
    confidentialityLevelId: defaultConfidentiality,
    approvingAuthority: "",
    recipients: [],
    changeReason: "",
  };
}

/** Dedicated AEMS workspace for generated Draft and Final Audit Reports. */
export default function AemsReportsPage() {
  const { user } = useAuth();
  const toast = useToast();
  const [params, setParams] = useSearchParams();
  const [engagements, setEngagements] = useState([]);
  const [engagementId, setEngagementId] = useState(
    params.get("engagementId") ?? "",
  );
  const [workspace, setWorkspace] = useState(null);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState("");
  const [errors, setErrors] = useState({});
  const [generationOpen, setGenerationOpen] = useState(false);
  const [generationMode, setGenerationMode] = useState("create");
  const [content, setContent] = useState(emptyContent());
  const [actionOpen, setActionOpen] = useState(false);
  const [action, setAction] = useState({
    type: "RETURN",
    comment: "",
    issuanceDate: "",
  });

  const canCreate = hasPermission(user, "aems.report.create");
  const canReview = hasPermission(user, "aems.report.review");
  const canApprove = hasPermission(user, "aems.report.approve");
  const canIssue = hasPermission(user, "aems.report.issue");

  const loadEngagements = useCallback(async () => {
    setLoading(true);
    try {
      const records = await aemsReportApi.engagements();
      setEngagements(records);
      setEngagementId((current) => current || String(records[0]?.id ?? ""));
    } catch (requestError) {
      setError(requestError.message);
    } finally {
      setLoading(false);
    }
  }, []);

  const loadWorkspace = useCallback(async () => {
    if (!engagementId) {
      setWorkspace(null);
      return;
    }
    setLoading(true);
    try {
      setWorkspace(await aemsReportApi.show(engagementId));
      setError("");
    } catch (requestError) {
      setWorkspace(null);
      setError(requestError.message);
    } finally {
      setLoading(false);
    }
  }, [engagementId]);

  useEffect(() => {
    const timer = window.setTimeout(loadEngagements, 0);
    return () => window.clearTimeout(timer);
  }, [loadEngagements]);

  useEffect(() => {
    const timer = window.setTimeout(loadWorkspace, 0);
    return () => window.clearTimeout(timer);
  }, [loadWorkspace]);

  useEffect(() => {
    if (engagementId) setParams({ engagementId }, { replace: true });
  }, [engagementId, setParams]);

  const report = workspace?.report ?? null;
  const versions = useMemo(() => report?.versions ?? [], [report?.versions]);
  const currentVersion =
    versions.find((version) => version.id === report?.currentVersionId) ??
    versions.at(-1) ??
    null;

  function showError(requestError) {
    if (requestError instanceof ApiError) {
      setErrors(requestError.errors ?? {});
    }
    toast.error(requestError.message);
  }

  async function refresh(message) {
    await loadWorkspace();
    if (message) toast.success(message);
  }

  function openGeneration(mode) {
    setErrors({});
    setGenerationMode(mode);
    const snapshot = currentVersion?.contentSnapshot;
    const defaultConfidentiality =
      report?.confidentialityLevel?.id ??
      workspace?.references?.confidentialityLevels?.find(
        (item) => item.code === "INTERNAL",
      )?.id ??
      "";
    setContent(
      snapshot
        ? {
            title: snapshot.title ?? report.title,
            executiveSummary: snapshot.executiveSummary ?? "",
            sections: snapshot.sections?.map((section) => ({
              title: section.title,
              content: section.content,
            })) ?? [{ title: "", content: "" }],
            findingIds:
              mode === "final"
                ? snapshot.findings
                    ?.filter((finding) => finding.status === "FINALIZED")
                    .map((finding) => finding.id) ?? []
                : snapshot.findings?.map((finding) => finding.id) ?? [],
            confidentialityLevelId: defaultConfidentiality,
            approvingAuthority: report.approvingAuthority ?? "",
            recipients:
              mode === "final" || report.reportStage === "FINAL_REPORT"
                ? currentVersion.recipients.map((recipient) => ({
                    recipientType: recipient.recipientType,
                    userId: recipient.user?.id ?? "",
                    officeId: recipient.office?.id ?? "",
                    externalName: recipient.externalName ?? "",
                    externalEmail: recipient.externalEmail ?? "",
                    deliveryMethod: recipient.deliveryMethod ?? "SYSTEM",
                  }))
                : [],
            changeReason: "",
          }
        : emptyContent(defaultConfidentiality),
    );
    setGenerationOpen(true);
  }

  async function generate() {
    setSaving(true);
    setErrors({});
    try {
      const payload = {
        ...content,
        confidentialityLevelId: Number(content.confidentialityLevelId),
        findingIds: content.findingIds.map(Number),
        ...(generationMode !== "create"
          ? { lockVersion: report.lockVersion }
          : {}),
      };
      if (generationMode === "create") {
        await aemsReportApi.create(engagementId, payload);
      } else if (generationMode === "final") {
        await aemsReportApi.createFinal(engagementId, report.id, payload);
      } else {
        await aemsReportApi.revise(engagementId, report.id, payload);
      }
      setGenerationOpen(false);
      await refresh(
        generationMode === "final"
          ? "Final Report draft generated."
          : "Immutable report version generated.",
      );
    } catch (requestError) {
      showError(requestError);
    } finally {
      setSaving(false);
    }
  }

  async function transition(type, overrides = {}) {
    setSaving(true);
    setErrors({});
    try {
      await aemsReportApi.transition(engagementId, report.id, {
        action: type,
        lockVersion: report.lockVersion,
        comment: action.comment || null,
        issuanceDate: action.issuanceDate || null,
        ...overrides,
      });
      setActionOpen(false);
      await refresh(`Report ${label(type).toLowerCase()} completed.`);
    } catch (requestError) {
      showError(requestError);
    } finally {
      setSaving(false);
    }
  }

  function openAction(type) {
    setErrors({});
    setAction({
      type,
      comment: "",
      issuanceDate:
        type === "ISSUE" ? new Date().toISOString().slice(0, 10) : "",
    });
    setActionOpen(true);
  }

  async function syncCms() {
    setSaving(true);
    try {
      await aemsReportApi.transferRecommendations(engagementId, report.id, {
        lockVersion: report.lockVersion,
      });
      await refresh("CMS transfer synchronized without duplicates.");
    } catch (requestError) {
      showError(requestError);
    } finally {
      setSaving(false);
    }
  }

  return (
    <main className="min-w-0 p-4 sm:p-5">
      <RegistryHeader
        description="Generate immutable Draft and Final Report PDFs, preserve review history, issue to controlled recipients, and transfer recommendations once."
        icon={FileCheck2}
        title="Audit Reports"
        actions={
          canCreate && workspace && !report ? (
            <button
              className="inline-flex min-h-10 items-center gap-2 rounded-lg bg-sky-700 px-4 text-sm font-bold text-white hover:bg-sky-800"
              onClick={() => openGeneration("create")}
              type="button"
            >
              <FilePlus2 size={17} /> Generate Draft Report
            </button>
          ) : null
        }
      />

      <section className="mb-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <SummaryCard
          icon={FileText}
          label="Report family"
          tone="sky"
          value={report ? 1 : 0}
        />
        <SummaryCard
          icon={FileClock}
          label="Immutable versions"
          tone="amber"
          value={versions.length}
        />
        <SummaryCard
          icon={LockKeyhole}
          label="Locked versions"
          tone="emerald"
          value={versions.filter((version) => version.isLocked).length}
        />
        <SummaryCard
          icon={Send}
          label="CMS transfers"
          tone="slate"
          value={report?.cmsTransfers?.length ?? 0}
        />
      </section>

      <section className="mb-5 grid gap-3 rounded-xl border border-slate-200 bg-white p-4 shadow-sm lg:grid-cols-[minmax(20rem,1fr)_auto]">
        <SearchableSelect
          onChange={(value) => setEngagementId(String(value ?? ""))}
          options={engagements.map((engagement) => ({
            value: engagement.id,
            label: `${engagement.engagementCode} — ${engagement.title}`,
          }))}
          placeholder="Select engagement"
          value={engagementId}
        />
        <ActionButton onClick={loadWorkspace}>
          <RefreshCw size={15} /> Refresh
        </ActionButton>
      </section>

      {error && (
        <div className="mb-5 rounded-xl border border-red-200 bg-red-50 p-4 text-sm font-semibold text-red-700">
          {error}
        </div>
      )}

      {!engagementId ? (
        <div className="rounded-xl border border-dashed border-slate-300 bg-white p-10 text-center text-sm text-slate-500">
          No report-enabled engagement is available.
        </div>
      ) : loading && !workspace ? (
        <div className="rounded-xl border border-slate-200 bg-white p-10 text-center text-sm text-slate-500">
          Loading report workspace…
        </div>
      ) : !report ? (
        <div className="rounded-xl border border-dashed border-slate-300 bg-white p-10 text-center">
          <FileText className="mx-auto text-slate-300" size={38} />
          <h2 className="mt-3 font-bold text-slate-700">No report generated</h2>
          <p className="mt-1 text-sm text-slate-500">
            Start a Draft Report from validated or later Findings.
          </p>
        </div>
      ) : (
        <div className="space-y-5">
          <section className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <div className="flex flex-wrap items-start justify-between gap-4">
              <div>
                <div className="flex flex-wrap items-center gap-2">
                  <h2 className="text-xl font-bold text-slate-800">
                    {report.reportCode}
                  </h2>
                  <StatusBadge
                    label={label(report.reportStage)}
                    tone={report.reportStage === "FINAL_REPORT" ? "active" : "info"}
                  />
                  <StatusBadge
                    label={label(report.status)}
                    tone={statusTone(report.status)}
                  />
                </div>
                <p className="mt-2 text-sm font-semibold text-slate-700">
                  {report.title}
                </p>
                <p className="mt-1 text-xs text-slate-500">
                  Prepared by {report.preparedBy?.name} ·{" "}
                  {report.confidentialityLevel?.label} · Version{" "}
                  {report.currentVersionNumber}
                </p>
              </div>
              <div className="flex flex-wrap gap-2">
                {canCreate &&
                  ["DRAFT", "RETURNED_FOR_REVISION"].includes(report.status) && (
                    <ActionButton onClick={() => openGeneration("revise")}>
                      <FilePlus2 size={15} /> Generate version
                    </ActionButton>
                  )}
                {canCreate &&
                  ["DRAFT", "RESUBMITTED"].includes(report.status) && (
                    <ActionButton
                      onClick={() => transition("SUBMIT", { comment: null })}
                      tone="green"
                    >
                      <Send size={15} /> Submit
                    </ActionButton>
                  )}
                {canReview && report.status === "PENDING_REVIEW" && (
                  <ActionButton onClick={() => openAction("RETURN")} tone="red">
                    <Undo2 size={15} /> Return
                  </ActionButton>
                )}
                {canApprove &&
                  ["PENDING_REVIEW", "RESUBMITTED"].includes(report.status) && (
                    <ActionButton onClick={() => openAction("APPROVE")} tone="green">
                      <BadgeCheck size={15} /> Approve
                    </ActionButton>
                  )}
                {canCreate &&
                  report.reportStage === "DRAFT_REPORT" &&
                  report.status === "APPROVED" && (
                    <ActionButton onClick={() => openGeneration("final")} tone="green">
                      <FileCheck2 size={15} /> Generate Final Report
                    </ActionButton>
                  )}
                {canIssue &&
                  report.reportStage === "FINAL_REPORT" &&
                  report.status === "APPROVED" && (
                    <ActionButton onClick={() => openAction("ISSUE")} tone="green">
                      <Send size={15} /> Issue
                    </ActionButton>
                  )}
                {canIssue && report.status === "ISSUED" && (
                  <ActionButton disabled={saving} onClick={syncCms}>
                    <RefreshCw size={15} /> Sync CMS transfer
                  </ActionButton>
                )}
              </div>
            </div>

            {report.reportStage === "FINAL_REPORT" && (
              <div className="mt-4 grid gap-3 sm:grid-cols-3">
                <Meta title="Approving authority">
                  {report.approvingAuthority || "Not recorded"}
                </Meta>
                <Meta title="Approved">
                  {report.approvedAt
                    ? `${dateTime(report.approvedAt)} by ${report.approvedBy?.name}`
                    : "Pending"}
                </Meta>
                <Meta title="Issued">
                  {report.issuedAt
                    ? `${dateTime(report.issuedAt)} by ${report.issuedBy?.name}`
                    : "Pending"}
                </Meta>
              </div>
            )}
          </section>

          <section className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <h3 className="font-bold text-slate-800">Version history</h3>
            <div className="mt-4 space-y-4">
              {[...versions].reverse().map((version) => (
                <VersionCard
                  canDownload={
                    canCreate ||
                    canReview ||
                    (report.canDownloadCurrent &&
                      version.id === report.currentVersionId)
                  }
                  key={version.id}
                  onDownload={() =>
                    aemsReportApi
                      .download(engagementId, report.id, version)
                      .catch(showError)
                  }
                  version={version}
                />
              ))}
            </div>
          </section>
        </div>
      )}

      <GenerationModal
        errors={errors}
        form={content}
        mode={generationMode}
        onChange={setContent}
        onClose={() => setGenerationOpen(false)}
        onSave={generate}
        open={generationOpen}
        references={workspace?.references}
        reportStage={report?.reportStage}
        saving={saving}
      />

      <Modal
        footer={
          <>
            <ActionButton onClick={() => setActionOpen(false)}>Cancel</ActionButton>
            <ActionButton
              disabled={saving}
              onClick={() => transition(action.type)}
              tone={action.type === "RETURN" ? "red" : "green"}
            >
              Confirm {label(action.type)}
            </ActionButton>
          </>
        }
        onClose={() => setActionOpen(false)}
        open={actionOpen}
        title={`${label(action.type)} Report`}
      >
        <div className="space-y-4">
          {action.type === "ISSUE" && (
            <Field title="Issuance date">
              <input
                className={inputClass}
                onChange={(event) =>
                  setAction((current) => ({
                    ...current,
                    issuanceDate: event.target.value,
                  }))
                }
                type="date"
                value={action.issuanceDate}
              />
            </Field>
          )}
          {action.type !== "ISSUE" && (
            <Field error={errors.comment} title="Reviewer comment">
              <textarea
                className={textAreaClass}
                onChange={(event) =>
                  setAction((current) => ({
                    ...current,
                    comment: event.target.value,
                  }))
                }
                value={action.comment}
              />
            </Field>
          )}
        </div>
      </Modal>
    </main>
  );
}

function Meta({ title, children }) {
  return (
    <div className="rounded-lg bg-slate-50 p-3 text-sm text-slate-600">
      <p className="text-xs font-bold uppercase tracking-wide text-slate-400">
        {title}
      </p>
      <p className="mt-1">{children}</p>
    </div>
  );
}

function VersionCard({ version, onDownload, canDownload }) {
  const snapshot = version.contentSnapshot;
  return (
    <article className="rounded-xl border border-slate-200 p-4">
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div>
          <div className="flex items-center gap-2">
            <p className="font-bold text-slate-800">
              Version {version.versionNumber}
            </p>
            <StatusBadge
              label={label(version.reportStage)}
              tone={version.reportStage === "FINAL_REPORT" ? "active" : "info"}
            />
            {version.isLocked && (
              <StatusBadge label="Issued & Locked" tone="success" />
            )}
          </div>
          <p className="mt-1 text-xs text-slate-500">
            {dateTime(version.createdAt)} · {version.createdBy?.name} ·{" "}
            {bytes(version.fileSize)}
          </p>
        </div>
        {canDownload && (
          <ActionButton onClick={onDownload}>
            <Download size={15} /> PDF
          </ActionButton>
        )}
      </div>
      <p className="mt-3 text-sm leading-6 text-slate-600">
        {snapshot.executiveSummary}
      </p>
      <div className="mt-3 flex flex-wrap gap-2 text-xs font-semibold text-slate-500">
        <span className="rounded-full bg-slate-100 px-2.5 py-1">
          {snapshot.sections?.length ?? 0} sections
        </span>
        <span className="rounded-full bg-slate-100 px-2.5 py-1">
          {version.findings.length} findings
        </span>
        <span className="rounded-full bg-slate-100 px-2.5 py-1">
          {version.recipients.length} recipients
        </span>
      </div>
      <p className="mt-3 break-all font-mono text-[10px] text-slate-400">
        SHA-256 {version.checksumSha256}
      </p>
      {version.changeReason && (
        <p className="mt-2 text-xs text-slate-500">
          Change reason: {version.changeReason}
        </p>
      )}
      {version.reviewComments.length > 0 && (
        <div className="mt-4 space-y-2 border-t border-slate-100 pt-3">
          {version.reviewComments.map((review) => (
            <div className="rounded-lg bg-amber-50 p-3" key={review.id}>
              <p className="text-xs font-bold text-amber-800">
                {label(review.action)} · {review.reviewedBy?.name} ·{" "}
                {dateTime(review.reviewedAt)}
              </p>
              <p className="mt-1 text-sm leading-6 text-amber-900">
                {review.comment}
              </p>
            </div>
          ))}
        </div>
      )}
      {version.recipients.length > 0 && (
        <div className="mt-4 border-t border-slate-100 pt-3">
          <p className="text-xs font-bold uppercase tracking-wide text-slate-400">
            Controlled recipients
          </p>
          <div className="mt-2 flex flex-wrap gap-2">
            {version.recipients.map((recipient) => (
              <span
                className="rounded-full border border-slate-200 px-2.5 py-1 text-xs text-slate-600"
                key={recipient.id}
              >
                {recipient.user?.name ||
                  recipient.office?.name ||
                  recipient.externalName}{" "}
                · {label(recipient.deliveryStatus)}
              </span>
            ))}
          </div>
        </div>
      )}
    </article>
  );
}

function GenerationModal({
  open,
  mode,
  reportStage,
  form,
  references,
  errors,
  saving,
  onChange,
  onClose,
  onSave,
}) {
  const final = mode === "final" || reportStage === "FINAL_REPORT";
  const findings = (references?.findings ?? []).filter(
    (finding) => !final || finding.status === "FINALIZED",
  );
  const confidentiality = references?.confidentialityLevels ?? [];
  const users = references?.users ?? [];
  const offices = references?.offices ?? [];

  function moveSection(index, direction) {
    const destination = index + direction;
    if (destination < 0 || destination >= form.sections.length) return;
    onChange((current) => {
      const sections = [...current.sections];
      [sections[index], sections[destination]] = [
        sections[destination],
        sections[index],
      ];
      return { ...current, sections };
    });
  }

  function updateRecipient(index, changes) {
    onChange((current) => ({
      ...current,
      recipients: current.recipients.map((recipient, position) =>
        position === index ? { ...recipient, ...changes } : recipient,
      ),
    }));
  }

  return (
    <Modal
      description={
        final
          ? "Final versions accept only finalized Findings and require authority and controlled recipients."
          : "Draft versions can include current validated or later Findings."
      }
      footer={
        <>
          <ActionButton onClick={onClose}>Cancel</ActionButton>
          <ActionButton disabled={saving} onClick={onSave} tone="green">
            Generate immutable PDF
          </ActionButton>
        </>
      }
      onClose={onClose}
      open={open}
      size="xl"
      title={
        mode === "create"
          ? "Generate Draft Report"
          : mode === "final"
            ? "Generate Final Report Draft"
            : "Generate Report Revision"
      }
    >
      <div className="grid gap-4 sm:grid-cols-2">
        <Field error={errors.title} title="Report title" wide>
          <input
            className={inputClass}
            onChange={(event) =>
              onChange((current) => ({ ...current, title: event.target.value }))
            }
            value={form.title}
          />
        </Field>
        <Field error={errors.confidentialityLevelId} title="Confidentiality">
          <select
            className={inputClass}
            onChange={(event) =>
              onChange((current) => ({
                ...current,
                confidentialityLevelId: event.target.value,
              }))
            }
            value={form.confidentialityLevelId}
          >
            <option value="">Select confidentiality</option>
            {confidentiality.map((item) => (
              <option key={item.id} value={item.id}>
                {item.label}
              </option>
            ))}
          </select>
        </Field>
        {final && (
          <Field error={errors.approvingAuthority} title="Approving authority">
            <input
              className={inputClass}
              onChange={(event) =>
                onChange((current) => ({
                  ...current,
                  approvingAuthority: event.target.value,
                }))
              }
              value={form.approvingAuthority}
            />
          </Field>
        )}
        <Field error={errors.executiveSummary} title="Executive summary" wide>
          <textarea
            className={`${textAreaClass} min-h-36`}
            onChange={(event) =>
              onChange((current) => ({
                ...current,
                executiveSummary: event.target.value,
              }))
            }
            value={form.executiveSummary}
          />
        </Field>
      </div>

      <section className="mt-6">
        <div className="flex items-center justify-between gap-3">
          <h3 className="font-bold text-slate-800">Arranged report sections</h3>
          <ActionButton
            onClick={() =>
              onChange((current) => ({
                ...current,
                sections: [...current.sections, { title: "", content: "" }],
              }))
            }
          >
            <Plus size={14} /> Add section
          </ActionButton>
        </div>
        <div className="mt-3 space-y-3">
          {form.sections.map((section, index) => (
            <div className="rounded-lg border border-slate-200 p-3" key={index}>
              <div className="flex gap-2">
                <input
                  className={inputClass}
                  onChange={(event) =>
                    onChange((current) => ({
                      ...current,
                      sections: current.sections.map((record, position) =>
                        position === index
                          ? { ...record, title: event.target.value }
                          : record,
                      ),
                    }))
                  }
                  placeholder={`Section ${index + 1} title`}
                  value={section.title}
                />
                <ActionButton disabled={index === 0} onClick={() => moveSection(index, -1)}>
                  <ArrowUp size={14} />
                </ActionButton>
                <ActionButton
                  disabled={index === form.sections.length - 1}
                  onClick={() => moveSection(index, 1)}
                >
                  <ArrowDown size={14} />
                </ActionButton>
              </div>
              <textarea
                className={`${textAreaClass} mt-2`}
                onChange={(event) =>
                  onChange((current) => ({
                    ...current,
                    sections: current.sections.map((record, position) =>
                      position === index
                        ? { ...record, content: event.target.value }
                        : record,
                    ),
                  }))
                }
                placeholder="Section content"
                value={section.content}
              />
            </div>
          ))}
        </div>
      </section>

      <section className="mt-6">
        <h3 className="font-bold text-slate-800">
          {final ? "Finalized Findings" : "Validated and later Findings"}
        </h3>
        <div className="mt-3 grid gap-2 md:grid-cols-2">
          {findings.map((finding) => (
            <label
              className="flex cursor-pointer items-start gap-3 rounded-lg border border-slate-200 p-3 hover:bg-slate-50"
              key={finding.id}
            >
              <input
                checked={form.findingIds.includes(finding.id)}
                className="mt-1"
                onChange={(event) =>
                  onChange((current) => ({
                    ...current,
                    findingIds: event.target.checked
                      ? [...current.findingIds, finding.id]
                      : current.findingIds.filter((id) => id !== finding.id),
                  }))
                }
                type="checkbox"
              />
              <span>
                <span className="block text-xs font-bold text-sky-700">
                  {finding.findingCode} · {label(finding.status)}
                </span>
                <span className="mt-1 block text-sm font-semibold text-slate-700">
                  {finding.title}
                </span>
              </span>
            </label>
          ))}
        </div>
        {errors.findingIds && (
          <p className="mt-2 text-xs text-red-600">{errors.findingIds[0]}</p>
        )}
      </section>

      {final && (
        <section className="mt-6">
          <div className="flex items-center justify-between gap-3">
            <h3 className="font-bold text-slate-800">Controlled recipients</h3>
            <ActionButton
              onClick={() =>
                onChange((current) => ({
                  ...current,
                  recipients: [
                    ...current.recipients,
                    {
                      recipientType: "OFFICE",
                      userId: "",
                      officeId: "",
                      externalName: "",
                      externalEmail: "",
                      deliveryMethod: "SYSTEM",
                    },
                  ],
                }))
              }
            >
              <Plus size={14} /> Add recipient
            </ActionButton>
          </div>
          <div className="mt-3 space-y-3">
            {form.recipients.map((recipient, index) => (
              <div
                className="grid gap-3 rounded-lg border border-slate-200 p-3 md:grid-cols-4"
                key={`${index}-${recipient.recipientType}`}
              >
                <select
                  className={inputClass}
                  onChange={(event) =>
                    updateRecipient(index, {
                      recipientType: event.target.value,
                      userId: "",
                      officeId: "",
                      externalName: "",
                    })
                  }
                  value={recipient.recipientType}
                >
                  <option value="OFFICE">Office</option>
                  <option value="USER">Internal user</option>
                  <option value="EXTERNAL">External</option>
                </select>
                {recipient.recipientType === "OFFICE" && (
                  <select
                    className={inputClass}
                    onChange={(event) =>
                      updateRecipient(index, { officeId: event.target.value })
                    }
                    value={recipient.officeId}
                  >
                    <option value="">Select office</option>
                    {offices.map((office) => (
                      <option key={office.id} value={office.id}>
                        {office.name}
                      </option>
                    ))}
                  </select>
                )}
                {recipient.recipientType === "USER" && (
                  <select
                    className={inputClass}
                    onChange={(event) =>
                      updateRecipient(index, { userId: event.target.value })
                    }
                    value={recipient.userId}
                  >
                    <option value="">Select user</option>
                    {users.map((member) => (
                      <option key={member.id} value={member.id}>
                        {member.name}
                      </option>
                    ))}
                  </select>
                )}
                {recipient.recipientType === "EXTERNAL" && (
                  <input
                    className={inputClass}
                    onChange={(event) =>
                      updateRecipient(index, {
                        externalName: event.target.value,
                      })
                    }
                    placeholder="Recipient name"
                    value={recipient.externalName}
                  />
                )}
                <input
                  className={inputClass}
                  disabled={recipient.recipientType !== "EXTERNAL"}
                  onChange={(event) =>
                    updateRecipient(index, {
                      externalEmail: event.target.value,
                    })
                  }
                  placeholder="External email"
                  type="email"
                  value={recipient.externalEmail}
                />
                <div className="flex gap-2">
                  <select
                    className={inputClass}
                    onChange={(event) =>
                      updateRecipient(index, {
                        deliveryMethod: event.target.value,
                      })
                    }
                    value={recipient.deliveryMethod}
                  >
                    <option value="SYSTEM">System</option>
                    <option value="EMAIL">Email</option>
                    <option value="HAND_DELIVERY">Hand delivery</option>
                    <option value="REGISTERED_MAIL">Registered mail</option>
                  </select>
                  <button
                    aria-label="Remove recipient"
                    className="rounded-lg border border-red-200 px-3 font-bold text-red-600 hover:bg-red-50"
                    onClick={() =>
                      onChange((current) => ({
                        ...current,
                        recipients: current.recipients.filter(
                          (_, position) => position !== index,
                        ),
                      }))
                    }
                    type="button"
                  >
                    ×
                  </button>
                </div>
              </div>
            ))}
          </div>
          {errors.recipients && (
            <p className="mt-2 text-xs text-red-600">{errors.recipients[0]}</p>
          )}
        </section>
      )}

      {mode !== "create" && (
        <div className="mt-6">
          <Field error={errors.changeReason} title="Version change reason">
            <textarea
              className={textAreaClass}
              onChange={(event) =>
                onChange((current) => ({
                  ...current,
                  changeReason: event.target.value,
                }))
              }
              value={form.changeReason}
            />
          </Field>
        </div>
      )}
    </Modal>
  );
}
