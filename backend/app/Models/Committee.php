<?php

namespace App\Models;

use App\Traits\BelongsToOrganization;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class Committee extends Model
{
    use HasFactory, BelongsToOrganization;

    protected $fillable = [
        'organization_id',
        'organization_period_id',
        'user_id',
        'member_id',
        'name',
        'position',
        'department',
        'period',
        'start_date',
        'end_date',
        'photo',
        'status',
        'status_mode',
        'status_reason',
        'status_changed_by',
        'status_changed_at',
        'notes',
    ];

    protected $appends = [
        'photo_url',
        'is_active_effective',
        'effective_status',
        'status_changed_by_user',
    ];

    protected function casts(): array
    {
        return [
            'start_date'        => 'date',
            'end_date'          => 'date',
            'status_changed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function statusChangedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'status_changed_by');
    }

    public function organizationPeriod(): BelongsTo
    {
        return $this->belongsTo(OrganizationPeriod::class, 'organization_period_id');
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function getPhotoUrlAttribute(): ?string
    {
        return $this->photo ? url(Storage::url($this->photo)) : null;
    }

    public function getStatusChangedByUserAttribute()
    {
        if ($this->relationLoaded('statusChangedBy')) {
            return $this->getRelation('statusChangedBy');
        }
        return $this->status_changed_by ? $this->statusChangedBy()->first(['id', 'name', 'email']) : null;
    }

    /**
     * Nama fungsionaris selalu bersumber tunggal dari data entitas Member jika terhubung
     */
    public function getNameAttribute(): string
    {
        if ($this->relationLoaded('member') && $this->member) {
            return $this->member->name;
        }

        if ($this->member_id) {
            $mem = Member::find($this->member_id);
            if ($mem) {
                return $mem->name;
            }
        }

        return $this->attributes['name'] ?? '';
    }

    /**
     * Status efektif pengurus:
     * 1. Kelayakan anggota: Anggota nonaktif/alumni TIDAK BOLEH menjadi pengurus aktif.
     * 2. Evaluasi periode:
     *    - Periode yang telah berakhir (expired) otomatis berstatus 'expired'.
     *    - Periode yang belum mulai (upcoming) otomatis berstatus 'upcoming'.
     *    - Periode yang berakhir TIDAK DAPAT menjadi aktif pada periode berjalan meskipun status manual diset aktif!
     * 3. Mode Manual & Mode Otomatis pada periode yang sedang berlangsung (ongoing):
     *    - Mode Manual: admin dapat menonaktifkan penempatan (misal reshuffle / mengundurkan diri).
     *    - Mode Otomatis: mengikuti status penempatan dan periode yang berjalan.
     */
    public function getEffectiveStatusAttribute(): string
    {
        // 1. Periksa status anggota
        $memberModel = $this->relationLoaded('member') ? $this->member : ($this->member_id ? Member::find($this->member_id) : null);
        if ($memberModel && $memberModel->status !== 'active') {
            return 'inactive';
        }

        // 2. Evaluasi status periode
        $periodStatus = null;
        if ($this->organizationPeriod) {
            $periodStatus = $this->organizationPeriod->calculated_status;
        } elseif ($this->organization_period_id) {
            $orgP = OrganizationPeriod::find($this->organization_period_id);
            $periodStatus = $orgP?->calculated_status;
        } else {
            $tz = config('app.timezone', 'Asia/Jakarta');
            $today = Carbon::today($tz);
            if ($this->end_date && $today->gt(Carbon::parse($this->end_date, $tz)->endOfDay())) {
                $periodStatus = 'expired';
            } elseif ($this->start_date && $today->lt(Carbon::parse($this->start_date, $tz)->startOfDay())) {
                $periodStatus = 'upcoming';
            } else {
                $periodStatus = 'ongoing';
            }
        }

        // Aturan mutlak: Periode yang sudah berakhir tidak boleh tampil aktif
        if ($periodStatus === 'expired') {
            return 'expired';
        }

        if ($periodStatus === 'upcoming') {
            return 'upcoming';
        }

        // 3. Periode sedang berlangsung (ongoing / active)
        if ($this->status_mode === 'manual') {
            return $this->status === 'active' ? 'active' : 'inactive';
        }

        // Mode otomatis: Otomatis AKTIF selama periode berlangsung dan anggota aktif
        return 'active';
    }

    /**
     * Apakah pengurus ini efektif aktif saat ini
     */
    public function getIsActiveEffectiveAttribute(): bool
    {
        return $this->effective_status === 'active';
    }

    /**
     * Scope query untuk hanya mengambil pengurus yang AKTIF SECARA EFEKTIF
     */
    public function scopeEffectiveActive(Builder $query): Builder
    {
        $tz = config('app.timezone', 'Asia/Jakarta');
        $today = Carbon::today($tz)->toDateString();

        return $query->where(function ($q) {
                $q->where(function ($qAuto) {
                    $qAuto->whereNull('committees.status_mode')
                          ->orWhere('committees.status_mode', 'auto');
                })->orWhere(function ($qManual) {
                    $qManual->where('committees.status_mode', 'manual')
                            ->where('committees.status', 'active');
                });
            })
            ->where(function ($q) {
                $q->whereDoesntHave('member')
                  ->orWhereHas('member', function ($qm) {
                      $qm->where('status', 'active');
                  });
            })
            ->where(function ($q) use ($today) {
                $q->whereHas('organizationPeriod', function ($qp) use ($today) {
                    $qp->where('status', '!=', 'rejected')
                       ->where('status', '!=', 'pending')
                       ->whereDate('start_date', '<=', $today)
                       ->whereDate('end_date', '>=', $today);
                })->orWhere(function ($qpNull) use ($today) {
                    $qpNull->whereNull('organization_period_id')
                           ->where(function ($qDate) use ($today) {
                               $qDate->whereNull('end_date')
                                     ->orWhereDate('end_date', '>=', $today);
                           });
                });
            });
    }
}
