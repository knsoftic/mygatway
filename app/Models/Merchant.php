<?php

namespace App\Models;

use App\Enums\MerchantStatus;
use App\Enums\Mode;
use App\Enums\VerificationStatus;
use App\Support\PublicId;
use Database\Factories\MerchantFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['name', 'business_email', 'business_website', 'owner_user_id'])]
class Merchant extends Model
{
    /** @use HasFactory<MerchantFactory> */
    use HasFactory;

    protected $attributes = [
        'status' => 'pending',
        'verification_status' => 'unverified',
        'live_enabled' => false,
    ];

    protected static function booted(): void
    {
        static::creating(function (Merchant $merchant) {
            $merchant->public_id ??= PublicId::make('mch');
        });
    }

    protected function casts(): array
    {
        return [
            'status' => MerchantStatus::class,
            'verification_status' => VerificationStatus::class,
            'live_enabled' => 'boolean',
            'live_enabled_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function profile(): HasOne
    {
        return $this->hasOne(MerchantProfile::class);
    }

    public function verifications(): HasMany
    {
        return $this->hasMany(MerchantVerification::class);
    }

    public function apiKeys(): HasMany
    {
        return $this->hasMany(MerchantApiKey::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function payouts(): HasMany
    {
        return $this->hasMany(Payout::class);
    }

    public function webhookEndpoints(): HasMany
    {
        return $this->hasMany(WebhookEndpoint::class);
    }

    public function limits(): HasMany
    {
        return $this->hasMany(MerchantLimit::class);
    }

    public function feeRules(): HasMany
    {
        return $this->hasMany(FeeRule::class);
    }

    /**
     * Live mode requires an active account, approved business verification
     * and explicit admin enablement.
     */
    public function isLiveEnabled(): bool
    {
        return $this->status === MerchantStatus::Active
            && $this->verification_status === VerificationStatus::Approved
            && $this->live_enabled;
    }

    public function canTransact(Mode $mode): bool
    {
        if (! $this->status->canTransact()) {
            return false;
        }

        return $mode === Mode::Test || $this->isLiveEnabled();
    }
}
