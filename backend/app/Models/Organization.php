<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Organization extends Model
{
    protected $fillable = [
        'nama',
        'jenis',
        'subdomain',
        'email',
        'telepon',
        'alamat',
        'media_sosial',
        'logo',
        'hero_image',
        'warna_tema',
        'modul_aktif',
        'label_menu',
        'status',
        'ga_tracking_id',
    ];

    protected function casts(): array
    {
        return [
            'modul_aktif'  => 'array',
            'label_menu'   => 'array',
            'media_sosial' => 'array',
        ];
    }

    /**
     * Mendapatkan link / handle akun media sosial tertentu
     */
    public function getSocialMedia(string $platform, ?string $default = null): ?string
    {
        $socials = $this->media_sosial;
        if (!empty($socials) && is_array($socials) && !empty($socials[$platform])) {
            return $socials[$platform];
        }
        return $default;
    }

    /**
     * Format URL WhatsApp yang siap diklik (wa.me/...)
     */
    public function getWhatsAppUrl(): ?string
    {
        $phone = $this->telepon ?? $this->getSocialMedia('whatsapp');
        if (empty($phone)) {
            return null;
        }

        $clean = preg_replace('/[^0-9]/', '', $phone);
        if (str_starts_with($clean, '0')) {
            $clean = '62' . substr($clean, 1);
        }

        return "https://wa.me/{$clean}";
    }

    /**
     * Mengecek apakah modul tertentu aktif
     */
    public function isModuleActive(string $moduleKey): bool
    {
        $modules = $this->modul_aktif;
        if (empty($modules) || !is_array($modules)) {
            return true; // Default aktif jika belum dikonfigurasi
        }
        return isset($modules[$moduleKey]) ? (bool) $modules[$moduleKey] : true;
    }

    /**
     * Mendapatkan label kustom menu untuk modul tertentu
     */
    public function getMenuLabel(string $moduleKey, string $defaultLabel): string
    {
        $labels = $this->label_menu;
        if (!empty($labels) && is_array($labels) && !empty($labels[$moduleKey])) {
            return $labels[$moduleKey];
        }
        return $defaultLabel;
    }

    /**
     * Relasi ke semua User dalam organisasi ini
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * Relasi ke semua Period dalam organisasi ini
     */
    public function periods(): HasMany
    {
        return $this->hasMany(OrganizationPeriod::class);
    }

    /**
     * Relasi ke semua Anggota dalam organisasi ini
     */
    public function members(): HasMany
    {
        return $this->hasMany(Member::class);
    }

    public function committees(): HasMany
    {
        return $this->hasMany(Committee::class);
    }

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class);
    }

    public function announcements(): HasMany
    {
        return $this->hasMany(Announcement::class);
    }

    public function media(): HasMany
    {
        return $this->hasMany(Media::class);
    }

    /**
     * Mendapatkan period aktif saat ini berbasis tanggal berjalan
     */
    public function currentPeriod()
    {
        $tz = config('app.timezone', 'Asia/Jakarta');
        $today = now($tz)->toDateString();

        // 1. Cari periode aktif/berjalan yang rentang tanggalnya melingkupi hari ini
        $ongoing = $this->periods()
            ->where('status', '!=', 'rejected')
            ->where('status', '!=', 'pending')
            ->whereDate('start_date', '<=', $today)
            ->whereDate('end_date', '>=', $today)
            ->latest('start_date')
            ->first();

        if ($ongoing) {
            return $ongoing;
        }

        // 2. Fallback jika ada periode yang status DB-nya active (kompatibilitas data lama)
        return $this->periods()
            ->where('status', 'active')
            ->latest('end_date')
            ->first();
    }

    /**
     * Mengecek apakah organisasi beroperasi (aktif & punya period aktif)
     */
    public function isActive(): bool
    {
        if ($this->status !== 'active') {
            return false;
        }
        
        $periodsCount = $this->periods()->count();
        if ($periodsCount === 0) {
            return true;
        }

        return $this->currentPeriod() !== null;
    }
}