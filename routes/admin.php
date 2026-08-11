<?php

use App\Http\Admin\V1\Controllers\Access\AccessControlController;
use App\Http\Admin\V1\Controllers\Admins\AdminManagementController;
use App\Http\Admin\V1\Controllers\Admins\ImpersonationController;
use App\Http\Admin\V1\Controllers\Audit\AuditController;
use App\Http\Admin\V1\Controllers\Complexes\ComplexManagementController;
use App\Http\Admin\V1\Controllers\Auth\AdminAuthController;
use App\Http\Admin\V1\Controllers\Devices\AdminDeviceCommController;
use App\Http\Admin\V1\Controllers\Devices\AdminDeviceController;
use App\Http\Admin\V1\Controllers\Devices\AdminDeviceRosterController;
use App\Http\Admin\V1\Controllers\Orders\AdminOrderController;
use App\Http\Admin\V1\Controllers\Payments\AdminPaymentLogController;
use App\Http\Admin\V1\Controllers\Refunds\AdminRefundController;
use App\Http\Admin\V1\Controllers\Residents\AdminResidentController;
use App\Http\Admin\V1\Controllers\Settings\SettingsController;
use App\Http\Admin\V1\Controllers\Settings\SettingsTestController;
use App\Http\Admin\V1\Controllers\Settings\SystemHealthController;
use App\Http\Admin\V1\Controllers\Subscriptions\AdminSubscriptionController;
use App\Http\Admin\V1\Controllers\Visitor\AdminVisitorLinkController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Admin API — prefix /admin/v1 (group "api", name prefix "admin.")
|--------------------------------------------------------------------------
| The auth:auth.admin JWT guard lands with the Auth module (batch 07). Login + 2FA
| verify are public (security: []); everything else requires a verified admin token,
| and state-mutating re-auth routes additionally require tfa_verified (admin.tfa).
*/

// ---- Public admin auth (batch 07) ----
Route::middleware('throttle:public')->group(function (): void {
    Route::post('auth/login', [AdminAuthController::class, 'login'])->name('adminLogin');
    Route::post('auth/2fa/verify', [AdminAuthController::class, 'verify2fa'])->name('adminVerify2fa');
});

