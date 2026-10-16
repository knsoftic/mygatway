<p class="lead">A Pay In is a payment from your customer to you. You create it from your server, the customer pays on the provider's checkout, and your balance is credited with the amount received minus our service fee once the provider confirms it.</p>

<h2>Create a payment</h2>
<p><code>POST /payments</code> · scope <code>payments:write</code> · requires an <code>Idempotency-Key</code> header</p>
<div class="table-responsive"><table class="table table-sm">
    <thead><tr><th>Field</th><th>Type</th><th>Description</th></tr></thead>
    <tbody>
        <tr><td><code>amount</code> <span class="badge text-bg-danger">required</span></td><td>string</td><td>Positive decimal with up to 8 decimals, for example <code>"49.90"</code>.</td></tr>
        <tr><td><code>currency</code> <span class="badge text-bg-danger">required</span></td><td>string</td><td>Currency code such as <code>USDT</code>. See <code>GET /currencies</code>.</td></tr>
        <tr><td><code>reference</code> <span class="badge text-bg-danger">required</span></td><td>string</td><td>Your order ID. 1–64 characters: letters, digits, <code>_</code> and <code>-</code>. Must be unique per mode.</td></tr>
        <tr><td><code>network</code></td><td>string</td><td>Blockchain network, if you want to fix it (for example <code>tron</code>). Omit to let the payer choose.</td></tr>
        <tr><td><code>provider</code></td><td>string</td><td><code>cryptomus</code> or <code>binance_pay</code>. Omit to use the first enabled provider that supports the currency.</td></tr>
        <tr><td><code>description</code></td><td>string</td><td>Up to 255 characters.</td></tr>
        <tr><td><code>customer_email</code></td><td>string</td><td>Customer's email, if you have it.</td></tr>
        <tr><td><code>success_url</code>, <code>cancel_url</code></td><td>string</td><td>Where to send the customer after checkout. Never treat a visit to these URLs as proof of payment.</td></tr>
        <tr><td><code>lifetime</code></td><td>integer</td><td>Seconds before the payment expires, between 300 and 43200. Default 3600.</td></tr>
        <tr><td><code>metadata</code></td><td>object</td><td>Up to 20 keys with string values (max 500 characters). Returned unchanged on the payment and in webhooks.</td></tr>
    </tbody>
</table></div>
<p>You cannot send <code>fee</code>, <code>service_fee</code>, <code>network_fee</code>, <code>net_amount</code> or <code>total_debit</code>. Fees are always calculated by the platform, and sending them returns a validation error.</p>

<h3>Response <span class="badge text-bg-success">201</span></h3>
<div class="code-window mb-3"><pre>{
  "data": {
    "id": "pay_01j9x4...",
    "object": "payment",
    "mode": "test",
    "status": "pending",
    "provider": "cryptomus",
    "reference": "order-1001",
    "amount": "49.90000000",
    "currency": "USDT",
    "network": null,
    "received_amount": null,
    "service_fee": "0.49900000",
    "net_amount": null,
    "checkout_url": "https://...",
    "pay_address": null,
    "tx_hash": null,
    "metadata": {},
    "callback_verified": false,
    "expires_at": "2026-10-16T13:00:00+00:00",
    "completed_at": null,
    "created_at": "2026-10-16T12:00:00+00:00"
  }
}</pre></div>
<p><code>service_fee</code> is an estimate at creation. When the payment settles, it is recalculated on the amount actually received and <code>net_amount</code> is set.</p>

<h2>Payment statuses</h2>
<div class="table-responsive"><table class="table table-sm">
    <thead><tr><th>Status</th><th>Meaning</th><th>Final?</th></tr></thead>
    <tbody>
        <tr><td><code>created</code></td><td>Recorded, not yet created at the provider.</td><td>No</td></tr>
        <tr><td><code>pending</code></td><td>Waiting for the customer to pay.</td><td>No</td></tr>
        <tr><td><code>processing</code></td><td>Payment detected by the provider.</td><td>No</td></tr>
        <tr><td><code>confirming</code></td><td>Waiting for blockchain confirmations.</td><td>No</td></tr>
        <tr><td><code>completed</code></td><td>Paid and confirmed. Your balance has been credited.</td><td>Yes*</td></tr>
        <tr><td><code>underpaid</code></td><td>Less than requested was received. Only the received amount, minus fees, is credited.</td><td>Yes</td></tr>
        <tr><td><code>failed</code></td><td>The provider reported a failure.</td><td>Yes</td></tr>
        <tr><td><code>expired</code></td><td>Not paid before <code>expires_at</code>.</td><td>Yes</td></tr>
        <tr><td><code>cancelled</code></td><td>Cancelled at the provider.</td><td>Yes</td></tr>
        <tr><td><code>on_hold</code></td><td>Held for compliance review by us or the provider.</td><td>No</td></tr>
        <tr><td><code>refund_pending</code> / <code>refunded</code></td><td>A refund was started by our support team, or has completed.</td><td>*</td></tr>
    </tbody>
</table></div>
<p>A payment becomes <code>completed</code> only from a provider callback with a valid signature, confirmed by an authenticated status query to the provider. Redirects and browser responses never change a payment's status.</p>

<h2>Fees</h2>
<p>Pay In fees are deducted from the amount received. With a 1% fee on a 10,000 USDT payment, the service fee is 100 and your net amount is 9,900. Call <code>GET /fees</code> for the rules that apply to your account, or <code>GET /fees/quote</code> to calculate a specific amount.</p>

<h2>Common errors</h2>
<div class="table-responsive"><table class="table table-sm">
    <thead><tr><th>Status</th><th>Code</th><th>When</th></tr></thead>
    <tbody>
        <tr><td>409</td><td><code>duplicate_reference</code></td><td>The <code>reference</code> was already used. <code>details.existing_id</code> has that payment's ID.</td></tr>
        <tr><td>422</td><td><code>currency_not_supported</code>, <code>network_not_supported</code></td><td>The provider does not accept that currency or network.</td></tr>
        <tr><td>422</td><td><code>amount_out_of_range</code></td><td>Below the provider minimum or above its maximum.</td></tr>
        <tr><td>422</td><td><code>limit_exceeded</code></td><td>Over a per-transaction or daily limit set on your account.</td></tr>
        <tr><td>403</td><td><code>account_not_allowed</code></td><td>Live payments are not enabled for your account yet.</td></tr>
        <tr><td>503</td><td><code>service_unavailable</code></td><td>New payments are temporarily paused for maintenance.</td></tr>
    </tbody>
</table></div>
<p>See <a href="{{ route('docs.show', 'errors') }}">Error codes</a> for the full list.</p>
