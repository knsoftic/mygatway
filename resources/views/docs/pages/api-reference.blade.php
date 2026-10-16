<p class="lead">All endpoints of the v1 merchant API. Base URL: <code>{{ $baseUrl }}</code>. Every request needs <code>Authorization: Bearer sk_…</code>.</p>

<div class="table-responsive mb-4"><table class="table table-sm">
    <thead><tr><th>Method</th><th>Path</th><th>Scope</th><th>Description</th></tr></thead>
    <tbody>
        <tr><td><span class="badge text-bg-primary">POST</span></td><td><code>/payments</code></td><td><code>payments:write</code></td><td>Create a payment. Needs <code>Idempotency-Key</code>.</td></tr>
        <tr><td><span class="badge text-bg-success">GET</span></td><td><code>/payments</code></td><td><code>payments:read</code></td><td>List payments</td></tr>
        <tr><td><span class="badge text-bg-success">GET</span></td><td><code>/payments/{id}</code></td><td><code>payments:read</code></td><td>Retrieve a payment</td></tr>
        <tr><td><span class="badge text-bg-primary">POST</span></td><td><code>/payments/{id}/verify</code></td><td><code>payments:write</code></td><td>Re-check an open payment with the provider</td></tr>
        <tr><td><span class="badge text-bg-primary">POST</span></td><td><code>/payouts</code></td><td><code>payouts:write</code></td><td>Request a payout. Needs <code>Idempotency-Key</code>.</td></tr>
        <tr><td><span class="badge text-bg-success">GET</span></td><td><code>/payouts</code></td><td><code>payouts:read</code></td><td>List payouts</td></tr>
        <tr><td><span class="badge text-bg-success">GET</span></td><td><code>/payouts/{id}</code></td><td><code>payouts:read</code></td><td>Retrieve a payout</td></tr>
        <tr><td><span class="badge text-bg-primary">POST</span></td><td><code>/payouts/{id}/cancel</code></td><td><code>payouts:write</code></td><td>Cancel a payout before approval</td></tr>
        <tr><td><span class="badge text-bg-success">GET</span></td><td><code>/balance</code></td><td><code>balance:read</code></td><td>Balances per currency</td></tr>
        <tr><td><span class="badge text-bg-success">GET</span></td><td><code>/transactions</code></td><td><code>payments:read</code></td><td>Ledger history (every balance change)</td></tr>
        <tr><td><span class="badge text-bg-success">GET</span></td><td><code>/fees</code></td><td>any</td><td>Fee rules that apply to you</td></tr>
        <tr><td><span class="badge text-bg-success">GET</span></td><td><code>/fees/quote</code></td><td>any</td><td>Calculate the fee for an amount</td></tr>
        <tr><td><span class="badge text-bg-success">GET</span></td><td><code>/currencies</code></td><td>any</td><td>Supported providers, currencies and networks</td></tr>
    </tbody>
</table></div>
<p>Request fields and status values for payments and payouts are described in <a href="{{ route('docs.show', 'pay-in') }}">Pay In</a> and <a href="{{ route('docs.show', 'pay-out') }}">Pay Out</a>.</p>

<h2>Conventions</h2>
<ul>
    <li><strong>Amounts</strong> are strings with 8 decimals in responses, for example <code>"49.90000000"</code>. Send them as strings too. Numbers are accepted but converted to strings before validation.</li>
    <li><strong>IDs</strong> are prefixed: <code>pay_</code> payments, <code>po_</code> payouts, <code>txn_</code> ledger transactions, <code>evt_</code> webhook events.</li>
    <li><strong>Timestamps</strong> are ISO 8601 with a time zone.</li>
    <li>Single objects are returned inside <code>data</code>. Lists return <code>data</code> (an array), <code>links</code> and <code>meta</code>.</li>
    <li>Every response has an <code>X-Request-Id</code> header. Include it when you contact support.</li>
</ul>

<h2>Pagination</h2>
<p>Lists use cursor pagination, newest first. Pass <code>limit</code> (1–100, default 25). To get the next page, pass <code>cursor</code> = <code>meta.next_cursor</code> from the previous response. When <code>meta.next_cursor</code> is <code>null</code>, there are no more pages.</p>
<div class="code-window mb-3"><pre>{
  "data": [ { "id": "pay_...", ... } ],
  "links": { "first": null, "last": null, "prev": null, "next": "{{ $baseUrl }}/payments?cursor=eyJ..." },
  "meta": { "path": "{{ $baseUrl }}/payments", "per_page": 25, "next_cursor": "eyJ...", "prev_cursor": null }
}</pre></div>

<h2>GET /balance</h2>
<p>Balances per currency. Different currencies are never added together.</p>
<div class="code-window mb-3"><pre>{
  "object": "balance",
  "mode": "live",
  "balances": [
    { "currency": "USDT", "available": "1520.40000000", "reserved": "253.50000000", "pending": "100.00000000" }
  ]
}</pre></div>
<ul>
    <li><code>available</code>: funds you can pay out now.</li>
    <li><code>reserved</code>: held for payouts that are not final yet.</li>
    <li><code>pending</code>: gross amount of payments seen by the provider but not yet confirmed. Not yet credited.</li>
</ul>

<h2>GET /transactions</h2>
<p>Every change to your balance, from the ledger. Filters: <code>currency</code>, <code>created_from</code>, <code>created_to</code>, <code>limit</code>, <code>cursor</code>.</p>
<div class="code-window mb-3"><pre>{
  "data": [{
    "id": "txn_...",
    "object": "transaction",
    "mode": "live",
    "type": "payin_settlement",
    "currency": "USDT",
    "description": "...",
    "source": { "object": "payment", "id": "pay_..." },
    "entries": [
      { "balance": "available", "direction": "in", "amount": "49.40100000", "balance_after": "1520.40000000" }
    ],
    "posted_at": "2026-10-16T12:05:00+00:00"
  }]
}</pre></div>

<h2>GET /fees</h2>
<p>For each enabled provider, direction and currency, the fee rule that currently applies to your account.</p>
<div class="code-window mb-3"><pre>{ "object": "list", "data": [
  { "direction": "payin", "provider": "cryptomus", "currency": "USDT",
    "percentage": "1.0000", "fixed_amount": "0.00000000", "min_fee": null, "max_fee": null }
] }</pre></div>

<h2>GET /fees/quote</h2>
<p>Query parameters (all required): <code>direction</code> (<code>payin</code> or <code>payout</code>), <code>provider</code>, <code>currency</code>, <code>amount</code>.</p>
<div class="code-window mb-3"><pre>GET /fees/quote?direction=payin&amp;provider=cryptomus&amp;currency=USDT&amp;amount=10000

{ "object": "fee_quote", "direction": "payin", "provider": "cryptomus", "currency": "USDT",
  "amount": "10000.00000000", "service_fee": "100.00000000", "net_amount": "9900.00000000", "note": null }</pre></div>
<p>For payouts, <code>net_amount</code> is <code>null</code> and <code>note</code> explains that a provider network fee may be added when the payout is created.</p>

<h2>GET /currencies</h2>
<p>Enabled providers with their capabilities, plus every currency and network you can use, with minimum and maximum amounts.</p>
<div class="code-window mb-3"><pre>{
  "object": "list",
  "providers": [
    { "code": "cryptomus", "name": "Cryptomus", "pay_in": true, "pay_out": true, "capabilities": { "pay_in": true, "pay_out": true, ... } }
  ],
  "data": [
    { "provider": "cryptomus", "direction": "payin", "currency": "USDT", "network": "tron", "min_amount": "1.00000000", "max_amount": null }
  ]
}</pre></div>
