<?php

namespace App\Http\Controllers\Web;

use App\Enums\Mode;
use App\Http\Controllers\Controller;
use App\Integrations\GatewayManager;
use App\Integrations\Sandbox\SandboxGateway;
use App\Models\Payment;
use App\Services\Payments\PaymentService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

/**
 * TEST MODE checkout page. Reachable only through the signed checkout_url returned
 * for test-mode payments; lets the integrator choose the simulated outcome.
 */
class SandboxCheckoutController extends Controller
{
    public function show(Request $request, string $payment): View
    {
        $model = $this->find($payment);

        return view('sandbox.checkout', [
            'payment' => $model,
            'outcomes' => SandboxGateway::OUTCOMES,
            'actionUrl' => URL::temporarySignedRoute('sandbox.simulate', now()->addHour(), ['payment' => $model->public_id]),
        ]);
    }

    public function simulate(Request $request, string $payment, GatewayManager $gateways, PaymentService $payments): RedirectResponse
    {
        $model = $this->find($payment);
        $outcome = $request->validate(['outcome' => ['required', 'in:'.implode(',', SandboxGateway::OUTCOMES)]])['outcome'];

        if ($model->status->isOpen()) {
            $data = $gateways->sandbox($model->provider->code)->simulate($model, $outcome);
            $model = $payments->applyProviderData($model, $data, 'sandbox');
        }

        $target = match (true) {
            $model->status->isSettled() && $model->success_url !== null => $model->success_url,
            ! $model->status->isSettled() && $model->cancel_url !== null => $model->cancel_url,
            default => null,
        };

        return $target
            ? redirect()->away($target)
            : redirect()->to(URL::temporarySignedRoute('sandbox.checkout', now()->addHour(), ['payment' => $model->public_id]))
                ->with('success', 'Simulated outcome applied: '.$model->status->label());
    }

    private function find(string $publicId): Payment
    {
        return Payment::query()->with(['provider', 'merchant'])
            ->where('public_id', $publicId)
            ->where('mode', Mode::Test->value)
            ->firstOrFail();
    }
}
