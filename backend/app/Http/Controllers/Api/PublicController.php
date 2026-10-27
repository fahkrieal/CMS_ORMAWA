<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\Announcement;
use App\Models\Category;
use App\Models\Committee;
use App\Models\Media;
use App\Models\Organization;
use App\Models\Post;
use App\Models\Tag;
use Carbon\Carbon;
use Illuminate\Http\Request;

class PublicController extends Controller
{
    /**
     * Helper: Resolve active organization by subdomain slug
     */
    private function resolveOrganization(string $slug): Organization
    {
        $org = Organization::where('subdomain', strtolower(trim($slug)))
            ->first();

        if (!$org) {
            abort(404, 'Organisasi tidak ditemukan.');
        }

        return $org;
    }

    /**
     * Helper: Check if specific public module is enabled
     */
    private function ensureModuleEnabled(Organization $org, string $moduleKey): void
    {
        $modules = $org->modul_aktif ?? [];

        if (isset($modules['public_website_enabled']) && $modules['public_website_enabled'] === false) {
            abort(404, 'Website publik organisasi ini sedang dinonaktifkan.');
        }
        
        $aliasMap = [
            'galeri' => ['galeri', 'gallery'],
            'gallery' => ['galeri', 'gallery'],
            'announcements' => ['announcements', 'pengumuman'],
            'pengumuman' => ['announcements', 'pengumuman'],
            'documents' => ['documents', 'dokumen'],
            'dokumen' => ['documents', 'dokumen'],
            'posts' => ['posts', 'berita', 'articles'],
            'berita' => ['posts', 'berita', 'articles'],
            'articles' => ['posts', 'berita', 'articles'],
            'agenda' => ['agenda', 'kegiatan', 'activities'],
            'kegiatan' => ['agenda', 'kegiatan', 'activities'],
            'activities' => ['agenda', 'kegiatan', 'activities'],
            'structure' => ['structure', 'struktur', 'pengurus'],
            'struktur' => ['structure', 'struktur', 'pengurus'],
            'contact' => ['contact', 'kontak'],
            'kontak' => ['contact', 'kontak'],
        ];

        $keysToCheck = $aliasMap[$moduleKey] ?? [$moduleKey];
        foreach ($keysToCheck as $key) {
            if (isset($modules[$key]) && $modules[$key] === false) {
                abort(404, "Modul {$moduleKey} tidak diaktifkan pada organisasi ini.");
            }
        }
    }

    /**
     * Helper: Record internal page view for analytics
     */
    private function recordPageView(Organization $org): void
    {
        try {
            $req = request();
            \App\Models\PageView::create([
                'organization_id' => $org->id,
                'url_path'        => '/' . ltrim($req->path(), '/'),
                'ip_address'      => $req->ip() ?? '127.0.0.1',
                'user_agent'      => substr($req->userAgent() ?? 'Web', 0, 255),
            ]);
        } catch (\Throwable) {
            // Silently ignore to avoid failing public requests
        }
    }

    /**
     * GET /api/public/platform-settings
     * Public platform settings including homepage customization and platform footer
     */
    public function platformSettings()
    {
        $defaults = \App\Http\Controllers\Api\PlatformSettingController::getDefaultSettings();
        $cachedSettings = \Illuminate\Support\Facades\Cache::get(\App\Http\Controllers\Api\PlatformSettingController::CACHE_KEY, []);
        $platformSettings = array_merge($defaults, is_array($cachedSettings) ? $cachedSettings : []);

        return response()->json([
            'status' => 'success',
            'data' => $platformSettings,
        ]);
    }

