@extends('pages.legal', ['heading' => 'Privacy Policy', 'updated' => '16 October 2026'])

@section('legal')
<p>This policy explains what personal data {{ config('app.name') }} processes, why, and your rights.</p>

<h2>Data we collect</h2>
<ul>
    <li><strong>Account data:</strong> name, email, password hash, two-factor settings.</li>
    <li><strong>Business data:</strong> business name, website, registration and contact details, verification documents.</li>
    <li><strong>Transaction data:</strong> amounts, currencies, wallet addresses or Binance IDs, transaction hashes, timestamps, customer emails you choose to send.</li>
    <li><strong>Security data:</strong> IP addresses, user agents, login history, API request logs.</li>
</ul>

<h2>Why we use it</h2>
<ul>
    <li>To provide and secure the service and process transactions with our providers.</li>
    <li>To meet legal obligations such as record keeping and anti-money-laundering checks.</li>
    <li>To detect and prevent fraud and abuse.</li>
</ul>

<h2>Sharing</h2>
<p>We share transaction data with the payment provider that processes each transaction (Cryptomus or Binance Pay) and with authorities where legally required. We do not sell personal data.</p>

<h2>Retention</h2>
<p>Financial records are kept for the period required by law. Technical logs are retained for a limited time (for example, API request logs for 90 days).</p>

<h2>Security</h2>
<p>Data is encrypted in transit (HTTPS). API secrets are stored only as keyed hashes, sensitive settings are encrypted at rest, and access is restricted by role with audit logging.</p>

<h2>Your rights</h2>
<p>Depending on your jurisdiction, you may request access, correction, deletion or a copy of your data, subject to our legal retention obligations. Contact us through the Contact page.</p>
@endsection
