import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  scrollBehavior(to, from, savedPosition) {
    if (savedPosition) {
      return savedPosition
    }
    if (to.hash) {
      return new Promise((resolve) => {
        setTimeout(() => {
          const element = document.querySelector(to.hash)
          if (element) {
            resolve({
              el: to.hash,
              behavior: 'smooth',
              top: 80,
            })
          } else {
            resolve({ top: 0, behavior: 'smooth' })
          }
        }, 150)
      })
    }
    return { top: 0, behavior: 'smooth' }
  },
  routes: [
    // ─── Public Website ─────────────────────────────────────────
    {
      path: '/',
      name: 'public-home',
      component: () => import('@/views/public/Index.vue'),
      meta: { title: 'Beranda', public: true },
    },
    {
      path: '/organizations',
      name: 'public-organizations-directory',
      component: () => import('@/views/public/OrganizationsDirectory.vue'),
      meta: { title: 'Direktori Ormawa', public: true },
    },
    {
      path: '/berita',
      alias: '/articles',
      name: 'public-global-news-hub',
      component: () => import('@/views/public/GlobalNewsHub.vue'),
      meta: { title: 'Warta & Berita Mahasiswa', public: true },
    },
    {
      path: '/agenda',
      alias: '/kegiatan',
      name: 'public-global-agenda-hub',
      component: () => import('@/views/public/GlobalAgendaHub.vue'),
      meta: { title: 'Agenda & Kegiatan Mahasiswa', public: true },
    },
    {
      path: '/pengumuman',
      alias: '/announcements',
      name: 'public-global-announcement-hub',
      component: () => import('@/views/public/GlobalAnnouncementHub.vue'),
      meta: { title: 'Pengumuman Resmi', public: true },
    },
    {
      path: '/organizations/:slug',
      component: () => import('@/components/public/OrganizationLayout.vue'),
      meta: { public: true },
      children: [
        {
          path: '',
          name: 'public-organization-home',
          component: () => import('@/views/public/OrganizationHome.vue'),
          meta: { title: 'Organisasi', public: true }
        },
        {
          path: 'profil',
          redirect: (to) => `/organizations/${to.params.slug}`
        },
        {
          path: 'articles',
          alias: 'berita',
          name: 'public-organization-articles-list',
          component: () => import('@/views/public/organization/NewsList.vue'),
          meta: { title: 'Berita & Artikel', public: true }
        },
        {
          path: 'articles/:articleSlug',
          alias: 'berita/:articleSlug',
          name: 'public-organization-article-detail',
          component: () => import('@/views/public/organization/NewsDetail.vue'),
          meta: { title: 'Detail Berita', public: true }
        },
        {
          path: 'agenda',
          alias: 'kegiatan',
          name: 'public-organization-agenda-list',
          component: () => import('@/views/public/organization/ActivityList.vue'),
          meta: { title: 'Agenda Kegiatan', public: true }
        },
        {
          path: 'agenda/:id',
          alias: 'kegiatan/:id',
          name: 'public-organization-agenda-detail',
          component: () => import('@/views/public/organization/ActivityDetail.vue'),
          meta: { title: 'Detail Kegiatan', public: true }
        },
        {
          path: 'announcements',
          alias: 'pengumuman',
          name: 'public-organization-announcements',
          component: () => import('@/views/public/organization/AnnouncementsPage.vue'),
          meta: { title: 'Pengumuman', public: true }
        },
        {
          path: 'gallery',
          alias: 'galeri',
          name: 'public-organization-gallery',
          component: () => import('@/views/public/organization/GalleryPage.vue'),
          meta: { title: 'Galeri Foto', public: true }
        },
        {
          path: 'documents',
          alias: 'dokumen',
          name: 'public-organization-documents',
          component: () => import('@/views/public/organization/DocumentsPage.vue'),
          meta: { title: 'Dokumen', public: true }
        },
        {
          path: 'structure',
          alias: 'struktur',
          name: 'public-organization-structure',
          component: () => import('@/views/public/organization/Structure.vue'),
          meta: { title: 'Struktur Organisasi', public: true }
        },
      ]
    },
    // Compatibility alias: /org/:slug redirect to /organizations/:slug
    {
      path: '/org/:slug/:pathMatch(.*)*',
      redirect: to => {
        const path = Array.isArray(to.params.pathMatch) ? to.params.pathMatch.join('/') : (to.params.pathMatch || '')
        return `/organizations/${to.params.slug}${path ? '/' + path : ''}`
      },
    },
    {
      path: '/org/:slug',
      redirect: to => `/organizations/${to.params.slug}`,
    },
    // Keep other existing public routes as fallbacks if needed, but route / dynamically
    // ─── Auth ───────────────────────────────────────────────────
    {
      path: '/login',
      name: 'login',
      component: () => import('@/views/Auth/Login.vue'),
      meta: { title: 'Login', public: true },
    },
    {
      path: '/forgot-password',
      name: 'forgot-password',
      component: () => import('@/views/Auth/ForgotPassword.vue'),
      meta: { title: 'Lupa Kata Sandi', public: true },
    },
    {
      path: '/reset-password',
      name: 'reset-password',
      component: () => import('@/views/Auth/ResetPassword.vue'),
      meta: { title: 'Atur Ulang Kata Sandi', public: true },
    },
    {
      path: '/register',
      name: 'register',
      component: () => import('@/views/Auth/OrganizationRegistration.vue'),
      meta: {
        title: 'Pendaftaran Organisasi',
        public: true
      },
    },
    {
      path: '/register/success',
      name: 'register-success',
      component: () => import('@/views/Auth/RegistrationSuccess.vue'),
      meta: {
        title: 'Pendaftaran Berhasil',
        public: true
      },
    },

    // ─── Super Admin ──────────────────────────────────────────────
    {
      path: '/dashboard',
      name: 'super-admin-dashboard',
      component: () => import('@/views/dashboard/SuperAdminDashboard.vue'),
      meta: { requiresAuth: true, roles: ['Super Admin'], title: 'Dashboard Global' },
    },
    {
      path: '/super-admin/organizations',
      name: 'super-admin-organizations',
      component: () => import('@/views/super-admin/organizations/List.vue'),
      meta: { requiresAuth: true, roles: ['Super Admin'], title: 'Semua Organisasi' },
    },
    {
      path: '/super-admin/organization-registrations',
      name: 'super-admin-organization-registrations',
      component: () => import('@/views/super-admin/organization-registrations/List.vue'),
      meta: { requiresAuth: true, roles: ['Super Admin'], title: 'Pendaftaran Organisasi' }
    },
    {
      path: '/super-admin/organization-admins',
      name: 'super-admin-organization-admins',
      component: () => import('@/views/super-admin/organization-admins/List.vue'),
      meta: { requiresAuth: true, roles: ['Super Admin'], title: 'Admin Organisasi' }
    },
    {
      path: '/super-admin/periods',
      name: 'super-admin-periods',
      component: () => import('@/views/super-admin/periods/List.vue'),
      meta: { requiresAuth: true, roles: ['Super Admin'], title: 'Periode / Renewal' }
    },
    {
      path: '/super-admin/users',
      name: 'super-admin-users',
      component: () => import('@/views/super-admin/users/List.vue'),
      meta: { requiresAuth: true, roles: ['Super Admin'], title: 'Pengguna Global' }
    },
    {
      path: '/super-admin/roles',
      name: 'super-admin-roles',
      component: () => import('@/views/super-admin/roles/List.vue'),
      meta: { requiresAuth: true, roles: ['Super Admin'], title: 'Role & Hak Akses' }
    },
    {
      path: '/super-admin/content',
      name: 'super-admin-content',
      component: () => import('@/views/super-admin/content/List.vue'),
      meta: { requiresAuth: true, roles: ['Super Admin'], title: 'Monitoring Konten' }
    },
    {
      path: '/super-admin/activity-logs',
      name: 'super-admin-activity-logs',
      component: () => import('@/views/super-admin/activity-logs/List.vue'),
      meta: { title: 'Activity Log', requiresAuth: true, roles: ['Super Admin'] }
    },
    {
      path: '/super-admin/approvals',
      redirect: '/super-admin/periods',
    },
    {
      path: '/super-admin/roles',
      name: 'super-admin-roles',
      component: () => import('@/views/super-admin/roles/List.vue'),
      meta: { requiresAuth: true, roles: ['Super Admin'], title: 'Role & Hak Akses' },
    },
    {
      path: '/super-admin/settings',
      name: 'super-admin-settings',
      component: () => import('@/views/super-admin/settings/PlatformSettings.vue'),
      meta: { requiresAuth: true, roles: ['Super Admin'], title: 'Pengaturan Platform' },
    },
    {
      path: '/super-admin/logs',
      redirect: '/super-admin/activity-logs',
    },
    {
      path: '/super-admin/organizations/:id/periods',
      name: 'super-admin-organizations-periods',
      component: () => import('@/views/super-admin/organizations/Periods.vue'),
      meta: { requiresAuth: true, roles: ['Super Admin'], title: 'Manage Periods' },
    },

    // ─── Organization (Admin / Editor / Contributor) ─────────────
    {
      path: '/organization/dashboard',
      name: 'organization-dashboard',
      component: () => import('@/views/dashboard/OrganizationDashboard.vue'),
      meta: { requiresAuth: true, roles: ['Admin Organisasi', 'Editor', 'Kontributor'], title: 'Dashboard' },
    },
    // ─── Unified Content Routes ──────────────────────────────
    {
      path: '/organization/content',
      name: 'organization-content',
      component: () => import('@/views/organization/content/List.vue'),
      meta: { requiresAuth: true, roles: ['Admin Organisasi', 'Editor', 'Kontributor'], title: 'Konten' },
    },
    {
      path: '/organization/content/create',
      name: 'organization-content-create',
      component: () => import('@/views/organization/content/Create.vue'),
      meta: { requiresAuth: true, roles: ['Admin Organisasi', 'Editor', 'Kontributor'], title: 'Buat Konten' },
    },
    {
      path: '/organization/content/edit/:type/:id',
      name: 'organization-content-edit',
      component: () => import('@/views/organization/content/Edit.vue'),
      meta: { requiresAuth: true, roles: ['Admin Organisasi', 'Editor', 'Kontributor'], title: 'Edit Konten' },
    },

    // ─── Legacy Routes (Redirected) ─────────────────────────
    {
      path: '/organization/activity-logs',
      name: 'organization-activity-logs',
      component: () => import('@/views/organization/activity-logs/List.vue'),
      meta: { requiresAuth: true, roles: ['Admin Organisasi'], permission: 'activity_logs.view', title: 'Activity Logs' },
    },
    {
      path: '/organization/posts',
      name: 'organization-posts',
      component: () => import('@/views/organization/posts/List.vue'),
      meta: { requiresAuth: true, roles: ['Admin Organisasi', 'Editor', 'Kontributor'], permission: 'posts.view', title: 'Berita & Artikel' },
    },
    {
      path: '/organization/posts/create',
      name: 'organization-posts-create',
      component: () => import('@/views/organization/posts/Create.vue'),
      meta: { requiresAuth: true, roles: ['Admin Organisasi', 'Editor', 'Kontributor'], permission: 'posts.create', title: 'Tulis Artikel' },
    },
    {
      path: '/organization/posts/edit/:id',
      name: 'organization-posts-edit',
      component: () => import('@/views/organization/posts/Edit.vue'),
      meta: { requiresAuth: true, roles: ['Admin Organisasi', 'Editor', 'Kontributor'], permission: 'posts.update', title: 'Edit Artikel' },
    },
    {
      path: '/organization/agenda',
      name: 'organization-agenda',
      component: () => import('@/views/organization/agenda/List.vue'),
      meta: { requiresAuth: true, roles: ['Admin Organisasi', 'Editor'], permission: 'agenda.view', title: 'Agenda Acara' },
    },
    {
      path: '/organization/agenda/create',
      name: 'organization-agenda-create',
      component: () => import('@/views/organization/agenda/Create.vue'),
      meta: { requiresAuth: true, roles: ['Admin Organisasi', 'Editor'], permission: 'agenda.create', title: 'Tambah Agenda' },
    },
    {
      path: '/organization/agenda/edit/:id',
      name: 'organization-agenda-edit',
      component: () => import('@/views/organization/agenda/Edit.vue'),
      meta: { requiresAuth: true, roles: ['Admin Organisasi', 'Editor'], permission: 'agenda.update', title: 'Edit Agenda' },
    },
    {
      path: '/organization/announcements',
      name: 'organization-announcements',
      component: () => import('@/views/organization/announcements/List.vue'),
      meta: { requiresAuth: true, roles: ['Admin Organisasi', 'Editor'], permission: 'announcements.view', title: 'Pengumuman' },
    },
    {
      path: '/organization/media',
      name: 'organization-media',
      component: () => import('@/views/organization/media/List.vue'),
      meta: { requiresAuth: true, roles: ['Admin Organisasi', 'Editor'], permission: 'gallery.view', title: 'Media Library' },
    },
    {
      path: '/organization/gallery',
      name: 'organization-gallery',
      component: () => import('@/views/organization/gallery/List.vue'),
      meta: { requiresAuth: true, roles: ['Admin Organisasi', 'Editor'], permission: 'gallery.view', title: 'Galeri Publik' },
    },
    {
      path: '/organization/documents',
      name: 'organization-documents',
      component: () => import('@/views/organization/documents/List.vue'),
      meta: { requiresAuth: true, roles: ['Admin Organisasi', 'Editor'], permission: 'documents.view', title: 'Documents' },
    },
    {
      path: '/organization/users',
      name: 'organization-users',
      component: () => import('@/views/organization/users/List.vue'),
      meta: { requiresAuth: true, roles: ['Admin Organisasi'], permission: 'users.view', title: 'Organization Users' },
    },
    {
      path: '/organization/settings',
      name: 'organization-settings',
      component: () => import('@/views/organization/settings/OrganizationSettings.vue'),
      meta: { requiresAuth: true, roles: ['Admin Organisasi'], permission: 'organizations.manage', title: 'Settings' },
    },
    {
      path: '/organization/members',
      name: 'organization-members',
      component: () => import('@/views/organization/members/Index.vue'),
      meta: { requiresAuth: true, roles: ['Admin Organisasi', 'Editor', 'Kontributor'], permission: 'structure.view', title: 'Manajemen Anggota & Kepengurusan' },
    },
    {
      path: '/organization/period',
      name: 'organization-period',
      component: () => import('@/views/organization/period/List.vue'),
      meta: { requiresAuth: true, roles: ['Admin Organisasi'], permission: 'periods.manage', title: 'Manage Period' },
    },
    {
      path: '/organization/committees',
      name: 'organization-committees',
      component: () => import('@/views/organization/committees/List.vue'),
      meta: { requiresAuth: true, roles: ['Admin Organisasi', 'Editor', 'Kontributor'], permission: 'structure.view', title: 'Struktur Organisasi' },
    },

    // ─── Shared Admin Routes ──────────────────────────────────────
    {
      path: '/profile',
      alias: '/account/profile',
      name: 'profile',
      component: () => import('@/views/profile/Profile.vue'),
      meta: { requiresAuth: true, title: 'Edit Profile' },
    },
    {
      path: '/settings',
      alias: '/account/settings',
      name: 'settings',
      component: () => import('@/views/settings/Settings.vue'),
      meta: { requiresAuth: true, title: 'Pengaturan Akun' },
    },

    // ─── Errors ──────────────────────────────────────────────────
    {
      path: '/404',
      name: 'not-found',
      component: () => import('@/views/public/NotFoundPage.vue'),
      meta: { title: '404 | Not Found', public: true },
    },
    {
      path: '/500',
      name: 'server-error',
      component: () => import('@/views/public/NotFoundPage.vue'),
      meta: { title: '500 | Server Error', public: true },
    },
    {
      path: '/403',
      name: 'forbidden',
      component: () => import('@/views/public/NotFoundPage.vue'),
      meta: { title: '403 | Forbidden', public: true },
    },
    {
      path: '/:pathMatch(.*)*',
      redirect: '/404',
    },
  ],
})

