import {
  Activity,
  BadgeCheck,
  Bell,
  Bot,
  Blocks,
  BriefcaseBusiness,
  Building2,
  CalendarCheck2,
  CalendarDays,
  ChartColumnBig,
  ChartNoAxesCombined,
  ClipboardCheck,
  Database,
  FileText,
  Files,
  FileBarChart,
  KeyRound,
  LayoutDashboard,
  ListChecks,
  MessageSquareText,
  Network,
  Settings,
  ShieldAlert,
  ShieldCheck,
  SquareCheckBig,
  Target,
  UserRound,
  UsersRound,
  Workflow,
} from "lucide-react";

export const iapPages = [
  {
    label: "IAP Dashboard",
    path: "/internal-audit-planning/dashboard",
    permission: "iap.view",
    icon: LayoutDashboard,
  },
  {
    label: "Strategic Audit Plan",
    path: "/internal-audit-planning/strategic-plan",
    permission: "iap.view",
    icon: ChartNoAxesCombined,
  },
  {
    label: "Audit Universe",
    path: "/internal-audit-planning/audit-universe",
    permission: "iap.view",
    icon: Blocks,
  },
  {
    label: "Risk Assessment",
    path: "/internal-audit-planning/risk-assessment",
    permission: "iap.view",
    icon: ShieldAlert,
  },
  {
    label: "Audit Prioritization",
    path: "/internal-audit-planning/prioritization",
    permission: "iap.view",
    icon: ListChecks,
  },
  {
    label: "Annual Audit Plan",
    path: "/internal-audit-planning",
    permission: "iap.view",
    icon: CalendarDays,
    end: true,
  },
  {
    label: "Audit Scheduling",
    path: "/internal-audit-planning/scheduling",
    permission: "iap.view",
    icon: CalendarDays,
  },
  {
    label: "Resource Capacity",
    path: "/internal-audit-planning/resource-capacity",
    permission: "iap.view",
    icon: UsersRound,
  },
  {
    label: "IAP Reports",
    path: "/internal-audit-planning/reports",
    permission: "iap.view",
    icon: FileBarChart,
  },
];

export const aemsPages = [
  {
    label: "AEMS Dashboard",
    path: "/audit-engagement-management/dashboard",
    permission: "aems.engagement.view",
    icon: LayoutDashboard,
  },
  {
    label: "Engagement Registry",
    path: "/audit-engagement-management",
    permission: "aems.engagement.view",
    icon: BriefcaseBusiness,
    end: true,
  },
  {
    label: "Audit Team",
    path: "/audit-engagement-management/team",
    permission: "aems.team.view",
    icon: UsersRound,
  },
  {
    label: "Engagement Orders",
    path: "/audit-engagement-management/aeo",
    permission: "aems.aeo.view",
    icon: FileText,
  },
  {
    label: "Engagement Plan",
    path: "/audit-engagement-management/aep",
    permission: "aems.aep.view",
    icon: SquareCheckBig,
  },
  {
    label: "Audit Program",
    path: "/audit-engagement-management/audit-program",
    permission: "aems.program.view",
    icon: ListChecks,
  },
  {
    label: "Entry Conferences",
    path: "/audit-engagement-management/entry-conferences",
    permission: "aems.entry-conference.view",
    icon: CalendarCheck2,
  },
  {
    label: "Working Papers & Evidence",
    path: "/audit-engagement-management/working-papers",
    permission: "aems.working-paper.view",
    icon: Files,
  },
  {
    label: "Audit Issues",
    path: "/audit-engagement-management/issues",
    permission: "aems.issue.view",
    icon: ShieldAlert,
  },
  {
    label: "Findings & Recommendations",
    path: "/audit-engagement-management/findings",
    permission: "aems.finding.view",
    icon: ClipboardCheck,
  },
  {
    label: "Auditee Responses",
    path: "/audit-engagement-management/auditee-responses",
    permission: "aems.management-response.view",
    icon: MessageSquareText,
  },
  {
    label: "Exit Conferences",
    path: "/audit-engagement-management/exit-conferences",
    permission: "aems.conference.view",
    icon: CalendarCheck2,
  },
  {
    label: "Audit Reports",
    path: "/audit-engagement-management/reports",
    permission: ["aems.report.view", "aems.report.view_issued"],
    icon: FileBarChart,
  },
];

