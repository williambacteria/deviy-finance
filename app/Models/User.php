<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Auth\MustVerifyEmail as MustVerifyEmailTrait;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable([
    'name',
    'email',
    'phone',
    'qq',
    'company',
    'quiet_hours_start',
    'quiet_hours_end',
    'password',
    'pterodactyl_password',
    'balance',
    'credit_limit',
    'status',
    'real_name_status',
    'real_name_type',
    'real_name',
    'real_name_company',
    'real_name_credit_code',
    'real_name_legal_name',
    'real_name_legal_phone',
    'real_name_license',
    'real_name_legal_id',
    'real_name_id',
    'real_name_phone',
    'real_name_submitted_at',
    'real_name_verified_at',
    'real_name_reject_reason',
    'customer_group',
    'last_login_at',
    'last_seen_at',
    'notes',
    'metadata',
])]
#[Hidden(['password', 'remember_token', 'pterodactyl_password', 'real_name_id', 'real_name_phone', 'real_name_legal_phone', 'real_name_license', 'real_name_legal_id'])]
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, MustVerifyEmailTrait, Notifiable, SoftDeletes;

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
            'pterodactyl_password' => 'encrypted',
            'real_name' => 'encrypted',
            'real_name_legal_name' => 'encrypted',
            'real_name_legal_phone' => 'encrypted',
            'real_name_license' => 'encrypted:array',
            'real_name_legal_id' => 'encrypted:array',
            'real_name_id' => 'encrypted',
            'real_name_phone' => 'encrypted',
            'real_name_submitted_at' => 'datetime',
            'real_name_verified_at' => 'datetime',
            'balance' => 'decimal:6',
            'credit_limit' => 'decimal:6',
            'last_login_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function services(): HasMany
    {
        return $this->hasMany(Service::class);
    }

    public function hasVerifiedRealName(): bool
    {
        return $this->real_name_status === 'verified';
    }

    public function maskedRealNameId(): string
    {
        $id = (string) ($this->real_name_id ?? '');
        if (mb_strlen($id) < 8) {
            return $id;
        }

        return mb_substr($id, 0, 4).'**********'.mb_substr($id, -4);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function notices(): HasMany
    {
        return $this->hasMany(Notice::class);
    }

    public function notificationPreferences(): HasMany
    {
        return $this->hasMany(NotificationPreference::class);
    }

    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(LedgerEntry::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
