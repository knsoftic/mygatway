<?php

namespace App\Services\Ledger;

use App\Enums\EntryDirection;
use App\Enums\LedgerAccountType;
use App\Enums\Mode;
use App\Exceptions\GatewayException;
use App\Models\LedgerAccount;
use App\Models\LedgerEntry;
use App\Models\Payment;
use App\Models\Payout;
use App\Models\Refund;
use App\Models\Transaction;
use App\Support\Decimal;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Double-entry ledger. Every balance change is a balanced journal (Transaction)
 * of immutable entries. Accounts are row-locked in a fixed order while posting,
 * which serialises concurrent postings and prevents negative merchant balances
 * and double debits. Each business event carries an idempotency key, so the
 * same event can never be posted twice.
 */
class LedgerService
{
    public function account(Mode $mode, string $currency, LedgerAccountType $type, ?int $merchantId = null, ?int $providerId = null): LedgerAccount
    {
        if ($type->isMerchantAccount() && $merchantId === null) {
            throw new LogicException("{$type->value} requires a merchant.");
        }
        if ($type->isProviderAccount() && $providerId === null) {
            throw new LogicException("{$type->value} requires a provider.");
        }

        $key = LedgerAccount::keyFor($mode, $currency, $type, $merchantId, $providerId);

        $existing = LedgerAccount::query()->where('account_key', $key)->first();
        if ($existing) {
            return $existing;
        }

        try {
            return LedgerAccount::query()->create([
                'account_key' => $key,
                'mode' => $mode->value,
                'currency' => strtoupper($currency),
                'type' => $type->value,
                'merchant_id' => $merchantId,
                'provider_id' => $providerId,
                'normal_balance' => $type->normalBalance()->value,
            ]);
        } catch (UniqueConstraintViolationException) {
            return LedgerAccount::query()->where('account_key', $key)->firstOrFail();
        }
    }

    /**
     * Post a balanced journal. Returns the existing journal if the idempotency key was already used.
     *
     * @param  list<LedgerLine>  $lines
     */
    public function post(
        string $idempotencyKey,
        Mode $mode,
        string $currency,
        string $type,
        ?int $merchantId,
        ?Model $reference,
        string $description,
        array $lines,
        ?int $userId = null,
    ): Transaction {
        $lines = array_values(array_filter($lines, fn (LedgerLine $l) => ! Decimal::isZero($l->amount)));
        $this->assertBalanced($lines, $currency);

        try {
            return $this->write($idempotencyKey, $mode, $currency, $type, $merchantId, $reference, $description, $lines, $userId);
        } catch (UniqueConstraintViolationException $e) {
            // A concurrent worker posted the same business event first.
            return Transaction::query()->where('idempotency_key', $idempotencyKey)->first() ?? throw $e;
        }
    }

    /** @param  list<LedgerLine>  $lines */
    private function write(
        string $idempotencyKey,
        Mode $mode,
        string $currency,
        string $type,
        ?int $merchantId,
        ?Model $reference,
        string $description,
        array $lines,
        ?int $userId,
    ): Transaction {
        return DB::transaction(function () use ($idempotencyKey, $mode, $currency, $type, $merchantId, $reference, $description, $lines, $userId) {
            $existing = Transaction::query()->where('idempotency_key', $idempotencyKey)->first();
            if ($existing) {
                return $existing;
            }

            // Lock every touched account in id order to avoid deadlocks.
            $ids = array_values(array_unique(array_map(fn (LedgerLine $l) => $l->account->id, $lines)));
            sort($ids);
            $locked = LedgerAccount::query()->whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get()->keyBy('id');

            $transaction = Transaction::query()->create([
                'merchant_id' => $merchantId,
                'mode' => $mode->value,
                'type' => $type,
                'reference_type' => $reference?->getMorphClass(),
                'reference_id' => $reference?->getKey(),
                'currency' => strtoupper($currency),
                'description' => $description,
                'idempotency_key' => $idempotencyKey,
                'created_by' => $userId,
                'posted_at' => now(),
            ]);

            $balances = $locked->map(fn (LedgerAccount $a) => Decimal::of((string) $a->balance))->all();
            $now = now();

            foreach ($lines as $line) {
                $account = $locked[$line->account->id];
                $delta = $line->direction === $account->normal_balance ? $line->amount : Decimal::negate($line->amount);
                $balances[$account->id] = Decimal::add($balances[$account->id], $delta);

                if ($account->type->isMerchantAccount() && Decimal::isNegative($balances[$account->id])) {
                    throw GatewayException::insufficientFunds(
                        $account->currency,
                        Decimal::of((string) $account->balance),
                        $line->amount,
                    );
                }

                LedgerEntry::query()->create([
                    'transaction_id' => $transaction->id,
                    'ledger_account_id' => $account->id,
                    'direction' => $line->direction->value,
                    'amount' => $line->amount,
                    'currency' => $account->currency,
                    'balance_after' => $balances[$account->id],
                    'created_at' => $now,
                ]);
            }

            foreach ($balances as $accountId => $balance) {
                LedgerAccount::query()->whereKey($accountId)->update(['balance' => $balance, 'updated_at' => $now]);
            }

            return $transaction;
        });
    }