    /**
     * 1. GET /api/public/home
     * Aggregated portal homepage data
     */
    public function home()
    {
        // 1. Organizations for Directory Preview (Active & Inactive visible in public directory)
        $organizations = Organization::query()
            ->select(['id', 'nama', 'jenis', 'subdomain', 'logo', 'warna_tema', 'modul_aktif', 'status'])
            ->withCount([
                'posts' => fn($q) => $q->where('status', 'published'),
                'activities' => fn($q) => $q->where('status', 'published'),
            ])
            ->latest('created_at')
            ->take(12)
            ->get()
            ->map(function ($org) {
                return [
                    'id' => $org->id,
                    'nama' => $org->nama,
                    'jenis' => $org->jenis,
                    'subdomain' => $org->subdomain,
                    'logo' => $org->logo,
                    'warna_tema' => $org->warna_tema,
                    'status' => $org->status,
                    'slogan' => $org->modul_aktif['slogan'] ?? null,
                    'deskripsi' => $org->modul_aktif['deskripsi'] ?? null,
                    'posts_count' => $org->posts_count,
                    'activities_count' => $org->activities_count,
                ];
            });

        // 2. Featured / Latest Published Articles
        $articles = Post::withoutGlobalScopes()
            ->where('status', 'published')
            ->with([
                'organization:id,nama,subdomain,logo,warna_tema',
                'user:id,name',
                'category:id,name,slug',
                'tags:id,name,slug',
            ])
            ->latest('published_at')
            ->take(6)
            ->get()
            ->map(fn($post) => $this->formatArticle($post));

        // 3. Upcoming Published Agenda
        $today = Carbon::today()->toDateString();
        $agenda = Activity::withoutGlobalScopes()
            ->where('status', 'published')
            ->whereDate('tanggal_pelaksanaan', '>=', $today)
            ->with('organization:id,nama,subdomain,logo,warna_tema')
            ->orderBy('tanggal_pelaksanaan', 'asc')
            ->take(6)
            ->get()
            ->map(fn($act) => $this->formatAgenda($act));

        // 4. Active Announcements (Within effective date range and not expired)
        $today = Carbon::today()->toDateString();
        $announcements = Announcement::withoutGlobalScopes()
            ->where('status', 'published')
            ->where(function ($q) use ($today) {
                $q->whereNull('effective_date')
                  ->orWhereDate('effective_date', '<=', $today);
            })
            ->where(function ($q) use ($today) {
                $q->whereNull('expires_at')
                  ->orWhereDate('expires_at', '>=', $today);
            })
            ->whereHas('organization', function ($q) {
                $q->where(function ($sub) {
                      $sub->whereNull('modul_aktif->announcements')
                          ->orWhere('modul_aktif->announcements', '!=', false);
                  })
                  ->where(function ($sub) {
                      $sub->whereNull('modul_aktif->pengumuman')
                          ->orWhere('modul_aktif->pengumuman', '!=', false);
                  })
                  ->where(function ($sub) {
                      $sub->whereNull('modul_aktif->public_website_enabled')
                          ->orWhere('modul_aktif->public_website_enabled', '!=', false);
                  });
            })
            ->with('organization:id,nama,subdomain,logo,warna_tema')
            ->orderByRaw("CASE priority WHEN 'urgent' THEN 1 WHEN 'high' THEN 2 WHEN 'normal' THEN 3 ELSE 4 END")
            ->latest('published_at')
            ->latest('id')
            ->take(5)
            ->get()
            ->map(fn($ann) => $this->formatAnnouncement($ann));

        $defaults = \App\Http\Controllers\Api\PlatformSettingController::getDefaultSettings();
        $cachedSettings = \Illuminate\Support\Facades\Cache::get(\App\Http\Controllers\Api\PlatformSettingController::CACHE_KEY, []);
        $platformSettings = array_merge($defaults, is_array($cachedSettings) ? $cachedSettings : []);

        return response()->json([
            'status' => 'success',
            'data' => [
                'organizations' => $organizations,
                'featured_articles' => $articles,
                'upcoming_agenda' => $agenda,
                'active_announcements' => $announcements,
                'platform_settings' => $platformSettings,
                'stats' => [
                    'total_organizations' => Organization::count(),
                    'total_articles' => Post::withoutGlobalScopes()->where('status', 'published')->count(),
                    'total_agenda' => Activity::withoutGlobalScopes()->where('status', 'published')->count(),
                ]
            ]
        ]);
    }

