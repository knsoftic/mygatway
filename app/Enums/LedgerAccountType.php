<?php

namespace App\Enums;

/**
 * Chart of accounts (per mode + currency).
 *
 * Liabilities (normal credit) - what the platform owes merchants:
 *   merchant_available, merchant_reserved
 * Revenue (normal credit):
 *   platform_fee_revenue, platform_network_fee
 * Assets (normal debit) - funds held at a provider:
 *   provider_clearing
 * Expenses (normal debit):
 *   provider_fee_expense
 */
enum LedgerAccountType: string
{
    case MerchantAvailable = 'merchant_available';
    case MerchantReserved = 'merchant_reserved';
    case PlatformFeeRevenue = 'platform_fee_revenue';
    case PlatformNetworkFee = 'platform_network_fee';
    case ProviderClearing = 'provider_clearing';
    case ProviderFeeExpense = 'provider_fee_expense';

    public function normalBalance(): EntryDirection
    {
        return match ($this) {
            self::ProviderClearing, self::ProviderFeeExpense => EntryDirection::Debit,
            default => EntryDirection::Credit,
        };
    }

    public function isMerchantAccount(): bool
    {
        return $this === self::MerchantAvailable || $this === self::MerchantReserved;
    }

    public function isProviderAccount(): bool
    {
        return $this === self::ProviderClearing || $this === self::ProviderFeeExpense;
    }

    public function label(): string
    {
        return match ($this) {
            self::MerchantAvailable => 'Merchant available',
            self::MerchantReserved => 'Merchant reserved',
            self::PlatformFeeRevenue => 'Platform service fee revenue',
            self::PlatformNetworkFee => 'Network fee recovery',
            self::ProviderClearing => 'Provider clearing',
            self::ProviderFeeExpense => 'Provider fee expense',
        };
    }
}
