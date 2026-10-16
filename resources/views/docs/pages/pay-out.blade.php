<p class="lead">A Pay Out sends funds from your available balance to a recipient. The amount plus fees is reserved straight away, so a payout can never spend money twice. Live payouts may need approval by our team before they are sent to the provider.</p>

<h2>Request a payout</h2>
<p><code>POST /payouts</code> · scope <code>payouts:write</code> · requires an <code>Idempotency-Key</code> header</p>
<div class="table-responsive"><table class="table table-sm">
    <thead><tr><th>Field</th><th>Type</th><th>Description</th></tr></thead>
    <tbody>
        <tr><td><code>amount</code> <span class="badge text-bg-danger">required</span></td><td>string</td><td>Amount the recipient should receive, as a decimal string.</td></tr>
        <tr><td><code>currency</code> <span class="badge text-bg-danger">required</span></td><td>string</td><td>Currency code. Paid from your balance in the same currency; currencies are never converted.</td></tr>
        <tr><td><code>reference</code> <span class="badge text-bg-danger">required</span></td><td>string</td><td>Your unique ID for this payout (1–64 characters: letters, digits, <code>_</code>, <code>-</code>).</td></tr>
        <tr><td><code>destination.type</code> <span class="badge text-bg-danger">required</span></td><td>string</td><td><code>crypto_address</code>, <code>binance_id</code> or <code>email</code>. Which types are allowed depends on the provider (see below).</td></tr>
        <tr><td><code>destination.value</code> <span class="badge text-bg-danger">required</span></td><td>string</td><td>The wallet address, numeric Binance ID or email address.</td></tr>
        <tr><td><code>destination.memo</code></td><td>string</td><td>Memo or destination tag, for networks that need one (up to 64 characters).</td></tr>
        <tr><td><code>network</code></td><td>string</td><td>Required for <code>crypto_address</code> payouts through Cryptomus, for example <code>tron</code>.</td></tr>
        <tr><td><code>provider</code></td><td>string</td><td><code>cryptomus</code> or <code>binance_pay</code>. Omit to pick the first enabled provider that supports the currency and destination type.</td></tr>
        <tr><td><code>description</code></td><td>string</td><td>Up to 255 characters.</td></tr>
        <tr><td><code>metadata</code></td><td>object</td><td>Up to 20 string values.</td></tr>
    </tbody>
</table></div>

<h3>Destinations per provider</h3>
<div class="table-responsive"><table class="table table-sm">
    <thead><tr><th>Provider</th><th>Destination types</th><th>Notes</th></tr></thead>
    <tbody>
        <tr><td>Cryptomus</td><td><code>crypto_address</code></td><td>Sent on-chain from the platform's Cryptomus wallet. <code>network</code> is required.</td></tr>
        <tr><td>Binance Pay</td><td><code>binance_id</code>, <code>email</code></td><td>Internal Binance transfer to a Binance account. Binance Pay cannot pay out to external blockchain addresses.</td></tr>
    </tbody>
</table></div>

<h3>Example</h3>
<div class="code-window mb-3"><pre>curl -X POST {{ $baseUrl }}/payouts \
  -H "Authorization: Bearer $GATEWAY_SECRET_KEY" \
  -H "Idempotency-Key: withdrawal-778" \
  -H "Content-Type: application/json" \
  -d '{
    "amount": "250.00",
    "currency": "USDT",
    "network": "tron",
    "reference": "withdrawal-778",
    "destination": { "type": "crypto_address", "value": "TQ5n...your-wallet" }
  }'</pre></div>

<h3>Response <span class="badge text-bg-success">201</span></h3>
<div class="code-window mb-3"><pre>{
  "data": {
    "id": "po_01j9x5...",
    "object": "payout",
    "status": "under_review",
    "provider": "cryptomus",
    "reference": "withdrawal-778",
    "amount": "250.00000000",
    "currency": "USDT",
    "network": "tron",
    "destination": { "type": "crypto_address", "value": "TQ5n...", "memo": null },
    "service_fee": "2.50000000",
    "network_fee": "1.00000000",
    "total_debit": "253.50000000",
    "tx_hash": null,
    "failure_reason": null,
    "rejection_reason": null
  }
}</pre></div>

<h2>How fees work</h2>
<p>Payout fees are added on top of the amount. The recipient gets <code>amount</code> and your balance is debited <code>total_debit</code> = amount + <code>service_fee</code> + <code>network_fee</code>. The network fee is the provider's own estimate where it publishes one. With a 1% fee on 10,000 USDT, the service fee is 100 and the total debit is 10,100 plus any network fee.</p>

<h2>Payout statuses</h2>
<div class="table-responsive"><table class="table table-sm">
    <thead><tr><th>Status</th><th>Meaning</th><th>Funds</th></tr></thead>
    <tbody>
        <tr><td><code>requested</code></td><td>Accepted and funds reserved.</td><td>Reserved</td></tr>
        <tr><td><code>under_review</code></td><td>Waiting for approval by our team.</td><td>Reserved</td></tr>
        <tr><td><code>approved</code></td><td>Approved and queued for the provider.</td><td>Reserved</td></tr>
        <tr><td><code>processing</code></td><td>Submitted to the provider.</td><td>Reserved</td></tr>
        <tr><td><code>completed</code></td><td>Sent. <code>tx_hash</code> is set when the provider returns one.</td><td>Debited</td></tr>
        <tr><td><code>rejected</code></td><td>Rejected by our team; see <code>rejection_reason</code>.</td><td>Returned</td></tr>
        <tr><td><code>failed</code></td><td>The provider could not send it; see <code>failure_reason</code>.</td><td>Returned</td></tr>
        <tr><td><code>cancelled</code></td><td>Cancelled by you before approval.</td><td>Returned</td></tr>
    </tbody>
</table></div>

<h2>Cancel a payout</h2>
<p><code>POST /payouts/{id}/cancel</code> · scope <code>payouts:write</code>. You can cancel only while the status is <code>requested</code> or <code>under_review</code>. Later states return <code>409 invalid_state</code>.</p>

<h2>Common errors</h2>
<div class="table-responsive"><table class="table table-sm">
    <thead><tr><th>Status</th><th>Code</th><th>When</th></tr></thead>
    <tbody>
        <tr><td>422</td><td><code>insufficient_balance</code></td><td>Available balance does not cover <code>total_debit</code>. <code>details</code> shows available and required amounts.</td></tr>
        <tr><td>422</td><td><code>destination_not_supported</code></td><td>The provider cannot pay to that destination type.</td></tr>
        <tr><td>422</td><td><code>network_required</code></td><td>A network is required for this currency and provider.</td></tr>
        <tr><td>409</td><td><code>duplicate_reference</code></td><td>The reference was already used for another payout.</td></tr>
        <tr><td>422</td><td><code>limit_exceeded</code></td><td>Over a per-transaction or daily payout limit.</td></tr>
        <tr><td>503</td><td><code>service_unavailable</code></td><td>New payouts are temporarily paused.</td></tr>
    </tbody>
</table></div>
