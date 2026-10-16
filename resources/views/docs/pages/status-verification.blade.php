<p class="lead">Webhooks are the fastest way to learn about status changes. You can also read the current status at any time, and ask us to re-check an open payment with the provider.</p>

<h2>How we verify payments</h2>
<ol>
    <li>The provider sends a callback to our server. We check its signature and, for Cryptomus, its source IP. Callbacks that fail these checks are logged and ignored.</li>
    <li>We then confirm the status with an authenticated server-to-server query to the provider before applying it.</li>
    <li>Only after that do we update the payment, post the ledger entries and send your webhook.</li>
</ol>
<p>Customer redirects, browser responses and checkout page visits never change a payment's status.</p>

<h2>Read a payment</h2>
<p><code>GET /payments/{id}</code> · scope <code>payments:read</code>. Returns the payment as we currently know it. This makes no call to the provider.</p>
<div class="code-window mb-3"><pre>curl {{ $baseUrl }}/payments/pay_01j9x4... \
  -H "Authorization: Bearer $GATEWAY_SECRET_KEY"</pre></div>

<h2>Re-verify with the provider</h2>
<p><code>POST /payments/{id}/verify</code> · scope <code>payments:write</code></p>
<p>For a live payment that is still open (<code>created</code>, <code>pending</code>, <code>processing</code>, <code>confirming</code> or <code>on_hold</code>) or <code>refund_pending</code>, this queries the provider and applies any verified change. For final or test-mode payments, it returns the stored payment unchanged.</p>
<ul>
    <li>Limited to 6 calls per minute per payment. More return <code>429 rate_limited</code>.</li>
    <li>Use it to recover from a missed webhook, not to poll. We already sync open payments in the background every few minutes.</li>
</ul>

<h2>Read a payout</h2>
<p><code>GET /payouts/{id}</code> · scope <code>payouts:read</code>. Processing payouts are synced with the provider in the background, and you receive a webhook when they reach a final state.</p>

<h2>List and filter</h2>
<p><code>GET /payments</code> and <code>GET /payouts</code> accept these query parameters:</p>
<div class="table-responsive"><table class="table table-sm">
    <tbody>
        <tr><td><code>status</code></td><td>One status value</td></tr>
        <tr><td><code>currency</code></td><td>Currency code</td></tr>
        <tr><td><code>reference</code></td><td>Your exact reference</td></tr>
        <tr><td><code>created_from</code>, <code>created_to</code></td><td>Dates or ISO 8601 timestamps</td></tr>
        <tr><td><code>limit</code></td><td>1–100, default 25</td></tr>
        <tr><td><code>cursor</code></td><td>The <code>meta.next_cursor</code> value from the previous page</td></tr>
    </tbody>
</table></div>
<p>Results are newest first. To find a payment by your order ID, use <code>GET /payments?reference=order-1001</code>.</p>

<h2>Isolation</h2>
<p>A key can only read its own merchant's records in its own mode. Asking for another merchant's ID, or for a live ID with a test key, returns <code>404 resource_not_found</code>, exactly as if the record did not exist.</p>
