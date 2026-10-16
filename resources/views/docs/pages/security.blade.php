<p class="lead">Your integration moves money, so it deserves the same care as a payment card integration. These practices protect you and your customers.</p>

<h2>Protect your secret keys</h2>
<ul>
    <li>Keep keys in server-side configuration or a secrets manager. Never put them in front-end code, mobile apps, logs or version control.</li>
    <li>Use separate keys per system, with only the scopes each one needs.</li>
    <li>Add an IP allowlist to live keys, especially keys with <code>payouts:write</code>.</li>
    <li>Rotate keys when someone with access leaves, and immediately if you suspect a leak. Revoke keys you no longer use.</li>
    <li>Turn on two-factor authentication for every dashboard user. It is required to create live keys.</li>
</ul>

<h2>Trust only verified status</h2>
<ul>
    <li>Fulfil orders only from a verified <code>payment.completed</code> webhook or from <code>GET /payments/{id}</code>, never from the customer reaching your <code>success_url</code>.</li>
    <li>Compare <code>received_amount</code> and <code>currency</code> with what you expected. Treat <code>underpaid</code> as not fully paid.</li>
    <li>Look up orders by your own <code>reference</code> and our <code>id</code>. Do not trust identifiers supplied by the browser.</li>
</ul>

<h2>Verify every webhook</h2>
<ul>
    <li>Check the HMAC signature over the <strong>raw</strong> body with a constant-time comparison.</li>
    <li>Reject timestamps older than 5 minutes to block replayed requests.</li>
    <li>Make handlers idempotent: remember processed event IDs.</li>
    <li>Serve your webhook endpoint over HTTPS. This is required in live mode.</li>
</ul>

<h2>Safe requests</h2>
<ul>
    <li>Send an <code>Idempotency-Key</code> with every <code>POST</code> and reuse it on retries.</li>
    <li>Handle amounts as decimal strings or integer minor units, never floating-point.</li>
    <li>Never let customers set fees or amounts that your server has not checked. The API rejects any request that tries to set a fee.</li>
</ul>

<h2>Payouts</h2>
<ul>
    <li>Build payout requests only from destinations your users registered and confirmed, for example by email confirmation.</li>
    <li>Check the address format and network before sending. Crypto payouts to a wrong address cannot be reversed.</li>
    <li>Watch <code>payout.failed</code> and <code>payout.rejected</code> events and alert your team.</li>
</ul>

<h2>What we do on our side</h2>
<ul>
    <li>API secrets are stored only as hashes; webhook secrets are encrypted at rest.</li>
    <li>Provider credentials are kept server-side only: in environment variables or encrypted in our database. They are never sent to browsers.</li>
    <li>Provider callbacks are verified by signature, and status is confirmed with the provider before any balance change.</li>
    <li>Every balance change is a balanced double-entry ledger record, protected against double spending with database transactions and locks.</li>
    <li>Sensitive admin actions are audit-logged, and live payouts can require manual approval.</li>
</ul>

<h2>Reporting a vulnerability</h2>
<p>If you find a security issue, please <a href="{{ route('contact') }}">contact us</a> privately before disclosing it.</p>
