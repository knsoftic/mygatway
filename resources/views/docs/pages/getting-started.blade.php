<p class="lead">The {{ config('app.name') }} API lets your server create crypto payments (Pay In), request payouts (Pay Out), read balances and receive signed webhooks, through one interface for every supported provider.</p>

<h2>Base URL</h2>
<pre class="code-window p-3"><code>{{ $baseUrl }}</code></pre>
<p>All requests and responses use JSON over HTTPS. Amounts are <strong>decimal strings</strong> with up to 8 decimals (for example <code>"125.50"</code>), never floating-point numbers.</p>

<h2>Quick start</h2>
<ol>
    <li><a href="{{ route('register') }}">Create a merchant account</a> and verify your email.</li>
    <li>Open <strong>Developers → API keys</strong> and create a <strong>test</strong> key. Copy the secret (<code>sk_test_…</code>); it is shown once.</li>
    <li>Create your first payment from your server:</li>
</ol>
<div class="code-window mb-3"><pre>curl -X POST {{ $baseUrl }}/payments \
  -H "Authorization: Bearer $GATEWAY_SECRET_KEY" \
  -H "Idempotency-Key: order-1001" \
  -H "Content-Type: application/json" \
  -d '{"amount":"25.00","currency":"USDT","reference":"order-1001"}'</pre></div>
<ol start="4">
    <li>Redirect your customer to the returned <code>checkout_url</code>. In test mode this opens the sandbox checkout where you choose the outcome.</li>
    <li>Configure a webhook endpoint and fulfil the order when you receive <code>payment.completed</code>.</li>
</ol>

<h2>Core concepts</h2>
<ul>
    <li><strong>Modes.</strong> Test keys only see test data and use the sandbox simulator; live keys only see live data and use real providers.</li>
    <li><strong>Providers.</strong> Cryptomus and Binance Pay are integrated behind the same endpoints. Pass <code>provider</code> to choose, or let the platform select an enabled provider for the currency.</li>
    <li><strong>Fees.</strong> Calculated by the platform, returned on every object (<code>service_fee</code>), never accepted from requests.</li>
    <li><strong>Verified status.</strong> A payment is completed only from signed provider callbacks confirmed by an authenticated provider status query, never from a redirect.</li>
</ul>
<p>The endpoints described here are this platform's own API. They are not Cryptomus or Binance endpoints.</p>
