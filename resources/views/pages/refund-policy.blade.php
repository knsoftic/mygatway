@extends('pages.legal', ['heading' => 'Refund and Dispute Policy', 'updated' => '16 October 2026'])

@section('legal')
<h2>Refunds to your customers</h2>
<ul>
    <li>Refunds can be issued for settled payments where the processing provider supports refunds.</li>
    <li>Cryptomus refunds return the full paid amount to a wallet address you provide. Binance Pay refunds return funds to the payer's Binance account.</li>
    <li>The refunded amount is debited from your available balance in the same currency. Service fees on the original payment are not returned.</li>
    <li>Blockchain transactions are irreversible. Double-check refund addresses; funds sent to a wrong address cannot be recovered.</li>
</ul>

<h2>Underpaid, overpaid and late payments</h2>
<ul>
    <li>Underpaid invoices finalised by the provider are credited at the amount actually received.</li>
    <li>Payments received after an invoice expired are credited when confirmed by the provider and flagged for review.</li>
</ul>

<h2>Disputes</h2>
<ul>
    <li>Crypto payments have no card-style chargebacks. Disputes between you and your customers are your responsibility.</li>
    <li>If you believe a transaction was recorded incorrectly, contact us with the payment ID. We reconcile against the provider's records; balances are corrected only through documented ledger entries.</li>
    <li>We aim to respond to dispute requests within 5 business days.</li>
</ul>

<h2>Service fees</h2>
<p>Service fees for completed payouts are non-refundable. If a payout fails or is rejected, the reserved amount and fees are returned to your available balance.</p>
@endsection
