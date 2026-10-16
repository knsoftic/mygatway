<?php

namespace App\Services\Reconciliation;

use App\Enums\LedgerAccountType;
use App\Enums\Mode;
use App\Enums\PaymentStatus;
use App\Enums\PayoutStatus;
use App\Integrations\Exceptions\ProviderException;
use App\Integrations\GatewayManager;
use App\Models\LedgerAccount;
use App\Models\Payment;
use App\Models\PaymentProvider;
use App\Models\Payout;
use App\Models\ReconciliationItem;
use App\Models\ReconciliationRun;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Ledger\LedgerService;
use App\Support\Decimal;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Two independent checks:
 *  - ledger:   cached balances == SUM(entries), every journal balanced, every money
 *              movement on payments/payouts has its matching journal.
 *  - provider: our payment/payout states compared with the provider's own records
 *              via authenticated status queries.
 * Findings are recorded for review; fixes always go through the normal verified
 * processing path, never by editing balances.
 */
class ReconciliationService
{
    public function __construct(private readonly LedgerService $ledger, private readonly GatewayManager $gateways) {}

    public function runLedger(?User $user = null): ReconciliationRun
    {
        $run = $this->start('ledger', null, $user);
        $checked = 0;

        LedgerAccount::query()->orderBy('id')->chunkById(500, function ($accounts) use ($run, &$checked) {
            foreach ($accounts as $account) {
                $checked++;
                $computed = $this->ledger->computedBalance($account);
                if (! Decimal::eq($computed, (string) $account->balance)) {
                    $this->item($run, $account, 'balance_mismatch', [
                        'account' => $account->account_key, 'cached' => (string) $account->balance, 'computed' => $computed,
                    ]);
                }
                if ($account->type->isMerchantAccount() && Decimal::isNegative((string) $account->balance)) {
                    $this->item($run, $account, 'negative_balance', ['account' => $account->account_key, 'balance' => (string) $account->balance]);
                }
            }
        });

        $unbalanced = DB::table('ledger_entries')
            ->select('transaction_id')
            ->selectRaw("SUM(CASE WHEN direction = 'debit' THEN amount ELSE 0 END) AS debits")
            ->selectRaw("SUM(CASE WHEN direction = 'credit' THEN amount ELSE 0 END) AS credits")
            ->groupBy('transaction_id')
            ->havingRaw("SUM(CASE WHEN direction = 'debit' THEN amount ELSE -amount END) <> 0")
            ->get();
        foreach ($unbalanced as $row) {
            $this->item($run, Transaction::query()->find($row->transaction_id), 'unbalanced_transaction', [
                'debits' => (string) $row->debits, 'credits' => (string) $row->credits,
            ]);
        }

        $settled = [PaymentStatus::Completed->value, PaymentStatus::Underpaid->value, PaymentStatus::RefundPending->value, PaymentStatus::Refunded->value];
        Payment::query()->whereIn('status', $settled)->whereDoesntHave('ledgerTransactions', fn ($q) => $q->where('type', Transaction::PAYIN_SETTLEMENT))
            ->each(function (Payment $p) use ($run, &$checked) {
                $checked++;
                $this->item($run, $p, 'missing_settlement', ['payment' => $p->public_id, 'status' => $p->status->value]);
            });

        Payout::query()->whereDoesntHave('ledgerTransactions', fn ($q) => $q->where('type', Transaction::PAYOUT_RESERVE))
            ->each(fn (Payout $p) => $this->item($run, $p, 'missing_reserve', ['payout' => $p->public_id]));

        Payout::query()->where('status', PayoutStatus::Completed->value)
            ->whereDoesntHave('ledgerTransactions', fn ($q) => $q->where('type', Transaction::PAYOUT_SETTLEMENT))
            ->each(fn (Payout $p) => $this->item($run, $p, 'missing_settlement', ['payout' => $p->public_id]));

        Payout::query()->whereIn('status', [PayoutStatus::Failed->value, PayoutStatus::Rejected->value, PayoutStatus::Cancelled->value])
            ->whereDoesntHave('ledgerTransactions', fn ($q) => $q->where('type', Transaction::PAYOUT_RELEASE))
            ->each(fn (Payout $p) => $this->item($run, $p, 'missing_release', ['payout' => $p->public_id]));

        // Reserved balance must equal the sum of payouts still holding funds (per merchant/mode/currency).
        $holding = Payout::query()
            ->whereIn('status', array_map(fn ($s) => $s->value, array_filter(PayoutStatus::cases(), fn ($s) => $s->holdsFunds())))
            ->selectRaw('merchant_id, mode, currency, SUM(total_debit) AS held')
            ->groupBy('merchant_id', 'mode', 'currency')->get()
            ->keyBy(fn ($r) => "{$r->mode}:{$r->currency}:{$r->merchant_id}");

        LedgerAccount::query()->where('type', LedgerAccountType::MerchantReserved->value)->each(function (LedgerAccount $a) use ($run, $holding) {
            $refundsHeld = (string) DB::table('refunds')->where('merchant_id', $a->merchant_id)->where('mode', $a->mode->value)
                ->where('currency', $a->currency)->whereIn('status', ['requested', 'processing'])->sum('amount');
            $expected = Decimal::add(Decimal::of((string) ($holding["{$a->mode->value}:{$a->currency}:{$a->merchant_id}"]->held ?? '0')), Decimal::of($refundsHeld ?: '0'));
            if (! Decimal::eq($expected, (string) $a->balance)) {
                $this->item($run, $a, 'reserved_mismatch', ['account' => $a->account_key, 'reserved' => (string) $a->balance, 'expected' => $expected]);
            }
        });

        return $this->finish($run, $checked);
    }

