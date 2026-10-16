<p class="lead">We send a signed <code>POST</code> request to your endpoint whenever a payment or payout changes state. Webhooks are how you should learn that a payment is completed.</p>

<h2>Setting up an endpoint</h2>
<p>Add endpoints in <strong>Developers → Webhooks</strong>. Each endpoint belongs to one mode, has its own signing secret and can subscribe to all events or a chosen list. Live endpoints must use HTTPS, and every endpoint must resolve to a public internet address.</p>

<h2>Events</h2>
<div class="table-responsive"><table class="table table-sm">
    <thead><tr><th>Payments</th><th>Payouts</th></tr></thead>
    <tbody><tr>
        <td><code>payment.created</code><br><code>payment.pending</code><br><code>payment.processing</code><br><code>payment.completed</code><br><code>payment.underpaid</code><br><code>payment.failed</code><br><code>payment.expired</code><br><code>payment.cancelled</code><br><code>payment.on_hold</code><br><code>payment.refunded</code></td>
        <td><code>payout.requested</code><br><code>payout.approved</code><br><code>payout.processing</code><br><code>payout.completed</code><br><code>payout.failed</code><br><code>payout.rejected</code><br><code>payout.cancelled</code></td>
    </tr></tbody>
</table></div>
<p>The <strong>Send test event</strong> button next to each endpoint in the dashboard sends a <code>webhook.ping</code> event.</p>

<h2>Request format</h2>
<div class="code-window mb-3"><pre>POST /your/webhook HTTP/1.1
Content-Type: application/json
X-Gateway-Event: payment.completed
X-Gateway-Event-Id: evt_01j9x6...
X-Gateway-Timestamp: 1760616000
X-Gateway-Signature: t=1760616000,v1=5f8c2b...

{
  "id": "evt_01j9x6...",
  "type": "payment.completed",
  "mode": "live",
  "created_at": "2026-10-16T12:00:00+00:00",
  "data": { "id": "pay_01j9x4...", "object": "payment", "status": "completed", ... }
}</pre></div>
<p><code>data</code> is the full payment or payout object, in the same format the API returns.</p>

<h2>Verifying the signature</h2>
<ol>
    <li>Read the raw request body exactly as received, before any JSON parsing.</li>
    <li>Split <code>X-Gateway-Signature</code> on <code>,</code> to get <code>t</code> (Unix timestamp) and <code>v1</code> (signature).</li>
    <li>Compute <code>HMAC-SHA256(secret, t + "." + raw_body)</code> as lowercase hex.</li>
    <li>Compare it with <code>v1</code> using a constant-time comparison.</li>
    <li>Reject the request if <code>t</code> is more than 5 minutes from your current time. This blocks replayed requests.</li>
</ol>
<div class="code-window mb-3"><pre>// PHP
$payload   = file_get_contents('php://input');
$header    = $_SERVER['HTTP_X_GATEWAY_SIGNATURE'] ?? '';
parse_str(str_replace(',', '&amp;', $header), $parts);   // ['t' => ..., 'v1' => ...]
$expected  = hash_hmac('sha256', $parts['t'] . '.' . $payload, getenv('GATEWAY_WEBHOOK_SECRET'));

if (! hash_equals($expected, $parts['v1'] ?? '') || abs(time() - (int) $parts['t']) > 300) {
    http_response_code(400);
    exit;
}
$event = json_decode($payload, true);</pre></div>
<p>More languages are in <a href="{{ route('docs.show', 'code-examples') }}">Code examples</a>.</p>

<h2>Responding and retries</h2>
<ul>
    <li>Return any <code>2xx</code> status within 10 seconds. Do slow work (emails, fulfilment) after responding, or in a queue.</li>
    <li>Any other status, a timeout or a connection error counts as a failure. We retry after 1 minute, 5 minutes, 30 minutes, 2 hours, 6 hours, 12 hours and 24 hours. That is 8 attempts in total.</li>
    <li>After the last attempt the delivery is marked failed. You can see every attempt and retry it manually under <strong>Developers → Webhooks</strong>.</li>
</ul>

<h2>Duplicates and ordering</h2>
<ul>
    <li>We create each event once per status change, but a retry can deliver it again. Store <code>X-Gateway-Event-Id</code> and skip IDs you have already handled.</li>
    <li>Events can arrive out of order. Check <code>data.status</code> rather than assuming order. If unsure, read the latest state with <code>GET /payments/{id}</code>.</li>
</ul>

<h2>Rolling the secret</h2>
<p>Rolling an endpoint's secret in the dashboard replaces it immediately, and the old secret stops working. Update your server first, then roll.</p>
