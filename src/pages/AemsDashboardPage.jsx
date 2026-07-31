import { useCallback, useEffect, useMemo, useState } from "react";
import {
  AlertTriangle,
  ArrowRight,
  BriefcaseBusiness,
  CalendarCheck2,
  CheckCircle2,
  ChevronDown,
  ChevronUp,
  ClipboardClock,
  Download,
  FileCheck2,
  FileClock,
  Gauge,
  ListChecks,
  MessageSquareWarning,
  Network,
  PlugZap,
  RefreshCw,
  Search,
  ShieldCheck,
  TimerOff,
  X,
} from "lucide-react";
import { Link } from "react-router";
import RegistryHeader from "../components/ui/RegistryHeader";
import StatusBadge from "../components/ui/StatusBadge";
import { aemsDashboardApi } from "../services/api";

const cardDefinitions = [
  {
    key: "activeEngagements",
    label: "Active engagements",
    note: "Open portfolio",
    icon: BriefcaseBusiness,
    tone: "sky",
  },
  {
    key: "engagementsInPlanning",
    label: "In planning",
    note: "Through AEP",
    icon: ClipboardClock,
    tone: "indigo",
  },
  {
    key: "engagementsInFieldwork",
    label: "In fieldwork",
    note: "Active execution",
    icon: Gauge,
    tone: "cyan",
  },
  {
    key: "overdueProcedures",
    label: "Overdue procedures",
    note: "Past target date",
    icon: TimerOff,
    tone: "rose",
  },
  {
    key: "workingPapersAwaitingReview",
    label: "WP awaiting review",
    note: "Submitted or resubmitted",
    icon: FileClock,
    tone: "amber",
  },
  {
    key: "findingsAwaitingResponse",
    label: "Findings awaiting response",
    note: "Formally communicated",
    icon: MessageSquareWarning,
    tone: "orange",
  },
  {
    key: "upcomingExitConferences",
    label: "Upcoming conferences",
    note: "Next 30 days",
    icon: CalendarCheck2,
    tone: "violet",
  },
  {
    key: "reportsPendingApproval",
    label: "Reports pending approval",
    note: "In review queue",
    icon: FileCheck2,
    tone: "blue",
  },
  {
    key: "engagementsReadyForClosure",
    label: "Ready for closure",
    note: "Pre-closure gates met",
    icon: CheckCircle2,
    tone: "emerald",
  },
];

const toneClasses = {
  sky: "border-sky-200 bg-sky-50 text-sky-700",
  indigo: "border-indigo-200 bg-indigo-50 text-indigo-700",
  cyan: "border-cyan-200 bg-cyan-50 text-cyan-700",
  rose: "border-rose-200 bg-rose-50 text-rose-700",
  amber: "border-amber-200 bg-amber-50 text-amber-700",
  orange: "border-orange-200 bg-orange-50 text-orange-700",
  violet: "border-violet-200 bg-violet-50 text-violet-700",
  blue: "border-blue-200 bg-blue-50 text-blue-700",
  emerald: "border-emerald-200 bg-emerald-50 text-emerald-700",
};

const statusTones = {
  COMPLETE: "success",
  READY: "success",
  NOT_APPLICABLE: "inactive",
  AWAITING_REVIEW: "warning",
  SCHEDULED: "info",
  IN_PROGRESS: "info",
  RETURNED: "danger",
  OVERDUE: "danger",
  BLOCKED: "danger",
  NOT_STARTED: "inactive",
};

const healthLabels = {
  ON_TRACK: "On track",
  OVERDUE: "Needs attention",
  BLOCKED: "Blocked",
  READY_FOR_CLOSURE: "Ready for closure",
};

const healthTones = {
  ON_TRACK: "info",
  OVERDUE: "danger",
  BLOCKED: "danger",
  READY_FOR_CLOSURE: "success",
};

function titleCase(value) {
  return String(value ?? "")
    .toLowerCase()
    .replaceAll("_", " ")
    .replace(/\b\w/g, (letter) => letter.toUpperCase());
}

function formatDate(value) {
  if (!value) return "Not set";
  return new Intl.DateTimeFormat("en-PH", {
    month: "short",
    day: "numeric",
    year: "numeric",
  }).format(new Date(`${value}T00:00:00`));
}

