<?php

namespace App\Http\Controllers\Merchant;

use App\Enums\VerificationStatus;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Merchants\MerchantOnboardingService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Merchant business profile, verification (KYB) submission and notification preferences.
 */
class AccountController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function editProfile(Request $request): View
    {
        $merchant = $request->user()->merchant->load('profile');

        return view('merchant.account.profile', ['merchant' => $merchant, 'profile' => $merchant->profile]);
    }

    public function updateProfile(Request $request): RedirectResponse
    {
        $merchant = $request->user()->merchant;
        abort_unless($request->user()->id === $merchant->owner_user_id, 403, 'Only the account owner can change business details.');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'business_email' => ['required', 'email:rfc', 'max:255'],
            'business_website' => ['nullable', 'url:https,http', 'max:255'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:40', 'regex:/^[0-9+()\-\s]{5,40}$/'],
            'support_email' => ['nullable', 'email:rfc', 'max:255'],
            'business_description' => ['nullable', 'string', 'max:2000'],
        ]);

        // The verified legal identity can only change through a new verification.
        if ($merchant->verification_status === VerificationStatus::Approved && $data['name'] !== $merchant->name) {
            return back()->with('error', 'Your business name is verified. Contact support to change it.');
        }

        $merchant->fill(collect($data)->only(['name', 'business_email', 'business_website'])->all())->save();
        $this->audit->logChanges('merchant.profile_updated', $merchant);

        $profile = $merchant->profile()->firstOrCreate(['merchant_id' => $merchant->id]);
        $profile->fill(collect($data)->only(['contact_name', 'contact_phone', 'support_email', 'business_description'])->all())->save();
        $this->audit->logChanges('merchant.contact_updated', $profile);

        return back()->with('success', 'Business profile saved.');
    }

    public function showVerification(Request $request): View
    {
        $merchant = $request->user()->merchant->load('profile');

        return view('merchant.account.verification', [
            'merchant' => $merchant,
            'profile' => $merchant->profile,
            'latest' => $merchant->verifications()->latest('id')->first(),
        ]);
    }

    public function submitVerification(Request $request, MerchantOnboardingService $onboarding): RedirectResponse
    {
        abort_unless($request->user()->id === $request->user()->merchant->owner_user_id, 403);

        $data = $request->validate([
            'legal_name' => ['required', 'string', 'max:255'],
            'registration_number' => ['required', 'string', 'max:100'],
            'country' => ['required', 'string', 'size:2', 'alpha'],
            'address_line1' => ['required', 'string', 'max:255'],
            'address_line2' => ['nullable', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:120'],
            'state' => ['nullable', 'string', 'max:120'],
            'postal_code' => ['nullable', 'string', 'max:32'],
            'business_description' => ['required', 'string', 'min:20', 'max:2000'],
            'document' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'declaration' => ['accepted'],
        ]);

        $data['country'] = strtoupper($data['country']);
        $onboarding->submitVerification($request->user()->merchant, $request->user(),
            collect($data)->except(['document', 'declaration'])->all(), $request->file('document'));

        return back()->with('success', 'Verification submitted. We will review it and notify you by email.');
    }

    public function editNotifications(Request $request): View
    {
        return view('merchant.account.notifications', [
            'prefs' => array_merge(User::NOTIFICATION_DEFAULTS, $request->user()->notification_preferences ?? []),
        ]);
    }

    public function updateNotifications(Request $request): RedirectResponse
    {
        $keys = array_keys(User::NOTIFICATION_DEFAULTS);
        $request->validate(['prefs' => ['nullable', 'array'], 'prefs.*' => [Rule::in($keys)]]);
        $selected = $request->input('prefs', []);

        $prefs = collect($keys)->mapWithKeys(fn ($k) => [$k => in_array($k, $selected, true)])->all();
        $prefs['security_alerts'] = true; // always on

        $request->user()->forceFill(['notification_preferences' => $prefs])->save();

        return back()->with('success', 'Notification preferences saved.');
    }

    public function notifications(Request $request): View
    {
        $user = $request->user();
        $list = $user->notifications()->latest()->paginate(20);
        $user->unreadNotifications()->update(['read_at' => now()]);

        return view('account.notifications', ['notifications' => $list, 'area' => $user->merchant_id ? 'merchant' : 'admin']);
    }
}