    /**
     * Pay In settled: provider credited R (minus its own fee P) to the platform's provider account;
     * the merchant is owed R - F and the platform earns F.
     *
     *   Dr provider_clearing      R - P
     *   Dr provider_fee_expense   P
     *     Cr merchant_available   R - F
     *     Cr platform_fee_revenue F
     */
    public function postPaymentSettlement(Payment $payment): Transaction
    {
        $mode = $payment->mode;
        $cur = $payment->currency;
        $received = Decimal::of((string) $payment->received_amount);
        $serviceFee = Decimal::of((string) $payment->service_fee);
        $providerFee = Decimal::of((string) ($payment->provider_fee ?? '0'));

        return $this->post(
            idempotencyKey: "payment:{$payment->id}:settlement",
            mode: $mode,
            currency: $cur,
            type: Transaction::PAYIN_SETTLEMENT,
            merchantId: $payment->merchant_id,
            reference: $payment,
            description: "Pay In {$payment->public_id} settled",
            lines: [
                $this->signed($this->account($mode, $cur, LedgerAccountType::ProviderClearing, null, $payment->provider_id), EntryDirection::Debit, Decimal::sub($received, $providerFee)),
                $this->signed($this->account($mode, $cur, LedgerAccountType::ProviderFeeExpense, null, $payment->provider_id), EntryDirection::Debit, $providerFee),
                LedgerLine::credit($this->account($mode, $cur, LedgerAccountType::MerchantAvailable, $payment->merchant_id), Decimal::sub($received, $serviceFee)),
                LedgerLine::credit($this->account($mode, $cur, LedgerAccountType::PlatformFeeRevenue), $serviceFee),
            ],
        );
    }

    /**
     * Payout requested: move the full debit (amount + service fee + network fee) from available to reserved.
     * Fails with insufficient_balance when available funds do not cover it.
     */
    public function postPayoutReserve(Payout $payout): Transaction
    {
        $mode = $payout->mode;
        $total = Decimal::of((string) $payout->total_debit);

        return $this->post(
            idempotencyKey: "payout:{$payout->id}:reserve",
            mode: $mode,
            currency: $payout->currency,
            type: Transaction::PAYOUT_RESERVE,
            merchantId: $payout->merchant_id,
            reference: $payout,
            description: "Payout {$payout->public_id} reserved",
            lines: [
                LedgerLine::debit($this->account($mode, $payout->currency, LedgerAccountType::MerchantAvailable, $payout->merchant_id), $total),
                LedgerLine::credit($this->account($mode, $payout->currency, LedgerAccountType::MerchantReserved, $payout->merchant_id), $total),
            ],
        );
    }