function MetricCard({ definition, value, loading }) {
  const Icon = definition.icon;
  return (
    <article
      className={`flex min-h-28 min-w-0 items-start gap-3 rounded-xl border p-4 shadow-sm transition duration-200 hover:-translate-y-1 hover:shadow-md ${toneClasses[definition.tone]}`}
    >
      <span className="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-white/90 shadow-sm ring-1 ring-black/5">
        <Icon size={19} />
      </span>
      <div className="min-w-0">
        <strong className="block text-2xl leading-none text-slate-950">
          {loading ? "—" : value}
        </strong>
        <span className="mt-2 block text-xs font-bold uppercase leading-4 tracking-wide">
          {definition.label}
        </span>
        <span className="mt-1 block text-xs leading-4 opacity-80">
          {definition.note}
        </span>
      </div>
    </article>
  );
}

function IntegrationStrip({ integrations, loading }) {
  const items = [
    {
      key: "core",
      label: "Core services",
      detail: `${integrations.core?.capabilities?.length ?? 0} shared capabilities`,
      healthy: integrations.core?.available,
    },
    {
      key: "iap",
      label: "IAP source",
      detail:
        integrations.iap?.eligibleEngagements == null
          ? "Approved-plan import connected"
          : `${integrations.iap.eligibleEngagements} approved engagement options`,
      healthy: integrations.iap?.available,
    },
    {
      key: "cms",
      label: "CMS intake",
      detail:
        integrations.cms?.transferredRecommendations == null
          ? "Immutable intake connected"
          : `${integrations.cms.transferredRecommendations} recommendations transferred`,
      healthy: integrations.cms?.available,
    },
    {
      key: "armis",
      label: "Resource provider",
      detail:
        integrations.armis?.mode === "IAP_INTERIM_FALLBACK"
          ? "IAP interim fallback"
          : (integrations.armis?.mode ?? "Not configured"),
      healthy: integrations.armis?.available,
      fallback: integrations.armis?.authoritative === false,
    },
  ];

  return (
    <section className="mt-4 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
      <header className="flex items-center gap-2 border-b border-slate-200 px-4 py-3 text-sm font-bold text-slate-800">
        <PlugZap className="text-sky-700" size={17} />
        Integration boundaries
      </header>
      <div className="grid divide-y divide-slate-200 sm:grid-cols-2 sm:divide-x sm:divide-y-0 xl:grid-cols-4">
        {items.map((item) => (
          <div className="flex min-w-0 items-start gap-3 p-3.5" key={item.key}>
            <span
              className={`grid h-8 w-8 shrink-0 place-items-center rounded-lg ${
                item.fallback
                  ? "bg-amber-100 text-amber-700"
                  : item.healthy
                    ? "bg-emerald-100 text-emerald-700"
                    : "bg-rose-100 text-rose-700"
              }`}
            >
              <Network size={16} />
            </span>
            <span className="min-w-0">
              <strong className="block text-xs text-slate-800">
                {item.label}
              </strong>
              <span className="mt-1 block truncate text-[11px] text-slate-500">
                {loading ? "Checking provider…" : item.detail}
              </span>
            </span>
          </div>
        ))}
      </div>
    </section>
  );
}

function ProgressBar({ percent, status, compact = false }) {
  const fill =
    status === "OVERDUE" || status === "BLOCKED" || status === "RETURNED"
      ? "bg-rose-500"
      : status === "COMPLETE" ||
          status === "READY" ||
          status === "NOT_APPLICABLE"
        ? "bg-emerald-500"
        : status === "AWAITING_REVIEW"
          ? "bg-amber-500"
          : "bg-sky-600";

  return (
    <div
      aria-label={`${percent}% complete`}
      className={`overflow-hidden rounded-full bg-slate-200 ${compact ? "h-1.5" : "h-2.5"}`}
      role="progressbar"
      aria-valuemax="100"
      aria-valuemin="0"
      aria-valuenow={percent}
    >
      <div
        className={`h-full rounded-full transition-all ${fill}`}
        style={{ width: `${percent}%` }}
      />
    </div>
  );
}

