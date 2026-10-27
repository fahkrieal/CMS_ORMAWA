<?php

namespace App\Models;

use App\Traits\BelongsToOrganization;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrganizationPeriod extends Model
{
    use HasFactory, BelongsToOrganization;

    protected $fillable = [
        'organization_id',
        'period_name',
        'start_date',
        'end_date',
        'status',
        'notes',
        'rejection_reason',
        'approved_by',
        'approved_at',
    ];

    protected $appends = [
        'calculated_status',
        'is_ongoing',
        'is_expired',
        'is_upcoming',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'approved_at' => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function committees(): HasMany
    {
        return $this->hasMany(Committee::class);
    }

    /**
     * Hitung status otomatis berbasis tanggal dan timezone aplikasi (Asia/Jakarta)
     * Aturan:
     * - Pending / Rejected tetap sesuai approval status.
     * - Sebelum start_date: 'upcoming' (akan datang).
     * - Antara start_date s/d end_date (inklusif end_date): 'active' (berjalan).
     * - Setelah end_date: 'expired' (berakhir).
     */
    public function getCalculatedStatusAttribute(): string
    {
        if ($this->status === 'rejected') {
            return 'rejected';
        }
        if ($this->status === 'pending') {
            return 'pending';
        }

        $tz = config('app.timezone', 'Asia/Jakarta');
        $today = Carbon::today($tz);
        
        $startDate = $this->start_date ? Carbon::parse($this->start_date, $tz)->startOfDay() : null;
        $endDate = $this->end_date ? Carbon::parse($this->end_date, $tz)->endOfDay() : null;

        if ($startDate && $today->lt($startDate)) {
            return 'upcoming';
        }

        if ($endDate && $today->gt($endDate)) {
            return 'expired';
        }

        return 'ongoing';
    }

    public function getIsOngoingAttribute(): bool
    {
        return $this->isOngoing();
    }

    public function getIsExpiredAttribute(): bool
    {
        return $this->isExpired();
    }

    public function getIsUpcomingAttribute(): bool
    {
        return $this->isUpcoming();
    }

    protected static function booted()
    {
        static::updated(function ($period) {
            if ($period->wasChanged('period_name')) {
                Committee::where('organization_period_id', $period->id)->update(['period' => $period->period_name]);
            }
        });
    }

    /**
     * Apakah periode ini saat ini sedang berjalan sah (active & dalam rentang tanggal)
     */
    public function isOngoing(): bool
    {
        if (in_array($this->status, ['rejected', 'pending'])) {
            return false;
        }

        $tz = config('app.timezone', 'Asia/Jakarta');
        $today = Carbon::today($tz);
        
        $startDate = $this->start_date ? Carbon::parse($this->start_date, $tz)->startOfDay() : null;
        $endDate = $this->end_date ? Carbon::parse($this->end_date, $tz)->endOfDay() : null;

        if ($startDate && $today->lt($startDate)) {
            return false;
        }

        if ($endDate && $today->gt($endDate)) {
            return false;
        }

        return true;
    }

    /**
     * Apakah periode ini telah berakhir
     */
    public function isExpired(): bool
    {
        if ($this->status === 'rejected') {
            return false;
        }

        $tz = config('app.timezone', 'Asia/Jakarta');
        $today = Carbon::today($tz);
        $endDate = $this->end_date ? Carbon::parse($this->end_date, $tz)->endOfDay() : null;

        return $endDate && $today->gt($endDate);
    }

    /**
     * Apakah periode ini belum dimulai (akan datang)
     */
    public function isUpcoming(): bool
    {
        if ($this->status === 'rejected') {
            return false;
        }

        $tz = config('app.timezone', 'Asia/Jakarta');
        $today = Carbon::today($tz);
        $startDate = $this->start_date ? Carbon::parse($this->start_date, $tz)->startOfDay() : null;

        return $startDate && $today->lt($startDate);
    }
}
