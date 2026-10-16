<p class="lead">Two providers sit behind the API. The endpoints are the same for both, but what each one can do differs. The table below lists only features that are implemented and available through the provider's official merchant API.</p>

<div class="table-responsive mb-4"><table class="table">
    <thead><tr><th>Feature</th><th>Cryptomus</th><th>Binance Pay</th></tr></thead>
    <tbody>
        <tr><td>Pay In</td><td><i class="bi bi-check-lg text-success"></i> Hosted invoice, customer pays from any wallet</td><td><i class="bi bi-check-lg text-success"></i> Hosted checkout, QR code or deeplink; customer pays from their Binance account</td></tr>
        <tr><td>Pay Out</td><td><i class="bi bi-check-lg text-success"></i> To blockchain wallet addresses (<code>crypto_address</code>)</td><td><i class="bi bi-check-lg text-success"></i> To Binance accounts (<code>binance_id</code>, or <code>email</code> for whitelisted merchants)</td></tr>
        <tr><td>Payout to an external wallet address</td><td><i class="bi bi-check-lg text-success"></i></td><td><i class="bi bi-x-lg text-danger"></i> Not offered by the Binance Pay Merchant API</td></tr>
        <tr><td>Network required for payouts</td><td>Yes</td><td>No</td></tr>
        <tr><td>Refunds</td><td>Full amount only, to a customer wallet address</td><td>Full or partial</td></tr>
        <tr><td>Signed callbacks</td><td><i class="bi bi-check-lg text-success"></i> Signed with the merchant API key, checked against Cryptomus's published source IP</td><td><i class="bi bi-check-lg text-success"></i> Signed with Binance's certificate (RSA)</td></tr>
        <tr><td>Status query</td><td><i class="bi bi-check-lg text-success"></i></td><td><i class="bi bi-check-lg text-success"></i></td></tr>
        <tr><td>Provider sandbox</td><td>None (test webhooks only)</td><td>None</td></tr>
    </tbody>
</table></div>

<p>Refunds are started by our support team from the admin panel; there is no refund endpoint in the merchant API yet.</p>

<h2>Choosing a provider</h2>
<ul>
    <li>Leave out <code>provider</code> and we use the first enabled provider, in platform priority order, that supports your currency, network, amount and (for payouts) destination type.</li>
    <li>Set <code>provider</code> to use a specific one. If it cannot handle the request, you get a clear error (for example <code>destination_not_supported</code>) instead of a silent switch.</li>
    <li>If no provider can handle a request, the error explains why each one was skipped.</li>
</ul>

<h2>Currencies and limits</h2>
<p>Supported currencies, networks and minimum and maximum amounts come from each provider and are synced daily. Always read them from <code>GET /currencies</code> rather than hard-coding them. Values may change when the provider updates its list.</p>

<h2>Live capabilities</h2>
<p><code>GET /currencies</code> also returns each enabled provider's capabilities, so your integration can hide options a provider cannot offer. For example, hide "withdraw to wallet address" when only Binance Pay is enabled for payouts.</p>

<h2>Status mapping</h2>
<p>Each provider reports status in its own words. We map them to the common statuses in <a href="{{ route('docs.show', 'pay-in') }}">Pay In</a> and <a href="{{ route('docs.show', 'pay-out') }}">Pay Out</a>. The provider's original value is kept for our support team, but your integration only needs the common statuses.</p>