function StageCard({ engagementId, stage }) {
  const target = `${stage.route}?engagementId=${engagementId}`;
  return (
    <Link
      className="group min-w-0 rounded-lg border border-slate-200 bg-white p-3 transition hover:border-sky-300 hover:bg-sky-50"
      to={target}
    >
      <div className="flex min-w-0 items-start justify-between gap-2">
        <strong className="min-w-0 truncate text-xs text-slate-800">
          {stage.label}
        </strong>
        <span className="shrink-0 text-xs font-bold text-slate-600">
          {stage.percent}%
        </span>
      </div>
      <div className="mt-2">
        <ProgressBar compact percent={stage.percent} status={stage.status} />
      </div>
      <div className="mt-2 flex min-w-0 items-center justify-between gap-2">
        <StatusBadge tone={statusTones[stage.status] ?? "info"}>
          {titleCase(stage.status)}
        </StatusBadge>
        <ArrowRight
          className="shrink-0 text-slate-300 transition group-hover:translate-x-0.5 group-hover:text-sky-700"
          size={14}
        />
      </div>
      <p className="mt-2 line-clamp-2 text-[11px] leading-4 text-slate-500">
        {stage.detail}
      </p>
    </Link>
  );
}

function EngagementTrackerCard({ engagement, expanded, onToggle }) {
  return (
    <article className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
      <div className="p-4 sm:p-5">
        <div className="flex min-w-0 flex-col gap-4 xl:flex-row xl:items-start">
          <div className="min-w-0 flex-1">
            <div className="flex flex-wrap items-center gap-2">
              <Link
                className="truncate text-base font-bold text-slate-900 hover:text-sky-700"
                to={`/audit-engagement-management/${engagement.id}`}
              >
                {engagement.engagementCode} · {engagement.title}
              </Link>
              <StatusBadge tone="info">
                {titleCase(engagement.status)}
              </StatusBadge>
              <StatusBadge tone={healthTones[engagement.health] ?? "info"}>
                {healthLabels[engagement.health] ?? titleCase(engagement.health)}
              </StatusBadge>
            </div>
            <p className="mt-2 truncate text-xs text-slate-500">
              {engagement.offices.map((office) => office.name).join(", ") ||
                "No office linked"}
              {" · "}
              {engagement.sourceType === "PLANNED"
                ? "Approved IAP"
                : "Special engagement"}
            </p>
            <div className="mt-3 grid gap-2 text-xs text-slate-600 sm:grid-cols-3">
              <span>
                Start:{" "}
                <strong className="text-slate-800">
                  {formatDate(engagement.plannedStartDate)}
                </strong>
              </span>
              <span>
                Planned end:{" "}
                <strong className="text-slate-800">
                  {formatDate(engagement.plannedEndDate)}
                </strong>
              </span>
              <span>
                Expected report:{" "}
                <strong className="text-slate-800">
                  {formatDate(engagement.expectedReportDate)}
                </strong>
              </span>
            </div>
          </div>

          <div className="w-full shrink-0 xl:w-72">
            <div className="flex items-center justify-between text-xs">
              <span className="font-bold uppercase tracking-wide text-slate-500">
                Overall progress
              </span>
              <strong className="text-lg text-slate-900">
                {engagement.overallProgress}%
              </strong>
            </div>
            <div className="mt-2">
              <ProgressBar
                percent={engagement.overallProgress}
                status={
                  engagement.health === "OVERDUE"
                    ? "OVERDUE"
                    : engagement.health === "READY_FOR_CLOSURE"
                      ? "READY"
                      : "IN_PROGRESS"
                }
              />
            </div>
          </div>
        </div>

        {engagement.alerts.length > 0 && (
          <div className="mt-4 flex flex-wrap gap-2">
            {engagement.alerts.map((alert) => (
              <span
                className="inline-flex items-center gap-1.5 rounded-full bg-rose-50 px-2.5 py-1 text-xs font-semibold text-rose-700 ring-1 ring-rose-200"
                key={alert}
              >
                <AlertTriangle size={13} />
                {alert}
              </span>
            ))}
          </div>
        )}

        <button
          className="mt-4 inline-flex min-h-9 items-center gap-2 rounded-lg border border-slate-200 bg-slate-50 px-3 text-xs font-bold text-slate-700 hover:border-sky-300 hover:bg-sky-50 hover:text-sky-800"
          onClick={onToggle}
          type="button"
        >
          <ListChecks size={15} />
          {expanded ? "Hide workflow details" : "View all workflow stages"}
          {expanded ? <ChevronUp size={14} /> : <ChevronDown size={14} />}
        </button>
      </div>

      {expanded && (
        <div className="border-t border-slate-200 bg-slate-50 p-4 sm:p-5">
          <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-4">
            {engagement.stages.map((stage) => (
              <StageCard
                engagementId={engagement.id}
                key={stage.key}
                stage={stage}
              />
            ))}
          </div>

          <div className="mt-4 rounded-xl border border-slate-200 bg-white p-4">
            <div className="flex flex-wrap items-center justify-between gap-2">
              <div>
                <h3 className="text-sm font-bold text-slate-800">
                  Pre-closure readiness
                </h3>
                <p className="mt-1 text-xs text-slate-500">
                  Operational gates derived from issued records and terminal
                  child workflows.
                </p>
              </div>
              <StatusBadge
                tone={engagement.closure.isReady ? "success" : "warning"}
              >
                {engagement.closure.isReady
                  ? "Ready for closure"
                  : `${engagement.closure.blockers.length} outstanding`}
              </StatusBadge>
            </div>
            <div className="mt-3 grid gap-2 sm:grid-cols-2 xl:grid-cols-5">
              {engagement.closure.gates.map((gate) => (
                <span
                  className={`flex items-start gap-2 rounded-lg px-2.5 py-2 text-xs font-semibold ${
                    gate.met
                      ? "bg-emerald-50 text-emerald-700"
                      : "bg-slate-100 text-slate-600"
                  }`}
                  key={gate.key}
                >
                  {gate.met ? (
                    <CheckCircle2 className="mt-0.5 shrink-0" size={14} />
                  ) : (
                    <AlertTriangle className="mt-0.5 shrink-0" size={14} />
                  )}
                  {gate.label}
                </span>
              ))}
            </div>
          </div>
        </div>
      )}
    </article>
  );
}

