<p class="lead">Rate limits keep the platform fast for everyone. Idempotency keys make it safe to retry money-moving requests without creating duplicates.</p>

<h2>Rate limits</h2>
<div class="table-responsive"><table class="table table-sm">
    <thead><tr><th>Limit</th><th>Value</th></tr></thead>
    <tbody>
        <tr><td>Requests per API key</td><td>120 per minute by default. Set per key in the dashboard; the platform maximum is 1,200 per minute.</td></tr>
        <tr><td><code>POST /payments/{id}/verify</code></td><td>6 per minute for each payment</td></tr>
    </tbody>
</table></div>
<p>Over the limit, you get <code>429 rate_limited</code>. Limits are counted per minute, so back off and retry after a short wait. Use webhooks instead of polling to stay well below the limits.</p>

<h2>Idempotency</h2>
<p><code>POST /payments</code> and <code>POST /payouts</code> require an <code>Idempotency-Key</code> header: any unique string of 1–191 printable ASCII characters, such as your order ID or a UUID.</p>
<div class="table-responsive"><table class="table table-sm">
    <thead><tr><th>Situation</th><th>Result</th></tr></thead>
    <tbody>
        <tr><td>First request with a key</td><td>Runs normally. A successful (2xx) response is stored for 24 hours.</td></tr>
        <tr><td>Same key, same body, after success</td><td>The stored response is returned again with header <code>Idempotent-Replayed: true</code>. Nothing new is created.</td></tr>
        <tr><td>Same key, different body</td><td><code>422 idempotency_key_reused</code></td></tr>
        <tr><td>Same key while the first request is still running</td><td><code>409 request_in_progress</code>. Retry shortly.</td></tr>
        <tr><td>First request failed (4xx/5xx)</td><td>Nothing is stored, so you can retry with the same key.</td></tr>
    </tbody>
</table></div>
<p>Keys are scoped to your account and mode. Reusing a key after 24 hours starts a new request.</p>

<h2>A second safety net: references</h2>
<p>Each payment and payout <code>reference</code> must be unique within your account and mode. Even with a new idempotency key, a repeated reference returns <code>409 duplicate_reference</code> with the ID of the existing object.</p>

<h2>Recommended retry strategy</h2>
<ul>
    <li>Network error, timeout or 5xx: retry with the same idempotency key, with exponential backoff (for example 1s, 2s, 4s, 8s).</li>
    <li><code>429</code>: back off (for example 5s, 15s, 60s) and retry.</li>
    <li>Other 4xx errors: do not retry until you have fixed the request.</li>
</ul>
