<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\FeeRuleRequest;
use App\Models\AuditLog;
use App\Models\FeeRule;
use App\Models\Merchant;
use App\Models\PaymentProvider;
use App\Services\Audit\AuditLogger;
use App\Services\Fees\FeeCalculator;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Fee rule administration. Rules are versioned by effective dates; editing a rule
 * never changes historical transactions because every transaction stores an
 * immutable fee snapshot (transaction_fees).
 */
class FeeRuleController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(Request $request): View
    {
        $f = $request->validate([
            'direction' => ['nullable', 'in:payin,payout'],
            'scope' => ['nullable', 'in:global,merchant'],
            'active' => ['nullable', 'boolean'],
        ]);

        return view('admin.fees.index', [
            'filters' => $f,
            'rules' => FeeRule::query()->with(['merchant', 'provider'])
                ->when($f['direction'] ?? null, fn ($q, $d) => $q->where('direction', $d))
                ->when(($f['scope'] ?? null) === 'global', fn ($q) => $q->whereNull('merchant_id'))
                ->when(($f['scope'] ?? null) === 'merchant', fn ($q) => $q->whereNotNull('merchant_id'))
                ->when(isset($f['active']), fn ($q) => $q->where('is_active', (bool) $f['active']))
                ->orderBy('direction')->orderByRaw('merchant_id IS NULL DESC')->latest('effective_from')
                ->paginate(30)->withQueryString(),
            'history' => AuditLog::query()->with('user')->where('auditable_type', (new FeeRule)->getMorphClass())->latest('id')->limit(15)->get(),
        ]);
    }

    public function create(Request $request): View
    {
        return view('admin.fees.form', $this->formData(new FeeRule([
            'merchant_id' => $request->integer('merchant_id') ?: null,
            'direction' => 'payin',
            'percentage' => '1.0000',
            'fixed_amount' => '0',
            'effective_from' => now(),
            'is_active' => true,
        ])));
    }

    public function store(FeeRuleRequest $request): RedirectResponse
    {
        $rule = FeeRule::query()->create($request->ruleData() + ['created_by' => $request->user()->id, 'updated_by' => $request->user()->id]);
        $this->audit->log('fee_rule.created', $rule, new: $rule->snapshot());

        return redirect()->route('admin.fee-rules.index')->with('success', 'Fee rule created.');
    }

    public function edit(FeeRule $feeRule): View
    {
        return view('admin.fees.form', $this->formData($feeRule));
    }

    public function update(FeeRuleRequest $request, FeeRule $feeRule): RedirectResponse
    {
        $before = $feeRule->snapshot();
        $feeRule->fill($request->ruleData() + ['updated_by' => $request->user()->id])->save();
        $this->audit->log('fee_rule.updated', $feeRule, $before, $feeRule->refresh()->snapshot());

        return redirect()->route('admin.fee-rules.index')->with('success', 'Fee rule updated. Existing transactions keep their original fees.');
    }

    public function toggle(Request $request, FeeRule $feeRule): RedirectResponse
    {
        abort_unless($request->user()->can('fees.manage'), 403);
        $before = $feeRule->snapshot();
        $feeRule->forceFill(['is_active' => ! $feeRule->is_active, 'updated_by' => $request->user()->id])->save();
        $this->audit->log($feeRule->is_active ? 'fee_rule.activated' : 'fee_rule.deactivated', $feeRule, $before, $feeRule->snapshot());

        return back()->with('success', $feeRule->is_active ? 'Rule activated.' : 'Rule deactivated.');
    }

    /** Preview which rule applies and what it charges, for a hypothetical transaction. */
    public function preview(Request $request, FeeCalculator $fees): View
    {
        $v = $request->validate([
            'direction' => ['required', 'in:payin,payout'],
            'merchant_id' => ['required', 'integer', 'exists:merchants,id'],
            'provider_id' => ['required', 'integer', 'exists:payment_providers,id'],
            'currency' => ['required', 'string', 'max:16'],
            'amount' => ['required', 'string', 'regex:/^\d{1,18}(\.\d{1,8})?$/'],
        ]);

        $direction = \App\Enums\FeeDirection::from($v['direction']);
        $rule = $fees->resolveRule($direction, (int) $v['merchant_id'], (int) $v['provider_id'], $v['currency']);
        $quote = $fees->quote($direction, (int) $v['merchant_id'], (int) $v['provider_id'], $v['currency'], $v['amount']);

        return view('admin.fees.preview', ['input' => $v, 'rule' => $rule, 'quote' => $quote,
            'merchant' => Merchant::query()->find($v['merchant_id']), 'provider' => PaymentProvider::query()->find($v['provider_id'])]);
    }

    private function formData(FeeRule $rule): array
    {
        return [
            'rule' => $rule,
            'merchants' => Merchant::query()->orderBy('name')->get(['id', 'name', 'public_id']),
            'providers' => PaymentProvider::query()->orderBy('priority')->get(),
        ];
    }
}
