<p class="lead">Test mode and live mode are fully separate. The API key you use decides the mode, and each mode has its own payments, payouts, balances, webhooks and logs.</p>

<div class="table-responsive"><table class="table">
    <thead><tr><th></th><th>Test mode</th><th>Live mode</th></tr></thead>
    <tbody>
        <tr><td>Key prefix</td><td><code>sk_test_</code></td><td><code>sk_live_</code></td></tr>
        <tr><td>Available</td><td>Immediately after sign-up</td><td>After business verification is approved and live mode is enabled for your account</td></tr>
        <tr><td>Payments</td><td>Processed by our built-in simulator. No real funds move.</td><td>Created with Cryptomus or Binance Pay</td></tr>
        <tr><td>Payouts</td><td>Approved automatically and simulated</td><td>Reviewed by our team (when approval is required), then sent through the provider</td></tr>
        <tr><td>Data</td><td>Only visible to test keys and the dashboard's test view</td><td>Only visible to live keys and the dashboard's live view</td></tr>
    </tbody>
</table></div>

<h2>Why a simulator?</h2>
<p>Neither Cryptomus nor Binance Pay offers a public sandbox for merchant payments. To let you build and test your integration safely, test mode uses a simulator that follows the same request and response formats, status flow and webhooks as live mode.</p>

<h2>Simulating payments</h2>
<p>Create a payment with a test key, then open its <code>checkout_url</code>. The sandbox checkout page lets you choose the outcome:</p>
<div class="table-responsive"><table class="table table-sm">
    <thead><tr><th>Outcome</th><th>Resulting status</th></tr></thead>
    <tbody>
        <tr><td>Paid</td><td><code>completed</code>, and your test balance is credited with the net amount</td></tr>
        <tr><td>Underpaid</td><td><code>underpaid</code>, and only the amount actually received is credited</td></tr>
        <tr><td>Failed</td><td><code>failed</code></td></tr>
        <tr><td>Expired</td><td><code>expired</code></td></tr>
    </tbody>
</table></div>
<p>Each outcome sends the same webhook events a live payment would, so you can test your fulfilment logic end to end.</p>

<h2>Simulating payouts</h2>
<p>Test payouts are approved automatically. The create response shows <code>approved</code>, and a few moments later the payout moves to <code>completed</code> and a <code>payout.completed</code> webhook is sent. To simulate a provider-side failure, include the word <code>fail</code> in the destination value. For example, use a crypto address such as <code>TFAILxxxxxxxxxxxxxxxxxxxxx</code>. The payout then ends in <code>failed</code> and the reserved amount returns to your test balance.</p>
<p>You need a test balance to request a test payout, so complete a few simulated payments first.</p>

<h2>Going live</h2>
<ol>
    <li>Submit business verification from <strong>Settings → Verification</strong>.</li>
    <li>Once approved, create a <strong>live</strong> API key and a live webhook endpoint (HTTPS only).</li>
    <li>Swap the test key for the live key in your server configuration.</li>
    <li>Run one small real payment before you announce the launch.</li>
</ol>
