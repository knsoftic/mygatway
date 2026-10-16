<p class="lead">Every API request is authenticated with a secret API key sent as a Bearer token. The key identifies your merchant account and decides whether the request runs in test or live mode.</p>

<h2>Creating a key</h2>
<ol>
    <li>Sign in and open <strong>Developers → API keys</strong>.</li>
    <li>Choose <strong>Test</strong> or <strong>Live</strong>. Live keys are only available after your business verification is approved.</li>
    <li>Pick the permissions (scopes) the key needs, and optionally restrict it to your server IP addresses.</li>
    <li>Copy the secret. It starts with <code>sk_test_</code> or <code>sk_live_</code> and is <strong>shown only once</strong>. We store only a hash of it and cannot show it again.</li>
</ol>

<h2>Sending the key</h2>
<div class="code-window mb-3"><pre>curl {{ $baseUrl }}/balance \
  -H "Authorization: Bearer sk_test_..."</pre></div>
<p>Keys belong on your server only. Never put a secret key in browser JavaScript, a mobile app, or a public repository.</p>

<h2>Scopes</h2>
<p>Each key carries a set of permissions. A request to an endpoint the key is not allowed to use fails with <code>403 insufficient_scope</code>.</p>
<div class="table-responsive"><table class="table table-sm">
    <thead><tr><th>Scope</th><th>Allows</th></tr></thead>
    <tbody>
        <tr><td><code>payments:write</code></td><td><code>POST /payments</code>, <code>POST /payments/{id}/verify</code></td></tr>
        <tr><td><code>payments:read</code></td><td><code>GET /payments</code>, <code>GET /payments/{id}</code>, <code>GET /transactions</code></td></tr>
        <tr><td><code>payouts:write</code></td><td><code>POST /payouts</code>, <code>POST /payouts/{id}/cancel</code></td></tr>
        <tr><td><code>payouts:read</code></td><td><code>GET /payouts</code>, <code>GET /payouts/{id}</code></td></tr>
        <tr><td><code>balance:read</code></td><td><code>GET /balance</code></td></tr>
        <tr><td><em>any valid key</em></td><td><code>GET /fees</code>, <code>GET /fees/quote</code>, <code>GET /currencies</code></td></tr>
    </tbody>
</table></div>
<p>Give each integration only the scopes it uses. For example, a checkout server that only takes payments does not need <code>payouts:write</code>.</p>

<h2>IP allowlist</h2>
<p>When a key has an IP allowlist, requests from any other address are rejected with <code>403 ip_not_allowed</code>. We recommend an allowlist for every live key that can request payouts.</p>

<h2>Rotation and revocation</h2>
<ul>
    <li><strong>Rotate</strong> issues a new secret with the same settings and revokes the old one. Deploy the new secret before you rotate, or expect requests with the old secret to fail.</li>
    <li><strong>Revoke</strong> disables a key immediately. Revoked or expired keys return <code>401 invalid_api_key</code>.</li>
    <li>The dashboard shows when and from which IP each key was last used, which helps you find unused keys.</li>
</ul>

<h2>Authentication errors</h2>
<div class="table-responsive"><table class="table table-sm">
    <thead><tr><th>Status</th><th>Code</th><th>Meaning</th></tr></thead>
    <tbody>
        <tr><td>401</td><td><code>authentication_required</code></td><td>No <code>Authorization: Bearer</code> header was sent.</td></tr>
        <tr><td>401</td><td><code>invalid_api_key</code></td><td>The key is wrong, expired or revoked.</td></tr>
        <tr><td>403</td><td><code>ip_not_allowed</code></td><td>The request came from an IP that is not on the key's allowlist.</td></tr>
        <tr><td>403</td><td><code>insufficient_scope</code></td><td>The key lacks the scope named in <code>details.required_scope</code>.</td></tr>
        <tr><td>403</td><td><code>account_suspended</code></td><td>Your merchant account is suspended. Contact support.</td></tr>
    </tbody>
</table></div>
