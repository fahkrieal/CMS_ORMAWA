<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\OrganizationController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\ActivityController;
use App\Http\Controllers\Api\PostController;
use App\Http\Controllers\Api\MediaController;
use App\Http\Controllers\Api\AnnouncementController;
use App\Http\Controllers\Api\MemberController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\TagController;
use App\Http\Controllers\CommitteeController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\PublicController;

// Public Routes (Accessible without Authentication)
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:6,1');
Route::post('/mfa/verify', [AuthController::class, 'verifyMfa'])->middleware('throttle:5,1');
Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:5,1');
Route::post('/reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:5,1');
Route::post('/register/organization', [\App\Http\Controllers\Api\OrganizationRegistrationController::class, 'store'])->middleware('throttle:10,1');
Route::post('/organization-registrations', [\App\Http\Controllers\Api\OrganizationRegistrationController::class, 'store'])->middleware('throttle:10,1');

Route::prefix('public')->group(function () {
    Route::get('/home', [PublicController::class, 'home']);
    Route::get('/organizations', [PublicController::class, 'organizations']);
    Route::get('/articles', [PublicController::class, 'globalArticles']);
    Route::get('/categories', [PublicController::class, 'globalCategories']);
    Route::get('/agenda', [PublicController::class, 'globalAgenda']);
    Route::get('/announcements', [PublicController::class, 'globalAnnouncements']);
    Route::get('/organizations/{slug}', [PublicController::class, 'organization']);
    Route::get('/organizations/{slug}/articles', [PublicController::class, 'articles']);
    Route::get('/organizations/{slug}/articles/{articleSlug}', [PublicController::class, 'article']);
    Route::get('/organizations/{slug}/agenda', [PublicController::class, 'agenda']);
    Route::get('/organizations/{slug}/agenda/{id}', [PublicController::class, 'agendaDetail']);
    Route::get('/organizations/{slug}/announcements', [PublicController::class, 'announcements']);
    Route::get('/organizations/{slug}/gallery', [PublicController::class, 'gallery']);
    Route::get('/organizations/{slug}/documents', [PublicController::class, 'documents']);
    Route::get('/organizations/{slug}/documents/{id}/download', [PublicController::class, 'downloadDocument']);
    Route::get('/organizations/{slug}/structure', [PublicController::class, 'structure']);
    Route::get('/platform-settings', [PublicController::class, 'platformSettings']);
    Route::get('/sitemap', [PublicController::class, 'sitemap']);
});

    // Protected Routes 
    Route::middleware(['auth:sanctum', 'active.org'])->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::get('/profile', [AuthController::class, 'me']);
    Route::patch('/profile', [AuthController::class, 'updateProfile']);
    Route::post('/profile', [AuthController::class, 'updateProfile']);
    Route::put('/password', [AuthController::class, 'updatePassword'])->middleware('throttle:6,1');
    Route::patch('/password', [AuthController::class, 'updatePassword'])->middleware('throttle:6,1');

    // MFA Management
    Route::post('/mfa/setup', [AuthController::class, 'setupMfa']);
    Route::post('/mfa/confirm', [AuthController::class, 'confirmMfa'])->middleware('throttle:5,1');
    Route::post('/mfa/disable', [AuthController::class, 'disableMfa'])->middleware('throttle:5,1');
    Route::post('/mfa/recovery-codes', [AuthController::class, 'regenerateRecoveryCodes'])->middleware('throttle:5,1');

    // API Media Library Management
    Route::get('/media', [MediaController::class, 'index']);
    Route::post('/upload-image', [MediaController::class, 'upload']);
    Route::delete('/media/{id}', [MediaController::class, 'destroy']);
    Route::post('/logout', [AuthController::class, 'logout']);

    // Super Admin Dashboard Summary
    Route::get('/super-admin/dashboard', [\App\Http\Controllers\Api\DashboardController::class, 'superAdminSummary']);
    // Organization Admin Dashboard Summary
    Route::get('/organization/dashboard', [\App\Http\Controllers\Api\DashboardController::class, 'organizationSummary'])->name('api.organization.dashboard');

    // Route Manajemen Organisasi
    Route::post('/organizations/{organization}/activate', [OrganizationController::class, 'activate']);
    Route::post('/organizations/{organization}/deactivate', [OrganizationController::class, 'deactivate']);
    
    // Period Management (Organization Periods)
    Route::get('/organization-periods', [\App\Http\Controllers\Api\OrganizationPeriodController::class, 'globalIndex']);
    Route::get('/organizations/{organization}/periods', [\App\Http\Controllers\Api\OrganizationPeriodController::class, 'index']);
    Route::post('/organizations/{organization}/periods', [\App\Http\Controllers\Api\OrganizationPeriodController::class, 'store']);
    Route::get('/organization-periods/{period}', [\App\Http\Controllers\Api\OrganizationPeriodController::class, 'show']);
    Route::put('/organization-periods/{period}', [\App\Http\Controllers\Api\OrganizationPeriodController::class, 'update']);
    Route::post('/organization-periods/{period}/approve', [\App\Http\Controllers\Api\OrganizationPeriodController::class, 'approve']);
    Route::post('/organization-periods/{period}/reject', [\App\Http\Controllers\Api\OrganizationPeriodController::class, 'reject']);
    Route::post('/organization-periods/{period}/activate', [\App\Http\Controllers\Api\OrganizationPeriodController::class, 'activate']);
    Route::post('/organization-periods/{period}/deactivate', [\App\Http\Controllers\Api\OrganizationPeriodController::class, 'deactivate']);

    Route::post('/users/{id}/activate', [UserController::class, 'activate']);
    Route::post('/users/{id}/deactivate', [UserController::class, 'deactivate']);
    Route::post('/users/{id}/reset-password', [UserController::class, 'resetPassword']);

    Route::post('/organizations/{id}/upload-logo', [OrganizationController::class, 'uploadLogo'])->name('api.organizations.upload.logo');
    Route::post('/organizations/{id}/upload-hero', [OrganizationController::class, 'uploadHero'])->name('api.organizations.upload.hero');
    Route::delete('/organizations/{id}/hero', [OrganizationController::class, 'removeHero'])->name('api.organizations.remove.hero');

    Route::apiResource('organizations', OrganizationController::class)->names([
        'index'   => 'api.organizations.index',
        'store'   => 'api.organizations.store',
        'show'    => 'api.organizations.show',
        'update'  => 'api.organizations.update',
        'destroy' => 'api.organizations.destroy',
    ]);
    Route::apiResource('users', UserController::class)->names([
        'index'   => 'api.users.index',
        'store'   => 'api.users.store',
        'show'    => 'api.users.show',
        'update'  => 'api.users.update',
        'destroy' => 'api.users.destroy',
    ]);
    Route::get('/roles', [RoleController::class, 'index'])->name('api.roles.index');
    Route::put('/roles/{role}/permissions', [RoleController::class, 'update'])->name('api.roles.permissions.update');
    Route::put('/roles/{role}', [RoleController::class, 'update'])->name('api.roles.update');
    Route::apiResource('activities', ActivityController::class)->names([
        'index'   => 'api.activities.index',
        'store'   => 'api.activities.store',
        'show'    => 'api.activities.show',
        'update'  => 'api.activities.update',
        'destroy' => 'api.activities.destroy',
    ]);
    Route::apiResource('posts', PostController::class)->names([
        'index'   => 'api.posts.index',
        'store'   => 'api.posts.store',
        'show'    => 'api.posts.show',
        'update'  => 'api.posts.update',
        'destroy' => 'api.posts.destroy',
    ]);
    Route::apiResource('announcements', AnnouncementController::class)->names([
        'index'   => 'api.announcements.index',
        'store'   => 'api.announcements.store',
        'show'    => 'api.announcements.show',
        'update'  => 'api.announcements.update',
        'destroy' => 'api.announcements.destroy',
    ]);

    // Media Library
    Route::get('/media', [MediaController::class, 'index'])->name('api.media.index');
    Route::post('/upload-image', [MediaController::class, 'upload'])->name('api.upload.image');
    Route::put('/media/{id}', [MediaController::class, 'update'])->name('api.media.update');
    Route::patch('/media/{id}', [MediaController::class, 'update'])->name('api.media.update.patch');
    Route::patch('/media/{id}/toggle-gallery', [MediaController::class, 'toggleGalleryPublish'])->name('api.media.toggle.gallery');
    Route::delete('/media/{id}', [MediaController::class, 'destroy'])->name('api.media.destroy');

    // Documents Management
    Route::get('/documents', [MediaController::class, 'listDocuments'])->name('api.documents.index');
    Route::post('/documents', [MediaController::class, 'uploadDocument'])->name('api.documents.store');
    Route::put('/documents/{id}', [MediaController::class, 'update'])->name('api.documents.update');
    Route::patch('/documents/{id}', [MediaController::class, 'update'])->name('api.documents.update.patch');
    Route::delete('/documents/{id}', [MediaController::class, 'destroy'])->name('api.documents.destroy');

    // Categories & Tags
    Route::get('/categories', [CategoryController::class, 'index'])->name('api.categories.index');
    Route::post('/categories', [CategoryController::class, 'store'])->name('api.categories.store');
    Route::get('/tags', [TagController::class, 'index'])->name('api.tags.index');
    Route::post('/tags', [TagController::class, 'store'])->name('api.tags.store');

    // Members / Manajemen Anggota
    Route::get('/members/template', [MemberController::class, 'template'])->name('api.members.template');
    Route::get('/members/export', [MemberController::class, 'export'])->name('api.members.export');
    Route::post('/members/import', [MemberController::class, 'import'])->name('api.members.import');
    Route::apiResource('/members', MemberController::class)->names('api.members');

    // Committees / Pengurus
    Route::get('/organization-users/linkable', [CommitteeController::class, 'linkableUsers'])->name('api.organization_users.linkable');
    Route::get('/committees/linkable-users', [CommitteeController::class, 'linkableUsers'])->name('api.committees.linkable_users');
    Route::get('/committees/linkable-members', [CommitteeController::class, 'linkableMembers'])->name('api.committees.linkable_members');
    Route::get('/committees', [CommitteeController::class, 'index'])->name('api.committees.index');
    Route::get('/committees/{id}', [CommitteeController::class, 'show'])->name('api.committees.show');
    Route::post('/committees', [CommitteeController::class, 'store'])->name('api.committees.store');
    Route::post('/committees/{id}', [CommitteeController::class, 'update'])->name('api.committees.update.post');
    Route::put('/committees/{id}', [CommitteeController::class, 'update'])->name('api.committees.update');
    Route::delete('/committees/{id}', [CommitteeController::class, 'destroy'])->name('api.committees.destroy');

    Route::get('/activity-logs', [\App\Http\Controllers\Api\ActivityLogController::class, 'index']);

    // Super Admin: Organization Registration
    Route::get('/organization-registrations', [\App\Http\Controllers\Api\OrganizationRegistrationController::class, 'index']);
    Route::get('/organization-registrations/{id}', [\App\Http\Controllers\Api\OrganizationRegistrationController::class, 'show']);
    Route::post('/organization-registrations/{id}/approve', [\App\Http\Controllers\Api\OrganizationRegistrationController::class, 'approve']);
    Route::post('/organization-registrations/{id}/reject', [\App\Http\Controllers\Api\OrganizationRegistrationController::class, 'reject']);

    // Notifications
    Route::get('/notifications', [\App\Http\Controllers\Api\NotificationController::class, 'index']);
    Route::get('/notifications/unread-count', [\App\Http\Controllers\Api\NotificationController::class, 'unreadCount']);
    Route::post('/notifications/{id}/read', [\App\Http\Controllers\Api\NotificationController::class, 'markAsRead']);
    Route::post('/notifications/read-all', [\App\Http\Controllers\Api\NotificationController::class, 'markAllAsRead']);

    // Platform Settings (Super Admin)
    Route::get('/platform-settings', [\App\Http\Controllers\Api\PlatformSettingController::class, 'show']);
    Route::put('/platform-settings', [\App\Http\Controllers\Api\PlatformSettingController::class, 'update']);

    // Analytics (Scaped API)
    Route::get('/analytics', [\App\Http\Controllers\AnalyticsController::class, 'index']);

    // Global Search API
    Route::get('/global-search', [\App\Http\Controllers\SearchController::class, 'liveSearch']);

    // Backup & Restore
    Route::get('/backup/export', [\App\Http\Controllers\BackupController::class, 'export']);
    Route::post('/backup/import', [\App\Http\Controllers\BackupController::class, 'import']);
});