import { usePublicStore } from '@/stores/public'
import { resolveTenantFromHostname } from '@/utils/tenantResolver'

router.beforeEach(async (to, _from, next) => {
  document.title = `ORMAWA ITI | ${to.meta.title ?? 'App'}`

  const authStore = useAuthStore()
  const publicStore = usePublicStore()

  // 1. Ensure authentication session is fully restored/verified before evaluating routes
  if (!authStore.isInitialized) {
    await authStore.restoreSession()
  }

  // 2. Initialize tenant dynamically on first load
  if (publicStore.currentTenantSlug === null && !publicStore.isLoading) {
    const slug = resolveTenantFromHostname()
    await publicStore.setTenant(slug)
  }

  // 3. Public Routes Handling
  const isPublic = to.matched.some(record => record.meta.public) || to.meta.public
  if (isPublic) {
    // If authenticated user visits login or register pages, redirect to role-appropriate dashboard
    if (authStore.isAuthenticated && (to.name === 'login' || to.name === 'register' || to.name === 'signup')) {
      if (authStore.role === 'Super Admin') return next({ name: 'super-admin-dashboard' })
      return next({ name: 'organization-dashboard' })
    }
    return next()
  }

  // 4. Protected Routes Handling
  if (to.meta.requiresAuth) {
    if (!authStore.isAuthenticated) {
      return next({ name: 'login', query: { redirect: to.fullPath }, replace: true })
    }

    const allowedRoles = to.meta.roles as string[] | undefined
    if (allowedRoles && authStore.role && authStore.role !== 'Super Admin' && !allowedRoles.includes(authStore.role)) {
      return next({ name: 'organization-dashboard', replace: true })
    }

    // Dynamic Permission Enforcement (Super Admin retains global governance authority)
    const requiredPermission = to.meta.permission as string | undefined
    if (requiredPermission && authStore.role !== 'Super Admin') {
      if (!authStore.hasPermission(requiredPermission)) {
        return next({ name: 'organization-dashboard', replace: true })
      }
    }

    return next()
  }

  next()
})

export default router
