import { useEffect, useState } from "react";
import {
  ArrowLeft,
  BriefcaseBusiness,
  Building2,
  CalendarCheck2,
  CalendarDays,
  ChartNoAxesCombined,
  ClipboardList,
  FileClock,
  Files,
  Link2,
  ShieldCheck,
  Target,
  UsersRound,
} from "lucide-react";
import { Link, useNavigate, useParams, useSearchParams } from "react-router";
import { useAuth } from "../auth/auth-context";
import AemsEntryConferenceWorkspace from "../components/aems/AemsEntryConferenceWorkspace";
import AemsLifecycleWorkspace from "../components/aems/AemsLifecycleWorkspace";
import AemsCompletionAssessmentWorkspace from "../components/aems/AemsCompletionAssessmentWorkspace";
import AemsClosureWorkspace from "../components/aems/AemsClosureWorkspace";
import AemsDocumentIndexWorkspace from "../components/aems/AemsDocumentIndexWorkspace";
import AemsLessonsWorkspace from "../components/aems/AemsLessonsWorkspace";
import AemsRetentionWorkspace from "../components/aems/AemsRetentionWorkspace";
import RegistryHeader from "../components/ui/RegistryHeader";
import StatusBadge from "../components/ui/StatusBadge";
import { hasPermission } from "../config/navigation";
import useRecordView from "../hooks/useRecordView";
import { aemsEngagementApi } from "../services/api";