    public function runProvider(PaymentProvider $provider, Carbon $from, Carbon $to, ?User $user = null, int $limit = 500): ReconciliationRun
    {
        $run = $this->start('provider', $provider, $user);
        $gateway = $this->gateways->live($provider->code);
        $checked = 0;

        $payments = Payment::query()
            ->where('provider_id', $provider->id)->where('mode', Mode::Live->value)
            ->whereBetween('created_at', [$from, $to])
            ->where('status', '!=', PaymentStatus::Created->value)
            ->where(fn ($q) => $q->whereNotNull('provider_payment_id')->orWhere('status', '!=', PaymentStatus::Failed->value))
            ->orderBy('id')->limit($limit)->get();

        foreach ($payments as $payment) {
            $checked++;
            try {
                $data = $gateway->fetchPayment($payment);
            } catch (ProviderException|Throwable $e) {
                $this->item($run, $payment, 'provider_query_failed', ['payment' => $payment->public_id, 'error' => mb_substr($e->getMessage(), 0, 300)]);

                continue;
            }

            $providerStatus = $data->status;
            if ($providerStatus === PaymentStatus::Cancelled && $payment->expires_at?->isPast()) {
                $providerStatus = PaymentStatus::Expired;
            }

            if ($providerStatus !== null && $providerStatus !== $payment->status) {
                $this->item($run, $payment, 'status_mismatch', [
                    'payment' => $payment->public_id, 'ours' => $payment->status->value,
                    'provider' => $providerStatus->value, 'provider_raw' => $data->providerStatus,
                ]);
            } elseif ($payment->status->isSettled() && $data->receivedAmount !== null && ! Decimal::eq($data->receivedAmount, (string) $payment->received_amount)) {
                $this->item($run, $payment, 'amount_mismatch', [
                    'payment' => $payment->public_id, 'ours' => (string) $payment->received_amount, 'provider' => $data->receivedAmount,
                ]);
            }
        }

        $payouts = Payout::query()
            ->where('provider_id', $provider->id)->where('mode', Mode::Live->value)
            ->whereBetween('created_at', [$from, $to])
            ->whereIn('status', [PayoutStatus::Processing->value, PayoutStatus::Completed->value, PayoutStatus::Failed->value])
            ->whereNotNull('submitted_at')
            ->orderBy('id')->limit($limit)->get();

        foreach ($payouts as $payout) {
            $checked++;
            try {
                $data = $gateway->fetchPayout($payout);
            } catch (Throwable $e) {
                $this->item($run, $payout, 'provider_query_failed', ['payout' => $payout->public_id, 'error' => mb_substr($e->getMessage(), 0, 300)]);

                continue;
            }
            if ($data->status !== null && $data->status !== $payout->status) {
                $this->item($run, $payout, 'status_mismatch', [
                    'payout' => $payout->public_id, 'ours' => $payout->status->value,
                    'provider' => $data->status->value, 'provider_raw' => $data->providerStatus,
                ]);
            }
        }

        return $this->finish($run, $checked);
    }

    private function start(string $type, ?PaymentProvider $provider, ?User $user): ReconciliationRun
    {
        return ReconciliationRun::query()->create([
            'type' => $type,
            'provider_id' => $provider?->id,
            'status' => 'running',
            'triggered_by' => $user?->id,
            'started_at' => now(),
        ]);
    }

    private function finish(ReconciliationRun $run, int $checked): ReconciliationRun
    {
        $mismatches = $run->items()->count();
        $run->forceFill([
            'status' => 'completed',
            'checked_count' => $checked,
            'mismatch_count' => $mismatches,
            'summary' => $run->items()->selectRaw('issue, COUNT(*) AS c')->groupBy('issue')->pluck('c', 'issue')->all(),
            'finished_at' => now(),
        ])->save();

        return $run;
    }

    /** @param  array<string, mixed>  $details */
    private function item(ReconciliationRun $run, ?object $subject, string $issue, array $details): void
    {
        ReconciliationItem::query()->create([
            'reconciliation_run_id' => $run->id,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'issue' => $issue,
            'details' => $details,
        ]);
    }
}
