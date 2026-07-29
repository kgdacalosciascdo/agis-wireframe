<?php

/*
|--------------------------------------------------------------------------
| AGIS API Routes
|--------------------------------------------------------------------------
| Public endpoints are declared explicitly; all business routes are grouped
| behind authentication and granular permission middleware below.
*/

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CoreRegistryController;
use App\Http\Controllers\Api\DemoAccountController;
use App\Http\Controllers\Api\DocumentController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\IapAuditUniverseController;
use App\Http\Controllers\Api\IapDashboardController;
use App\Http\Controllers\Api\IapEngagementController;
use App\Http\Controllers\Api\IapPlanController;
use App\Http\Controllers\Api\IapPlanPrioritizationController;
use App\Http\Controllers\Api\IapPrioritizationController;
use App\Http\Controllers\Api\IapReportController;
use App\Http\Controllers\Api\IapResourceCapacityController;
use App\Http\Controllers\Api\IapRiskAssessmentController;
use App\Http\Controllers\Api\IapRiskPeriodController;
use App\Http\Controllers\Api\IapSchedulingController;
use App\Http\Controllers\Api\IapSupportingRecordController;
use App\Http\Controllers\Api\IapUniverseRiskAssessmentController;
use App\Http\Controllers\Api\IapWorkflowController;
use App\Http\Controllers\Api\LogController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\OfficeController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\RecordViewController;
use App\Http\Controllers\Api\ResetDemoDataController;
use App\Http\Controllers\Api\RuntimeConfigurationController;
use App\Http\Controllers\Api\SiapPlanController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\WorkflowController;
use Illuminate\Support\Facades\Route;