    /**
     * Payout completed. T = amount A + service fee F + network fee charged N; provider charged P.
     *
     *   Dr merchant_reserved      T
     *   Dr provider_fee_expense   P
     *     Cr provider_clearing    A + P
     *     Cr platform_fee_revenue F
     *     Cr platform_network_fee N
     */
    public function postPayoutSettlement(Payout $payout): Transaction
    {
        $mode = $payout->mode;
        $cur = $payout->currency;
        $amount = Decimal::of((string) $payout->amount);
        $providerFee = Decimal::of((string) ($payout->provider_fee ?? '0'));

        return $this->post(
            idempotencyKey: "payout:{$payout->id}:settlement",
            mode: $mode,
            currency: $cur,
            type: Transaction::PAYOUT_SETTLEMENT,
            merchantId: $payout->merchant_id,
            reference: $payout,
            description: "Payout {$payout->public_id} completed",
            lines: [
                LedgerLine::debit($this->account($mode, $cur, LedgerAccountType::MerchantReserved, $payout->merchant_id), Decimal::of((string) $payout->total_debit)),
                LedgerLine::debit($this->account($mode, $cur, LedgerAccountType::ProviderFeeExpense, null, $payout->provider_id), $providerFee),
                LedgerLine::credit($this->account($mode, $cur, LedgerAccountType::ProviderClearing, null, $payout->provider_id), Decimal::add($amount, $providerFee)),
                LedgerLine::credit($this->account($mode, $cur, LedgerAccountType::PlatformFeeRevenue), Decimal::of((string) $payout->service_fee)),
                LedgerLine::credit($this->account($mode, $cur, LedgerAccountType::PlatformNetworkFee), Decimal::of((string) $payout->network_fee)),
            ],
        );
    }

    /** Payout rejected / failed / cancelled: return the reserved funds to available. */
    public function postPayoutRelease(Payout $payout): Transaction
    {
        $mode = $payout->mode;
        $total = Decimal::of((string) $payout->total_debit);

        return $this->post(
            idempotencyKey: "payout:{$payout->id}:release",
            mode: $mode,
            currency: $payout->currency,
            type: Transaction::PAYOUT_RELEASE,
            merchantId: $payout->merchant_id,
            reference: $payout,
            description: "Payout {$payout->public_id} released ({$payout->status->value})",
            lines: [
                LedgerLine::debit($this->account($mode, $payout->currency, LedgerAccountType::MerchantReserved, $payout->merchant_id), $total),
                LedgerLine::credit($this->account($mode, $payout->currency, LedgerAccountType::MerchantAvailable, $payout->merchant_id), $total),
            ],
        );
    }

    public function postRefundReserve(Refund $refund): Transaction
    {
        $mode = $refund->mode;

        return $this->post("refund:{$refund->id}:reserve", $mode, $refund->currency, Transaction::REFUND_RESERVE,
            $refund->merchant_id, $refund, "Refund {$refund->public_id} reserved", [
                LedgerLine::debit($this->account($mode, $refund->currency, LedgerAccountType::MerchantAvailable, $refund->merchant_id), Decimal::of((string) $refund->amount)),
                LedgerLine::credit($this->account($mode, $refund->currency, LedgerAccountType::MerchantReserved, $refund->merchant_id), Decimal::of((string) $refund->amount)),
            ]);
    }

    /** Refund sent to the customer from the platform's provider balance. Service fees are not returned. */
    public function postRefundSettlement(Refund $refund): Transaction
    {
        $mode = $refund->mode;

        return $this->post("refund:{$refund->id}:settlement", $mode, $refund->currency, Transaction::REFUND_SETTLEMENT,
            $refund->merchant_id, $refund, "Refund {$refund->public_id} completed", [
                LedgerLine::debit($this->account($mode, $refund->currency, LedgerAccountType::MerchantReserved, $refund->merchant_id), Decimal::of((string) $refund->amount)),
                LedgerLine::credit($this->account($mode, $refund->currency, LedgerAccountType::ProviderClearing, null, $refund->provider_id), Decimal::of((string) $refund->amount)),
            ]);
    }