const labels = {
  DRAFT: "Draft",
  AUTHORIZATION_PREPARATION: "Authorization Preparation",
  RETURNED_FOR_REVISION: "Returned for Revision",
  AUTHORIZED: "Authorized",
  ENGAGEMENT_PLANNING: "Engagement Planning",
  ENTRY_CONFERENCE: "Entry Conference",
  FIELDWORK: "Fieldwork",
  FINDINGS_COMMUNICATION: "Findings Communication",
  REPORTING: "Reporting",
  ISSUED: "Issued",
  CLOSURE_REVIEW: "Closure Review",
  CLOSED: "Closed",
  SUSPENDED: "Suspended",
  CANCELLED: "Cancelled",
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

function Detail({ label, children }) {
  return (
    <div className="min-w-0 rounded-xl border border-slate-200 bg-white px-4 py-3">
      <p className="text-[11px] font-bold uppercase tracking-wide text-slate-400">
        {label}
      </p>
      <div className="mt-1 break-words text-sm font-semibold leading-6 text-slate-700">
        {children || "—"}
      </div>
    </div>
  );
}

function Panel({ icon: Icon, title, children, className = "" }) {
  return (
    <section
      className={`min-w-0 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm ${className}`}
    >
      <header className="flex items-center gap-2 border-b border-slate-200 px-4 py-3">
        <span className="grid h-8 w-8 place-items-center rounded-lg bg-sky-50 text-sky-700">
          <Icon size={17} />
        </span>
        <h3 className="text-sm font-bold text-slate-800">{title}</h3>
      </header>
      <div className="p-4">{children}</div>
    </section>
  );
}

/**
 * Shows the complete engagement aggregate and the immutable planning snapshot
 * captured at import time.
 */
export default function AemsEngagementDetailPage() {
  const { user } = useAuth();
  const { engagementId } = useParams();
  const navigate = useNavigate();
  const [searchParams, setSearchParams] = useSearchParams();
  const [engagement, setEngagement] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");

  useEffect(() => {
    let active = true;
    const timer = window.setTimeout(() => {
      if (!active) return;
      setLoading(true);
      setError("");
      aemsEngagementApi
        .show(engagementId)
        .then((record) => active && setEngagement(record))
        .catch((reason) => active && setError(reason.message))
        .finally(() => active && setLoading(false));
    }, 0);
    return () => {
      active = false;
      window.clearTimeout(timer);
    };
  }, [engagementId]);

  useRecordView(engagement, {
    module: "AEMS",
    recordType: "AuditEngagement",
    code: (record) => record.engagementCode,
    label: (record) => `${record.engagementCode} — ${record.title}`,
  });

  if (loading) {
    return (
      <main className="grid min-h-[60vh] place-items-center p-4 sm:p-5">
        <span className="h-9 w-9 animate-spin rounded-full border-2 border-slate-200 border-t-sky-700" />
      </main>
    );
  }

  if (!engagement) {
    return (
      <main className="p-4 sm:p-5">
        <div className="rounded-xl border border-red-200 bg-red-50 p-5 text-sm font-semibold text-red-700">
          {error || "The engagement could not be found."}
        </div>
      </main>
    );
  }

  const snapshot = engagement.sourceSnapshot ?? {};
  const plan = snapshot.plan;
  const ranking = snapshot.prioritization;
  const risk = snapshot.riskAssessment;
  const universe = snapshot.auditUniverse;
  const activeTab = searchParams.get("tab") ?? "overview";
  const tabs = [
    ["overview", "Overview"],
    ["lifecycle", "Lifecycle"],
    ...(hasPermission(user, "aems.entry-conference.view")
      ? [["entry-conference", "Entry Conference"]]
      : []),
    ...(hasPermission(user, "aems.completion-assessment.view")
      ? [["completion-assessment", "Completion Assessment"]]
      : []),
    ...(hasPermission(user, "aems.closure.view")
      ? [["closure", "Closure"]]
      : []),
    ...(hasPermission(user, "aems.document-index.view")
      ? [["document-index", "Final Document Index"]]
      : []),
    ...(hasPermission(user, "aems.retention.view")
      ? [["retention", "Retention"]]
      : []),
    ...(hasPermission(user, "aems.closure.view")
      ? [["lessons-learned", "Lessons Learned"]]
      : []),
  ];

  return (
    <main className="min-w-0 p-4 sm:p-5">
      <button
        className="mb-4 inline-flex items-center gap-2 rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-bold text-slate-700 transition hover:bg-slate-50"
        onClick={() => navigate("/audit-engagement-management")}
        type="button"
      >
        <ArrowLeft size={17} />
        Engagement Registry
      </button>

      <RegistryHeader
        description={`${engagement.engagementCode} · ${
          engagement.sourceType === "PLANNED"
            ? "Imported from approved IAP"
            : "Separately authorized special engagement"
        }`}
        icon={BriefcaseBusiness}
        title={engagement.title}
        actions={
          <>
            {hasPermission(user, "aems.team.view") && (
              <Link
                className="inline-flex min-h-10 items-center justify-center gap-2 rounded-lg border border-sky-300 bg-white px-3 text-xs font-bold text-sky-700 hover:bg-sky-50"
                to={`/audit-engagement-management/team?engagementId=${engagement.id}`}
              >
                <UsersRound size={16} /> Audit Team
              </Link>
            )}
            {hasPermission(user, "aems.aeo.view") && (
              <Link
                className="inline-flex min-h-10 items-center justify-center gap-2 rounded-lg bg-sky-700 px-3 text-xs font-bold text-white hover:bg-sky-800"
                to={`/audit-engagement-management/aeo?engagementId=${engagement.id}`}
              >
                <ClipboardList size={16} /> Engagement Order
              </Link>
            )}
            {hasPermission(user, "aems.working-paper.view") && (
              <Link
                className="inline-flex min-h-10 items-center justify-center gap-2 rounded-lg border border-emerald-300 bg-white px-3 text-xs font-bold text-emerald-700 hover:bg-emerald-50"
                to={`/audit-engagement-management/working-papers?engagementId=${engagement.id}`}
              >
                <Files size={16} /> Working Papers
              </Link>
            )}
            {hasPermission(user, "aems.issue.view") && (
              <Link
                className="inline-flex min-h-10 items-center justify-center gap-2 rounded-lg border border-amber-300 bg-white px-3 text-xs font-bold text-amber-700 hover:bg-amber-50"
                to={`/audit-engagement-management/issues?engagementId=${engagement.id}`}
              >
                Audit Issues
              </Link>
            )}
            {hasPermission(user, "aems.finding.view") && (
              <Link
                className="inline-flex min-h-10 items-center justify-center gap-2 rounded-lg border border-amber-300 bg-white px-3 text-xs font-bold text-amber-700 hover:bg-amber-50"
                to={`/audit-engagement-management/findings?engagementId=${engagement.id}`}
              >
                Findings & Recommendations
              </Link>
            )}
            {hasPermission(user, "aems.management-response.view") && (
              <Link
                className="inline-flex min-h-10 items-center justify-center gap-2 rounded-lg border border-sky-300 bg-white px-3 text-xs font-bold text-sky-700 hover:bg-sky-50"
                to={`/audit-engagement-management/auditee-responses?engagementId=${engagement.id}`}
              >
                Auditee Responses
              </Link>
            )}
            {hasPermission(user, "aems.conference.view") && (
              <Link
                className="inline-flex min-h-10 items-center justify-center gap-2 rounded-lg border border-emerald-300 bg-white px-3 text-xs font-bold text-emerald-700 hover:bg-emerald-50"
                to={`/audit-engagement-management/exit-conferences?engagementId=${engagement.id}`}
              >
                <CalendarCheck2 size={16} /> Exit Conferences
              </Link>
            )}
            {(hasPermission(user, "aems.report.view") ||
              hasPermission(user, "aems.report.view_issued")) && (
              <Link
                className="inline-flex min-h-10 items-center justify-center gap-2 rounded-lg border border-violet-300 bg-white px-3 text-xs font-bold text-violet-700 hover:bg-violet-50"
                to={`/audit-engagement-management/reports?engagementId=${engagement.id}`}
              >
                <FileClock size={16} /> Audit Reports
              </Link>
            )}
          </>
        }
      />

      <nav
        aria-label="Engagement workspace sections"
        className="mb-5 flex gap-1 overflow-x-auto rounded-xl border border-slate-200 bg-white p-1 shadow-sm"
      >
        {tabs.map(([value, label]) => (
          <button
            aria-current={activeTab === value ? "page" : undefined}
            className={`shrink-0 rounded-lg px-4 py-2.5 text-sm font-bold transition ${
              activeTab === value
                ? "bg-sky-700 text-white"
                : "text-slate-600 hover:bg-slate-100"
            }`}
            key={value}
            onClick={() =>
              setSearchParams(value === "overview" ? {} : { tab: value })
            }
            type="button"
          >
            {label}
          </button>
        ))}
      </nav>

      {activeTab === "lifecycle" ? (
        <AemsLifecycleWorkspace engagementId={engagement.id} />
      ) : activeTab === "entry-conference" &&
        hasPermission(user, "aems.entry-conference.view") ? (
        <AemsEntryConferenceWorkspace engagementId={engagement.id} />
      ) : activeTab === "completion-assessment" &&
        hasPermission(user, "aems.completion-assessment.view") ? (
        <AemsCompletionAssessmentWorkspace engagementId={engagement.id} />
      ) : activeTab === "closure" &&
        hasPermission(user, "aems.closure.view") ? (
        <AemsClosureWorkspace engagementId={engagement.id} />
      ) : activeTab === "document-index" &&
        hasPermission(user, "aems.document-index.view") ? (
        <AemsDocumentIndexWorkspace engagementId={engagement.id} />
      ) : activeTab === "retention" &&
        hasPermission(user, "aems.retention.view") ? (
        <AemsRetentionWorkspace engagementId={engagement.id} />
      ) : activeTab === "lessons-learned" &&
        hasPermission(user, "aems.closure.view") ? (
        <AemsLessonsWorkspace engagementId={engagement.id} />
      ) : (
        <>
      <section className="mb-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <Detail label="Status">
          <StatusBadge tone={engagement.isArchived ? "inactive" : "info"}>
            {engagement.isArchived
              ? "Archived"
              : (labels[engagement.status] ?? engagement.status)}
          </StatusBadge>
        </Detail>
        <Detail label="Planned schedule">
          {date(engagement.plannedStartDate)} to{" "}
          {date(engagement.plannedEndDate)}
        </Detail>
        <Detail label="Expected report">
          {date(engagement.expectedReportDate)}
        </Detail>
        <Detail label="Planned effort">
          {engagement.plannedPersonDays} person-days
        </Detail>
      </section>

      <div className="grid min-w-0 gap-5 2xl:grid-cols-[minmax(0,1.35fr)_minmax(22rem,.65fr)]">
        <div className="min-w-0 space-y-5">
          <Panel icon={ClipboardList} title="Engagement definition">
            <div className="space-y-4 text-sm leading-7 text-slate-700">
              {[
                ["Background", engagement.background],
                ["Objectives", engagement.objectives],
                ["Scope", engagement.scope],
                ["Exclusions", engagement.exclusions],
              ].map(([label, value]) => (
                <div key={label}>
                  <h4 className="text-xs font-bold uppercase tracking-wide text-slate-400">
                    {label}
                  </h4>
                  <p className="mt-1 whitespace-pre-wrap">
                    {value || "Not specified."}
                  </p>
                </div>
              ))}
            </div>
          </Panel>

          <Panel icon={Building2} title="Coverage">
            <div className="grid gap-4 md:grid-cols-3">
              <div>
                <h4 className="mb-2 text-xs font-bold uppercase text-slate-400">
                  Offices
                </h4>
                <div className="space-y-2">
                  {engagement.offices.map((office) => (
                    <div
                      className="rounded-lg bg-slate-50 px-3 py-2 text-sm text-slate-700"
                      key={office.id}
                    >
                      <strong className="text-sky-800">{office.code}</strong>{" "}
                      {office.name}
                    </div>
                  ))}
                </div>
              </div>
              <div>
                <h4 className="mb-2 text-xs font-bold uppercase text-slate-400">
                  Audit areas
                </h4>
                <div className="space-y-2">
                  {engagement.auditAreas.map((area) => (
                    <div
                      className="rounded-lg bg-slate-50 px-3 py-2 text-sm text-slate-700"
                      key={area.id}
                    >
                      <strong className="text-sky-800">{area.code}</strong>{" "}
                      {area.name}
                    </div>
                  ))}
                </div>
              </div>
              <div>
                <h4 className="mb-2 text-xs font-bold uppercase text-slate-400">
                  Audit focuses
                </h4>
                <div className="space-y-2">
                  {engagement.auditFocuses.length ? (
                    engagement.auditFocuses.map((focus) => (
                      <div
                        className="rounded-lg bg-slate-50 px-3 py-2 text-sm text-slate-700"
                        key={focus.id}
                      >
                        <strong className="text-sky-800">{focus.code}</strong>{" "}
                        {focus.name}
                      </div>
                    ))
                  ) : (
                    <p className="text-sm text-slate-500">No focus selected.</p>
                  )}
                </div>
              </div>
            </div>
          </Panel>

          {engagement.sourceType === "PLANNED" ? (
            <Panel icon={Link2} title="Approved IAP source and historical snapshot">
              <div className="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-xs leading-5 text-emerald-800">
                Snapshot captured {date(snapshot.capturedAt, true)}. These
                values are historical and do not change when IAP is revised.
              </div>
              <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                <Detail label="Annual plan">
                  {plan ? `${plan.code} · FY ${plan.fiscalYear}` : "—"}
                </Detail>
                <Detail label="Plan revision">
                  {plan ? `Revision ${plan.revisionNumber} · ${plan.status}` : "—"}
                </Detail>
                <Detail label="IAP engagement">
                  {snapshot.planEngagement?.code}
                </Detail>
                <Detail label="Prioritization run">
                  {ranking?.runCode || "Not linked"}
                </Detail>
                <Detail label="Final rank">
                  {ranking?.finalRank
                    ? `#${ranking.finalRank} · ${ranking.decision}`
                    : "Not ranked"}
                </Detail>
                <Detail label="Priority score">
                  {ranking?.priorityScore ?? "—"}
                </Detail>
                <Detail label="Original inherent risk">
                  {risk
                    ? `${risk.inherentRiskScore} · ${risk.inherentRiskLevel ?? "Unrated"}`
                    : "—"}
                </Detail>
                <Detail label="Original residual risk">
                  {risk
                    ? `${risk.residualRiskScore} · ${risk.residualRiskLevel ?? "Unrated"}`
                    : "—"}
                </Detail>
                <Detail label="Control effectiveness">
                  {risk
                    ? `${risk.controlEffectivenessPercent}%`
                    : "—"}
                </Detail>
              </div>
              {universe && (
                <div className="mt-4 rounded-xl bg-slate-50 p-4">
                  <h4 className="text-sm font-bold text-slate-800">
                    {universe.code} — {universe.name}
                  </h4>
                  <p className="mt-2 text-sm leading-6 text-slate-600">
                    {universe.description}
                  </p>
                </div>
              )}
            </Panel>
          ) : (
            <Panel icon={ShieldCheck} title="Special engagement authority">
              <div className="grid gap-3 sm:grid-cols-2">
                <Detail label="Authority reference">
                  {engagement.specialAuthorityReference}
                </Detail>
                <Detail label="Authority type">
                  {engagement.specialAuthorityTypeCode}
                </Detail>
                <Detail label="Authority date">
                  {date(engagement.specialAuthorityDate)}
                </Detail>
                <Detail label="Approving authority">
                  {engagement.specialAuthorityApprover?.name}
                </Detail>
              </div>
            </Panel>
          )}
        </div>

        <aside className="min-w-0 space-y-5">
          <Panel icon={ChartNoAxesCombined} title="Engagement tracker">
            <div className="grid grid-cols-2 gap-3">
              {[
                ["Team members", engagement.counts.teamMembers, UsersRound],
                ["Working papers", engagement.counts.workingPapers, FileClock],
                ["Findings", engagement.counts.findings, Target],
                ["Reports", engagement.counts.reports, ClipboardList],
              ].map(([label, value, Icon]) => (
                <div
                  className="rounded-xl bg-slate-50 p-3 text-center"
                  key={label}
                >
                  <Icon className="mx-auto text-sky-700" size={20} />
                  <strong className="mt-2 block text-xl text-slate-900">
                    {value}
                  </strong>
                  <span className="text-[11px] font-semibold text-slate-500">
                    {label}
                  </span>
                </div>
              ))}
            </div>
          </Panel>

          <Panel icon={UsersRound} title="Assigned audit team">
            {engagement.teamMembers.length ? (
              <div className="space-y-2">
                {engagement.teamMembers.map((member) => (
                  <div
                    className="rounded-lg border border-slate-200 px-3 py-2"
                    key={member.id}
                  >
                    <strong className="block text-sm text-slate-800">
                      {member.user?.name}
                    </strong>
                    <span className="text-xs text-slate-500">
                      {member.assignmentRoleCode} ·{" "}
                      {member.plannedPersonDays} days
                    </span>
                  </div>
                ))}
              </div>
            ) : (
              <p className="text-sm text-slate-500">
                The audit team has not been assigned yet.
              </p>
            )}
          </Panel>

          <Panel icon={CalendarDays} title="Registry history">
            {engagement.events.length ? (
              <ol className="space-y-4">
                {engagement.events
                  .slice()
                  .reverse()
                  .map((event) => (
                    <li
                      className="border-l-2 border-sky-200 pl-3"
                      key={event.id}
                    >
                      <strong className="block text-xs text-slate-800">
                        {event.action.replaceAll("_", " ")}
                      </strong>
                      <span className="block text-[11px] text-slate-500">
                        {event.actor?.name} · {date(event.createdAt, true)}
                      </span>
                      {event.comment && (
                        <p className="mt-1 text-xs leading-5 text-slate-600">
                          {event.comment}
                        </p>
                      )}
                    </li>
                  ))}
              </ol>
            ) : (
              <p className="text-sm text-slate-500">
                No registry events recorded.
              </p>
            )}
          </Panel>
        </aside>
      </div>
        </>
      )}
    </main>
  );
}