Route::get('/health', HealthController::class);
Route::get('/runtime-configuration', RuntimeConfigurationController::class);
Route::get('/demo-accounts', DemoAccountController::class)->middleware('throttle:30,1');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/record-views', RecordViewController::class);

    Route::get('/profile', [ProfileController::class, 'show'])
        ->middleware('permission:profile.view');
    Route::put('/profile', [ProfileController::class, 'update'])
        ->middleware('permission:profile.update');
    Route::put('/profile/password', [ProfileController::class, 'changePassword'])
        ->middleware('permission:profile.change_password');

    Route::get('/offices', [OfficeController::class, 'index'])
        ->middleware('permission:offices.view');
    Route::post('/offices', [OfficeController::class, 'store'])
        ->middleware('permission:offices.create');
    Route::put('/offices/{office}', [OfficeController::class, 'update'])
        ->middleware('permission:offices.update');
    Route::delete('/offices/{office}', [OfficeController::class, 'destroy'])
        ->middleware('permission:offices.delete');
    Route::post('/offices/{office}/restore', [OfficeController::class, 'restore'])
        ->middleware('permission:offices.restore');

    Route::get('/audit-areas', [CoreRegistryController::class, 'auditAreas'])
        ->middleware('permission:audit_areas.view');
    Route::post('/audit-areas', [CoreRegistryController::class, 'storeAuditArea'])
        ->middleware('permission:audit_areas.create');
    Route::put('/audit-areas/{auditArea}', [CoreRegistryController::class, 'updateAuditArea'])
        ->middleware('permission:audit_areas.update');
    Route::delete('/audit-areas/{auditArea}', [CoreRegistryController::class, 'destroyAuditArea'])
        ->middleware('permission:audit_areas.delete');
    Route::post('/audit-areas/{auditArea}/restore', [CoreRegistryController::class, 'restoreAuditArea'])
        ->middleware('permission:audit_areas.restore');

    Route::get('/audit-focuses', [CoreRegistryController::class, 'auditFocuses'])
        ->middleware('permission:audit_focus.view');
    Route::post('/audit-focuses', [CoreRegistryController::class, 'storeAuditFocus'])
        ->middleware('permission:audit_focus.create');
    Route::put('/audit-focuses/{auditFocus}', [CoreRegistryController::class, 'updateAuditFocus'])
        ->middleware('permission:audit_focus.update');
    Route::delete('/audit-focuses/{auditFocus}', [CoreRegistryController::class, 'destroyAuditFocus'])
        ->middleware('permission:audit_focus.delete');
    Route::post('/audit-focuses/{auditFocus}/restore', [CoreRegistryController::class, 'restoreAuditFocus'])
        ->middleware('permission:audit_focus.restore');

    Route::get('/users', [UserController::class, 'index'])
        ->middleware('permission:users.view');
    Route::get('/users/{user}', [UserController::class, 'show'])
        ->middleware('permission:users.view');
    Route::post('/users', [UserController::class, 'store'])
        ->middleware('permission:users.create');
    Route::put('/users/{user}', [UserController::class, 'update'])
        ->middleware('permission:users.update');
    Route::delete('/users/{user}', [UserController::class, 'archive'])
        ->middleware('permission:users.archive');
    Route::post('/users/{user}/activate', [UserController::class, 'activate'])
        ->middleware('permission:users.activate');
    Route::post('/users/{user}/disable', [UserController::class, 'disable'])
        ->middleware('permission:users.deactivate');
    Route::post('/users/{user}/lock', [UserController::class, 'lock'])
        ->middleware('permission:users.lock');
    Route::post('/users/{user}/unlock', [UserController::class, 'unlock'])
        ->middleware('permission:users.unlock');
    Route::post('/users/{user}/restore', [UserController::class, 'restore'])
        ->middleware('permission:users.restore');
    Route::put('/users/{user}/password', [UserController::class, 'resetPassword'])
        ->middleware('permission:users.reset_password');

    Route::get('/roles', [CoreRegistryController::class, 'roles'])
        ->middleware('permission:roles.view');
    Route::post('/roles', [CoreRegistryController::class, 'storeRole'])
        ->middleware('permission:roles.create');
    Route::post('/roles/{role}/clone', [CoreRegistryController::class, 'cloneRole'])
        ->middleware('permission:roles.clone');
    Route::put('/roles/{role}', [CoreRegistryController::class, 'updateRole'])
        ->middleware('permission:roles.update');
    Route::delete('/roles/{role}', [CoreRegistryController::class, 'destroyRole'])
        ->middleware('permission:roles.delete');
    Route::post('/roles/{role}/restore', [CoreRegistryController::class, 'restoreRole'])
        ->middleware('permission:roles.restore');
    Route::get('/permissions', [CoreRegistryController::class, 'permissions'])
        ->middleware('permission:permissions.view');
    Route::get('/master-lists', [CoreRegistryController::class, 'masterLists'])
        ->middleware('permission:master_lists.view');
    Route::post('/master-lists', [CoreRegistryController::class, 'storeMasterList'])
        ->middleware('permission:master_lists.manage');
    Route::put('/master-lists/{masterList}', [CoreRegistryController::class, 'updateMasterList'])
        ->middleware('permission:master_lists.manage');
    Route::get('/system-configurations', [CoreRegistryController::class, 'configurations'])
        ->middleware('permission:system_configuration.view');
    Route::put('/system-configurations', [CoreRegistryController::class, 'updateConfigurations'])
        ->middleware('permission:system_configuration.manage');
    Route::post('/system-configurations/logo', [CoreRegistryController::class, 'uploadLogo'])
        ->middleware('permission:system_configuration.manage');
    Route::post('/system-configurations/test-email', [CoreRegistryController::class, 'testEmail'])
        ->middleware('permission:system_configuration.manage');
    Route::get('/activity-logs', [LogController::class, 'activities'])
        ->middleware('permission:activity_logs.view');
    Route::get('/activity-logs/export', [LogController::class, 'exportActivities'])
        ->middleware('permission:activity_logs.export');
    Route::get('/audit-logs', [LogController::class, 'audits'])
        ->middleware('permission:audit_logs.view');
    Route::get('/audit-logs/export', [LogController::class, 'exportAudits'])
        ->middleware('permission:audit_logs.export');

    Route::get('/notifications', [NotificationController::class, 'index'])
        ->middleware('permission:notifications.view');
    Route::get('/notifications/recent', [NotificationController::class, 'recent'])
        ->middleware('permission:notifications.view');
    Route::post('/notifications', [NotificationController::class, 'deliver'])
        ->middleware('permission:notifications.manage');
    Route::post('/notifications/read-all', [NotificationController::class, 'readAll'])
        ->middleware('permission:notifications.view');
    Route::put('/notifications/preferences', [NotificationController::class, 'preferences'])
        ->middleware('permission:notifications.view');
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'read'])
        ->middleware('permission:notifications.view');
    Route::post('/notifications/{notification}/unread', [NotificationController::class, 'unread'])
        ->middleware('permission:notifications.view');
    Route::delete('/notifications/{notification}', [NotificationController::class, 'archive'])
        ->middleware('permission:notifications.view');
    Route::post('/notifications/{notification}/restore', [NotificationController::class, 'restore'])
        ->middleware('permission:notifications.view');

    Route::get('/workflows', [WorkflowController::class, 'index'])
        ->middleware('permission:workflows.view');
    Route::get('/workflows/{workflow}', [WorkflowController::class, 'show'])
        ->middleware('permission:workflows.view');
    Route::post('/workflows', [WorkflowController::class, 'store'])
        ->middleware('permission:workflows.create');
    Route::put('/workflows/{workflow}', [WorkflowController::class, 'update'])
        ->middleware('permission:workflows.update');
    Route::post('/workflows/{workflow}/publish', [WorkflowController::class, 'publish'])
        ->middleware('permission:workflows.publish');
    Route::post('/workflows/{workflow}/revisions', [WorkflowController::class, 'revision'])
        ->middleware('permission:workflows.create');
    Route::delete('/workflows/{workflow}', [WorkflowController::class, 'destroy'])
        ->middleware('permission:workflows.archive');
    Route::post('/workflows/{workflow}/restore', [WorkflowController::class, 'restore'])
        ->middleware('permission:workflows.restore');
    Route::post('/workflow-instances', [WorkflowController::class, 'start'])
        ->middleware('permission:workflows.start');
    Route::get('/workflow-instances/{instance}', [WorkflowController::class, 'instance'])
        ->middleware('permission:workflows.monitor');
    Route::post('/workflow-instances/{instance}/transitions/{action}', [WorkflowController::class, 'transition'])
        ->middleware('permission:workflows.act');
    Route::post('/workflow-instances/{instance}/cancel', [WorkflowController::class, 'cancel'])
        ->middleware('permission:workflows.act');

    Route::get('/iap/plans', [IapPlanController::class, 'index'])
        ->middleware('permission:iap.view');
    Route::get('/iap/dashboard', IapDashboardController::class)
        ->middleware('permission:iap.view');
    Route::get('/iap/reports', [IapReportController::class, 'index'])
        ->middleware('permission:iap.view');
    Route::get('/iap/reports/{report}', [IapReportController::class, 'show'])
        ->middleware('permission:iap.view');
    Route::get('/iap/reports/{report}/export', [IapReportController::class, 'export'])
        ->middleware('permission:iap.export');

    Route::get('/iap/strategic-plans', [SiapPlanController::class, 'index'])
        ->middleware('permission:iap.view');
    Route::post('/iap/strategic-plans', [SiapPlanController::class, 'store'])
        ->middleware('permission:iap.create');
    Route::get('/iap/strategic-plans/{strategicPlan}', [SiapPlanController::class, 'show'])
        ->middleware('permission:iap.view');
    Route::put('/iap/strategic-plans/{strategicPlan}', [SiapPlanController::class, 'update'])
        ->middleware('permission:iap.update');
    Route::delete('/iap/strategic-plans/{strategicPlan}', [SiapPlanController::class, 'destroy'])
        ->middleware('permission:iap.archive');
    Route::post('/iap/strategic-plans/{strategicPlan}/restore', [SiapPlanController::class, 'restore'])
        ->middleware('permission:iap.restore');
    Route::get('/iap/strategic-plans/{strategicPlan}/completeness', [SiapPlanController::class, 'completeness'])
        ->middleware('permission:iap.view');
    Route::post('/iap/strategic-plans/{strategicPlan}/transitions/{action}', [SiapPlanController::class, 'transition']);
    Route::post('/iap/strategic-plans/{strategicPlan}/revisions', [SiapPlanController::class, 'revision'])
        ->middleware('permission:iap.create_revision');

    Route::post('/iap/plans', [IapPlanController::class, 'store'])
        ->middleware('permission:iap.create');
    Route::get('/iap/plans/{plan}', [IapPlanController::class, 'show'])
        ->middleware('permission:iap.view');
    Route::put('/iap/plans/{plan}', [IapPlanController::class, 'update'])
        ->middleware('permission:iap.update');
    Route::delete('/iap/plans/{plan}', [IapPlanController::class, 'destroy'])
        ->middleware('permission:iap.archive');
    Route::post('/iap/plans/{plan}/restore', [IapPlanController::class, 'restore'])
        ->middleware('permission:iap.restore');

    Route::get('/iap/plans/{plan}/completeness', [IapWorkflowController::class, 'completeness'])
        ->middleware('permission:iap.view');
    Route::post('/iap/plans/{plan}/transitions/{action}', [IapWorkflowController::class, 'transition']);
    Route::post('/iap/plans/{plan}/revisions', [IapWorkflowController::class, 'revision'])
        ->middleware('permission:iap.create_revision');
    Route::get('/iap/plans/{plan}/supporting-records', [IapSupportingRecordController::class, 'index'])
        ->middleware('permission:iap.view');
    Route::post('/iap/plans/{plan}/attachments', [IapSupportingRecordController::class, 'storeAttachment'])
        ->middleware('permission:iap.update');
    Route::get('/iap/plans/{plan}/attachments/{attachment}/download', [IapSupportingRecordController::class, 'download'])
        ->middleware('permission:iap.view');
    Route::delete('/iap/plans/{plan}/attachments/{attachment}', [IapSupportingRecordController::class, 'destroyAttachment'])
        ->middleware('permission:iap.update');
    Route::post('/iap/plans/{plan}/attachments/{attachment}/restore', [IapSupportingRecordController::class, 'restoreAttachment'])
        ->middleware('permission:iap.update');
    Route::post('/iap/plans/{plan}/comments', [IapSupportingRecordController::class, 'storeComment'])
        ->middleware('permission:iap.review');

    Route::post('/iap/plans/{plan}/risk-assessments', [IapRiskAssessmentController::class, 'store'])
        ->middleware('permission:iap.assess_risk');
    Route::get('/iap/plans/{plan}/risk-assessments', [IapRiskAssessmentController::class, 'index'])
        ->middleware('permission:iap.assess_risk');
    Route::put('/iap/plans/{plan}/risk-assessments/{assessment}', [IapRiskAssessmentController::class, 'update'])
        ->middleware('permission:iap.assess_risk');
    Route::delete('/iap/plans/{plan}/risk-assessments/{assessment}', [IapRiskAssessmentController::class, 'destroy'])
        ->middleware('permission:iap.assess_risk');
    Route::post('/iap/plans/{plan}/risk-assessments/{assessment}/restore', [IapRiskAssessmentController::class, 'restore'])
        ->middleware('permission:iap.assess_risk');

    Route::post('/iap/plans/{plan}/engagements', [IapEngagementController::class, 'store'])
        ->middleware('permission:iap.manage_engagements');
    Route::put('/iap/plans/{plan}/prioritization', [IapPlanPrioritizationController::class, 'update'])
        ->middleware('permission:iap.manage_engagements');
    Route::get('/iap/schedules', [IapSchedulingController::class, 'index'])
        ->middleware('permission:iap.view');
    Route::post('/iap/schedules/{engagement}/conflicts', [IapSchedulingController::class, 'conflicts'])
        ->middleware('permission:iap.assign_team');
    Route::put('/iap/schedules/{engagement}', [IapSchedulingController::class, 'update'])
        ->middleware('permission:iap.assign_team');
    Route::post('/iap/schedules/{engagement}/cancel', [IapSchedulingController::class, 'cancel'])
        ->middleware('permission:iap.assign_team');
    Route::put('/iap/schedules/capacities/{user}', [IapSchedulingController::class, 'updateCapacity'])
        ->middleware('permission:iap.assign_team');
    Route::get('/iap/resources', [IapResourceCapacityController::class, 'index'])
        ->middleware('permission:iap.view');
    Route::put('/iap/resources/auditors/{user}/capacity', [IapResourceCapacityController::class, 'updateCapacity'])
        ->middleware('permission:iap.assign_team');
    Route::post('/iap/resources/auditors/{user}/unavailability', [IapResourceCapacityController::class, 'storeUnavailability'])
        ->middleware('permission:iap.assign_team');
    Route::put('/iap/resources/unavailability/{unavailability}', [IapResourceCapacityController::class, 'updateUnavailability'])
        ->middleware('permission:iap.assign_team');
    Route::delete('/iap/resources/unavailability/{unavailability}', [IapResourceCapacityController::class, 'destroyUnavailability'])
        ->middleware('permission:iap.assign_team');
    Route::post('/iap/resources/unavailability/{unavailability}/restore', [IapResourceCapacityController::class, 'restoreUnavailability'])
        ->middleware('permission:iap.assign_team');
    Route::put('/iap/resources/auditors/{user}/skills', [IapResourceCapacityController::class, 'syncSkills'])
        ->middleware('permission:iap.assign_team');
    Route::put('/iap/resources/engagements/{engagement}/requirements', [IapResourceCapacityController::class, 'syncRequirements'])
        ->middleware('permission:iap.assign_team');
    Route::put('/iap/plans/{plan}/engagements/{engagement}', [IapEngagementController::class, 'update'])
        ->middleware('permission:iap.manage_engagements');
    Route::delete('/iap/plans/{plan}/engagements/{engagement}', [IapEngagementController::class, 'destroy'])
        ->middleware('permission:iap.manage_engagements');
    Route::post('/iap/plans/{plan}/engagements/{engagement}/restore', [IapEngagementController::class, 'restore'])
        ->middleware('permission:iap.manage_engagements');
    Route::put('/iap/plans/{plan}/engagements/{engagement}/team', [IapEngagementController::class, 'updateTeam'])
        ->middleware('permission:iap.assign_team');

    Route::get('/iap/audit-universe', [IapAuditUniverseController::class, 'index'])
        ->middleware('permission:iap.view');
    Route::post('/iap/audit-universe', [IapAuditUniverseController::class, 'store'])
        ->middleware('permission:iap.manage_universe');
    Route::put('/iap/audit-universe/{auditUniverse}', [IapAuditUniverseController::class, 'update'])
        ->middleware('permission:iap.manage_universe');
    Route::delete('/iap/audit-universe/{auditUniverse}', [IapAuditUniverseController::class, 'destroy'])
        ->middleware('permission:iap.manage_universe');
    Route::post('/iap/audit-universe/{auditUniverse}/restore', [IapAuditUniverseController::class, 'restore'])
        ->middleware('permission:iap.manage_universe');

    Route::get('/iap/risk-periods', [IapRiskPeriodController::class, 'index'])
        ->middleware('permission:iap.view');
    Route::post('/iap/risk-periods', [IapRiskPeriodController::class, 'store'])
        ->middleware('permission:iap.create');
    Route::get('/iap/risk-periods/{period}', [IapRiskPeriodController::class, 'show'])
        ->middleware('permission:iap.view');
    Route::put('/iap/risk-periods/{period}', [IapRiskPeriodController::class, 'update'])
        ->middleware('permission:iap.update');
    Route::delete('/iap/risk-periods/{period}', [IapRiskPeriodController::class, 'destroy'])
        ->middleware('permission:iap.archive');
    Route::post('/iap/risk-periods/{period}/restore', [IapRiskPeriodController::class, 'restore'])
        ->middleware('permission:iap.restore');
    Route::post('/iap/risk-periods/{period}/transitions/{action}', [IapRiskPeriodController::class, 'transition']);
    Route::post('/iap/risk-periods/{period}/assessments', [IapUniverseRiskAssessmentController::class, 'store'])
        ->middleware('permission:iap.assess_risk');
    Route::put('/iap/risk-periods/{period}/assessments/{assessment}', [IapUniverseRiskAssessmentController::class, 'update'])
        ->middleware('permission:iap.assess_risk');
    Route::delete('/iap/risk-periods/{period}/assessments/{assessment}', [IapUniverseRiskAssessmentController::class, 'destroy'])
        ->middleware('permission:iap.assess_risk');
    Route::post('/iap/risk-periods/{period}/assessments/{assessment}/restore', [IapUniverseRiskAssessmentController::class, 'restore'])
        ->middleware('permission:iap.assess_risk');
    Route::post('/iap/risk-periods/{period}/assessments/{assessment}/evidence', [IapUniverseRiskAssessmentController::class, 'uploadEvidence'])
        ->middleware('permission:iap.assess_risk');
    Route::get('/iap/risk-periods/{period}/assessments/{assessment}/evidence/{evidence}', [IapUniverseRiskAssessmentController::class, 'downloadEvidence'])
        ->middleware('permission:iap.view');
    Route::delete('/iap/risk-periods/{period}/assessments/{assessment}/evidence/{evidence}', [IapUniverseRiskAssessmentController::class, 'destroyEvidence'])
        ->middleware('permission:iap.assess_risk');

    Route::get('/iap/prioritizations', [IapPrioritizationController::class, 'index'])
        ->middleware('permission:iap.view');
    Route::post('/iap/prioritizations', [IapPrioritizationController::class, 'store'])
        ->middleware('permission:iap.create');
    Route::get('/iap/prioritizations/{prioritization}', [IapPrioritizationController::class, 'show'])
        ->middleware('permission:iap.view');
    Route::put('/iap/prioritizations/{prioritization}', [IapPrioritizationController::class, 'update'])
        ->middleware('permission:iap.update');
    Route::put('/iap/prioritizations/{prioritization}/items/{item}', [IapPrioritizationController::class, 'updateItem'])
        ->middleware('permission:iap.update');
    Route::post('/iap/prioritizations/{prioritization}/transitions/{action}', [IapPrioritizationController::class, 'transition']);
    Route::delete('/iap/prioritizations/{prioritization}', [IapPrioritizationController::class, 'destroy'])
        ->middleware('permission:iap.archive');
    Route::post('/iap/prioritizations/{prioritization}/restore', [IapPrioritizationController::class, 'restore'])
        ->middleware('permission:iap.restore');

    Route::get('/documents', [DocumentController::class, 'index'])
        ->middleware('permission:documents.view');
    Route::post('/documents', [DocumentController::class, 'store'])
        ->middleware('permission:documents.upload');
    Route::put('/documents/{document}', [DocumentController::class, 'update'])
        ->middleware('permission:documents.update');
    Route::post('/documents/{document}/versions', [DocumentController::class, 'storeVersion'])
        ->middleware('permission:documents.update');
    Route::delete('/documents/{document}', [DocumentController::class, 'destroy'])
        ->middleware('permission:documents.delete');
    Route::post('/documents/{document}/restore', [DocumentController::class, 'restore'])
        ->middleware('permission:documents.restore');
    Route::get('/documents/{document}/download', [DocumentController::class, 'download'])
        ->middleware('permission:documents.download');
    Route::get('/documents/{document}/versions/{version}/download', [DocumentController::class, 'downloadVersion'])
        ->middleware('permission:documents.download');

    Route::post('/demo/reset', ResetDemoDataController::class)
        ->middleware('permission:system_configuration.manage');
});
