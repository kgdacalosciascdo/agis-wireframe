<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CoreRegistryController;
use App\Http\Controllers\Api\DemoAccountController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\OfficeController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\ResetDemoDataController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/health', HealthController::class);
Route::get('/demo-accounts', DemoAccountController::class)->middleware('throttle:30,1');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);

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
    Route::post('/users', [UserController::class, 'store'])
        ->middleware('permission:users.create');
    Route::put('/users/{user}', [UserController::class, 'update'])
        ->middleware('permission:users.update');
    Route::delete('/users/{user}', [UserController::class, 'deactivate'])
        ->middleware('permission:users.deactivate');
    Route::post('/users/{user}/restore', [UserController::class, 'restore'])
        ->middleware('permission:users.restore');
    Route::put('/users/{user}/password', [UserController::class, 'resetPassword'])
        ->middleware('permission:users.reset_password');

    Route::get('/roles', [CoreRegistryController::class, 'roles'])
        ->middleware('permission:roles.view');
    Route::put('/roles/{role}', [CoreRegistryController::class, 'updateRole'])
        ->middleware('permission:roles.update');
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
    Route::get('/activity-logs', [CoreRegistryController::class, 'activityLogs'])
        ->middleware('permission:activity_logs.view');

    Route::post('/demo/reset', ResetDemoDataController::class)
        ->middleware('permission:system_configuration.manage');
});
