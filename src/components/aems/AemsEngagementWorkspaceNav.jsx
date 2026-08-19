import {
  Activity,
  BriefcaseBusiness,
  CalendarCheck2,
  ClipboardCheck,
  FileBarChart,
  Files,
  ListChecks,
  ShieldAlert,
} from "lucide-react";
import { Link, useLocation } from "react-router";
import { useAuth } from "../../auth/auth-context";
import { hasPermission } from "../../config/navigation";

const tabs = [
  {
    key: "overview",
    label: "Overview",
    icon: BriefcaseBusiness,
    href: ({ id }) => `/audit-engagement-management/${id}`,
  },
  {
    key: "planning",
    label: "Planning",
    icon: ListChecks,
    permission: [
      "aems.planning-package.view",
      "aems.aep.view",
      "aems.program.view",
    ],
    href: ({ id }) =>
      `/audit-engagement-management/planning-package?engagementId=${id}`,
    paths: [
      "/audit-engagement-management/planning-package",
      "/audit-engagement-management/aep",
      "/audit-engagement-management/audit-program",
    ],
  },
  {
    key: "execution",
    label: "Execution",
    icon: Files,
    permission: ["aems.fieldwork.view", "aems.evidence-request.view"],
    href: ({ id }) =>
      `/audit-engagement-management/execution?engagementId=${id}`,
    paths: [
      "/audit-engagement-management/execution",
      "/audit-engagement-management/working-papers",
      "/audit-engagement-management/evidence",
    ],
  },
  {
    key: "issues",
    label: "Audit Issues",
    icon: ShieldAlert,
    permission: "aems.issue.view",
    href: ({ id }) => `/audit-engagement-management/issues?engagementId=${id}`,
    paths: ["/audit-engagement-management/issues"],
  },
  {
    key: "afrs",
    label: "AFRs",
    icon: ClipboardCheck,
    permission: "aems.finding.view",
    href: ({ id }) =>
      `/audit-engagement-management/findings?engagementId=${id}`,
    paths: [
      "/audit-engagement-management/findings",
      "/audit-engagement-management/auditee-responses",
    ],
  },
  {
    key: "conferences",
    label: "Conferences",
    icon: CalendarCheck2,
    permission: "aems.conference.view",
    href: ({ id }) =>
      `/audit-engagement-management/conferences?engagementId=${id}`,
    paths: [
      "/audit-engagement-management/entry-conferences",
      "/audit-engagement-management/entry-conference",
      "/audit-engagement-management/exit-conferences",
      "/audit-engagement-management/conferences",
    ],
  },
  {
    key: "reports",
    label: "Audit Reporting Workspace",
    icon: FileBarChart,
    permission: ["aems.report.view", "aems.report.view_issued"],
    href: ({ id }) => `/audit-engagement-management/reports?engagementId=${id}`,
    paths: ["/audit-engagement-management/reports"],
  },
  {
    key: "completion",
    label: "Completion & Transfer",
    icon: BriefcaseBusiness,
    permission: [
      "aems.completion-assessment.view",
      "aems.records.view",
      "aems.calendar.view",
    ],
    href: ({ id }) =>
      `/audit-engagement-management/${id}?tab=completion-assessment`,
    queryTabs: [
      "completion-assessment",
      "closure",
      "document-index",
      "retention",
      "records",
      "calendar",
      "lessons-learned",
    ],
  },
  {
    key: "activity",
    label: "Activity",
    icon: Activity,
    permission: ["activity_logs.view", "audit_logs.view"],
    href: ({ id }) => `/audit-trail?module=AEMS&recordId=${id}`,
    paths: ["/audit-trail", "/activity-log"],
  },
];

function isCurrentTab(tab, pathname, searchParams, engagementId) {
  if (tab.key === "overview") {
    return (
      pathname === `/audit-engagement-management/${engagementId}` &&
      !searchParams.get("tab")
    );
  }

  if (tab.queryTabs?.includes(searchParams.get("tab"))) return true;
  if (
    tab.paths?.some((path) => pathname === path || pathname.startsWith(path))
  ) {
    const contextId =
      searchParams.get("engagementId") ?? searchParams.get("recordId");
    return contextId === String(engagementId);
  }
  return false;
}

export default function AemsEngagementWorkspaceNav({ engagementId }) {
  const { user } = useAuth();
  const location = useLocation();
  const searchParams = new URLSearchParams(location.search);
  const visibleTabs = tabs.filter(
    (tab) => !tab.permission || hasPermission(user, tab.permission),
  );

  return (
    <nav
      aria-label="Engagement workspace tabs"
      className="mb-4 rounded-xl border border-slate-200 bg-white p-1 shadow-sm"
      data-testid="aems-engagement-tabs"
    >
      <div className="flex flex-wrap gap-1">
        {visibleTabs.map((tab) => {
          const Icon = tab.icon;
          const active = isCurrentTab(
            tab,
            location.pathname,
            searchParams,
            engagementId,
          );
          return (
            <Link
              aria-current={active ? "page" : undefined}
              className={`inline-flex min-h-10 min-w-0 flex-1 items-center justify-center gap-2 px-3 text-center text-xs font-bold leading-4 transition sm:flex-none sm:px-4 sm:text-sm ${
                active
                  ? "bg-sky-700 text-white shadow-sm"
                  : "text-slate-600 hover:bg-slate-100 hover:text-sky-800"
              }`}
              key={tab.key}
              to={tab.href({ id: engagementId })}
            >
              <Icon size={15} />
              {tab.label}
            </Link>
          );
        })}
      </div>
    </nav>
  );
}
