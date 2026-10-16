@extends('pages.legal', ['heading' => 'Terms and Conditions', 'updated' => '16 October 2026'])

@section('legal')
<p>These Terms govern your use of {{ config('app.name') }} ("we", "the Platform") as a merchant. By creating an account you agree to them.</p>

<h2>1. The service</h2>
<p>The Platform provides software that lets merchants accept ("Pay In") and send ("Pay Out") crypto-asset payments through third-party payment providers, currently Cryptomus and Binance Pay. Payments are executed by those providers under their own terms, which also apply to you.</p>

<h2>2. Eligibility and verification</h2>
<ul>
    <li>You must be a legally registered business or an adult individual acting for business purposes.</li>
    <li>You must provide accurate information and complete business verification before live payments are enabled.</li>
    <li>We may request additional information at any time and may refuse, restrict or suspend accounts.</li>
</ul>

<h2>3. Prohibited use</h2>
<p>You may not use the Platform for illegal goods or services, fraud, money laundering, terrorist financing, sanctions evasion, gambling without required licences, or any activity prohibited by our providers.</p>

<h2>4. Fees</h2>
<p>Service fees are published on our Pricing page or agreed individually, and are calculated by the Platform at the time of each transaction. Provider network fees for payouts are shown before a payout is requested.</p>

<h2>5. Balances and payouts</h2>
<ul>
    <li>Your balance reflects amounts recorded in our ledger from provider-confirmed transactions, less fees, refunds and payouts.</li>
    <li>Payouts are subject to review and may be delayed or refused for risk, compliance or legal reasons.</li>
    <li>Payout destinations are limited to those supported by the selected provider.</li>
</ul>

<h2>6. API keys and security</h2>
<p>You are responsible for keeping your API secrets, passwords and 2FA devices secure, and for all activity under your credentials. Notify us immediately of any suspected compromise.</p>

<h2>7. Liability</h2>
<p>To the maximum extent permitted by law, we are not liable for provider outages, blockchain delays, network fees, price movements of crypto-assets, or losses resulting from incorrect payout details you provide.</p>

<h2>8. Termination</h2>
<p>You may close your account at any time. We may suspend or terminate access for breach of these Terms. Settlement of remaining balances follows our verification and legal obligations.</p>

<h2>9. Changes</h2>
<p>We may update these Terms. Material changes will be announced in advance by email or in the dashboard.</p>
@endsection