export default function AemsDashboardPage() {
  const [dashboard, setDashboard] = useState({
    cards: {},
    engagements: [],
    pagination: { currentPage: 1, lastPage: 1, total: 0 },
    filters: { statuses: [], offices: [] },
    integrations: {},
    capabilities: { canExport: false },
  });
  const [filters, setFilters] = useState({
    search: "",
    status: "",
    officeId: "",
    page: 1,
    perPage: 10,
  });
  const [draftFilters, setDraftFilters] = useState({
    search: "",
    status: "",
    officeId: "",
  });
  const [expandedIds, setExpandedIds] = useState(new Set());
  const [loading, setLoading] = useState(true);
  const [exporting, setExporting] = useState(false);
  const [error, setError] = useState("");

  const load = useCallback(async () => {
    setLoading(true);
    setError("");
    try {
      const data = await aemsDashboardApi.show(filters);
      setDashboard({
        cards: data?.cards ?? {},
        engagements: Array.isArray(data?.engagements)
          ? data.engagements
          : [],
        pagination: data?.pagination ?? {
          currentPage: 1,
          lastPage: 1,
          total: 0,
        },
        filters: data?.filters ?? { statuses: [], offices: [] },
        integrations: data?.integrations ?? {},
        capabilities: data?.capabilities ?? { canExport: false },
      });
    } catch (requestError) {
      setError(
        requestError instanceof Error
          ? requestError.message
          : "Unable to load the engagement tracker.",
      );
    } finally {
      setLoading(false);
    }
  }, [filters]);

  useEffect(() => {
    const timer = window.setTimeout(load, 0);
    return () => window.clearTimeout(timer);
  }, [load]);

  const filtersActive = useMemo(
    () => Boolean(filters.search || filters.status || filters.officeId),
    [filters],
  );

  function applyFilters(event) {
    event.preventDefault();
    setFilters((current) => ({
      ...current,
      ...draftFilters,
      page: 1,
    }));
  }

  function clearFilters() {
    const cleared = { search: "", status: "", officeId: "" };
    setDraftFilters(cleared);
    setFilters((current) => ({ ...current, ...cleared, page: 1 }));
  }

  function toggleExpanded(id) {
    setExpandedIds((current) => {
      const next = new Set(current);
      if (next.has(id)) next.delete(id);
      else next.add(id);
      return next;
    });
  }

  async function exportReport() {
    setExporting(true);
    setError("");
    try {
      await aemsDashboardApi.export({
        search: filters.search,
        status: filters.status,
        officeId: filters.officeId,
      });
    } catch (requestError) {
      setError(
        requestError instanceof Error
          ? requestError.message
          : "Unable to export the Engagement Progress Report.",
      );
    } finally {
      setExporting(false);
    }
  }

  return (
    <main className="min-w-0 p-4 sm:p-5 lg:p-6">
      <RegistryHeader
        icon={ShieldCheck}
        title="AEMS Dashboard"
        description="Monitor every AEMS engagement from authorization through CMS transfer and closure readiness."
        actions={
          <>
            {dashboard.capabilities.canExport && (
              <button
                className="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg border border-slate-300 bg-white px-4 text-sm font-bold text-slate-700 shadow-sm transition hover:bg-slate-50 disabled:opacity-60"
                disabled={exporting}
                onClick={exportReport}
                type="button"
              >
                <Download size={17} />
                {exporting ? "Exporting..." : "Export Progress CSV"}
              </button>
            )}
            <Link
              className="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg bg-sky-700 px-4 text-sm font-bold text-white shadow-sm transition hover:-translate-y-0.5 hover:bg-sky-800 hover:shadow-md"
              to="/audit-engagement-management"
            >
              <BriefcaseBusiness size={17} />
              Open Engagement Registry
            </Link>
          </>
        }
      />

      {error && (
        <div className="mb-4 flex flex-col gap-3 rounded-xl border border-red-200 bg-red-50 p-4 text-sm font-semibold text-red-700 sm:flex-row sm:items-center sm:justify-between">
          <span>{error}</span>
          <button
            className="inline-flex items-center justify-center gap-2 rounded-lg border border-red-300 bg-white px-3 py-2"
            onClick={load}
            type="button"
          >
            <RefreshCw size={15} />
            Retry
          </button>
        </div>
      )}

      <section className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-5">
        {cardDefinitions.map((definition) => (
          <MetricCard
            definition={definition}
            key={definition.key}
            loading={loading}
            value={dashboard.cards[definition.key] ?? 0}
          />
        ))}
      </section>

      <IntegrationStrip
        integrations={dashboard.integrations}
        loading={loading}
      />

      <section className="mt-5 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <div className="border-b border-slate-200 p-4 sm:p-5">
          <div className="flex flex-wrap items-end justify-between gap-3">
            <div>
              <h2 className="font-bold text-slate-900">
                Engagement progress
              </h2>
              <p className="mt-1 text-xs text-slate-500">
                {dashboard.pagination.total ?? 0} visible engagement
                {(dashboard.pagination.total ?? 0) === 1 ? "" : "s"} · Click
                an engagement to inspect all 13 tracked stages.
              </p>
            </div>
            <button
              className="inline-flex min-h-9 items-center gap-2 rounded-lg border border-slate-200 px-3 text-xs font-bold text-slate-600 hover:bg-slate-50"
              disabled={loading}
              onClick={load}
              type="button"
            >
              <RefreshCw className={loading ? "animate-spin" : ""} size={14} />
              Refresh
            </button>
          </div>

          <form
            className="mt-4 grid gap-3 md:grid-cols-[minmax(12rem,1fr)_12rem_14rem_auto]"
            onSubmit={applyFilters}
          >
            <label className="relative min-w-0">
              <span className="sr-only">Search engagements</span>
              <Search
                className="pointer-events-none absolute left-3 top-3 text-slate-400"
                size={17}
              />
              <input
                className="min-h-11 w-full rounded-lg border border-slate-300 bg-white pl-10 pr-3 text-sm outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-100"
                onChange={(event) =>
                  setDraftFilters((current) => ({
                    ...current,
                    search: event.target.value,
                  }))
                }
                placeholder="Search code, title, or office"
                value={draftFilters.search}
              />
            </label>
            <label>
              <span className="sr-only">Engagement status</span>
              <select
                className="min-h-11 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-100"
                onChange={(event) =>
                  setDraftFilters((current) => ({
                    ...current,
                    status: event.target.value,
                  }))
                }
                value={draftFilters.status}
              >
                <option value="">All statuses</option>
                {dashboard.filters.statuses.map((status) => (
                  <option key={status} value={status}>
                    {titleCase(status)}
                  </option>
                ))}
              </select>
            </label>
            <label>
              <span className="sr-only">Responsible office</span>
              <select
                className="min-h-11 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-100"
                onChange={(event) =>
                  setDraftFilters((current) => ({
                    ...current,
                    officeId: event.target.value,
                  }))
                }
                value={draftFilters.officeId}
              >
                <option value="">All offices</option>
                {dashboard.filters.offices.map((office) => (
                  <option key={office.id} value={office.id}>
                    {office.code} · {office.name}
                  </option>
                ))}
              </select>
            </label>
            <div className="flex gap-2">
              <button
                className="inline-flex min-h-11 flex-1 items-center justify-center gap-2 rounded-lg bg-sky-700 px-4 text-sm font-bold text-white hover:bg-sky-800"
                type="submit"
              >
                <Search size={15} />
                Apply
              </button>
              {filtersActive && (
                <button
                  aria-label="Clear filters"
                  className="grid min-h-11 w-11 place-items-center rounded-lg border border-slate-300 bg-white text-slate-600 hover:bg-slate-50"
                  onClick={clearFilters}
                  type="button"
                >
                  <X size={17} />
                </button>
              )}
            </div>
          </form>
        </div>

        <div className="space-y-4 bg-slate-50/70 p-3 sm:p-4">
          {loading &&
            Array.from({ length: 3 }, (_, index) => (
              <div
                className="animate-pulse rounded-xl border border-slate-200 bg-white p-5"
                key={index}
              >
                <div className="h-5 w-2/3 rounded bg-slate-200" />
                <div className="mt-3 h-3 w-1/3 rounded bg-slate-100" />
                <div className="mt-5 h-2.5 rounded bg-slate-200" />
              </div>
            ))}

          {!loading &&
            dashboard.engagements.map((engagement) => (
              <EngagementTrackerCard
                engagement={engagement}
                expanded={expandedIds.has(engagement.id)}
                key={engagement.id}
                onToggle={() => toggleExpanded(engagement.id)}
              />
            ))}

          {!loading && dashboard.engagements.length === 0 && (
            <div className="grid min-h-64 place-items-center rounded-xl border border-dashed border-slate-300 bg-white p-6 text-center">
              <div>
                <BriefcaseBusiness
                  className="mx-auto text-slate-300"
                  size={38}
                />
                <p className="mt-3 text-sm font-bold text-slate-700">
                  No engagements match this view.
                </p>
                <p className="mt-1 text-xs text-slate-500">
                  Clear the filters or create the engagement in the registry.
                </p>
                {filtersActive && (
                  <button
                    className="mt-3 inline-flex items-center gap-2 text-sm font-bold text-sky-700"
                    onClick={clearFilters}
                    type="button"
                  >
                    Clear filters
                    <ArrowRight size={15} />
                  </button>
                )}
              </div>
            </div>
          )}
        </div>

        {!loading && (dashboard.pagination.lastPage ?? 1) > 1 && (
          <div className="flex items-center justify-between gap-3 border-t border-slate-200 px-4 py-3 text-xs text-slate-600 sm:px-5">
            <span>
              Showing {dashboard.pagination.from}–{dashboard.pagination.to} of{" "}
              {dashboard.pagination.total}
            </span>
            <div className="flex gap-2">
              <button
                className="min-h-9 rounded-lg border border-slate-300 px-3 font-bold disabled:cursor-not-allowed disabled:opacity-40"
                disabled={dashboard.pagination.currentPage <= 1}
                onClick={() =>
                  setFilters((current) => ({
                    ...current,
                    page: current.page - 1,
                  }))
                }
                type="button"
              >
                Previous
              </button>
              <button
                className="min-h-9 rounded-lg border border-slate-300 px-3 font-bold disabled:cursor-not-allowed disabled:opacity-40"
                disabled={
                  dashboard.pagination.currentPage >=
                  dashboard.pagination.lastPage
                }
                onClick={() =>
                  setFilters((current) => ({
                    ...current,
                    page: current.page + 1,
                  }))
                }
                type="button"
              >
                Next
              </button>
            </div>
          </div>
        )}
      </section>
    </main>
  );
}
