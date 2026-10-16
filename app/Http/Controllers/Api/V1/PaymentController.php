<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\CreatePaymentRequest;
use App\Http\Requests\Api\ListRequest;
use App\Http\Resources\PaymentResource;
use App\Http\Responses\ApiError;
use App\Models\Payment;
use App\Services\Payments\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\RateLimiter;

class PaymentController extends Controller
{
    public function __construct(private readonly PaymentService $payments) {}

    /** POST /api/v1/payments */
    public function store(CreatePaymentRequest $request): JsonResponse
    {
        $payment = $this->payments->create($request->merchant(), $request->apiKey(), $request->mode(), $request->validated());

        return (new PaymentResource($payment))->response()->setStatusCode(201);
    }

    /** GET /api/v1/payments/{id} */
    public function show(Request $request, string $id): PaymentResource|JsonResponse
    {
        $payment = $this->find($request, $id);

        return $payment ? new PaymentResource($payment) : ApiError::make('resource_not_found', 'No such payment.', 404);
    }

    /** GET /api/v1/payments */
    public function index(ListRequest $request): AnonymousResourceCollection
    {
        $payments = Payment::query()
            ->with('provider')
            ->where('merchant_id', $request->merchant()->id)
            ->where('mode', $request->mode()->value)
            ->when($request->validated('status'), fn ($q, $s) => $q->where('status', $s))
            ->when($request->validated('currency'), fn ($q, $c) => $q->where('currency', strtoupper($c)))
            ->when($request->validated('reference'), fn ($q, $r) => $q->where('merchant_reference', $r))
            ->when($request->validated('created_from'), fn ($q, $d) => $q->where('created_at', '>=', $d))
            ->when($request->validated('created_to'), fn ($q, $d) => $q->where('created_at', '<=', $d))
            ->orderByDesc('id')
            ->cursorPaginate($request->limit());

        return PaymentResource::collection($payments);
    }

    /**
     * POST /api/v1/payments/{id}/verify
     * Ask the provider for the current status (server-to-server). Rate limited per payment.
     */
    public function verify(Request $request, string $id): PaymentResource|JsonResponse
    {
        $payment = $this->find($request, $id);
        if (! $payment) {
            return ApiError::make('resource_not_found', 'No such payment.', 404);
        }

        $limiterKey = 'verify-payment:'.$payment->id;
        if (RateLimiter::tooManyAttempts($limiterKey, 6)) {
            return ApiError::make('rate_limited', 'Status checks for this payment are limited. Retry in a minute.', 429);
        }
        RateLimiter::hit($limiterKey, 60);

        if ($payment->mode->value === 'live' && ($payment->status->isOpen() || $payment->status->value === 'refund_pending')) {
            $payment = $this->payments->syncWithProvider($payment, 'api');
        }

        return new PaymentResource($payment->load('provider'));
    }

    /** Merchant isolation: a payment is only visible to its own merchant and mode. */
    private function find(Request $request, string $id): ?Payment
    {
        return Payment::query()
            ->with('provider')
            ->where('public_id', $id)
            ->where('merchant_id', $request->attributes->get('merchant')->id)
            ->where('mode', $request->attributes->get('mode')->value)
            ->first();
    }
}