export const cmsPages = [
  {
    label: "CMS Dashboard",
    path: "/compliance-management/dashboard",
    permission: "cms.dashboard.view",
    icon: LayoutDashboard,
  },
  {
    label: "Recommendation Registry",
    path: "/compliance-management/recommendations",
    permission: "cms.recommendation.view",
    icon: ClipboardCheck,
  },
  {
    label: "Automation & Candidates",
    path: "/compliance-management/automation",
    permission: "cms.automation.view",
    icon: Bot,
  },
  {
    label: "Reports & Exports",
    path: "/compliance-management/reports",
    permission: "cms.report.view",
    icon: FileBarChart,
  },
];

export const armisPages = [
  {
    label: "Resource Registry",
    path: "/audit-resource-management/resources",
    permission: "armis.resource.view",
    icon: UsersRound,
  },
  {
    label: "Competencies & Certifications",
    path: "/audit-resource-management/competencies",
    permission: "armis.competency.view",
    icon: BadgeCheck,
  },
];

export const modules = [
  {
    key: "core",
    code: "CORE",
    label: "Core Records",
    path: "/office-registry",
    permission: "offices.view",
    icon: Blocks,
    value: 43,
    note: "Registered Offices",
    tone: "blue",
  },
  {
    key: "iap",
    code: "IAP",
    label: "Internal Audit Planning",
    path: "/internal-audit-planning/dashboard",
    permission: "iap.view",
    icon: CalendarDays,
    value: 3,
    note: "Plans in Progress",
    tone: "blue",
    children: iapPages,
  },
  {
    key: "aem",
    code: "AEMS",
    label: "Audit Engagement Monitoring",
    path: "/audit-engagement-management/dashboard",
    permission: "aems.engagement.view",
    icon: ShieldCheck,
    value: 18,
    note: "Active Engagements",
    tone: "green",
    children: aemsPages,
  },
  {
    key: "afr",
    code: "AFR",
    label: "Audit Finding & Recommendation",
    path: "/audit-findings-recommendations",
    permission: "afr.view",
    icon: ShieldAlert,
    value: 43,
    note: "Findings Issued",
    tone: "orange",
  },
  {
    key: "cms",
    code: "CMS",
    label: "Compliance Management",
    path: "/compliance-management/dashboard",
    permission: ["cms.dashboard.view", "cms.recommendation.view"],
    icon: SquareCheckBig,
    tone: "purple",
    children: cmsPages,
  },
  {
    key: "arms",
    code: "ARMIS",
    label: "Audit Resource Management",
    path: "/audit-resource-management/resources",
    permission: "armis.resource.view",
    icon: UsersRound,
    value: 12,
    note: "Active Auditors",
    tone: "teal",
    children: armisPages,
  },
  {
    key: "ais",
    code: "AIS",
    label: "Audit Intelligence System",
    path: "/audit-intelligence-system",
    permission: "ais.view",
    icon: ChartNoAxesCombined,
    value: 8,
    note: "Key Insights",
    tone: "yellow",
  },
];

export const navigationSections = [
  {
    key: "primary",
    items: [
      {
        label: "Dashboard",
        path: "/dashboard",
        permission: "dashboard.view",
        icon: LayoutDashboard,
      },
      ...modules.filter(
        (module) => module.key !== "afr" && module.key !== "core",
      ),
    ],
  },
  {
    key: "registries",
    title: "Master Registries",
    items: [
      {
        label: "Office Registry",
        path: "/office-registry",
        permission: "offices.view",
        icon: Building2,
      },
      {
        label: "Audit Area Registry",
        path: "/audit-area-registry",
        permission: "audit_areas.view",
        icon: Network,
      },
      {
        label: "Audit Focus Registry",
        path: "/audit-focus-registry",
        permission: "audit_focus.view",
        icon: Target,
      },
      {
        label: "User Registry",
        path: "/user-registry",
        permission: "users.view",
        icon: UserRound,
      },
      {
        label: "Access Role Registry",
        path: "/access-role-registry",
        permission: "roles.view",
        icon: KeyRound,
      },
      {
        label: "Permission Registry",
        path: "/permission-registry",
        permission: "permissions.view",
        icon: ListChecks,
      },
      {
        label: "Master Lists",
        path: "/master-lists",
        permission: "master_lists.view",
        icon: Database,
      },
    ],
  },
  {
    key: "administration",
    title: "Administration",
    items: [
      {
        label: "Document Management",
        path: "/document-management",
        permission: "documents.view",
        icon: FileText,
      },
      {
        label: "Notifications",
        path: "/notifications",
        permission: "notifications.view",
        icon: Bell,
      },
      {
        label: "Workflow Management",
        path: "/workflow-management",
        permission: "workflows.view",
        icon: Workflow,
      },
      {
        label: "Activity Log",
        path: "/activity-log",
        permission: "activity_logs.view",
        icon: Activity,
      },
      {
        label: "Audit Trail",
        path: "/audit-trail",
        permission: "audit_logs.view",
        icon: Workflow,
      },
      {
        label: "System Configuration",
        path: "/system-configuration",
        permission: "system_configuration.view",
        icon: Settings,
      },
      {
        label: "Administrative Reports",
        path: "/administrative-reports",
        permission: "administrative_reports.view",
        icon: ChartColumnBig,
      },
    ],
  },
];

