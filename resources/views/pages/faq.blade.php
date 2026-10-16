@extends('layouts.public', ['title' => 'FAQ'])

@section('content')
@include('pages.partials.page-header', ['eyebrow' => 'FAQ', 'heading' => 'Frequently asked questions'])

<section class="section pt-4">
    <div class="container" style="max-width:52rem">
        <div class="accordion" id="faq">
            @foreach ([
                'Which providers do you support?' => 'Cryptomus and Binance Pay, integrated through their official merchant APIs. The Payment methods page lists the currencies currently enabled.',
                'When is a payment marked as completed?' => 'Only after the provider\'s signed callback is verified and the status is confirmed with an authenticated provider API request. A customer reaching your success page does not mark a payment as paid.',
                'Can I test without real money?' => 'Yes. Test API keys use a built-in sandbox simulator. You can simulate paid, underpaid, failed and expired payments, as well as payouts, without any real funds moving.',
                'How are fees calculated?' => 'Fees are calculated on our servers from published fee rules (percentage and/or fixed, with optional minimum and maximum). The applied rule is stored with every transaction, so later changes never affect past transactions.',
                'Where can payouts be sent?' => 'Cryptomus payouts go to blockchain wallet addresses. Binance Pay payouts go to Binance accounts (Binance ID, or email for whitelisted merchants). Binance Pay does not offer payouts to external blockchain addresses.',
                'Why does my payout need approval?' => 'Every live payout is reviewed by our team before it is sent, to protect you and your customers against fraud. The amount is reserved from your balance as soon as you request it.',
                'Can I refund a payment?' => 'Refunds of settled payments are supported for providers that offer them. Cryptomus refunds return the full paid amount to a wallet address; Binance Pay refunds go back to the payer\'s Binance account.',
                'What happens if a customer underpays?' => 'If the provider finalises an invoice with less than the requested amount, the payment is marked "underpaid", the received amount is credited, and fees are charged on what was actually received.',
                'How do I go live?' => 'Complete business verification from your dashboard. After approval, create live API keys and switch your integration to them.',
            ] as $q => $a)
                <div class="accordion-item">
                    <h2 class="accordion-header">
                        <button class="accordion-button {{ $loop->first ? '' : 'collapsed' }} fw-semibold" type="button" data-bs-toggle="collapse" data-bs-target="#faq{{ $loop->index }}" aria-expanded="{{ $loop->first ? 'true' : 'false' }}" aria-controls="faq{{ $loop->index }}">{{ $q }}</button>
                    </h2>
                    <div id="faq{{ $loop->index }}" class="accordion-collapse collapse {{ $loop->first ? 'show' : '' }}" data-bs-parent="#faq">
                        <div class="accordion-body text-muted-2">{{ $a }}</div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</section>
@endsection
