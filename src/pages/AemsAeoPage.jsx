import { useCallback, useEffect, useMemo, useState } from "react";
import {
  BadgeCheck,
  Download,
  FileCheck2,
  FileClock,
  FilePenLine,
  History,
  Plus,
  RotateCcw,
  Send,
  ShieldCheck,
  Undo2,
  UsersRound,
} from "lucide-react";
import { useSearchParams } from "react-router";
import { useAuth } from "../auth/auth-context";
import Modal from "../components/ui/Modal";
import RegistryHeader from "../components/ui/RegistryHeader";
import SearchableSelect from "../components/ui/SearchableSelect";
import StatusBadge from "../components/ui/StatusBadge";
import SummaryCard from "../components/ui/SummaryCard";
import { hasPermission } from "../config/navigation";
import { aemsAeoApi, aemsEngagementApi, ApiError } from "../services/api";
import { useToast } from "../ui/toast-context";

const statusLabels = {
  DRAFT: "Draft",
  PENDING_REVIEW: "Pending Review",
  RETURNED_FOR_REVISION: "Returned",
  RESUBMITTED: "Resubmitted",
  APPROVED: "Approved",
  ISSUED: "Issued",
  SUPERSEDED: "Superseded",
};

const statusTones = {
  DRAFT: "inactive",
  PENDING_REVIEW: "warning",
  RETURNED_FOR_REVISION: "danger",
  RESUBMITTED: "warning",
  APPROVED: "success",
  ISSUED: "active",
  SUPERSEDED: "inactive",
};

const emptyForm = {
  authority: "",
  objectives: "",
  scope: "",
  effectivityDate: "",
  plannedStartDate: "",
  plannedEndDate: "",
  changeReason: "",
};

function date(value, withTime = false) {
  if (!value) return "—";
  return new Intl.DateTimeFormat("en-PH", {
    month: "short",
    day: "numeric",
    year: "numeric",
    ...(withTime ? { hour: "numeric", minute: "2-digit" } : {}),
  }).format(new Date(withTime ? value : `${value}T00:00:00`));
}

function roleLabel(value) {
  return String(value ?? "")
    .replaceAll("_", " ")
    .replace(/\b\w/g, (letter) => letter.toUpperCase());
}

function Detail({ label, children }) {
  return (
    <div className="rounded-xl border border-slate-200 bg-slate-50 px-3 py-3">
      <span className="block text-[10px] font-bold uppercase tracking-wide text-slate-400">
        {label}
      </span>
      <div className="mt-1 text-sm font-semibold leading-6 text-slate-700">
        {children || "—"}
      </div>
    </div>
  );
}

/**
 * Provides one versioned AEO workspace per engagement. The UI exposes only
 * actions granted to the current role; the API remains authoritative for
 * status, team readiness, separation of duties, and concurrent updates.
 */
