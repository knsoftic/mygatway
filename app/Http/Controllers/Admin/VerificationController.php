<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MerchantVerification;
use App\Services\Audit\AuditLogger;
use App\Services\Merchants\MerchantOnboardingService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class VerificationController extends Controller
{
    public function __construct(private readonly MerchantOnboardingService $onboarding) {}

    public function index(Request $request): View
    {
        $status = $request->validate(['status' => ['nullable', 'in:submitted,approved,rejected']])['status'] ?? 'submitted';

        return view('admin.verifications.index', [
            'status' => $status,
            'verifications' => MerchantVerification::query()->with(['merchant', 'submitter', 'reviewer'])
                ->where('status', $status)->oldest('id')->paginate(25)->withQueryString(),
        ]);
    }

    public function show(MerchantVerification $verification): View
    {
        return view('admin.verifications.show', ['v' => $verification->load(['merchant.owner', 'submitter', 'reviewer'])]);
    }

    public function document(MerchantVerification $verification, AuditLogger $audit): StreamedResponse
    {
        abort_if(! $verification->document_path || ! Storage::disk('local')->exists($verification->document_path), 404);
        $audit->log('merchant.verification_document_viewed', $verification, merchantId: $verification->merchant_id);

        // Served as a download with the stored MIME type; never rendered inline from user input.
        return Storage::disk('local')->download($verification->document_path, 'verification-'.$verification->id.'.'.pathinfo($verification->document_path, PATHINFO_EXTENSION));
    }

    public function approve(Request $request, MerchantVerification $verification): RedirectResponse
    {
        abort_unless($verification->status === 'submitted', 409, 'Already reviewed.');
        $v = $request->validate(['note' => ['nullable', 'string', 'max:1000']]);
        $this->onboarding->approveVerification($verification, $request->user(), $request->boolean('enable_live'), $v['note'] ?? null);

        return redirect()->route('admin.verifications.index')->with('success', 'Verification approved.');
    }

    public function reject(Request $request, MerchantVerification $verification): RedirectResponse
    {
        abort_unless($verification->status === 'submitted', 409, 'Already reviewed.');
        $v = $request->validate(['note' => ['required', 'string', 'max:1000']]);
        $this->onboarding->rejectVerification($verification, $request->user(), $v['note']);

        return redirect()->route('admin.verifications.index')->with('success', 'Verification rejected; the merchant can resubmit.');
    }
}
