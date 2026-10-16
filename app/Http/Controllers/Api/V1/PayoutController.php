<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\CreatePayoutRequest;
use App\Http\Requests\Api\ListRequest;
use App\Http\Resources\PayoutResource;
use App\Http\Responses\ApiError;
use App\Models\Payout;
use App\Services\Payouts\PayoutService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PayoutController extends Controller
{
    public function __construct(private readonly PayoutService $payouts) {}

    /** POST /api/v1/payouts */
    public function store(CreatePayoutRequest $request): JsonResponse
    {
        $payout = $this->payouts->request($request->merchant(), $request->apiKey(), null, $request->mode(), $request->payoutInput());

        return (new PayoutResource($payout))->response()->setStatusCode(201);
    }

    /** GET /api/v1/payouts/{id} */
    public function show(Request $request, string $id): PayoutResource|JsonResponse
    {
        $payout = $this->find($request, $id);

        return $payout ? new PayoutResource($payout) : ApiError::make('resource_not_found', 'No such payout.', 404);
    }

    /** GET /api/v1/payouts */
    public function index(ListRequest $request): AnonymousResourceCollection
    {
        $payouts = Payout::query()
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

        return PayoutResource::collection($payouts);
    }

    /** POST /api/v1/payouts/{id}/cancel - only while requested or under review. */
    public function cancel(Request $request, string $id): PayoutResource|JsonResponse
    {
        $payout = $this->find($request, $id);
        if (! $payout) {
            return ApiError::make('resource_not_found', 'No such payout.', 404);
        }

        return new PayoutResource($this->payouts->cancel($payout, null, 'Cancelled via API')->load('provider'));
    }

    private function find(Request $request, string $id): ?Payout
    {
        return Payout::query()
            ->with('provider')
            ->where('public_id', $id)
            ->where('merchant_id', $request->attributes->get('merchant')->id)
            ->where('mode', $request->attributes->get('mode')->value)
            ->first();
    }
}
