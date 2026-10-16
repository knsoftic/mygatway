<p class="lead">Minimal, dependency-light examples for creating a payment and verifying a webhook. Set <code>GATEWAY_SECRET_KEY</code> and <code>GATEWAY_WEBHOOK_SECRET</code> as environment variables on your server.</p>

<h2>PHP</h2>
<h3>Create a payment</h3>
<div class="code-window mb-3"><pre>&lt;?php
$orderId = 'order-1001';

$ch = curl_init('{{ $baseUrl }}/payments');
curl_setopt_array($ch, [
    CURLOPT_POST           =&gt; true,
    CURLOPT_RETURNTRANSFER =&gt; true,
    CURLOPT_HTTPHEADER     =&gt; [
        'Authorization: Bearer ' . getenv('GATEWAY_SECRET_KEY'),
        'Idempotency-Key: ' . $orderId,
        'Content-Type: application/json',
    ],
    CURLOPT_POSTFIELDS =&gt; json_encode([
        'amount'      =&gt; '49.90',
        'currency'    =&gt; 'USDT',
        'reference'   =&gt; $orderId,
        'success_url' =&gt; 'https://shop.example.com/orders/1001/thanks',
    ]),
]);
$body   = json_decode(curl_exec($ch), true);
$status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);

if ($status !== 201) {
    throw new RuntimeException($body['error']['code'] . ': ' . $body['error']['message']);
}
header('Location: ' . $body['data']['checkout_url']);</pre></div>

<h3>Verify a webhook</h3>
<div class="code-window mb-3"><pre>&lt;?php
function verifyGatewayWebhook(string $payload, string $header, string $secret, int $tolerance = 300): ?array
{
    $parts = [];
    foreach (explode(',', $header) as $pair) {
        [$k, $v] = array_pad(explode('=', $pair, 2), 2, '');
        $parts[trim($k)] = trim($v);
    }
    if (empty($parts['t']) || empty($parts['v1']) || abs(time() - (int) $parts['t']) &gt; $tolerance) {
        return null;
    }
    $expected = hash_hmac('sha256', $parts['t'] . '.' . $payload, $secret);

    return hash_equals($expected, $parts['v1']) ? json_decode($payload, true) : null;
}

$event = verifyGatewayWebhook(file_get_contents('php://input'), $_SERVER['HTTP_X_GATEWAY_SIGNATURE'] ?? '', getenv('GATEWAY_WEBHOOK_SECRET'));
if ($event === null) { http_response_code(400); exit; }

if ($event['type'] === 'payment.completed') {
    // markOrderPaid($event['data']['reference'], $event['data']['id']);  // idempotent!
}
http_response_code(200);</pre></div>

<h2>JavaScript (Node.js 18+)</h2>
<h3>Create a payment</h3>
<div class="code-window mb-3"><pre>const res = await fetch('{{ $baseUrl }}/payments', {
  method: 'POST',
  headers: {
    Authorization: `Bearer ${process.env.GATEWAY_SECRET_KEY}`,
    'Idempotency-Key': 'order-1001',
    'Content-Type': 'application/json',
  },
  body: JSON.stringify({ amount: '49.90', currency: 'USDT', reference: 'order-1001' }),
});
const body = await res.json();
if (res.status !== 201) throw new Error(`${body.error.code}: ${body.error.message}`);
console.log(body.data.checkout_url);</pre></div>

<h3>Verify a webhook (Express)</h3>
<div class="code-window mb-3"><pre>import crypto from 'node:crypto';
import express from 'express';

const app = express();

// Use the raw body: the signature is computed over the exact bytes we sent.
app.post('/webhooks/gateway', express.raw({ type: 'application/json' }), (req, res) =&gt; {
  const header = req.get('X-Gateway-Signature') ?? '';
  const parts = Object.fromEntries(header.split(',').map((p) =&gt; p.split('=', 2).map((s) =&gt; s.trim())));
  const t = Number(parts.t);

  const expected = crypto.createHmac('sha256', process.env.GATEWAY_WEBHOOK_SECRET)
    .update(`${parts.t}.${req.body}`).digest('hex');
  const valid = parts.v1 &amp;&amp; expected.length === parts.v1.length
    &amp;&amp; crypto.timingSafeEqual(Buffer.from(expected), Buffer.from(parts.v1))
    &amp;&amp; Math.abs(Date.now() / 1000 - t) &lt;= 300;

  if (!valid) return res.sendStatus(400);

  const event = JSON.parse(req.body);
  if (event.type === 'payment.completed') {
    // await markOrderPaid(event.data.reference, event.data.id); // idempotent!
  }
  res.sendStatus(200);
});</pre></div>

<h2>Python 3</h2>
<h3>Create a payment</h3>
<div class="code-window mb-3"><pre>import os, requests

resp = requests.post(
    "{{ $baseUrl }}/payments",
    headers={
        "Authorization": f"Bearer {os.environ['GATEWAY_SECRET_KEY']}",
        "Idempotency-Key": "order-1001",
    },
    json={"amount": "49.90", "currency": "USDT", "reference": "order-1001"},
    timeout=30,
)
body = resp.json()
if resp.status_code != 201:
    raise RuntimeError(f"{body['error']['code']}: {body['error']['message']}")
print(body["data"]["checkout_url"])</pre></div>

<h3>Verify a webhook (Flask)</h3>
<div class="code-window mb-3"><pre>import hashlib, hmac, json, os, time
from flask import Flask, request, abort

app = Flask(__name__)

@app.post("/webhooks/gateway")
def gateway_webhook():
    payload = request.get_data()  # raw bytes
    parts = dict(p.strip().split("=", 1) for p in request.headers.get("X-Gateway-Signature", "").split(",") if "=" in p)
    t, v1 = parts.get("t", ""), parts.get("v1", "")

    expected = hmac.new(os.environ["GATEWAY_WEBHOOK_SECRET"].encode(), f"{t}.".encode() + payload, hashlib.sha256).hexdigest()
    if not t.isdigit() or abs(time.time() - int(t)) &gt; 300 or not hmac.compare_digest(expected, v1):
        abort(400)

    event = json.loads(payload)
    if event["type"] == "payment.completed":
        pass  # mark_order_paid(event["data"]["reference"], event["data"]["id"])  # idempotent!
    return "", 200</pre></div>

<h2>Request a payout</h2>
<div class="code-window mb-3"><pre>curl -X POST {{ $baseUrl }}/payouts \
  -H "Authorization: Bearer $GATEWAY_SECRET_KEY" \
  -H "Idempotency-Key: withdrawal-778" \
  -H "Content-Type: application/json" \
  -d '{"amount":"250.00","currency":"USDT","network":"tron","reference":"withdrawal-778",
       "destination":{"type":"crypto_address","value":"TQ5n...your-wallet"}}'</pre></div>
