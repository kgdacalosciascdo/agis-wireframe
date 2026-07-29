import { lazy, Suspense } from "react";
import { Navigate, Route, Routes } from "react-router";
import AuthProvider from "./auth/AuthProvider";
import AppLayout from "./layouts/AppLayout";
import LoginPage from "./pages/LoginPage";
import ModulePage from "./pages/ModulePage";
import UnauthorizedPage from "./pages/UnauthorizedPage";
import { pageRoutes } from "./config/navigation";
import ToastProvider from "./ui/ToastProvider";
import {
  PublicOnly,
  RequireAuth,
  RequirePermission,
} from "./routes/guards";

const DashboardPage = lazy(() => import("./pages/DashboardPage"));
const OfficeRegistryPage = lazy(() => import("./pages/OfficeRegistryPage"));
const AuditAreaRegistryPage = lazy(
  () => import("./pages/AuditAreaRegistryPage"),
);
const AuditFocusRegistryPage = lazy(
  () => import("./pages/AuditFocusRegistryPage"),
);
const UserRegistryPage = lazy(() => import("./pages/UserRegistryPage"));
const AccessControlRegistryPage = lazy(
  () => import("./pages/AccessControlRegistryPage"),
);
const MasterListsPage = lazy(() => import("./pages/MasterListsPage"));
const SystemConfigurationPage = lazy(
  () => import("./pages/SystemConfigurationPage"),
);
const ActivityLogPage = lazy(() => import("./pages/ActivityLogPage"));
const AuditTrailPage = lazy(() => import("./pages/AuditTrailPage"));
const DocumentManagementPage = lazy(
  () => import("./pages/DocumentManagementPage"),
);
const WorkflowManagementPage = lazy(
  () => import("./pages/WorkflowManagementPage"),
);
const NotificationCenterPage = lazy(
  () => import("./pages/NotificationCenterPage"),
);
const IapPlanRegistryPage = lazy(
  () => import("./pages/IapPlanRegistryPage"),
);
const IapDashboardPage = lazy(() => import("./pages/IapDashboardPage"));
const SiapPlanRegistryPage = lazy(
  () => import("./pages/SiapPlanRegistryPage"),
);
const IapAuditUniversePage = lazy(
  () => import("./pages/IapAuditUniversePage"),
);
const IapRiskAssessmentPeriodsPage = lazy(
  () => import("./pages/IapRiskAssessmentPeriodsPage"),
);
const IapPrioritizationPage = lazy(
  () => import("./pages/IapPrioritizationPage"),
);
const IapPlanWorkspacePage = lazy(
  () => import("./pages/IapPlanWorkspacePage"),
);
const IapSchedulingPage = lazy(() => import("./pages/IapSchedulingPage"));
const IapResourceCapacityPage = lazy(
  () => import("./pages/IapResourceCapacityPage"),
);
const IapReportsPage = lazy(() => import("./pages/IapReportsPage"));
const ProfilePage = lazy(() => import("./pages/ProfilePage"));

const implementedCorePaths = new Set([
  "/office-registry",
  "/audit-area-registry",
  "/audit-focus-registry",
  "/user-registry",
  "/access-role-registry",
  "/permission-registry",
  "/master-lists",
  "/activity-log",
  "/audit-trail",
  "/document-management",
  "/workflow-management",
  "/notifications",
  "/system-configuration",
  "/internal-audit-planning",
  "/internal-audit-planning/dashboard",
  "/internal-audit-planning/strategic-plan",
  "/internal-audit-planning/audit-universe",
  "/internal-audit-planning/risk-assessment",
  "/internal-audit-planning/prioritization",
  "/internal-audit-planning/scheduling",
  "/internal-audit-planning/resource-capacity",
  "/internal-audit-planning/reports",
]);

