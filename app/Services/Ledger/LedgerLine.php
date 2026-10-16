<?php

namespace App\Services\Ledger;

use App\Enums\EntryDirection;
use App\Models\LedgerAccount;

final readonly class LedgerLine
{
    public function __construct(
        public LedgerAccount $account,
        public EntryDirection $direction,
        public string $amount,
    ) {}

    public static function debit(LedgerAccount $account, string $amount): self
    {
        return new self($account, EntryDirection::Debit, $amount);
    }

    public static function credit(LedgerAccount $account, string $amount): self
    {
        return new self($account, EntryDirection::Credit, $amount);
    }
}
