<p class="lead">Errors use standard HTTP status codes and always return the same JSON shape, with a stable machine-readable <code>code</code>.</p>

<div class="code-window mb-3"><pre>{
  "error": {
    "code": "validation_error",
    "message": "The request parameters are invalid.",
    "details": { "fields": { "amount": ["The amount must be greater than zero."] } },
    "request_id": "9f2c1c1e-..."
  }
}</pre></div>
<p>Write your logic against <code>code</code>, not <code>message</code>. Messages may change, codes will not. <code>details</code> is only present when there is extra information.</p>

<h2>Retry or not?</h2>
<ul>
    <li><strong>4xx</strong>: fix the request first. Retrying the same request will fail the same way. The exceptions are <code>409 request_in_progress</code> and <code>429</code>, which you can retry after a short wait.</li>
    <li><strong>5xx</strong>: safe to retry with the <strong>same</strong> <code>Idempotency-Key</code>. Failed requests are not stored, so the retry runs again without creating a duplicate.</li>
</ul>

<h2>All error codes</h2>
<div class="table-responsive"><table class="table table-sm">
    <thead><tr><th>Status</th><th>Code</th><th>Meaning</th></tr></thead>
    <tbody>
        <tr class="table-light"><td colspan="3" class="fw-semibold">Authentication &amp; access</td></tr>
        <tr><td>401</td><td><code>authentication_required</code></td><td>No API key was sent.</td></tr>
        <tr><td>401</td><td><code>invalid_api_key</code></td><td>The key is invalid, expired or revoked.</td></tr>
        <tr><td>403</td><td><code>ip_not_allowed</code></td><td>The request IP is not on the key's allowlist.</td></tr>
        <tr><td>403</td><td><code>insufficient_scope</code></td><td>The key lacks the scope in <code>details.required_scope</code>.</td></tr>
        <tr><td>403</td><td><code>account_suspended</code></td><td>The merchant account is suspended.</td></tr>
        <tr><td>403</td><td><code>account_not_allowed</code></td><td>The account cannot perform this action, for example live payments before verification.</td></tr>

        <tr class="table-light"><td colspan="3" class="fw-semibold">Request</td></tr>
        <tr><td>422</td><td><code>validation_error</code></td><td>One or more fields are invalid. <code>details.fields</code> lists them.</td></tr>
        <tr><td>400</td><td><code>idempotency_key_required</code></td><td>The <code>POST</code> needs an <code>Idempotency-Key</code> header.</td></tr>
        <tr><td>400</td><td><code>invalid_idempotency_key</code></td><td>The key must be 1–191 printable ASCII characters.</td></tr>
        <tr><td>422</td><td><code>idempotency_key_reused</code></td><td>This key was already used with a different request body.</td></tr>
        <tr><td>409</td><td><code>request_in_progress</code></td><td>A request with this key is still running.</td></tr>
        <tr><td>409</td><td><code>duplicate_reference</code></td><td>The <code>reference</code> was already used; see <code>details.existing_id</code>.</td></tr>
        <tr><td>404</td><td><code>resource_not_found</code></td><td>No such object for your account and mode.</td></tr>
        <tr><td>429</td><td><code>rate_limited</code></td><td>Too many requests. See <a href="{{ route('docs.show', 'rate-limits') }}">Rate limits</a>.</td></tr>

        <tr class="table-light"><td colspan="3" class="fw-semibold">Payments &amp; payouts</td></tr>
        <tr><td>422</td><td><code>currency_not_supported</code></td><td>The provider does not support this currency in this direction.</td></tr>
        <tr><td>422</td><td><code>network_not_supported</code></td><td>The network is not supported for this currency.</td></tr>
        <tr><td>422</td><td><code>network_required</code></td><td>A network must be given for this payout.</td></tr>
        <tr><td>422</td><td><code>amount_out_of_range</code></td><td>Below the minimum or above the maximum; see <code>details</code>.</td></tr>
        <tr><td>422</td><td><code>limit_exceeded</code></td><td>Over a per-transaction or daily limit on your account.</td></tr>
        <tr><td>422</td><td><code>insufficient_balance</code></td><td>Available balance does not cover the payout's total debit.</td></tr>
        <tr><td>422</td><td><code>destination_not_supported</code></td><td>The provider cannot pay out to this destination type.</td></tr>
        <tr><td>422</td><td><code>provider_not_available</code></td><td>No enabled provider can handle the request, or the chosen one is not available.</td></tr>
        <tr><td>422</td><td><code>unsupported_operation</code></td><td>The provider does not support this operation.</td></tr>
        <tr><td>409</td><td><code>invalid_state</code></td><td>The object's current status does not allow this, for example cancelling an approved payout.</td></tr>

        <tr class="table-light"><td colspan="3" class="fw-semibold">Platform &amp; provider</td></tr>
        <tr><td>502 / 503</td><td><code>provider_error</code> and other provider codes</td><td>The provider rejected or failed the request. <code>details.retryable</code> says whether a retry may succeed (503) or not (502).</td></tr>
        <tr><td>503</td><td><code>service_unavailable</code></td><td>New payments or payouts are temporarily paused for maintenance.</td></tr>
        <tr><td>500</td><td><code>internal_error</code></td><td>Unexpected error on our side. Retry, and contact support with the <code>request_id</code> if it continues.</td></tr>
    </tbody>
</table></div>
