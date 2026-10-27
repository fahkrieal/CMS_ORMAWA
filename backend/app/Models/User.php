<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles; 

#[Fillable(['organization_id', 'name', 'email', 'avatar', 'password', 'status'])] 
#[Hidden(['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, HasRoles, HasApiTokens; 

    protected string $guard_name = 'web'; 

    protected $attributes = [
        'status' => 'active',
    ]; 

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_secret' => 'encrypted',
            'two_factor_recovery_codes' => 'array',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    /**
     * Cek apakah autentikasi dua faktor aktif dan terkonfirmasi
     */
    public function hasTwoFactorEnabled(): bool
    {
        return !empty($this->two_factor_confirmed_at) && !empty($this->two_factor_secret);
    }

    /**
     * Relasi ke model Organization
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}