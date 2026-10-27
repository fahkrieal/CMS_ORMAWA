<?php

namespace App\Models;

use App\Traits\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Member extends Model
{
    use HasFactory, BelongsToOrganization;

    public const STATUS_ACTIVE = 'active';
    public const STATUS_INACTIVE = 'inactive';
    public const STATUS_ALUMNI = 'alumni';

    protected $fillable = [
        'organization_id',
        'user_id',
        'nim',
        'name',
        'nama',
        'email',
        'phone',
        'telepon',
        'study_program',
        'jurusan',
        'join_date',
        'angkatan',
        'status',
        'notes',
        'catatan',
    ];

    protected $appends = [
        'nama',
        'telepon',
        'jurusan',
        'catatan',
    ];

    protected function casts(): array
    {
        return [
            'join_date' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function committees(): HasMany
    {
        return $this->hasMany(Committee::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    // Accessors & Mutators for Indonesian Aliases
    public function getNamaAttribute(): ?string
    {
        return $this->attributes['name'] ?? null;
    }

    public function setNamaAttribute($value): void
    {
        $this->attributes['name'] = $value;
    }

    public function getTeleponAttribute(): ?string
    {
        return $this->attributes['phone'] ?? null;
    }

    public function setTeleponAttribute($value): void
    {
        $this->attributes['phone'] = $value;
    }

    public function getJurusanAttribute(): ?string
    {
        return $this->attributes['study_program'] ?? null;
    }

    public function setJurusanAttribute($value): void
    {
        $this->attributes['study_program'] = $value;
    }

    public function getCatatanAttribute(): ?string
    {
        return $this->attributes['notes'] ?? null;
    }

    public function setCatatanAttribute($value): void
    {
        $this->attributes['notes'] = $value;
    }

    public function getAngkatanAttribute(): ?string
    {
        if (!empty($this->attributes['join_date'])) {
            return substr($this->attributes['join_date'], 0, 4);
        }
        return null;
    }

    public function setAngkatanAttribute($value): void
    {
        if ($value && strlen((string)$value) === 4) {
            $this->attributes['join_date'] = "{$value}-09-01";
        }
    }

    protected static function booted()
    {
        static::updated(function ($member) {
            if ($member->wasChanged('name')) {
                Committee::where('member_id', $member->id)->update(['name' => $member->name]);
            }
        });
    }

    /**
     * Check if member is currently an active committee in ongoing period
     */
    public function currentCommittee()
    {
        return $this->committees()
            ->where('status', 'active')
            ->whereHas('organizationPeriod', function ($q) {
                $today = now()->toDateString();
                $q->where('status', '!=', 'rejected')
                  ->whereDate('start_date', '<=', $today)
                  ->whereDate('end_date', '>=', $today);
            })
            ->latest()
            ->first();
    }
}