    /**
     * 2. GET /api/public/organizations
     * Filterable list of active organizations
     */
    public function organizations(Request $request)
    {
        $query = Organization::query()
            ->select(['id', 'nama', 'jenis', 'subdomain', 'logo', 'warna_tema', 'modul_aktif', 'status'])
            ->withCount([
                'posts' => fn($q) => $q->where('status', 'published'),
                'activities' => fn($q) => $q->where('status', 'published'),
                'committees' => fn($q) => $q->where('status', 'active'),
            ]);

        if ($request->has('jenis') && $request->jenis) {
            $query->where('jenis', $request->jenis);
        }

        if ($request->has('search') && $request->search) {
            $search = trim($request->search);
            $query->where(function ($q) use ($search) {
                $q->where('nama', 'like', "%{$search}%")
                  ->orWhere('subdomain', 'like', "%{$search}%");
            });
        }

        $perPage = min(max((int)($request->per_page ?? 12), 1), 50);
        $paginator = $query->latest('created_at')->paginate($perPage);

        $items = collect($paginator->items())->map(function ($org) {
            return [
                'id' => $org->id,
                'nama' => $org->nama,
                'jenis' => $org->jenis,
                'subdomain' => $org->subdomain,
                'logo' => $org->logo,
                'warna_tema' => $org->warna_tema,
                'status' => $org->status,
                'slogan' => $org->modul_aktif['slogan'] ?? null,
                'deskripsi' => $org->modul_aktif['deskripsi'] ?? null,
                'posts_count' => $org->posts_count,
                'activities_count' => $org->activities_count,
                'committees_count' => $org->committees_count,
            ];
        });

        return response()->json([
            'status' => 'success',
            'data' => $items,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ]
        ]);
    }

    /**
     * 2.1. GET /api/public/articles
     * Aggregated global list of published articles across all active organizations
     */
    public function globalArticles(Request $request)
    {
        $query = Post::withoutGlobalScopes()
            ->where('status', 'published')
            ->whereHas('organization', function ($q) {
                $q->where(function ($sub) {
                      $sub->whereNull('modul_aktif->posts')
                          ->orWhere('modul_aktif->posts', true);
                  })
                  ->where(function ($sub) {
                      $sub->whereNull('modul_aktif->articles')
                          ->orWhere('modul_aktif->articles', true);
                  })
                  ->where(function ($sub) {
                      $sub->whereNull('modul_aktif->berita')
                          ->orWhere('modul_aktif->berita', true);
                  })
                  ->where(function ($sub) {
                      $sub->whereNull('modul_aktif->public_website_enabled')
                          ->orWhere('modul_aktif->public_website_enabled', true);
                  });
            })
            ->with([
                'user:id,name',
                'category:id,name,slug',
                'tags:id,name,slug',
                'organization:id,nama,subdomain,logo,warna_tema',
            ]);

        // Search filter (judul, excerpt, konten)
        if ($request->filled('search')) {
            $search = trim($request->search);
            $query->where(function ($q) use ($search) {
                $q->where('judul', 'like', "%{$search}%")
                  ->orWhere('excerpt', 'like', "%{$search}%")
                  ->orWhere('konten', 'like', "%{$search}%");
            });
        }

        // Organization filter (subdomain or id or nama)
        if ($request->filled('organization')) {
            $orgParam = trim($request->organization);
            $query->whereHas('organization', function ($q) use ($orgParam) {
                $q->where('subdomain', $orgParam)
                  ->orWhere('id', $orgParam)
                  ->orWhere('nama', $orgParam);
            });
        }

        // Category filter (slug or name or id)
        if ($request->filled('category')) {
            $catParam = trim($request->category);
            $query->whereHas('category', function ($q) use ($catParam) {
                $q->where('slug', $catParam)
                  ->orWhere('name', $catParam)
                  ->orWhere('id', $catParam);
            });
        }

        // Tag filter (slug or name or id)
        if ($request->filled('tag')) {
            $tagParam = trim($request->tag);
            $query->whereHas('tags', function ($q) use ($tagParam) {
                $q->where('slug', $tagParam)
                  ->orWhere('name', $tagParam)
                  ->orWhere('id', $tagParam);
            });
        }

        // Sort order
        if ($request->input('sort') === 'oldest') {
            $query->orderBy('published_at', 'asc')->orderBy('id', 'asc');
        } else {
            $query->latest('published_at')->latest('id');
        }

        $perPage = min(max((int)($request->per_page ?? 12), 1), 50);
        $paginator = $query->paginate($perPage);

        $items = collect($paginator->items())->map(fn($p) => $this->formatArticle($p));

        return response()->json([
            'status' => 'success',
            'data' => $items,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ]
        ]);
    }

    /**
     * 2.2. GET /api/public/categories
     * List distinct categories for active published posts
     */
    public function globalCategories()
    {
        $categories = Category::withoutGlobalScopes()
            ->whereHas('posts', function ($q) {
                $q->where('status', 'published');
            })
            ->select(['id', 'name', 'slug'])
            ->distinct()
            ->orderBy('name')
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $categories
        ]);
    }

    /**
     * 2.3. GET /api/public/agenda
     * Aggregated global list of published activities across all active organizations
     */
    public function globalAgenda(Request $request)
    {
        $query = Activity::withoutGlobalScopes()
            ->where('status', 'published')
            ->whereHas('organization', function ($q) {
                $q->where(function ($sub) {
                      $sub->whereNull('modul_aktif->agenda')
                          ->orWhere('modul_aktif->agenda', true);
                  })
                  ->where(function ($sub) {
                      $sub->whereNull('modul_aktif->kegiatan')
                          ->orWhere('modul_aktif->kegiatan', true);
                  })
                  ->where(function ($sub) {
                      $sub->whereNull('modul_aktif->activities')
                          ->orWhere('modul_aktif->activities', true);
                  })
                  ->where(function ($sub) {
                      $sub->whereNull('modul_aktif->public_website_enabled')
                          ->orWhere('modul_aktif->public_website_enabled', true);
                  });
            })
            ->with('organization:id,nama,subdomain,logo,warna_tema');

        $today = Carbon::today()->toDateString();
        $tab = $request->get('tab', 'upcoming');

        if ($tab === 'upcoming') {
            $query->whereDate('tanggal_pelaksanaan', '>=', $today)->orderBy('tanggal_pelaksanaan', 'asc')->orderBy('id', 'asc');
        } elseif ($tab === 'today') {
            $query->whereDate('tanggal_pelaksanaan', '=', $today)->orderBy('id', 'asc');
        } elseif ($tab === 'past') {
            $query->whereDate('tanggal_pelaksanaan', '<', $today)->orderBy('tanggal_pelaksanaan', 'desc')->orderBy('id', 'desc');
        } else {
            $query->orderBy('tanggal_pelaksanaan', 'desc')->orderBy('id', 'desc');
        }

        // Search filter (judul, deskripsi, tempat)
        if ($request->filled('search')) {
            $search = trim($request->search);
            $query->where(function ($q) use ($search) {
                $q->where('judul', 'like', "%{$search}%")
                  ->orWhere('deskripsi', 'like', "%{$search}%")
                  ->orWhere('tempat', 'like', "%{$search}%");
            });
        }

        // Organization filter
        if ($request->filled('organization')) {
            $orgParam = trim($request->organization);
            $query->whereHas('organization', function ($q) use ($orgParam) {
                $q->where('subdomain', $orgParam)
                  ->orWhere('id', $orgParam)
                  ->orWhere('nama', $orgParam);
            });
        }

        $perPage = min(max((int)($request->per_page ?? 12), 1), 50);
        $paginator = $query->paginate($perPage);

        $items = collect($paginator->items())->map(fn($a) => $this->formatAgenda($a));

        return response()->json([
            'status' => 'success',
            'data' => $items,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ]
        ]);
    }

    /**
     * 2.4. GET /api/public/announcements
     * Aggregated global list of published active announcements across all active organizations
     */
    public function globalAnnouncements(Request $request)
    {
        $today = Carbon::today()->toDateString();
        $query = Announcement::withoutGlobalScopes()
            ->where('status', 'published')
            ->where(function ($q) use ($today) {
                $q->whereNull('effective_date')
                  ->orWhereDate('effective_date', '<=', $today);
            })
            ->where(function ($q) use ($today) {
                $q->whereNull('expires_at')
                  ->orWhereDate('expires_at', '>=', $today);
            })
            ->whereHas('organization', function ($q) {
                $q->where(function ($sub) {
                      $sub->whereNull('modul_aktif->announcements')
                          ->orWhere('modul_aktif->announcements', '!=', false);
                  })
                  ->where(function ($sub) {
                      $sub->whereNull('modul_aktif->pengumuman')
                          ->orWhere('modul_aktif->pengumuman', '!=', false);
                  })
                  ->where(function ($sub) {
                      $sub->whereNull('modul_aktif->public_website_enabled')
                          ->orWhere('modul_aktif->public_website_enabled', '!=', false);
                  });
            })
            ->with('organization:id,nama,subdomain,logo,warna_tema');

        // Priority filter
        if ($request->filled('priority')) {
            $priority = strtolower(trim($request->priority));
            if (in_array($priority, ['urgent', 'high', 'normal', 'low'])) {
                $query->where('priority', $priority);
            }
        }

        // Search filter (title, content)
        if ($request->filled('search')) {
            $search = trim($request->search);
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('content', 'like', "%{$search}%")
                  ->orWhere('slug', 'like', "%{$search}%");
            });
        }

        // Organization filter
        if ($request->filled('organization')) {
            $orgParam = trim($request->organization);
            $query->whereHas('organization', function ($q) use ($orgParam) {
                $q->where('subdomain', $orgParam)
                  ->orWhere('id', $orgParam)
                  ->orWhere('nama', $orgParam);
            });
        }

        $query->orderByRaw("CASE priority WHEN 'urgent' THEN 1 WHEN 'high' THEN 2 WHEN 'normal' THEN 3 ELSE 4 END")
              ->latest('published_at')
              ->latest('id');

        $perPage = min(max((int)($request->per_page ?? 12), 1), 50);
        $paginator = $query->paginate($perPage);

        $items = collect($paginator->items())->map(fn($ann) => $this->formatAnnouncement($ann));

        return response()->json([
            'status' => 'success',
            'data' => $items,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ]
        ]);
    }

    /**
     * 3. GET /api/public/organizations/{slug}
     * Public organization profile with current period & content counts
     */
    public function organization(string $slug)
    {
        $org = $this->resolveOrganization($slug);
        $this->recordPageView($org);

        $currentPeriod = $org->currentPeriod();
        $modules = $org->modul_aktif ?? [];

        // Check if website is enabled
        $websiteEnabled = $modules['public_website_enabled'] ?? true;
        if (!$websiteEnabled) {
            return response()->json([
                'status' => 'unavailable',
                'message' => 'Website publik organisasi ini sedang dinonaktifkan sementara.',
                'data' => [
                    'nama' => $org->nama,
                    'jenis' => $org->jenis,
                    'subdomain' => $org->subdomain,
                    'logo' => $org->logo,
                    'public_website_enabled' => false,
                ]
            ], 200);
        }

        // Preview counts of published items
        $publishedPostsCount = Post::withoutGlobalScopes()
            ->where('organization_id', $org->id)
            ->where('status', 'published')
            ->count();

        $publishedActivitiesCount = Activity::withoutGlobalScopes()
            ->where('organization_id', $org->id)
            ->where('status', 'published')
            ->count();

        $activeAnnouncementsCount = Announcement::withoutGlobalScopes()
            ->where('organization_id', $org->id)
            ->where('status', 'published')
            ->count();

        $publicMediaCount = Media::withoutGlobalScopes()
            ->where('organization_id', $org->id)
            ->count();

        $activeCommitteesCount = Committee::withoutGlobalScopes()
            ->where('organization_id', $org->id)
            ->where('status', 'active')
            ->count();

        return response()->json([
            'status' => 'success',
            'data' => [
                'id' => $org->id,
                'nama' => $org->nama,
                'jenis' => $org->jenis,
                'subdomain' => $org->subdomain,
                'logo' => $org->logo,
                'hero_image' => $org->hero_image ?? $modules['hero_image'] ?? null,
                'warna_tema' => $org->warna_tema ?? '#00346F',
                'status' => $org->status,
                'slogan' => $modules['slogan'] ?? null,
                'deskripsi' => $modules['deskripsi'] ?? null,
                'deskripsi_lengkap' => $modules['deskripsi_lengkap'] ?? null,
                'hero_title' => $modules['hero_title'] ?? null,
                'hero_subtitle' => $modules['hero_subtitle'] ?? null,
                'email' => $org->email ?? $modules['email_publik'] ?? null,
                'email_publik' => $org->email ?? $modules['email_publik'] ?? null,
                'telepon' => $org->telepon ?? null,
                'alamat' => $org->alamat ?? null,
                'media_sosial' => $org->media_sosial ?? ($modules['media_sosial'] ?? [
                    'instagram' => $modules['instagram'] ?? null,
                    'facebook' => $modules['facebook'] ?? null,
                    'youtube' => $modules['youtube'] ?? null,
                    'tiktok' => $modules['tiktok'] ?? null,
                    'linkedin' => $modules['linkedin'] ?? null,
                    'website' => $modules['website_eksternal'] ?? null,
                    'whatsapp' => $modules['whatsapp'] ?? null,
                ]),
                'instagram' => $org->media_sosial['instagram'] ?? ($modules['instagram'] ?? null),
                'facebook' => $org->media_sosial['facebook'] ?? ($modules['facebook'] ?? null),
                'youtube' => $org->media_sosial['youtube'] ?? ($modules['youtube'] ?? null),
                'tiktok' => $org->media_sosial['tiktok'] ?? ($modules['tiktok'] ?? null),
                'linkedin' => $org->media_sosial['linkedin'] ?? ($modules['linkedin'] ?? null),
                'website_eksternal' => $org->media_sosial['website'] ?? ($modules['website_eksternal'] ?? null),
                'copyright' => $modules['copyright'] ?? ($modules['footer_copyright'] ?? null),
                'footer_copyright' => $modules['copyright'] ?? ($modules['footer_copyright'] ?? null),
                'footer_description' => $modules['footer_description'] ?? ($modules['deskripsi'] ?? null),
                'label_menu' => $org->label_menu ?? [],
                'ga_tracking_id' => $org->ga_tracking_id ?? null,
                'seo_title' => $modules['seo_title'] ?? "{$org->nama} | ORMAWA ITI",
                'seo_description' => $modules['seo_description'] ?? ($modules['deskripsi'] ?? "Website resmi {$org->nama}"),
                'og_image' => $modules['og_image'] ?? $org->hero_image ?? $org->logo,
                'modules' => [
                    'posts' => $modules['posts'] ?? true,
                    'agenda' => $modules['agenda'] ?? true,
                    'announcements' => $modules['announcements'] ?? true,
                    'galeri' => $modules['galeri'] ?? true,
                    'documents' => $modules['documents'] ?? true,
                    'structure' => $modules['structure'] ?? true,
                    'public_website_enabled' => $websiteEnabled,
                ],
                'current_period' => $currentPeriod ? [
                    'id' => $currentPeriod->id,
                    'period_name' => $currentPeriod->period_name,
                    'start_date' => $currentPeriod->start_date,
                    'end_date' => $currentPeriod->end_date,
                    'status' => $currentPeriod->status,
                ] : null,
                'stats' => [
                    'posts_count' => $publishedPostsCount,
                    'activities_count' => $publishedActivitiesCount,
                    'announcements_count' => $activeAnnouncementsCount,
                    'media_count' => $publicMediaCount,
                    'committees_count' => $activeCommitteesCount,
                ]
            ]
        ]);
    }

    /**
     * 4. GET /api/public/organizations/{slug}/articles
     * Published articles for specific organization
     */
    public function articles(string $slug, Request $request)
    {
        $org = $this->resolveOrganization($slug);
        $this->ensureModuleEnabled($org, 'posts');

        $query = Post::withoutGlobalScopes()
            ->where('organization_id', $org->id)
            ->where('status', 'published')
            ->with([
                'user:id,name',
                'category:id,name,slug',
                'tags:id,name,slug',
                'organization:id,nama,subdomain,logo,warna_tema',
            ]);

        if ($request->has('search') && $request->search) {
            $search = trim($request->search);
            $query->where(function ($q) use ($search) {
                $q->where('judul', 'like', "%{$search}%")
                  ->orWhere('konten', 'like', "%{$search}%")
                  ->orWhere('excerpt', 'like', "%{$search}%");
            });
        }

        if ($request->has('category') && $request->category) {
            $cat = $request->category;
            $query->whereHas('category', function ($qc) use ($cat) {
                $qc->where('slug', $cat)->orWhere('name', $cat)->orWhere('id', $cat);
            });
        }

        if ($request->has('tag') && $request->tag) {
            $tag = $request->tag;
            $query->whereHas('tags', function ($qt) use ($tag) {
                $qt->where('slug', $tag)->orWhere('name', $tag)->orWhere('id', $tag);
            });
        }

        $perPage = min(max((int)($request->per_page ?? 12), 1), 50);
        $paginator = $query->latest('published_at')->paginate($perPage);

        $items = collect($paginator->items())->map(fn($p) => $this->formatArticle($p));

        return response()->json([
            'status' => 'success',
            'data' => $items,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ]
        ]);
    }

    /**
     * 5. GET /api/public/organizations/{slug}/articles/{articleSlug}
     * Single published article detail with related posts
     */
    public function article(string $slug, string $articleSlug)
    {
        $org = $this->resolveOrganization($slug);
        $this->ensureModuleEnabled($org, 'posts');
        $this->recordPageView($org);

        $post = Post::withoutGlobalScopes()
            ->where('organization_id', $org->id)
            ->where('status', 'published')
            ->where(function ($q) use ($articleSlug) {
                $q->where('slug', $articleSlug)
                  ->orWhere('id', $articleSlug);
            })
            ->with([
                'user:id,name',
                'category:id,name,slug',
                'tags:id,name,slug',
                'organization:id,nama,subdomain,logo,warna_tema',
            ])
            ->first();

        if (!$post) {
            abort(404, 'Artikel tidak ditemukan atau belum dipublikasikan.');
        }

        // Up to 3 Related Articles from same organization (prioritizing same category)
        $relatedQuery = Post::withoutGlobalScopes()
            ->where('organization_id', $org->id)
            ->where('status', 'published')
            ->where('id', '!=', $post->id);

        if ($post->category_id) {
            $relatedQuery->orderByRaw("CASE WHEN category_id = ? THEN 0 ELSE 1 END", [(int) $post->category_id]);
        }

        $related = $relatedQuery
            ->with(['category:id,name,slug', 'organization:id,nama,subdomain,logo,warna_tema', 'user:id,name', 'tags:id,name,slug'])
            ->latest('published_at')
            ->take(3)
            ->get()
            ->map(fn($p) => $this->formatArticle($p));

        return response()->json([
            'status' => 'success',
            'data' => array_merge($this->formatArticle($post), [
                'konten' => $post->konten,
                'related_articles' => $related,
            ])
        ]);
    }

    /**
     * 6. GET /api/public/organizations/{slug}/agenda
     * Published agenda items
     */
    public function agenda(string $slug, Request $request)
    {
        $org = $this->resolveOrganization($slug);
        $this->ensureModuleEnabled($org, 'agenda');

        $query = Activity::withoutGlobalScopes()
            ->where('organization_id', $org->id)
            ->where('status', 'published')
            ->with('organization:id,nama,subdomain,logo,warna_tema');

        $today = Carbon::today()->toDateString();
        $tab = $request->get('tab', 'upcoming');

        if ($tab === 'upcoming') {
            $query->whereDate('tanggal_pelaksanaan', '>=', $today)->orderBy('tanggal_pelaksanaan', 'asc');
        } elseif ($tab === 'today') {
            $query->whereDate('tanggal_pelaksanaan', '=', $today);
        } elseif ($tab === 'past') {
            $query->whereDate('tanggal_pelaksanaan', '<', $today)->orderBy('tanggal_pelaksanaan', 'desc');
        } else {
            $query->orderBy('tanggal_pelaksanaan', 'desc');
        }

        $perPage = min(max((int)($request->per_page ?? 10), 1), 50);
        $paginator = $query->paginate($perPage);

        $items = collect($paginator->items())->map(fn($a) => $this->formatAgenda($a));

        return response()->json([
            'status' => 'success',
            'data' => $items,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ]
        ]);
    }

    /**
     * 7. GET /api/public/organizations/{slug}/agenda/{id}
     * Single published agenda detail
     */
    public function agendaDetail(string $slug, string $id)
    {
        $org = $this->resolveOrganization($slug);
        $this->ensureModuleEnabled($org, 'agenda');
        $this->recordPageView($org);

        $activity = Activity::withoutGlobalScopes()
            ->where('organization_id', $org->id)
            ->where('status', 'published')
            ->where('id', $id)
            ->with('organization:id,nama,subdomain,logo,warna_tema')
            ->first();

        if (!$activity) {
            abort(404, 'Agenda tidak ditemukan atau belum dipublikasikan.');
        }

        return response()->json([
            'status' => 'success',
            'data' => $this->formatAgenda($activity),
        ]);
    }

    /**
     * 8. GET /api/public/organizations/{slug}/announcements
     * Active published announcements
     */
    public function announcements(string $slug)
    {
        $org = $this->resolveOrganization($slug);
        $this->ensureModuleEnabled($org, 'announcements');

        $today = Carbon::today()->toDateString();
        $announcements = Announcement::withoutGlobalScopes()
            ->where('organization_id', $org->id)
            ->where('status', 'published')
            ->where(function ($q) use ($today) {
                $q->whereNull('effective_date')
                  ->orWhereDate('effective_date', '<=', $today);
            })
            ->where(function ($q) use ($today) {
                $q->whereNull('expires_at')
                  ->orWhereDate('expires_at', '>=', $today);
            })
            ->with('organization:id,nama,subdomain,logo,warna_tema')
            ->orderByRaw("CASE priority WHEN 'urgent' THEN 1 WHEN 'high' THEN 2 WHEN 'normal' THEN 3 ELSE 4 END")
            ->latest('published_at')
            ->latest('id')
            ->get()
            ->map(fn($ann) => $this->formatAnnouncement($ann));

        return response()->json([
            'status' => 'success',
            'data' => $announcements,
        ]);
    }

    /**
     * 9. GET /api/public/organizations/{slug}/gallery
     * Public image media items
     */
    public function gallery(string $slug, Request $request)
    {
        $org = $this->resolveOrganization($slug);
        $this->ensureModuleEnabled($org, 'galeri');

        $query = Media::withoutGlobalScopes()
            ->where('organization_id', $org->id)
            ->where('mime_type', 'like', 'image/%')
            ->where('is_published_to_gallery', true)
            ->where(function ($q) {
                $q->whereNull('visibility')->orWhere('visibility', '!=', 'internal');
            })
            ->with('organization:id,nama,subdomain');

        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }

        if ($request->filled('search')) {
            $s = trim($request->search);
            $query->where(function ($q) use ($s) {
                $q->where('name', 'like', "%{$s}%")
                  ->orWhere('caption', 'like', "%{$s}%")
                  ->orWhere('filename', 'like', "%{$s}%");
            });
        }

        $perPage = min(max((int)($request->per_page ?? 12), 1), 50);
        $paginator = $query->latest('created_at')->paginate($perPage);

        $items = collect($paginator->items())->map(function ($m) {
            $title = $m->name ?? pathinfo($m->filename, PATHINFO_FILENAME);
            return [
                'id'         => $m->id,
                'title'      => $title,
                'name'       => $title,
                'judul'      => $title,
                'caption'    => $m->caption,
                'deskripsi'  => $m->caption,
                'alt_text'   => $m->alt_text ?: $title,
                'category'   => $m->category ?? 'Dokumentasi',
                'kategori'   => $m->category ?? 'Dokumentasi',
                'taken_at'   => $m->taken_at?->toDateString(),
                'tanggal'    => $m->taken_at?->toDateString() ?? $m->created_at?->toDateString(),
                'image_url'  => $m->url,
                'url'        => $m->url,
                'filename'   => $m->filename,
                'mime_type'  => $m->mime_type,
                'size'       => $m->size,
                'created_at' => $m->created_at?->toIso8601String(),
            ];
        });

        return response()->json([
            'status' => 'success',
            'data' => $items,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ]
        ]);
    }

    /**
     * 10. GET /api/public/organizations/{slug}/documents
     * Public downloadable documents
     */
    public function documents(string $slug, Request $request)
    {
        $org = $this->resolveOrganization($slug);
        $this->ensureModuleEnabled($org, 'documents');

        $query = Media::withoutGlobalScopes()
            ->where('organization_id', $org->id)
            ->where(function ($q) {
                $q->whereNull('visibility')->orWhere('visibility', 'public');
            })
            ->where('mime_type', 'not like', 'image/%')
            ->with('organization:id,nama,subdomain');

        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('name', 'like', "%{$s}%")
                  ->orWhere('filename', 'like', "%{$s}%");
            });
        }

        $perPage = min(max((int)($request->per_page ?? 12), 1), 50);
        $paginator = $query->latest('created_at')->paginate($perPage);

        $items = collect($paginator->items())->map(function ($m) use ($org) {
            return [
                'id' => $m->id,
                'name' => $m->name ?? $m->filename,
                'judul' => $m->name ?? $m->filename,
                'filename' => $m->filename,
                'category' => $m->category ?? 'Other',
                'kategori' => $m->category ?? 'Other',
                'mime_type' => $m->mime_type ?? 'application/octet-stream',
                'file_type' => strtoupper(pathinfo($m->filename, PATHINFO_EXTENSION) ?: 'FILE'),
                'size' => $m->size,
                'file_size' => $m->size,
                'formatted_size' => $m->size ? round($m->size / 1024, 1) . ' KB' : '0 B',
                'download_url' => url("/api/public/organizations/{$org->subdomain}/documents/{$m->id}/download"),
                'file_url' => url("/api/public/organizations/{$org->subdomain}/documents/{$m->id}/download"),
                'created_at' => $m->created_at?->toIso8601String(),
            ];
        });

        return response()->json([
            'status' => 'success',
            'data' => $items,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ]
        ]);
    }

    /**
     * 11. GET /api/public/organizations/{slug}/structure
     * Public organizational structure (Committees for ongoing active period or selected historical archive)
     */
    public function structure(Request $request, string $slug)
    {
        $org = $this->resolveOrganization($slug);
        $this->ensureModuleEnabled($org, 'structure');

        // Ambil semua periode organisasi untuk pilihan dropdown arsip publik
        $availablePeriods = $org->periods()
            ->orderBy('start_date', 'desc')
            ->get()
            ->map(function ($p) {
                return [
                    'id'                => $p->id,
                    'period_name'       => $p->period_name,
                    'start_date'        => $p->start_date?->format('Y-m-d'),
                    'end_date'          => $p->end_date?->format('Y-m-d'),
                    'status'            => $p->status,
                    'calculated_status' => $p->calculated_status,
                    'is_ongoing'        => $p->isOngoing(),
                ];
            });

        $currentOngoingPeriod = $org->currentPeriod();
        $selectedPeriod = null;

        if ($request->filled('period_id')) {
            $selectedPeriod = $org->periods()->where('id', $request->period_id)->first();
            if (!$selectedPeriod) {
                abort(404, 'Periode kepengurusan yang diminta tidak ditemukan.');
            }
        } else {
            $selectedPeriod = $currentOngoingPeriod;
        }

        $committeeQuery = Committee::withoutGlobalScopes()
            ->where('organization_id', $org->id);

        if ($selectedPeriod) {
            $committeeQuery->where('organization_period_id', $selectedPeriod->id);
            // Jika melihat periode yang sedang berjalan (ongoing), hanya tampilkan yang statusnya aktif secara efektif
            if ($selectedPeriod->isOngoing()) {
                $committeeQuery->effectiveActive();
            } else {
                // Untuk arsip periode lampau/mendatang, tampilkan yang tercatat aktif pada periode tersebut
                $committeeQuery->where('status', 'active');
            }
        } else {
            // Jika organisasi belum memiliki periode aktif saat ini dan tidak ada periode yang dipilih,
            // jangan tampilkan pengurus dari periode kedaluwarsa secara membabi-buta!
            $committeeQuery->whereRaw('1 = 0');
        }

        $committees = $committeeQuery
            ->orderBy('department', 'asc')
            ->orderBy('position', 'asc')
            ->get()
            ->map(function ($c) {
                return [
                    'id'                  => $c->id,
                    'name'                => $c->name,
                    'position'            => $c->position,
                    'department'          => $c->department ?? 'Pengurus Inti',
                    'period'              => $c->period,
                    'start_date'          => $c->start_date?->format('Y-m-d'),
                    'end_date'            => $c->end_date?->format('Y-m-d'),
                    'photo'               => $c->photo_url,
                    'photo_url'           => $c->photo_url,
                    'is_active_effective' => $c->is_active_effective,
                ];
            });

        // Group by department
        $grouped = $committees->groupBy('department');

        return response()->json([
            'status' => 'success',
            'data' => [
                'organization' => [
                    'nama'           => $org->nama,
                    'subdomain'      => $org->subdomain,
                    'logo'           => $org->logo,
                    'current_period' => $currentOngoingPeriod?->period_name,
                ],
                'selected_period'   => $selectedPeriod ? [
                    'id'                => $selectedPeriod->id,
                    'period_name'       => $selectedPeriod->period_name,
                    'start_date'        => $selectedPeriod->start_date?->format('Y-m-d'),
                    'end_date'          => $selectedPeriod->end_date?->format('Y-m-d'),
                    'status'            => $selectedPeriod->status,
                    'calculated_status' => $selectedPeriod->calculated_status,
                    'is_ongoing'        => $selectedPeriod->isOngoing(),
                ] : null,
                'available_periods' => $availablePeriods,
                'members'           => $committees,
                'by_department'     => $grouped,
            ]
        ]);
    }

    /**
     * 12. GET /api/public/sitemap
     * Generate dynamic sitemap payload
     */
    public function sitemap()
    {
        $urls = [];
        $appUrl = config('app.url', 'https://ormawa.iti.ac.id');

        // Static index routes
        $urls[] = ['loc' => "{$appUrl}/", 'priority' => '1.0', 'changefreq' => 'daily'];
        $urls[] = ['loc' => "{$appUrl}/organizations", 'priority' => '0.9', 'changefreq' => 'daily'];

        // All Organizations
        $activeOrgs = Organization::all();
        foreach ($activeOrgs as $org) {
            $modules = $org->modul_aktif ?? [];
            if (($modules['public_website_enabled'] ?? true) === false) {
                continue;
            }

            $orgBase = "{$appUrl}/organizations/{$org->subdomain}";
            $urls[] = ['loc' => $orgBase, 'priority' => '0.8', 'changefreq' => 'weekly'];

            if (($modules['posts'] ?? true) !== false) {
                $urls[] = ['loc' => "{$orgBase}/articles", 'priority' => '0.8', 'changefreq' => 'daily'];
            }
            if (($modules['agenda'] ?? true) !== false) {
                $urls[] = ['loc' => "{$orgBase}/agenda", 'priority' => '0.7', 'changefreq' => 'weekly'];
            }
            if (($modules['announcements'] ?? true) !== false) {
                $urls[] = ['loc' => "{$orgBase}/announcements", 'priority' => '0.7', 'changefreq' => 'weekly'];
            }
            if (($modules['galeri'] ?? true) !== false) {
                $urls[] = ['loc' => "{$orgBase}/gallery", 'priority' => '0.6', 'changefreq' => 'weekly'];
            }
            if (($modules['structure'] ?? true) !== false) {
                $urls[] = ['loc' => "{$orgBase}/structure", 'priority' => '0.6', 'changefreq' => 'monthly'];
            }
        }

        // Published Articles (Only from orgs with posts module enabled)
        $articles = Post::withoutGlobalScopes()
            ->where('status', 'published')
            ->with('organization:id,subdomain,modul_aktif')
            ->get();

        foreach ($articles as $art) {
            $orgModules = $art->organization?->modul_aktif ?? [];
            if ($art->organization && ($orgModules['posts'] ?? true) !== false && ($orgModules['public_website_enabled'] ?? true) !== false) {
                $urls[] = [
                    'loc' => "{$appUrl}/organizations/{$art->organization->subdomain}/articles/{$art->slug}",
                    'lastmod' => $art->updated_at?->toAtomString(),
                    'priority' => '0.9',
                    'changefreq' => 'monthly',
                ];
            }
        }

        return response()->json([
            'status' => 'success',
            'data' => $urls,
        ]);
    }

    // ─── Formatters (Ensuring Zero Credential / Internal Metadata Leakage) ───

    /**
     * 10b. GET /api/public/organizations/{slug}/documents/{id}/download
     * Secure public download for document
     */
    public function downloadDocument(string $slug, int|string $id)
    {
        $org = $this->resolveOrganization($slug);
        $this->ensureModuleEnabled($org, 'documents');

        $doc = Media::withoutGlobalScopes()
            ->where('organization_id', $org->id)
            ->where('id', $id)
            ->firstOrFail();

        if ($doc->visibility === 'internal') {
            abort(403, 'Dokumen ini bersifat internal organisasi dan tidak dapat diunduh oleh publik.');
        }

        // Check if file exists in storage
        if ($doc->path && \Illuminate\Support\Facades\Storage::disk('public')->exists($doc->path)) {
            $headers = [];
            if ($doc->mime_type) {
                $headers['Content-Type'] = $doc->mime_type;
            }
            return \Illuminate\Support\Facades\Storage::disk('public')->download($doc->path, $doc->filename, $headers);
        }

        abort(404, 'Berkas fisik dokumen tidak ditemukan di server.');
    }

    private function formatArticle(Post $post): array
    {
        return [
            'id' => $post->id,
            'judul' => $post->judul,
            'slug' => $post->slug,
            'excerpt' => $post->excerpt ?? substr(strip_tags($post->konten), 0, 160) . '...',
            'cover_image' => $post->cover_image,
            'published_at' => $post->published_at?->toIso8601String() ?? $post->created_at?->toIso8601String(),
            'author' => [
                'id' => $post->user?->id,
                'name' => $post->user?->name ?? 'Tim Redaksi',
            ],
            'category' => $post->category ? [
                'id' => $post->category->id,
                'name' => $post->category->name,
                'slug' => $post->category->slug,
            ] : null,
            'tags' => $post->tags->map(fn($t) => [
                'id' => $t->id,
                'name' => $t->name,
                'slug' => $t->slug,
            ]),
            'organization' => $post->organization ? [
                'id' => $post->organization->id,
                'nama' => $post->organization->nama,
                'subdomain' => $post->organization->subdomain,
                'logo' => $post->organization->logo,
                'warna_tema' => $post->organization->warna_tema,
            ] : null,
            'seo' => [
                'meta_title' => $post->meta_title ?? "{$post->judul} | {$post->organization?->nama}",
                'meta_description' => $post->meta_description ?? ($post->excerpt ?? substr(strip_tags($post->konten), 0, 160)),
                'og_image' => $post->cover_image ?? $post->organization?->logo,
            ]
        ];
    }

    private function formatAgenda(Activity $activity): array
    {
        return [
            'id' => $activity->id,
            'judul' => $activity->judul,
            'deskripsi' => $activity->deskripsi,
            'tanggal_pelaksanaan' => $activity->tanggal_pelaksanaan ? Carbon::parse($activity->tanggal_pelaksanaan)->toDateString() : null,
            'tempat' => $activity->tempat ?? 'Kampus ITI',
            'published_at' => $activity->published_at?->toIso8601String() ?? $activity->created_at?->toIso8601String(),
            'organization' => $activity->organization ? [
                'id' => $activity->organization->id,
                'nama' => $activity->organization->nama,
                'subdomain' => $activity->organization->subdomain,
                'logo' => $activity->organization->logo,
                'warna_tema' => $activity->organization->warna_tema,
            ] : null,
        ];
    }

    private function formatAnnouncement(Announcement $announcement): array
    {
        return [
            'id' => $announcement->id,
            'title' => $announcement->title,
            'slug' => $announcement->slug,
            'content' => $announcement->content,
            'priority' => $announcement->priority ?? 'normal',
            'effective_date' => $announcement->effective_date?->toDateString(),
            'expires_at' => $announcement->expires_at?->toDateString(),
            'published_at' => $announcement->published_at?->toIso8601String() ?? $announcement->created_at?->toIso8601String(),
            'organization' => $announcement->organization ? [
                'id' => $announcement->organization->id,
                'nama' => $announcement->organization->nama,
                'subdomain' => $announcement->organization->subdomain,
                'logo' => $announcement->organization->logo,
                'warna_tema' => $announcement->organization->warna_tema,
            ] : null,
        ];
    }
}