    public function postRefundRelease(Refund $refund): Transaction
    {
        $mode = $refund->mode;

        return $this->post("refund:{$refund->id}:release", $mode, $refund->currency, Transaction::REFUND_RELEASE,
            $refund->merchant_id, $refund, "Refund {$refund->public_id} released", [
                LedgerLine::debit($this->account($mode, $refund->currency, LedgerAccountType::MerchantReserved, $refund->merchant_id), Decimal::of((string) $refund->amount)),
                LedgerLine::credit($this->account($mode, $refund->currency, LedgerAccountType::MerchantAvailable, $refund->merchant_id), Decimal::of((string) $refund->amount)),
            ]);
    }

    /** Post the exact inverse of a journal (corrections never edit or delete entries). */
    public function reverse(Transaction $original, string $reason, ?int $userId = null): Transaction
    {
        $original->loadMissing('entries.account');

        $lines = $original->entries->map(fn (LedgerEntry $e) => new LedgerLine(
            $e->account,
            $e->direction === EntryDirection::Debit ? EntryDirection::Credit : EntryDirection::Debit,
            Decimal::of((string) $e->amount),
        ))->all();

        return $this->post("reversal:{$original->id}", $original->mode, $original->currency, Transaction::REVERSAL,
            $original->merchant_id, $original, "Reversal of {$original->public_id}: {$reason}", $lines, $userId);
    }

    /**
     * Merchant balances per currency for one mode. Different currencies are never combined.
     *
     * @return array<string, array{available: string, reserved: string}>
     */
    public function merchantBalances(int $merchantId, Mode $mode): array
    {
        $rows = LedgerAccount::query()
            ->where('merchant_id', $merchantId)
            ->where('mode', $mode->value)
            ->whereIn('type', [LedgerAccountType::MerchantAvailable->value, LedgerAccountType::MerchantReserved->value])
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $out[$row->currency] ??= ['available' => Decimal::zero(), 'reserved' => Decimal::zero()];
            $key = $row->type === LedgerAccountType::MerchantAvailable ? 'available' : 'reserved';
            $out[$row->currency][$key] = Decimal::of((string) $row->balance);
        }
        ksort($out);

        return $out;
    }

    /** Balance recomputed from entries (used by reconciliation). */
    public function computedBalance(LedgerAccount $account): string
    {
        $sums = LedgerEntry::query()
            ->where('ledger_account_id', $account->id)
            ->selectRaw('direction, SUM(amount) as total')
            ->groupBy('direction')
            ->pluck('total', 'direction');

        $debits = Decimal::of((string) ($sums['debit'] ?? '0'));
        $credits = Decimal::of((string) ($sums['credit'] ?? '0'));

        return $account->normal_balance === EntryDirection::Debit
            ? Decimal::sub($debits, $credits)
            : Decimal::sub($credits, $debits);
    }

    /** A negative computed amount is posted as the opposite side. */
    private function signed(LedgerAccount $account, EntryDirection $direction, string $amount): LedgerLine
    {
        if (Decimal::isNegative($amount)) {
            return new LedgerLine($account, $direction === EntryDirection::Debit ? EntryDirection::Credit : EntryDirection::Debit, Decimal::negate($amount));
        }

        return new LedgerLine($account, $direction, $amount);
    }

    /** @param  list<LedgerLine>  $lines */
    private function assertBalanced(array $lines, string $currency): void
    {
        if (count($lines) < 2) {
            throw new LogicException('A journal needs at least two entries.');
        }

        $debits = Decimal::zero();
        $credits = Decimal::zero();
        foreach ($lines as $line) {
            if (! Decimal::isPositive($line->amount)) {
                throw new LogicException('Ledger entry amounts must be positive.');
            }
            if (strtoupper($line->account->currency) !== strtoupper($currency)) {
                throw new LogicException('All entries in a journal must share one currency.');
            }
            $line->direction === EntryDirection::Debit
                ? $debits = Decimal::add($debits, $line->amount)
                : $credits = Decimal::add($credits, $line->amount);
        }

        if (! Decimal::eq($debits, $credits)) {
            throw new LogicException("Unbalanced journal: debits {$debits} != credits {$credits}.");
        }
    }
}