// ---- Authenticated admin (admin JWT guard) ----
Route::middleware(['auth:admin', 'throttle:admin'])->group(function (): void {
    // Admin auth session (batch 07)
    Route::post('auth/logout', [AdminAuthController::class, 'logout'])->name('adminLogout');
    Route::get('auth/me', [AdminAuthController::class, 'me'])->name('adminMe');
    Route::post('auth/recovery-codes', [AdminAuthController::class, 'regenerateRecoveryCodes'])
        ->middleware('admin.tfa')
        ->name('regenerateRecoveryCodes');
    Route::post('auth/stop-impersonation', [ImpersonationController::class, 'stop'])->name('adminStopImpersonation');

    // Admins & RBAC (super-admin tier; permission-gated in the controllers)
    Route::get('admins', [AdminManagementController::class, 'index'])->name('adminListAdmins');
    Route::post('admins', [AdminManagementController::class, 'store'])->name('adminCreateAdmin');
    Route::patch('admins/{adminId}', [AdminManagementController::class, 'update'])->whereNumber('adminId')->name('adminUpdateAdmin');
    Route::delete('admins/{adminId}', [AdminManagementController::class, 'destroy'])->whereNumber('adminId')->name('adminDeactivateAdmin');
    Route::post('admins/{adminId}/impersonate', [ImpersonationController::class, 'start'])->whereNumber('adminId')->name('adminImpersonate');
    Route::get('audit', [AuditController::class, 'index'])->name('adminListAudit');

    // Dynamic access control — roles, permission catalog, per-admin overrides (access.manage)
    Route::get('access/roles', [AccessControlController::class, 'roles'])->name('adminListRoles');
    Route::patch('access/roles/{role}', [AccessControlController::class, 'updateRole'])->name('adminUpdateRolePermissions');
    Route::get('access/permissions', [AccessControlController::class, 'permissions'])->name('adminListAllPermissions');
    Route::get('admins/{adminId}/permissions', [AccessControlController::class, 'userPermissions'])->whereNumber('adminId')->name('adminGetUserPermissions');
    Route::post('admins/{adminId}/permissions/grant', [AccessControlController::class, 'grant'])->whereNumber('adminId')->name('adminGrantPermission');
    Route::post('admins/{adminId}/permissions/revoke', [AccessControlController::class, 'revoke'])->whereNumber('adminId')->name('adminRevokePermission');
    Route::post('admins/{adminId}/permissions/reset', [AccessControlController::class, 'reset'])->whereNumber('adminId')->name('adminResetPermissions');

    // Residential complexes — the root entity (complexes.view / complexes.manage)
    Route::get('complexes', [ComplexManagementController::class, 'index'])->name('adminListComplexes');
    Route::post('complexes', [ComplexManagementController::class, 'store'])->name('adminCreateComplex');
    Route::get('complexes/{complexId}', [ComplexManagementController::class, 'show'])->whereNumber('complexId')->name('adminGetComplex');
    Route::patch('complexes/{complexId}', [ComplexManagementController::class, 'update'])->whereNumber('complexId')->name('adminUpdateComplex');
    Route::delete('complexes/{complexId}', [ComplexManagementController::class, 'destroy'])->whereNumber('complexId')->name('adminDeleteComplex');
    Route::post('complexes/{complexId}/managers', [ComplexManagementController::class, 'assignManager'])->whereNumber('complexId')->name('adminAssignComplexManager');
    Route::delete('complexes/{complexId}/managers/{adminId}', [ComplexManagementController::class, 'unassignManager'])->whereNumber('complexId')->whereNumber('adminId')->name('adminUnassignComplexManager');

    // System Settings module (DB-driven, grouped, encrypted secrets, audited, versioned) + live System health.
    // Static paths are declared before the {group} param routes so they are not captured by it.
    Route::get('settings', [SettingsController::class, 'index'])->name('adminGetSettings');
    Route::get('settings/export', [SettingsController::class, 'export'])->name('adminExportSettings');
    Route::post('settings/import', [SettingsController::class, 'import'])->name('adminImportSettings');
    Route::get('settings/versions', [SettingsController::class, 'versions'])->name('adminSettingsVersions');
    Route::get('settings/versions/compare', [SettingsController::class, 'compareVersions'])->name('adminCompareSettingsVersions');
    Route::post('settings/versions/{id}/restore', [SettingsController::class, 'restoreVersion'])->whereNumber('id')->name('adminRestoreSettingsVersion');
    Route::post('settings/email/send-test', [SettingsTestController::class, 'sendTestEmail'])->name('adminSendTestEmail');
    Route::post('settings/email/send-test-otp', [SettingsTestController::class, 'sendTestOtp'])->name('adminSendTestOtp');
    Route::post('settings/sms/send-test', [SettingsTestController::class, 'sendTestSms'])->name('adminSendTestSms');
    Route::post('settings/sms/send-test-otp', [SettingsTestController::class, 'sendTestSmsOtp'])->name('adminSendTestSmsOtp');
    Route::get('settings/traccar/status', [SettingsTestController::class, 'traccarStatus'])->name('adminTraccarStatus');
    Route::post('settings/security/force-logout', [SettingsTestController::class, 'forceLogout'])->name('adminForceLogout');
    Route::post('settings/payments/test-create', [SettingsTestController::class, 'testCreatePayment'])->name('adminTestCreatePayment');
    Route::post('settings/{group}/test', [SettingsTestController::class, 'test'])->name('adminTestSetting');
    Route::patch('settings/{group}', [SettingsController::class, 'updateGroup'])->name('adminUpdateSettings');
    Route::get('system/health', [SystemHealthController::class, 'index'])->name('adminSystemHealth');

    // Orders & Refunds (batch 05)
    Route::get('orders', [AdminOrderController::class, 'index'])->name('adminListOrders');
    Route::get('orders/{orderId}', [AdminOrderController::class, 'show'])->whereNumber('orderId')->name('adminGetOrder');
    Route::post('orders/{orderId}/refund', [AdminOrderController::class, 'refund'])->whereNumber('orderId')->name('adminRefundOrder');
    Route::post('orders/{orderId}/recheck', [AdminOrderController::class, 'recheck'])->whereNumber('orderId')->name('adminRecheckOrder');
    Route::get('payment-logs', [AdminPaymentLogController::class, 'index'])->name('adminListPaymentLogs');
    Route::get('payments/stats', [AdminPaymentLogController::class, 'stats'])->name('adminPaymentStats');
    Route::get('refunds', [AdminRefundController::class, 'index'])->name('adminListRefunds');

    // Subscriptions (batch 06)
    Route::get('subscriptions', [AdminSubscriptionController::class, 'index'])->name('adminListSubscriptions');

    // Residents directory (residents.view; complex_manager scoped) + account removal (residents.delete)
    Route::get('residents', [AdminResidentController::class, 'index'])->name('adminListResidents');
    Route::delete('residents/{userId}', [AdminResidentController::class, 'destroy'])->whereNumber('userId')->name('adminDeleteResident');

    // Devices (batch 08)
    Route::get('devices', [AdminDeviceController::class, 'index'])->name('adminListDevices');
    Route::post('devices', [AdminDeviceController::class, 'store'])->name('adminCreateDevice');
    Route::post('devices/reconcile', [AdminDeviceController::class, 'reconcile'])->name('adminReconcileDevice');
    Route::get('devices/{deviceId}', [AdminDeviceController::class, 'show'])->whereNumber('deviceId')->name('adminGetDevice');
    Route::patch('devices/{deviceId}', [AdminDeviceController::class, 'update'])->whereNumber('deviceId')->name('adminUpdateDevice');
    Route::post('devices/{deviceId}/image', [AdminDeviceController::class, 'uploadImage'])->whereNumber('deviceId')->name('adminUploadDeviceImage');
    Route::delete('devices/{deviceId}/image', [AdminDeviceController::class, 'deleteImage'])->whereNumber('deviceId')->name('adminDeleteDeviceImage');
    Route::delete('devices/{deviceId}', [AdminDeviceController::class, 'decommission'])->whereNumber('deviceId')->name('adminDecommissionDevice');
    Route::post('devices/{deviceId}/disable', [AdminDeviceController::class, 'disable'])->whereNumber('deviceId')->name('adminDisableDevice');
    Route::post('devices/{deviceId}/enable', [AdminDeviceController::class, 'enable'])->whereNumber('deviceId')->name('adminEnableDevice');
    Route::post('devices/{deviceId}/transfer', [AdminDeviceController::class, 'transfer'])->whereNumber('deviceId')->name('adminTransferDevice');

    // Barrier / resident assignment (Roster)
    Route::post('devices/{deviceId}/assign', [AdminDeviceRosterController::class, 'assignOwner'])->whereNumber('deviceId')->name('adminAssignDevice');
    Route::post('devices/{deviceId}/users', [AdminDeviceRosterController::class, 'addUser'])->whereNumber('deviceId')->name('adminAddDeviceUser');
    Route::delete('devices/{deviceId}/users/{userId}', [AdminDeviceRosterController::class, 'removeUser'])->whereNumber('deviceId')->whereNumber('userId')->name('adminRemoveDeviceUser');
    // Grant entitlement without a payment (subscriptions.manage)
    Route::post('devices/{deviceId}/users/{userId}/subscription', [AdminDeviceRosterController::class, 'grantSubscription'])->whereNumber('deviceId')->whereNumber('userId')->name('adminGrantSubscription');

    // DeviceComm core (batch 09-A)
    Route::get('devices/{deviceId}/commands', [AdminDeviceCommController::class, 'commands'])->whereNumber('deviceId')->name('adminDeviceCommands');
    Route::get('devices/{deviceId}/whitelist-queue', [AdminDeviceCommController::class, 'whitelistQueue'])->whereNumber('deviceId')->name('adminWhitelistQueue');
    Route::get('devices/{deviceId}/diagnostics', [AdminDeviceCommController::class, 'diagnostics'])->whereNumber('deviceId')->name('adminDeviceDiagnostics');
    Route::post('devices/{deviceId}/whitelist/resync', [AdminDeviceCommController::class, 'resync'])->whereNumber('deviceId')->name('adminResyncWhitelist');

    // Relay open/close test through the DeviceComm pipeline (Phase 4)
    Route::post('devices/{deviceId}/relay', [AdminDeviceCommController::class, 'relay'])->whereNumber('deviceId')->name('adminRelayTest');

    // Visitor links — shareable guest access (visitor_links.view / visitor_links.manage; complex-scoped).
    Route::get('visitor-links', [AdminVisitorLinkController::class, 'index'])->name('adminListVisitorLinks');
    Route::get('visitor-links/{id}/usages', [AdminVisitorLinkController::class, 'usages'])->whereNumber('id')->name('adminVisitorLinkUsages');
    Route::post('devices/{deviceId}/visitor-links', [AdminVisitorLinkController::class, 'store'])->whereNumber('deviceId')->name('adminCreateVisitorLink');
    Route::post('visitor-links/{id}/revoke', [AdminVisitorLinkController::class, 'revoke'])->whereNumber('id')->name('adminRevokeVisitorLink');
});