function RouteLoading() {
  return (
    <div className="grid min-h-[calc(100vh-6rem)] place-items-center">
      <div className="flex items-center gap-3 text-sm font-semibold text-slate-500">
        <span className="h-6 w-6 animate-spin rounded-full border-2 border-slate-200 border-t-sky-600" />
        Loading page...
      </div>
    </div>
  );
}

function ProtectedPage({ permission, children }) {
  return (
    <RequirePermission permission={permission}>{children}</RequirePermission>
  );
}

export default function App() {
  return (
    <AuthProvider>
      <ToastProvider>
        <Routes>
        <Route
          path="/login"
          element={
            <PublicOnly>
              <LoginPage />
            </PublicOnly>
          }
        />

        <Route
          path="/"
          element={
            <RequireAuth>
              <AppLayout />
            </RequireAuth>
          }
        >
          <Route index element={<Navigate to="/dashboard" replace />} />
          <Route
            path="dashboard"
            element={
              <ProtectedPage permission="dashboard.view">
                <Suspense fallback={<RouteLoading />}>
                  <DashboardPage />
                </Suspense>
              </ProtectedPage>
            }
          />

          <Route
            path="office-registry"
            element={
              <ProtectedPage permission="offices.view">
                <Suspense fallback={<RouteLoading />}>
                  <OfficeRegistryPage />
                </Suspense>
              </ProtectedPage>
            }
          />
          <Route
            path="audit-area-registry"
            element={
              <ProtectedPage permission="audit_areas.view">
                <Suspense fallback={<RouteLoading />}>
                  <AuditAreaRegistryPage />
                </Suspense>
              </ProtectedPage>
            }
          />
          <Route
            path="audit-focus-registry"
            element={
              <ProtectedPage permission="audit_focus.view">
                <Suspense fallback={<RouteLoading />}>
                  <AuditFocusRegistryPage />
                </Suspense>
              </ProtectedPage>
            }
          />
          <Route
            path="user-registry"
            element={
              <ProtectedPage permission="users.view">
                <Suspense fallback={<RouteLoading />}>
                  <UserRegistryPage />
                </Suspense>
              </ProtectedPage>
            }
          />
          <Route
            path="access-role-registry"
            element={
              <ProtectedPage permission="roles.view">
                <Suspense fallback={<RouteLoading />}>
                  <AccessControlRegistryPage mode="roles" />
                </Suspense>
              </ProtectedPage>
            }
          />
          <Route
            path="permission-registry"
            element={
              <ProtectedPage permission="permissions.view">
                <Suspense fallback={<RouteLoading />}>
                  <AccessControlRegistryPage mode="permissions" />
                </Suspense>
              </ProtectedPage>
            }
          />
          <Route
            path="master-lists"
            element={
              <ProtectedPage permission="master_lists.view">
                <Suspense fallback={<RouteLoading />}>
                  <MasterListsPage />
                </Suspense>
              </ProtectedPage>
            }
          />
          <Route
            path="activity-log"
            element={
              <ProtectedPage permission="activity_logs.view">
                <Suspense fallback={<RouteLoading />}>
                  <ActivityLogPage />
                </Suspense>
              </ProtectedPage>
            }
          />
          <Route
            path="audit-trail"
            element={
              <ProtectedPage permission="audit_logs.view">
                <Suspense fallback={<RouteLoading />}>
                  <AuditTrailPage />
                </Suspense>
              </ProtectedPage>
            }
          />
          <Route
            path="document-management"
            element={
              <ProtectedPage permission="documents.view">
                <Suspense fallback={<RouteLoading />}>
                  <DocumentManagementPage />
                </Suspense>
              </ProtectedPage>
            }
          />
          <Route
            path="workflow-management"
            element={
              <ProtectedPage permission="workflows.view">
                <Suspense fallback={<RouteLoading />}>
                  <WorkflowManagementPage />
                </Suspense>
              </ProtectedPage>
            }
          />
          <Route
            path="notifications"
            element={
              <ProtectedPage permission="notifications.view">
                <Suspense fallback={<RouteLoading />}>
                  <NotificationCenterPage />
                </Suspense>
              </ProtectedPage>
            }
          />
          <Route
            path="internal-audit-planning/dashboard"
            element={
              <ProtectedPage permission="iap.view">
                <Suspense fallback={<RouteLoading />}>
                  <IapDashboardPage />
                </Suspense>
              </ProtectedPage>
            }
          />
          <Route
            path="internal-audit-planning/strategic-plan"
            element={
              <ProtectedPage permission="iap.view">
                <Suspense fallback={<RouteLoading />}>
                  <SiapPlanRegistryPage />
                </Suspense>
              </ProtectedPage>
            }
          />
          <Route
            path="internal-audit-planning"
            element={
              <ProtectedPage permission="iap.view">
                <Suspense fallback={<RouteLoading />}>
                  <IapPlanRegistryPage />
                </Suspense>
              </ProtectedPage>
            }
          />
          <Route
            path="internal-audit-planning/audit-universe"
            element={
              <ProtectedPage permission="iap.view">
                <Suspense fallback={<RouteLoading />}>
                  <IapAuditUniversePage />
                </Suspense>
              </ProtectedPage>
            }
          />
          <Route
            path="internal-audit-planning/risk-assessment"
            element={
              <ProtectedPage permission="iap.view">
                <Suspense fallback={<RouteLoading />}>
                  <IapRiskAssessmentPeriodsPage />
                </Suspense>
              </ProtectedPage>
            }
          />
          <Route
            path="internal-audit-planning/prioritization"
            element={
              <ProtectedPage permission="iap.view">
                <Suspense fallback={<RouteLoading />}>
                  <IapPrioritizationPage />
                </Suspense>
              </ProtectedPage>
            }
          />
          <Route
            path="internal-audit-planning/scheduling"
            element={
              <ProtectedPage permission="iap.view">
                <Suspense fallback={<RouteLoading />}>
                  <IapSchedulingPage />
                </Suspense>
              </ProtectedPage>
            }
          />
          <Route
            path="internal-audit-planning/resource-capacity"
            element={
              <ProtectedPage permission="iap.view">
                <Suspense fallback={<RouteLoading />}>
                  <IapResourceCapacityPage />
                </Suspense>
              </ProtectedPage>
            }
          />
          <Route
            path="internal-audit-planning/reports"
            element={
              <ProtectedPage permission="iap.view">
                <Suspense fallback={<RouteLoading />}>
                  <IapReportsPage />
                </Suspense>
              </ProtectedPage>
            }
          />
          <Route
            path="internal-audit-planning/:planId"
            element={
              <ProtectedPage permission="iap.view">
                <Suspense fallback={<RouteLoading />}>
                  <IapPlanWorkspacePage />
                </Suspense>
              </ProtectedPage>
            }
          />
          <Route
            path="system-configuration"
            element={
              <ProtectedPage permission="system_configuration.view">
                <Suspense fallback={<RouteLoading />}>
                  <SystemConfigurationPage />
                </Suspense>
              </ProtectedPage>
            }
          />
          <Route
            path="profile"
            element={
              <ProtectedPage permission="profile.view">
                <Suspense fallback={<RouteLoading />}>
                  <ProfilePage />
                </Suspense>
              </ProtectedPage>
            }
          />

          {pageRoutes
            .filter((page) => !implementedCorePaths.has(page.path))
            .map((page) => (
            <Route
              key={page.path}
              path={page.path.slice(1)}
              element={
                <ProtectedPage permission={page.permission}>
                  <ModulePage />
                </ProtectedPage>
              }
            />
            ))}

          <Route path="unauthorized" element={<UnauthorizedPage />} />
          <Route path="*" element={<Navigate to="/dashboard" replace />} />
        </Route>

        <Route path="*" element={<Navigate to="/login" replace />} />
        </Routes>
      </ToastProvider>
    </AuthProvider>
  );
}
