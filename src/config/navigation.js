import {
  Activity,
  Bell,
  Blocks,
  Building2,
  CalendarDays,
  ChartColumnBig,
  ChartNoAxesCombined,
  Database,
  FileText,
  FileBarChart,
  KeyRound,
  LayoutDashboard,
  ListChecks,
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
    code: "AEM",
    label: "Audit Engagement Management",
    path: "/audit-engagement-management",
    permission: "aem.view",
    icon: ShieldCheck,
    value: 18,
    note: "Active Engagements",
    tone: "green",
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
    path: "/compliance-management",
    permission: "cms.view",
    icon: SquareCheckBig,
    value: 21,
    note: "Open Recommendations",
    tone: "purple",
  },
  {
    key: "arms",
    code: "ARMIS",
    label: "Audit Resource Management",
    path: "/audit-resource-management",
    permission: "arms.view",
    icon: UsersRound,
    value: 12,
    note: "Active Auditors",
    tone: "teal",
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
  return Boolean(user?.permissions?.includes(permission));
}

export function visibleFor(user, items) {
  return items.filter((item) => hasPermission(user, item.permission));
}

export function pageForPath(pathname) {
  if (/^\/internal-audit-planning\/\d+$/.test(pathname)) {
    return {
      label: "Annual Audit Plan",
      icon: CalendarDays,
      permission: "iap.view",
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
