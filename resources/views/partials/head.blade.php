<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<meta name="description" content="{{ $description ?? config('app.name').' - crypto payment gateway for Pay In and Pay Out via Cryptomus and Binance Pay.' }}">
<title>{{ isset($title) ? $title.' · ' : '' }}{{ config('app.name') }}</title>
<link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
{{-- Apply the saved theme before first paint to avoid a flash. --}}
<script nonce="{{ Vite::cspNonce() }}">
    (function () {
        var t = null;
        try { t = localStorage.getItem('gw-theme'); } catch (e) {}
        if (t !== 'light' && t !== 'dark') { t = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light'; }
        document.documentElement.setAttribute('data-bs-theme', t);
    })();
</script>
@vite(['resources/css/app.css', 'resources/js/app.js'])