export default function AemsAeoPage() {
  const { user } = useAuth();
  const toast = useToast();
  const [searchParams, setSearchParams] = useSearchParams();
  const [engagements, setEngagements] = useState([]);
  const [selectedId, setSelectedId] = useState(
    searchParams.get("engagementId") ?? "",
  );
  const [workspace, setWorkspace] = useState(null);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState("");
  const [formOpen, setFormOpen] = useState(false);
  const [form, setForm] = useState(emptyForm);
  const [errors, setErrors] = useState({});
  const [actionOpen, setActionOpen] = useState(false);
  const [workflowAction, setWorkflowAction] = useState("");
  const [comment, setComment] = useState("");

  const canPrepare = hasPermission(user, "aems.aeo.prepare");
  const canReview = hasPermission(user, "aems.aeo.review");
  const canApprove = hasPermission(user, "aems.aeo.approve");
  const canIssue = hasPermission(user, "aems.aeo.issue");
  const canRevise = hasPermission(user, "aems.aeo.revise");

  const loadEngagements = useCallback(async () => {
    setLoading(true);
    setError("");
    try {
      const data = await aemsEngagementApi.list({
        perPage: 100,
        sortBy: "updated_at",
        sortDirection: "desc",
      });
      setEngagements(data.engagements);
      setSelectedId(
        (current) => current || String(data.engagements[0]?.id ?? ""),
      );
    } catch (requestError) {
      setError(requestError.message);
    } finally {
      setLoading(false);
    }
  }, []);

  const loadWorkspace = useCallback(async () => {
    if (!selectedId) {
      setWorkspace(null);
      return;
    }
    setLoading(true);
    setError("");
    try {
      setWorkspace(await aemsAeoApi.show(selectedId));
    } catch (requestError) {
      setError(requestError.message);
      setWorkspace(null);
    } finally {
      setLoading(false);
    }
  }, [selectedId]);

  useEffect(() => {
    const timer = window.setTimeout(loadEngagements, 0);
    return () => window.clearTimeout(timer);
  }, [loadEngagements]);

  useEffect(() => {
    const timer = window.setTimeout(loadWorkspace, 0);
    return () => window.clearTimeout(timer);
  }, [loadWorkspace]);

  useEffect(() => {
    if (selectedId)
      setSearchParams({ engagementId: selectedId }, { replace: true });
  }, [selectedId, setSearchParams]);

  const engagementOptions = engagements.map((engagement) => ({
    value: engagement.id,
    label: `${engagement.engagementCode} — ${engagement.title}`,
    keywords: engagement.offices?.map((office) => office.name).join(" "),
  }));
  const order = workspace?.order;
  const version = order?.latestVersion;

  const actions = useMemo(() => {
    if (!order) return [];
    const items = [];
    if (order.status === "DRAFT" && canPrepare) {
      items.push(["SUBMIT", "Submit for review", Send, "primary"]);
    }
    if (["PENDING_REVIEW", "RESUBMITTED"].includes(order.status) && canReview) {
      items.push(["REVIEW", "Record review", FileCheck2, "primary"]);
      items.push(["RETURN", "Return for revision", Undo2, "warning"]);
    }
    if (
      ["PENDING_REVIEW", "RESUBMITTED"].includes(order.status) &&
      canApprove
    ) {
      items.push(["APPROVE", "Approve AEO", BadgeCheck, "success"]);
    }
    if (order.status === "RETURNED_FOR_REVISION" && canPrepare) {
      items.push(["RESUBMIT", "Resubmit", Send, "primary"]);
    }
    if (order.status === "APPROVED" && canIssue) {
      items.push(["ISSUE", "Issue AEO", ShieldCheck, "success"]);
    }
    if (["APPROVED", "ISSUED"].includes(order.status) && canRevise) {
      items.push(["REVISE", "Create revision", RotateCcw, "warning"]);
    }
    return items;
  }, [canApprove, canIssue, canPrepare, canReview, canRevise, order]);

  function openForm() {
    setErrors({});
    setForm(
      version
        ? {
            authority: version.authority,
            objectives: version.objectives,
            scope: version.scope,
            effectivityDate: version.effectivityDate ?? "",
            plannedStartDate: version.plannedStartDate ?? "",
            plannedEndDate: version.plannedEndDate ?? "",
            changeReason: "",
          }
        : {
            ...emptyForm,
            authority:
              "Authority is granted under the approved Internal Annual Audit Plan and the mandate of the City Internal Audit Office.",
            objectives: workspace?.engagement.objectives ?? "",
            scope: workspace?.engagement.scope ?? "",
            plannedStartDate: workspace?.engagement.plannedStartDate ?? "",
            plannedEndDate: workspace?.engagement.plannedEndDate ?? "",
          },
    );
    setFormOpen(true);
  }

  async function saveContent() {
    setSaving(true);
    setErrors({});
    try {
      if (order) {
        await aemsAeoApi.update(selectedId, order.id, {
          ...form,
          lockVersion: order.lockVersion,
          changeReason:
            form.changeReason ||
            "Updated following engagement authorization review.",
        });
        toast.success("A new immutable AEO version was created.");
      } else {
        await aemsAeoApi.create(selectedId, form);
        toast.success("Draft AEO created.");
      }
      setFormOpen(false);
      await loadWorkspace();
    } catch (requestError) {
      if (requestError instanceof ApiError) setErrors(requestError.errors);
      toast.error(requestError.message);
    } finally {
      setSaving(false);
    }
  }

  function openAction(action) {
    setWorkflowAction(action);
    setComment("");
    setErrors({});
    setActionOpen(true);
  }

  async function performAction() {
    setSaving(true);
    setErrors({});
    try {
      if (workflowAction === "REVISE") {
        await aemsAeoApi.revise(selectedId, order.id, {
          lockVersion: order.lockVersion,
          reason: comment,
        });
      } else {
        await aemsAeoApi.transition(selectedId, order.id, {
          action: workflowAction,
          lockVersion: order.lockVersion,
          comment: comment || null,
        });
      }
      toast.success(`${roleLabel(workflowAction)} completed.`);
      setActionOpen(false);
      await loadWorkspace();
    } catch (requestError) {
      if (requestError instanceof ApiError) setErrors(requestError.errors);
      toast.error(requestError.message);
    } finally {
      setSaving(false);
    }
  }

  async function downloadPdf() {
    try {
      await aemsAeoApi.downloadPdf(selectedId, order);
      toast.success("Approved AEO PDF downloaded.");
    } catch (requestError) {
      toast.error(requestError.message);
    }
  }

  return (
    <main className="min-w-0 p-3 sm:p-5 lg:p-6">
      <RegistryHeader
        icon={FileCheck2}
        title="Audit Engagement Orders"
        description="Prepare, independently review, approve, issue, and formally revise immutable Audit Engagement Order versions."
        readOnly={!canPrepare && !canReview && !canApprove && !canIssue}
        actions={
          !order && canPrepare && selectedId ? (
            <button
              className="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg bg-sky-700 px-4 text-sm font-bold text-white shadow-sm hover:bg-sky-800"
              onClick={openForm}
              type="button"
            >
              <Plus size={17} /> Create draft AEO
            </button>
          ) : null
        }
      />

      <section className="mb-5 rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
        <label className="block text-xs font-bold uppercase tracking-wide text-slate-500">
          Engagement
          <span className="mt-2 block max-w-4xl normal-case">
            <SearchableSelect
              options={engagementOptions}
              placeholder="Select an engagement"
              searchPlaceholder="Search engagement code, title, or office..."
              value={selectedId}
              onChange={(value) => setSelectedId(String(value))}
            />
          </span>
        </label>
      </section>

      {error && (
        <div className="mb-5 rounded-xl border border-red-200 bg-red-50 p-4 text-sm font-semibold text-red-700">
          {error}
        </div>
      )}

      {workspace && !order && !loading && (
        <section className="grid min-h-72 place-items-center rounded-xl border border-dashed border-slate-300 bg-white p-6 text-center">
          <div className="max-w-xl">
            <FilePenLine className="mx-auto text-sky-600" size={42} />
            <h2 className="mt-4 text-xl font-bold text-slate-800">
              No Audit Engagement Order yet
            </h2>
            <p className="mt-2 text-sm leading-6 text-slate-500">
              Prepare the authority, objectives, scope, effectivity, schedule,
              and a snapshot of the currently assigned audit team.
            </p>
            {!workspace.teamReady && (
              <div className="mt-4 rounded-lg border border-amber-200 bg-amber-50 p-3 text-left text-xs text-amber-800">
                <strong>Team setup is incomplete:</strong>
                <ul className="mt-1 list-disc pl-5">
                  {workspace.teamErrors.map((item) => (
                    <li key={item}>{item}</li>
                  ))}
                </ul>
              </div>
            )}
            {canPrepare && (
              <button
                className="mt-5 inline-flex items-center gap-2 rounded-lg bg-sky-700 px-5 py-3 text-sm font-bold text-white"
                onClick={openForm}
                type="button"
              >
                <Plus size={17} /> Create draft AEO
              </button>
            )}
          </div>
        </section>
      )}

      {order && (
        <>
          <section className="mb-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <SummaryCard
              icon={FileCheck2}
              label="AEO status"
              value={statusLabels[order.status]}
              tone={order.status === "ISSUED" ? "emerald" : "sky"}
            />
            <SummaryCard
              icon={History}
              label="Current version"
              value={`v${order.currentVersionNumber}`}
              tone="sky"
            />
            <SummaryCard
              icon={UsersRound}
              label="Team snapshot"
              value={version?.teamSnapshot?.length ?? 0}
              tone="amber"
            />
            <SummaryCard
              icon={FileClock}
              label="Versions retained"
              value={order.versions.length}
              tone="slate"
            />
          </section>

          <section className="mb-5 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            <div className="flex flex-col gap-3 border-b border-slate-200 px-4 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-5">
              <div>
                <div className="flex flex-wrap items-center gap-2">
                  <h2 className="font-bold text-slate-800">
                    {order.orderCode}
                  </h2>
                  <StatusBadge tone={statusTones[order.status] ?? "info"}>
                    {statusLabels[order.status] ?? order.status}
                  </StatusBadge>
                </div>
                <p className="mt-1 text-xs text-slate-500">
                  {workspace.engagement.engagementCode} · Version{" "}
                  {order.currentVersionNumber}
                </p>
              </div>
              <div className="flex flex-wrap gap-2">
                {canPrepare &&
                  ["DRAFT", "RETURNED_FOR_REVISION"].includes(order.status) && (
                    <button
                      className="inline-flex h-10 items-center gap-2 rounded-lg border border-sky-300 bg-white px-3 text-xs font-bold text-sky-700 hover:bg-sky-50"
                      onClick={openForm}
                      type="button"
                    >
                      <FilePenLine size={15} /> New content version
                    </button>
                  )}
                {order.approvedPdfAvailable && (
                  <button
                    className="inline-flex h-10 items-center gap-2 rounded-lg border border-emerald-300 bg-white px-3 text-xs font-bold text-emerald-700 hover:bg-emerald-50"
                    onClick={downloadPdf}
                    type="button"
                  >
                    <Download size={15} /> Approved PDF
                  </button>
                )}
              </div>
            </div>

            <div className="grid gap-4 p-4 sm:grid-cols-2 xl:grid-cols-4 sm:p-5">
              <Detail label="Prepared by">{order.preparedBy?.name}</Detail>
              <Detail label="Submitted">
                {order.submittedAt
                  ? `${order.submittedBy?.name} · ${date(order.submittedAt, true)}`
                  : "Not submitted"}
              </Detail>
              <Detail label="Approved">
                {order.approvedAt
                  ? `${order.approvedBy?.name} · ${date(order.approvedAt, true)}`
                  : "Not approved"}
              </Detail>
              <Detail label="Issued">
                {order.issuedAt
                  ? `${order.issuedBy?.name} · ${date(order.issuedAt, true)}`
                  : "Not issued"}
              </Detail>
            </div>

            <div className="grid gap-5 border-t border-slate-200 p-4 lg:grid-cols-[minmax(0,1fr)_22rem] sm:p-5">
              <div className="space-y-5">
                {[
                  ["Authority", version?.authority],
                  ["Objectives", version?.objectives],
                  ["Scope", version?.scope],
                ].map(([label, value]) => (
                  <div key={label}>
                    <h3 className="text-xs font-bold uppercase tracking-wide text-slate-400">
                      {label}
                    </h3>
                    <p className="mt-1 whitespace-pre-wrap text-sm leading-7 text-slate-700">
                      {value}
                    </p>
                  </div>
                ))}
              </div>
              <div className="grid content-start gap-3">
                <Detail label="Effectivity">
                  {date(version?.effectivityDate)}
                </Detail>
                <Detail label="Planned schedule">
                  {date(version?.plannedStartDate)} –{" "}
                  {date(version?.plannedEndDate)}
                </Detail>
                <Detail label="Version captured">
                  {date(version?.createdAt, true)}
                </Detail>
                <Detail label="Change reason">
                  {version?.changeReason || "Initial version"}
                </Detail>
              </div>
            </div>
          </section>

          {!!actions.length && (
            <section className="mb-5 rounded-xl border border-sky-200 bg-sky-50 p-4">
              <h2 className="text-sm font-bold text-slate-800">
                Available workflow actions
              </h2>
              <p className="mt-1 text-xs text-slate-600">
                The backend validates status, role, assignment, independent
                review, and lock version.
              </p>
              <div className="mt-3 flex flex-wrap gap-2">
                {actions.map(([action, label, Icon, tone]) => (
                  <button
                    className={`inline-flex h-10 items-center gap-2 rounded-lg px-4 text-xs font-bold text-white ${
                      tone === "success"
                        ? "bg-emerald-700 hover:bg-emerald-800"
                        : tone === "warning"
                          ? "bg-amber-600 hover:bg-amber-700"
                          : "bg-sky-700 hover:bg-sky-800"
                    }`}
                    key={action}
                    onClick={() => openAction(action)}
                    type="button"
                  >
                    <Icon size={15} /> {label}
                  </button>
                ))}
              </div>
            </section>
          )}

          <div className="grid gap-5 xl:grid-cols-2">
            <section className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
              <div className="border-b border-slate-200 px-4 py-3 sm:px-5">
                <h2 className="font-bold text-slate-800">
                  Authorized team snapshot
                </h2>
                <p className="mt-1 text-xs text-slate-500">
                  Later team changes do not alter this AEO version.
                </p>
              </div>
              <div className="divide-y divide-slate-100">
                {(version?.teamSnapshot ?? []).map((member) => (
                  <div
                    className="grid gap-1 px-4 py-3 text-xs sm:grid-cols-[8rem_1fr_auto] sm:px-5"
                    key={member.assignmentId}
                  >
                    <strong className="text-sky-800">
                      {roleLabel(member.role)}
                    </strong>
                    <span className="text-slate-700">{member.user?.name}</span>
                    <span className="text-slate-500">
                      {member.plannedPersonDays} days
                    </span>
                  </div>
                ))}
              </div>
            </section>

            <section className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
              <div className="border-b border-slate-200 px-4 py-3 sm:px-5">
                <h2 className="font-bold text-slate-800">Version history</h2>
                <p className="mt-1 text-xs text-slate-500">
                  Every content version remains immutable.
                </p>
              </div>
              <div className="divide-y divide-slate-100">
                {order.versions.map((item) => (
                  <div
                    className="grid gap-1 px-4 py-3 text-xs sm:grid-cols-[5rem_1fr_auto] sm:px-5"
                    key={item.id}
                  >
                    <strong className="text-sky-800">
                      Version {item.versionNumber}
                    </strong>
                    <span className="text-slate-600">
                      {item.changeReason || "Initial AEO version"}
                    </span>
                    <span className="text-slate-400">
                      {date(item.createdAt, true)}
                    </span>
                  </div>
                ))}
              </div>
            </section>
          </div>

          <section className="mt-5 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            <div className="border-b border-slate-200 px-4 py-3 sm:px-5">
              <h2 className="font-bold text-slate-800">Workflow history</h2>
              <p className="mt-1 text-xs text-slate-500">
                Actor, action, version, comment, and status transition.
              </p>
            </div>
            <div className="divide-y divide-slate-100">
              {order.events.map((event) => (
                <div
                  className="grid gap-2 px-4 py-3 text-xs sm:grid-cols-[9rem_5rem_1fr_auto] sm:px-5"
                  key={event.id}
                >
                  <strong className="text-sky-800">
                    {roleLabel(event.action.replace("AEO_", ""))}
                  </strong>
                  <span className="text-slate-500">
                    v{event.subjectVersion}
                  </span>
                  <span className="text-slate-600">
                    {event.comment ||
                      `${event.fromStatus ?? "New"} → ${event.toStatus}`}
                  </span>
                  <span className="text-slate-400">
                    {event.actor?.name} · {date(event.createdAt, true)}
                  </span>
                </div>
              ))}
            </div>
          </section>
        </>
      )}

      <Modal
        open={formOpen}
        onClose={() => !saving && setFormOpen(false)}
        size="lg"
        title={
          order
            ? "Create new AEO content version"
            : "Create draft Audit Engagement Order"
        }
        description="Saved content is immutable. Additional edits create another version rather than overwriting history."
        footer={
          <>
            <button
              className="h-10 rounded-lg border border-slate-300 bg-white px-4 text-sm font-bold text-slate-700"
              disabled={saving}
              onClick={() => setFormOpen(false)}
              type="button"
            >
              Cancel
            </button>
            <button
              className="h-10 rounded-lg bg-sky-700 px-5 text-sm font-bold text-white disabled:opacity-60"
              disabled={saving}
              onClick={saveContent}
              type="button"
            >
              {saving ? "Saving..." : "Save immutable version"}
            </button>
          </>
        }
      >
        <div className="grid gap-4 sm:grid-cols-2">
          {[
            [
              "authority",
              "Authority",
              "State the legal, administrative, or approved-plan authority.",
            ],
            [
              "objectives",
              "Objectives",
              "Define what this engagement is authorized to accomplish.",
            ],
            [
              "scope",
              "Scope",
              "Define the processes, period, offices, systems, and records covered.",
            ],
          ].map(([key, label, placeholder]) => (
            <label
              className="sm:col-span-2 text-sm font-semibold text-slate-700"
              key={key}
            >
              {label}
              <textarea
                className="mt-1.5 min-h-28 w-full rounded-lg border border-slate-300 p-3 font-normal"
                placeholder={placeholder}
                value={form[key]}
                onChange={(event) =>
                  setForm((current) => ({
                    ...current,
                    [key]: event.target.value,
                  }))
                }
              />
              {errors[key] && (
                <small className="text-red-600">{errors[key][0]}</small>
              )}
            </label>
          ))}
          <label className="text-sm font-semibold text-slate-700">
            Effectivity date
            <input
              className="mt-1.5 h-11 w-full rounded-lg border border-slate-300 px-3 font-normal"
              type="date"
              value={form.effectivityDate}
              onChange={(event) =>
                setForm((current) => ({
                  ...current,
                  effectivityDate: event.target.value,
                }))
              }
            />
          </label>
          <span />
          <label className="text-sm font-semibold text-slate-700">
            Planned start
            <input
              className="mt-1.5 h-11 w-full rounded-lg border border-slate-300 px-3 font-normal"
              type="date"
              value={form.plannedStartDate}
              onChange={(event) =>
                setForm((current) => ({
                  ...current,
                  plannedStartDate: event.target.value,
                }))
              }
            />
          </label>
          <label className="text-sm font-semibold text-slate-700">
            Planned end
            <input
              className="mt-1.5 h-11 w-full rounded-lg border border-slate-300 px-3 font-normal"
              type="date"
              value={form.plannedEndDate}
              onChange={(event) =>
                setForm((current) => ({
                  ...current,
                  plannedEndDate: event.target.value,
                }))
              }
            />
          </label>
          {order && (
            <label className="sm:col-span-2 text-sm font-semibold text-slate-700">
              Change reason
              <textarea
                className="mt-1.5 min-h-20 w-full rounded-lg border border-slate-300 p-3 font-normal"
                value={form.changeReason}
                onChange={(event) =>
                  setForm((current) => ({
                    ...current,
                    changeReason: event.target.value,
                  }))
                }
              />
            </label>
          )}
        </div>
      </Modal>

      <Modal
        open={actionOpen}
        onClose={() => !saving && setActionOpen(false)}
        size="sm"
        title={`${roleLabel(workflowAction)} AEO`}
        description={
          workflowAction === "REVISE"
            ? "A formal revision creates a new draft version; the approved or issued version remains immutable."
            : "This controlled action records your identity, date, comment, old status, new status, and AEO version."
        }
        footer={
          <>
            <button
              className="h-10 rounded-lg border border-slate-300 bg-white px-4 text-sm font-bold text-slate-700"
              disabled={saving}
              onClick={() => setActionOpen(false)}
              type="button"
            >
              Cancel
            </button>
            <button
              className="h-10 rounded-lg bg-sky-700 px-5 text-sm font-bold text-white disabled:opacity-60"
              disabled={saving}
              onClick={performAction}
              type="button"
            >
              {saving ? "Processing..." : "Confirm action"}
            </button>
          </>
        }
      >
        <label className="text-sm font-semibold text-slate-700">
          {["RETURN", "REVISE"].includes(workflowAction)
            ? "Required reason"
            : "Review comment"}
          <textarea
            className="mt-1.5 min-h-28 w-full rounded-lg border border-slate-300 p-3 font-normal"
            placeholder={
              workflowAction === "RETURN"
                ? "Clearly state what must be corrected before resubmission."
                : "Record the basis, instructions, or decision comment."
            }
            value={comment}
            onChange={(event) => setComment(event.target.value)}
          />
          {errors.comment && (
            <small className="text-red-600">{errors.comment[0]}</small>
          )}
          {errors.reason && (
            <small className="text-red-600">{errors.reason[0]}</small>
          )}
          {errors.action && (
            <small className="text-red-600">{errors.action[0]}</small>
          )}
          {errors.team && (
            <small className="text-red-600">{errors.team.join(" ")}</small>
          )}
        </label>
      </Modal>
    </main>
  );
}
