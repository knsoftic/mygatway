<?php

namespace App\Services\Merchants;

use App\Enums\MerchantStatus;
use App\Enums\VerificationStatus;
use App\Exceptions\GatewayException;
use App\Models\Merchant;
use App\Models\MerchantProfile;
use App\Models\MerchantVerification;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * Merchant registration, business verification (KYB) and account status changes.
 */
class MerchantOnboardingService
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function register(User $owner, string $businessName, ?string $website): Merchant
    {
        return DB::transaction(function () use ($owner, $businessName, $website) {
            $merchant = Merchant::query()->create([
                'name' => $businessName,
                'business_email' => $owner->email,
                'business_website' => $website,
                'owner_user_id' => $owner->id,
            ]);
            MerchantProfile::query()->create(['merchant_id' => $merchant->id, 'contact_name' => $owner->name]);
            $owner->forceFill(['merchant_id' => $merchant->id])->save();

            $this->audit->log('merchant.registered', $merchant, new: ['name' => $businessName], user: $owner, merchantId: $merchant->id);

            return $merchant;
        });
    }

    /** @param  array<string, mixed>  $data */
    public function submitVerification(Merchant $merchant, User $user, array $data, ?UploadedFile $document): MerchantVerification
    {
        if (in_array($merchant->verification_status, [VerificationStatus::Submitted, VerificationStatus::Approved], true)) {
            throw new GatewayException('invalid_state', 'Verification is already '.strtolower($merchant->verification_status->label()).'.');
        }

        return DB::transaction(function () use ($merchant, $user, $data, $document) {
            $merchant->profile()->updateOrCreate(['merchant_id' => $merchant->id], $data);

            $path = $document?->store('verification/'.$merchant->public_id, 'local');

            $verification = MerchantVerification::query()->create([
                'merchant_id' => $merchant->id,
                'status' => 'submitted',
                'submitted_data' => $data,
                'document_path' => $path,
                'document_original_name' => $document ? mb_substr($document->getClientOriginalName(), 0, 255) : null,
                'submitted_by' => $user->id,
            ]);

            $merchant->forceFill(['verification_status' => VerificationStatus::Submitted])->save();
            $this->audit->log('merchant.verification_submitted', $verification, user: $user, merchantId: $merchant->id);

            return $verification;
        });
    }

    public function approveVerification(MerchantVerification $verification, User $admin, bool $enableLive, ?string $note): void
    {
        DB::transaction(function () use ($verification, $admin, $enableLive, $note) {
            $merchant = $verification->merchant;

            $verification->forceFill(['status' => 'approved', 'reviewed_by' => $admin->id, 'reviewed_at' => now(), 'review_note' => $note])->save();

            $changes = ['verification_status' => VerificationStatus::Approved];
            if ($merchant->status === MerchantStatus::Pending && SystemSetting::get('merchants.auto_activate_on_verification', true)) {
                $changes['status'] = MerchantStatus::Active;
            }
            if ($enableLive) {
                $changes['live_enabled'] = true;
                $changes['live_enabled_at'] = now();
            }
            $merchant->forceFill($changes)->save();

            $this->audit->log('merchant.verification_approved', $verification, new: ['live_enabled' => $enableLive], context: ['note' => $note], user: $admin, merchantId: $merchant->id);
        });
    }

    public function rejectVerification(MerchantVerification $verification, User $admin, string $note): void
    {
        DB::transaction(function () use ($verification, $admin, $note) {
            $verification->forceFill(['status' => 'rejected', 'reviewed_by' => $admin->id, 'reviewed_at' => now(), 'review_note' => $note])->save();
            $verification->merchant->forceFill(['verification_status' => VerificationStatus::Rejected])->save();
            $this->audit->log('merchant.verification_rejected', $verification, context: ['note' => $note], user: $admin, merchantId: $verification->merchant_id);
        });
    }

    public function changeStatus(Merchant $merchant, MerchantStatus $status, ?string $reason, User $admin): void
    {
        $old = $merchant->status;
        $merchant->forceFill(['status' => $status, 'status_reason' => $reason])->save();
        $this->audit->log('merchant.status_changed', $merchant, ['status' => $old->value], ['status' => $status->value], ['reason' => $reason], $admin);
    }

    public function setLiveEnabled(Merchant $merchant, bool $enabled, User $admin): void
    {
        if ($enabled && $merchant->verification_status !== VerificationStatus::Approved) {
            throw new GatewayException('verification_required', 'Live mode requires approved business verification.');
        }

        $merchant->forceFill(['live_enabled' => $enabled, 'live_enabled_at' => $enabled ? now() : $merchant->live_enabled_at])->save();
        $this->audit->log($enabled ? 'merchant.live_enabled' : 'merchant.live_disabled', $merchant, user: $admin);
    }
}