export const pageRoutes = [
  ...modules.flatMap((module) => [module, ...(module.children ?? [])]),
  ...navigationSections.flatMap((section) => section.items),
].filter(
  (item, index, items) =>
    item.path !== "/dashboard" &&
    items.findIndex((candidate) => candidate.path === item.path) === index,
);

export const allNavigationItems = navigationSections.flatMap(
  (section) => section.items,
);

export function hasPermission(user, permission) {
  if (Array.isArray(permission)) {
    return permission.some((code) => user?.permissions?.includes(code));
  }
  return Boolean(user?.permissions?.includes(permission));
}

export function visibleFor(user, items) {
  return items.filter((item) => hasPermission(user, item.permission));
}

export function pageForPath(pathname) {
  if (/^\/audit-resource-management\/competencies(?:\/\d+)?$/.test(pathname)) {
    return {
      label: "ARMIS Competencies & Certifications",
      icon: BadgeCheck,
      permission: "armis.competency.view",
    };
  }

  if (/^\/audit-resource-management\/resources(?:\/\d+)?$/.test(pathname)) {
    return {
      label: "ARMIS Resource Registry",
      icon: UsersRound,
      permission: "armis.resource.view",
    };
  }

  if (/^\/internal-audit-planning\/\d+$/.test(pathname)) {
    return {
      label: "Annual Audit Plan",
      icon: CalendarDays,
      permission: "iap.view",
    };
  }

  if (/^\/audit-engagement-management\/\d+$/.test(pathname)) {
    return {
      label: "Engagement Details",
      icon: ShieldCheck,
      permission: "aems.engagement.view",
    };
  }

  if (
    /^\/compliance-management\/recommendations\/\d+\/action-plan$/.test(
      pathname,
    )
  ) {
    return {
      label: "Corrective Action Plan",
      icon: ListChecks,
      permission: "cms.action-plan.view",
    };
  }

  if (
    /^\/compliance-management\/recommendations\/\d+\/progress-updates(?:\/\d+)?$/.test(
      pathname,
    )
  ) {
    return {
      label: "Progress Updates",
      icon: ClipboardCheck,
      permission: "cms.progress.view",
    };
  }

  if (
    /^\/compliance-management\/recommendations\/\d+\/validations(?:\/\d+)?$/.test(
      pathname,
    )
  ) {
    return {
      label: "Independent Validation",
      icon: ClipboardCheck,
      permission: "cms.validation.view",
    };
  }

  if (/^\/compliance-management\/recommendations\/\d+$/.test(pathname)) {
    return {
      label: "Recommendation Details",
      icon: ClipboardCheck,
      permission: "cms.recommendation.view",
    };
  }

  if (pathname === "/profile") {
    return {
      label: "My Profile",
      icon: UserRound,
      permission: "profile.view",
    };
  }

  if (pathname === "/office-registry") {
    return navigationSections
      .flatMap((section) => section.items)
      .find((item) => item.path === pathname);
  }

  if (pathname === "/dashboard") {
    return {
      label: "Dashboard",
      icon: LayoutDashboard,
      permission: "dashboard.view",
    };
  }

  return pageRoutes.find((item) => item.path === pathname) ?? null;
}
