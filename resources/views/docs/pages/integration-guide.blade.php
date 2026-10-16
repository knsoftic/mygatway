<p class="lead">This guide walks through a complete checkout integration: create a payment when the customer checks out, send them to pay, and fulfil the order when our signed webhook confirms the payment.</p>

<h2>1. Create the payment on your server</h2>
<p>When the customer places an order, call <code>POST /payments</code> from your backend. Use your order ID as both the <code>reference</code> and the <code>Idempotency-Key</code>, so a retried request can never create two payments.</p>
<div class="code-window mb-3"><pre>curl -X POST {{ $baseUrl }}/payments \
  -H "Authorization: Bearer $GATEWAY_SECRET_KEY" \
  -H "Idempotency-Key: order-1001" \
  -H "Content-Type: application/json" \
  -d '{
    "amount": "49.90",
    "currency": "USDT",
    "reference": "order-1001",
    "description": "Order #1001",
    "customer_email": "buyer@example.com",
    "success_url": "https://shop.example.com/orders/1001/thanks",
    "cancel_url": "https://shop.example.com/cart"
  }'</pre></div>
<p>Save the returned <code>id</code> (for example <code>pay_01h…</code>) with your order.</p>

<h2>2. Send the customer to pay</h2>
<p>Redirect the customer to <code>checkout_url</code>. They pay on the provider's hosted page and are then sent back to your <code>success_url</code> or <code>cancel_url</code>.</p>
<div class="alert alert-warning small"><i class="bi bi-exclamation-triangle"></i> <strong>Do not fulfil the order when the customer lands on <code>success_url</code>.</strong> Anyone can open that URL. Fulfil only after the webhook in step 4, or after <code>GET /payments/{id}</code> returns <code>completed</code>.</div>

<h2>3. Set up a webhook endpoint</h2>
<p>In <strong>Developers → Webhooks</strong>, add the URL on your server that should receive events and copy its signing secret. Subscribe to at least <code>payment.completed</code>, <code>payment.failed</code> and <code>payment.expired</code>.</p>

<h2>4. Fulfil on <code>payment.completed</code></h2>
<p>Verify the signature, then look up the order by <code>data.reference</code> and fulfil it. See <a href="{{ route('docs.show', 'webhooks') }}">Webhooks</a> for the signature algorithm.</p>
<div class="code-window mb-3"><pre>// Pseudocode
event = verifyAndParse(rawBody, headers["X-Gateway-Signature"], WEBHOOK_SECRET)
if event.type == "payment.completed":
    order = findOrder(event.data.reference)
    if not order.paid:                      // events can arrive more than once
        order.markPaid(event.data.id, event.data.received_amount)
return 200</pre></div>

<h2>5. Handle other outcomes</h2>
<ul>
    <li><code>payment.expired</code> / <code>payment.failed</code>: release the stock or ask the customer to try again with a new payment.</li>
    <li><code>payment.underpaid</code>: the customer sent less than requested. Only the received amount is credited. Decide whether to accept it, request the difference or refund (contact support for refunds).</li>
    <li><code>payment.on_hold</code>: the payment is under compliance review by us or the provider. Wait for a final event.</li>
</ul>

<h2>6. Reconcile</h2>
<p>If a webhook was missed (for example, your server was down), call <code>GET /payments/{id}</code>. If the payment is still open, <code>POST /payments/{id}/verify</code> asks the provider for the latest status. Keep a periodic job that checks orders stuck in "awaiting payment".</p>

<h2>Checklist before going live</h2>
<ul>
    <li>Secret key stored in server configuration, not in code or the browser.</li>
    <li>Every <code>POST</code> sends a unique <code>Idempotency-Key</code>.</li>
    <li>Webhook signatures are verified with a constant-time comparison and a timestamp check.</li>
    <li>Your webhook handler is idempotent and returns <code>2xx</code> quickly.</li>
    <li>Orders are fulfilled only from verified status, never from the redirect.</li>
    <li>Amounts are handled as decimal strings, not floats.</li>
</ul>
