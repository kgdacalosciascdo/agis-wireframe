<?php

/*
|--------------------------------------------------------------------------
| AGIS API Routes
|--------------------------------------------------------------------------
| Public endpoints are declared explicitly; all business routes are grouped
| behind authentication and granular permission middleware below.
*/

use App\Http\Controllers\Api\AemsAeoController;
use App\Http\Controllers\Api\AemsAepController;
use App\Http\Controllers\Api\AemsClosureController;
use App\Http\Controllers\Api\AemsCompletionAssessmentController;
use App\Http\Controllers\Api\AemsDashboardController;
use App\Http\Controllers\Api\AemsDocumentIndexController;
use App\Http\Controllers\Api\AemsEngagementController;
use App\Http\Controllers\Api\AemsEngagementLifecycleController;
use App\Http\Controllers\Api\AemsEntryConferenceController;
use App\Http\Controllers\Api\AemsEvidenceController;
use App\Http\Controllers\Api\AemsExitConferenceController;
use App\Http\Controllers\Api\AemsFindingController;
use App\Http\Controllers\Api\AemsIssueController;
use App\Http\Controllers\Api\AemsProgramController;
use App\Http\Controllers\Api\AemsReopenController;
use App\Http\Controllers\Api\AemsReportController;
use App\Http\Controllers\Api\AemsTeamController;
use App\Http\Controllers\Api\AemsWorkingPaperController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CmsActionPlanController;
use App\Http\Controllers\Api\CmsDashboardController;
use App\Http\Controllers\Api\CmsProgressUpdateController;
use App\Http\Controllers\Api\CmsRecommendationAssignmentController;
use App\Http\Controllers\Api\CmsRecommendationController;
use App\Http\Controllers\Api\CmsValidationController;
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

    Route::get('/cms/dashboard', CmsDashboardController::class)
        ->middleware('permission:cms.dashboard.view');
    Route::get('/cms/recommendations', [CmsRecommendationController::class, 'index'])
        ->middleware('permission:cms.recommendation.view');
    Route::get('/cms/recommendations/{recommendation}', [CmsRecommendationController::class, 'show'])
        ->middleware('permission:cms.recommendation.view');
    Route::get('/cms/recommendations/{recommendation}/assignments', [CmsRecommendationAssignmentController::class, 'index'])
        ->middleware('permission:cms.recommendation.view');
    Route::post('/cms/recommendations/{recommendation}/assignments', [CmsRecommendationAssignmentController::class, 'store'])
        ->middleware('permission:cms.recommendation.assign');
    Route::post('/cms/recommendations/{recommendation}/assignments/{assignment}/end', [CmsRecommendationAssignmentController::class, 'end'])
        ->middleware('permission:cms.recommendation.assign');
    Route::get('/cms/recommendations/{recommendation}/action-plan', [CmsActionPlanController::class, 'forRecommendation'])
        ->middleware('permission:cms.action-plan.view');
    Route::post('/cms/recommendations/{recommendation}/action-plans', [CmsActionPlanController::class, 'store'])
        ->middleware('permission:cms.action-plan.create');
    Route::get('/cms/action-plans/{actionPlan}', [CmsActionPlanController::class, 'show'])
        ->middleware('permission:cms.action-plan.view');
    Route::put('/cms/action-plans/{actionPlan}/versions/{version}', [CmsActionPlanController::class, 'update'])
        ->middleware('permission:cms.action-plan.update');
    Route::post('/cms/action-plans/{actionPlan}/versions/{version}/transitions/submit', [CmsActionPlanController::class, 'submit'])
        ->middleware('permission:cms.action-plan.submit');
    Route::post('/cms/action-plans/{actionPlan}/versions/{version}/transitions/start-review', [CmsActionPlanController::class, 'startReview'])
        ->middleware('permission:cms.action-plan.review');
    Route::post('/cms/action-plans/{actionPlan}/versions/{version}/transitions/return', [CmsActionPlanController::class, 'return'])
        ->middleware('permission:cms.action-plan.return');
    Route::post('/cms/action-plans/{actionPlan}/versions/{version}/transitions/accept', [CmsActionPlanController::class, 'accept'])
        ->middleware('permission:cms.action-plan.accept');
    Route::post('/cms/action-plans/{actionPlan}/versions/{version}/revisions', [CmsActionPlanController::class, 'revise'])
        ->middleware('permission:cms.action-plan.revise');
    Route::get('/cms/recommendations/{recommendation}/progress-updates', [CmsProgressUpdateController::class, 'forRecommendation'])
        ->middleware('permission:cms.progress.view');
    Route::post('/cms/recommendations/{recommendation}/progress-updates', [CmsProgressUpdateController::class, 'store'])
        ->middleware('permission:cms.progress.create');
    Route::get('/cms/progress-updates/{progressUpdate}', [CmsProgressUpdateController::class, 'show'])
        ->middleware('permission:cms.progress.view');
    Route::put('/cms/progress-updates/{progressUpdate}/versions/{version}', [CmsProgressUpdateController::class, 'update'])
        ->middleware('permission:cms.progress.update');
    Route::post('/cms/progress-updates/{progressUpdate}/versions/{version}/transitions/submit', [CmsProgressUpdateController::class, 'submit'])
        ->middleware('permission:cms.progress.submit');
    Route::post('/cms/progress-updates/{progressUpdate}/versions/{version}/transitions/start-review', [CmsProgressUpdateController::class, 'startReview'])
        ->middleware('permission:cms.progress.review');
    Route::post('/cms/progress-updates/{progressUpdate}/versions/{version}/transitions/return', [CmsProgressUpdateController::class, 'return'])
        ->middleware('permission:cms.progress.return');
    Route::post('/cms/progress-updates/{progressUpdate}/versions/{version}/transitions/record', [CmsProgressUpdateController::class, 'record'])
        ->middleware('permission:cms.progress.record');
    Route::post('/cms/progress-updates/{progressUpdate}/versions/{version}/revisions', [CmsProgressUpdateController::class, 'revise'])
        ->middleware('permission:cms.progress.revise');
    Route::post('/cms/progress-updates/{progressUpdate}/versions/{version}/evidence', [CmsProgressUpdateController::class, 'uploadEvidence'])
        ->middleware('permission:cms.evidence.upload');
    Route::get('/cms/progress-evidence/{evidence}/download', [CmsProgressUpdateController::class, 'downloadEvidence'])
        ->middleware('permission:cms.evidence.download');
    Route::delete('/cms/progress-evidence/{evidence}', [CmsProgressUpdateController::class, 'removeEvidence'])
        ->middleware('permission:cms.evidence.remove_draft');
    Route::get('/cms/recommendations/{recommendation}/validations', [CmsValidationController::class, 'forRecommendation'])
        ->middleware('permission:cms.validation.view');
    Route::post('/cms/recommendations/{recommendation}/validations', [CmsValidationController::class, 'store'])
        ->middleware('permission:cms.validation.create');
    Route::get('/cms/validations/{validation}', [CmsValidationController::class, 'show'])
        ->middleware('permission:cms.validation.view');
    Route::get('/cms/validations/{validation}/assignments', [CmsValidationController::class, 'assignments'])
        ->middleware('permission:cms.validation.view');
    Route::post('/cms/validations/{validation}/assignments', [CmsValidationController::class, 'assign'])
        ->middleware('permission:cms.validation.assign');
    Route::post('/cms/validations/{validation}/assignments/{assignment}/end', [CmsValidationController::class, 'endAssignment'])
        ->middleware('permission:cms.validation.assign');
    Route::put('/cms/validations/{validation}/versions/{version}', [CmsValidationController::class, 'update'])
        ->middleware('permission:cms.validation.update');
    Route::post('/cms/validations/{validation}/versions/{version}/transitions/submit', [CmsValidationController::class, 'submit'])
        ->middleware('permission:cms.validation.submit');
    Route::post('/cms/validations/{validation}/versions/{version}/transitions/start-review', [CmsValidationController::class, 'startReview'])
        ->middleware('permission:cms.validation.review');
    Route::post('/cms/validations/{validation}/versions/{version}/transitions/return', [CmsValidationController::class, 'return'])
        ->middleware('permission:cms.validation.return');
    Route::post('/cms/validations/{validation}/versions/{version}/transitions/finalize', [CmsValidationController::class, 'finalize'])
        ->middleware('permission:cms.validation.finalize');
    Route::post('/cms/validations/{validation}/versions/{version}/revisions', [CmsValidationController::class, 'revise'])
        ->middleware('permission:cms.validation.revise');
    Route::post('/cms/validations/{validation}/versions/{version}/evidence', [CmsValidationController::class, 'uploadEvidence'])
        ->middleware('permission:cms.validation-evidence.upload');
    Route::get('/cms/validation-evidence/{evidence}/download', [CmsValidationController::class, 'downloadEvidence'])
        ->middleware('permission:cms.validation-evidence.download');
    Route::delete('/cms/validation-evidence/{evidence}', [CmsValidationController::class, 'removeEvidence'])
        ->middleware('permission:cms.validation-evidence.remove_draft');

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

    Route::get('/aems/engagements', [AemsEngagementController::class, 'index'])
        ->middleware('permission:aems.engagement.view');
    Route::get('/aems/dashboard', AemsDashboardController::class)
        ->middleware('permission:aems.engagement.view');
    Route::get('/aems/dashboard/export', [AemsDashboardController::class, 'export'])
        ->middleware('permission:aems.engagement.export');
    Route::get('/aems/engagements/import-options', [AemsEngagementController::class, 'importOptions'])
        ->middleware('permission:aems.engagement.create');
    Route::post('/aems/engagements/import', [AemsEngagementController::class, 'import'])
        ->middleware('permission:aems.engagement.create');
    Route::post('/aems/engagements', [AemsEngagementController::class, 'store'])
        ->middleware('permission:aems.engagement.create');
    Route::get('/aems/engagements/{engagement}/team', [AemsTeamController::class, 'show'])
        ->middleware('permission:aems.team.view');
    Route::post('/aems/engagements/{engagement}/team', [AemsTeamController::class, 'store'])
        ->middleware('permission:aems.team.assign');
    Route::put('/aems/engagements/{engagement}/team/{teamMember}', [AemsTeamController::class, 'update'])
        ->middleware('permission:aems.team.assign');
    Route::post('/aems/engagements/{engagement}/team/{teamMember}/reassign', [AemsTeamController::class, 'reassign'])
        ->middleware('permission:aems.team.reassign');
    Route::delete('/aems/engagements/{engagement}/team/{teamMember}', [AemsTeamController::class, 'destroy'])
        ->middleware('permission:aems.team.assign');
    Route::get('/aems/engagements/{engagement}/aeo', [AemsAeoController::class, 'show'])
        ->middleware('permission:aems.aeo.view');
    Route::post('/aems/engagements/{engagement}/aeo', [AemsAeoController::class, 'store'])
        ->middleware('permission:aems.aeo.prepare');
    Route::put('/aems/engagements/{engagement}/aeo/{order}', [AemsAeoController::class, 'update'])
        ->middleware('permission:aems.aeo.prepare');
    Route::post('/aems/engagements/{engagement}/aeo/{order}/transition', [AemsAeoController::class, 'transition'])
        ->middleware('permission:aems.aeo.view');
    Route::post('/aems/engagements/{engagement}/aeo/{order}/revise', [AemsAeoController::class, 'revise'])
        ->middleware('permission:aems.aeo.revise');
    Route::get('/aems/engagements/{engagement}/aeo/{order}/pdf', [AemsAeoController::class, 'pdf'])
        ->middleware('permission:aems.aeo.view');
    Route::get('/aems/engagements/{engagement}/aep', [AemsAepController::class, 'show'])
        ->middleware('permission:aems.aep.view');
    Route::post('/aems/engagements/{engagement}/aep', [AemsAepController::class, 'store'])
        ->middleware('permission:aems.aep.create');
    Route::put('/aems/engagements/{engagement}/aep/{plan}', [AemsAepController::class, 'update'])
        ->middleware('permission:aems.aep.create');
    Route::post('/aems/engagements/{engagement}/aep/{plan}/transition', [AemsAepController::class, 'transition'])
        ->middleware('permission:aems.aep.view');
    Route::post('/aems/engagements/{engagement}/aep/{plan}/revise', [AemsAepController::class, 'revise'])
        ->middleware('permission:aems.aep.revise');
    Route::get('/aems/engagements/{engagement}/programs', [AemsProgramController::class, 'index'])
        ->middleware('permission:aems.program.view');
    Route::post('/aems/engagements/{engagement}/programs', [AemsProgramController::class, 'store'])
        ->middleware('permission:aems.program.manage');
    Route::put('/aems/engagements/{engagement}/programs/{program}', [AemsProgramController::class, 'update'])
        ->middleware('permission:aems.program.manage');
    Route::post('/aems/engagements/{engagement}/programs/{program}/transition', [AemsProgramController::class, 'transition'])
        ->middleware('permission:aems.program.view');
    Route::post('/aems/engagements/{engagement}/programs/{program}/revise', [AemsProgramController::class, 'revise'])
        ->middleware('permission:aems.program.approve');
    Route::post('/aems/engagements/{engagement}/programs/{program}/procedures', [AemsProgramController::class, 'storeProcedure'])
        ->middleware('permission:aems.program.manage');
    Route::put('/aems/engagements/{engagement}/programs/{program}/procedures/{procedure}', [AemsProgramController::class, 'updateProcedure'])
        ->middleware('permission:aems.program.manage');
    Route::delete('/aems/engagements/{engagement}/programs/{program}/procedures/{procedure}', [AemsProgramController::class, 'destroyProcedure'])
        ->middleware('permission:aems.program.manage');
    Route::post('/aems/engagements/{engagement}/programs/{program}/procedures/{procedure}/progress', [AemsProgramController::class, 'progressProcedure'])
        ->middleware('permission:aems.program.manage');
    Route::post('/aems/engagements/{engagement}/programs/{program}/procedures/{procedure}/review', [AemsProgramController::class, 'reviewProcedure'])
        ->middleware('permission:aems.program.review');
    Route::get('/aems/engagements/{engagement}/working-papers', [AemsWorkingPaperController::class, 'index'])
        ->middleware('permission:aems.working-paper.view');
    Route::post('/aems/engagements/{engagement}/working-papers', [AemsWorkingPaperController::class, 'store'])
        ->middleware('permission:aems.working-paper.create');
    Route::put('/aems/engagements/{engagement}/working-papers/{paper}', [AemsWorkingPaperController::class, 'update'])
        ->middleware('permission:aems.working-paper.create');
    Route::post('/aems/engagements/{engagement}/working-papers/{paper}/transition', [AemsWorkingPaperController::class, 'transition'])
        ->middleware('permission:aems.working-paper.view');
    Route::post('/aems/engagements/{engagement}/working-papers/{paper}/revise', [AemsWorkingPaperController::class, 'revise'])
        ->middleware('permission:aems.working-paper.create');
    Route::post('/aems/engagements/{engagement}/evidence', [AemsEvidenceController::class, 'store'])
        ->middleware('permission:aems.evidence.upload');
    Route::post('/aems/engagements/{engagement}/evidence/{evidence}/revisions', [AemsEvidenceController::class, 'replace'])
        ->middleware('permission:aems.evidence.upload');
    Route::post('/aems/engagements/{engagement}/evidence/{evidence}/transition', [AemsEvidenceController::class, 'transition'])
        ->middleware('permission:aems.evidence.view');
    Route::get('/aems/engagements/{engagement}/evidence/{evidence}/download', [AemsEvidenceController::class, 'download'])
        ->middleware('permission:aems.evidence.view');
    Route::get('/aems/findings-workspaces', [AemsFindingController::class, 'engagements'])
        ->middleware('permission:aems.finding.view');
    Route::get('/aems/engagements/{engagement}/findings-workspace', [AemsFindingController::class, 'index'])
        ->middleware('permission:aems.finding.view');
    Route::post('/aems/engagements/{engagement}/issues', [AemsIssueController::class, 'store'])
        ->middleware('permission:aems.issue.create');
    Route::put('/aems/engagements/{engagement}/issues/{issue}', [AemsIssueController::class, 'update'])
        ->middleware('permission:aems.issue.create');
    Route::post('/aems/engagements/{engagement}/issues/{issue}/transition', [AemsIssueController::class, 'transition'])
        ->middleware('permission:aems.issue.view');
    Route::post('/aems/engagements/{engagement}/findings', [AemsFindingController::class, 'store'])
        ->middleware('permission:aems.finding.create');
    Route::put('/aems/engagements/{engagement}/findings/{finding}', [AemsFindingController::class, 'update'])
        ->middleware('permission:aems.finding.create');
    Route::post('/aems/engagements/{engagement}/findings/{finding}/transition', [AemsFindingController::class, 'transition'])
        ->middleware('permission:aems.finding.view');
    Route::post('/aems/engagements/{engagement}/findings/{finding}/recommendations', [AemsFindingController::class, 'saveRecommendation'])
        ->middleware('permission:aems.finding.create');
    Route::put('/aems/engagements/{engagement}/findings/{finding}/recommendations/{recommendation}', [AemsFindingController::class, 'saveRecommendation'])
        ->middleware('permission:aems.finding.create');
    Route::delete('/aems/engagements/{engagement}/findings/{finding}/recommendations/{recommendation}', [AemsFindingController::class, 'deleteRecommendation'])
        ->middleware('permission:aems.finding.create');
    Route::post('/aems/engagements/{engagement}/findings/{finding}/responses', [AemsFindingController::class, 'createResponse'])
        ->middleware('permission:aems.management-response.submit');
    Route::put('/aems/engagements/{engagement}/findings/{finding}/responses/{response}', [AemsFindingController::class, 'updateResponse'])
        ->middleware('permission:aems.management-response.submit');
    Route::post('/aems/engagements/{engagement}/findings/{finding}/responses/{response}/transition', [AemsFindingController::class, 'transitionResponse'])
        ->middleware('permission:aems.management-response.view');
    Route::post('/aems/engagements/{engagement}/findings/{finding}/responses/{response}/revisions', [AemsFindingController::class, 'reviseResponse'])
        ->middleware('permission:aems.management-response.submit');
    Route::post('/aems/engagements/{engagement}/findings/{finding}/responses/{response}/attachments', [AemsFindingController::class, 'uploadResponseAttachment'])
        ->middleware('permission:aems.management-response.submit');
    Route::post('/aems/engagements/{engagement}/findings/{finding}/responses/{response}/rejoinders', [AemsFindingController::class, 'saveRejoinder'])
        ->middleware('permission:aems.rejoinder.create');
    Route::put('/aems/engagements/{engagement}/findings/{finding}/responses/{response}/rejoinders/{rejoinder}', [AemsFindingController::class, 'saveRejoinder'])
        ->middleware('permission:aems.rejoinder.create');
    Route::post('/aems/engagements/{engagement}/findings/{finding}/responses/{response}/rejoinders/{rejoinder}/finalize', [AemsFindingController::class, 'finalizeRejoinder'])
        ->middleware('permission:aems.rejoinder.finalize');
    Route::post('/aems/engagements/{engagement}/findings/{finding}/responses/{response}/rejoinders/{rejoinder}/attachments', [AemsFindingController::class, 'uploadRejoinderAttachment'])
        ->middleware('permission:aems.rejoinder.create');
    Route::get('/aems/engagements/{engagement}/findings/{finding}/dialogue-attachments/{attachment}/download', [AemsFindingController::class, 'downloadAttachment'])
        ->middleware('permission:aems.finding.view');
    Route::get('/aems/exit-conference-workspaces', [AemsExitConferenceController::class, 'engagements'])
        ->middleware('permission:aems.conference.view');
    Route::get('/aems/engagements/{engagement}/exit-conferences', [AemsExitConferenceController::class, 'index'])
        ->middleware('permission:aems.conference.view');
    Route::post('/aems/engagements/{engagement}/exit-conferences', [AemsExitConferenceController::class, 'store'])
        ->middleware('permission:aems.conference.manage');
    Route::put('/aems/engagements/{engagement}/exit-conferences/{conference}', [AemsExitConferenceController::class, 'update'])
        ->middleware('permission:aems.conference.manage');
    Route::post('/aems/engagements/{engagement}/exit-conferences/{conference}/complete', [AemsExitConferenceController::class, 'complete'])
        ->middleware('permission:aems.conference.manage');
    Route::post('/aems/engagements/{engagement}/exit-conferences/{conference}/transition', [AemsExitConferenceController::class, 'transition'])
        ->middleware('permission:aems.conference.manage');
    Route::post('/aems/engagements/{engagement}/exit-conferences/{conference}/attachments', [AemsExitConferenceController::class, 'uploadAttachment'])
        ->middleware('permission:aems.conference.manage');
    Route::post('/aems/engagements/{engagement}/exit-conferences/{conference}/acknowledgements', [AemsExitConferenceController::class, 'acknowledge'])
        ->middleware('permission:aems.conference.acknowledge');
    Route::get('/aems/engagements/{engagement}/exit-conferences/{conference}/attachments/{attachment}/download', [AemsExitConferenceController::class, 'downloadAttachment'])
        ->middleware('permission:aems.conference.view');
    Route::get('/aems/report-workspaces', [AemsReportController::class, 'engagements']);
    Route::get('/aems/engagements/{engagement}/reports', [AemsReportController::class, 'index']);
    Route::post('/aems/engagements/{engagement}/reports', [AemsReportController::class, 'store'])
        ->middleware('permission:aems.report.create');
    Route::post('/aems/engagements/{engagement}/reports/{report}/versions', [AemsReportController::class, 'revise'])
        ->middleware('permission:aems.report.create');
    Route::post('/aems/engagements/{engagement}/reports/{report}/final', [AemsReportController::class, 'createFinal'])
        ->middleware('permission:aems.report.create');
    Route::post('/aems/engagements/{engagement}/reports/{report}/transition', [AemsReportController::class, 'transition'])
        ->middleware('permission:aems.report.view');
    Route::post('/aems/engagements/{engagement}/reports/{report}/cms-transfer', [AemsReportController::class, 'transferRecommendations'])
        ->middleware('permission:aems.report.issue');
    Route::get('/aems/engagements/{engagement}/reports/{report}/versions/{version}/download', [AemsReportController::class, 'download']);
    Route::get('/aems/engagements/{engagement}/lifecycle', [AemsEngagementLifecycleController::class, 'show'])
        ->middleware('permission:aems.engagement.view');
    Route::post('/aems/engagements/{engagement}/transitions/{action}', [AemsEngagementLifecycleController::class, 'transition'])
        ->middleware('permission:aems.engagement.view');
    Route::get('/aems/entry-conference-workspaces', [AemsEntryConferenceController::class, 'engagements'])
        ->middleware('permission:aems.entry-conference.view');
    Route::get('/aems/engagements/{engagement}/entry-conference', [AemsEntryConferenceController::class, 'show'])
        ->middleware('permission:aems.entry-conference.view');
    Route::post('/aems/engagements/{engagement}/entry-conference', [AemsEntryConferenceController::class, 'store'])
        ->middleware('permission:aems.entry-conference.manage');
    Route::put('/aems/engagements/{engagement}/entry-conference/{conference}', [AemsEntryConferenceController::class, 'update'])
        ->middleware('permission:aems.entry-conference.manage');
    Route::post('/aems/engagements/{engagement}/entry-conference/{conference}/transitions/{action}', [AemsEntryConferenceController::class, 'transition'])
        ->middleware('permission:aems.entry-conference.view');
    Route::post('/aems/engagements/{engagement}/entry-conference/{conference}/acknowledgements', [AemsEntryConferenceController::class, 'acknowledge'])
        ->middleware('permission:aems.entry-conference.acknowledge');
    Route::post('/aems/engagements/{engagement}/entry-conference/{conference}/attachments', [AemsEntryConferenceController::class, 'uploadAttachment'])
        ->middleware('permission:aems.entry-conference.manage');
    Route::get('/aems/engagements/{engagement}/entry-conference/{conference}/attachments/{attachment}/download', [AemsEntryConferenceController::class, 'downloadAttachment'])
        ->middleware('permission:aems.entry-conference.view');
    Route::get('/aems/engagements/{engagement}/completion-assessments', [AemsCompletionAssessmentController::class, 'index'])
        ->middleware('permission:aems.completion-assessment.view');
    Route::post('/aems/engagements/{engagement}/completion-assessments', [AemsCompletionAssessmentController::class, 'store'])
        ->middleware('permission:aems.completion-assessment.create');
    Route::put('/aems/engagements/{engagement}/completion-assessments/{assessment}', [AemsCompletionAssessmentController::class, 'update'])
        ->middleware('permission:aems.completion-assessment.update');
    Route::post('/aems/engagements/{engagement}/completion-assessments/{assessment}/transitions/{action}', [AemsCompletionAssessmentController::class, 'transition'])
        ->middleware('permission:aems.completion-assessment.view');
    Route::post('/aems/engagements/{engagement}/completion-assessments/{assessment}/items/{item}/accept-blocker', [AemsCompletionAssessmentController::class, 'acceptBlocker'])
        ->middleware('permission:aems.completion-assessment.approve');
    Route::post('/aems/engagements/{engagement}/completion-assessments/{assessment}/revisions', [AemsCompletionAssessmentController::class, 'revise'])
        ->middleware('permission:aems.completion-assessment.create');
    Route::get('/aems/engagements/{engagement}/closure', [AemsClosureController::class, 'show'])
        ->middleware('permission:aems.closure.view');
    Route::post('/aems/engagements/{engagement}/closure', [AemsClosureController::class, 'store'])
        ->middleware('permission:aems.closure.create');
    Route::put('/aems/engagements/{engagement}/closures/{closure}', [AemsClosureController::class, 'update'])
        ->middleware('permission:aems.closure.update');
    Route::get('/aems/engagements/{engagement}/closures/{closure}/checklist', [AemsClosureController::class, 'show'])
        ->middleware('permission:aems.closure.view');
    Route::post('/aems/engagements/{engagement}/closures/{closure}/refresh-checklist', [AemsClosureController::class, 'refreshChecklist'])
        ->middleware('permission:aems.closure.update');
    Route::post('/aems/engagements/{engagement}/closures/{closure}/transitions/{action}', [AemsClosureController::class, 'transition'])
        ->middleware('permission:aems.closure.view');
    Route::get('/aems/engagements/{engagement}/document-index', [AemsDocumentIndexController::class, 'show'])
        ->middleware('permission:aems.document-index.view');
    Route::post('/aems/engagements/{engagement}/document-index/refresh', [AemsDocumentIndexController::class, 'refresh'])
        ->middleware('permission:aems.document-index.manage');
    Route::post('/aems/engagements/{engagement}/document-index', [AemsDocumentIndexController::class, 'store'])
        ->middleware('permission:aems.document-index.manage');
    Route::post('/aems/engagements/{engagement}/document-index/{item}/exclude', [AemsDocumentIndexController::class, 'exclude'])
        ->middleware('permission:aems.document-index.finalize');
    Route::get('/aems/engagements/{engagement}/document-index/export', [AemsDocumentIndexController::class, 'export'])
        ->middleware('permission:aems.document-index.view');
    Route::put('/aems/engagements/{engagement}/retention', [AemsClosureController::class, 'saveRetention'])
        ->middleware('permission:aems.retention.manage');
    Route::post('/aems/engagements/{engagement}/retention/{retention}/approve', [AemsClosureController::class, 'approveRetention'])
        ->middleware('permission:aems.retention.approve');
    Route::post('/aems/engagements/{engagement}/lessons-learned', [AemsClosureController::class, 'addLesson'])
        ->middleware('permission:aems.closure.update');
    Route::post('/aems/engagements/{engagement}/recommendations/{recommendation}/cms-exclusion', [AemsClosureController::class, 'excludeRecommendation'])
        ->middleware('permission:aems.closure.approve');
    Route::get('/aems/engagements/{engagement}/reopen-requests', [AemsReopenController::class, 'index'])
        ->middleware('permission:aems.closure.view');
    Route::post('/aems/engagements/{engagement}/reopen-requests', [AemsReopenController::class, 'store'])
        ->middleware('permission:aems.engagement.reopen_request');
    Route::post('/aems/engagements/{engagement}/reopen-requests/{reopen}/transitions/{action}', [AemsReopenController::class, 'transition'])
        ->middleware('permission:aems.closure.view');
    Route::get('/aems/engagements/{engagement}', [AemsEngagementController::class, 'show'])
        ->middleware('permission:aems.engagement.view');
    Route::put('/aems/engagements/{engagement}', [AemsEngagementController::class, 'update'])
        ->middleware('permission:aems.engagement.update');
    Route::delete('/aems/engagements/{engagement}', [AemsEngagementController::class, 'destroy'])
        ->middleware('permission:aems.engagement.archive');
    Route::post('/aems/engagements/{engagement}/restore', [AemsEngagementController::class, 'restore'])
        ->middleware('permission:aems.engagement.restore');

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
