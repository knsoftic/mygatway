<?php

namespace App\Providers;

use App\Integrations\BinancePay\BinancePayClient;
use App\Integrations\BinancePay\BinancePayGateway;
use App\Integrations\Cryptomus\CryptomusClient;
use App\Integrations\Cryptomus\CryptomusGateway;
use App\Models\LoginHistory;
use App\Models\MerchantApiKey;
use App\Models\PaymentProvider;
use App\Models\User;
use App\Services\Providers\ProviderCredentials;
use App\Services\Risk\RiskMonitor;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Each provider integration is its own module. Credentials come from the admin panel
        // (encrypted) or config/services.php (env). Bound per resolution, not as singletons,
        // so long-running queue workers pick up credentials changed in the admin panel.
        $this->app->bind(CryptomusClient::class, fn ($app) => new CryptomusClient(
            $app->make(HttpFactory::class), $app->make(ProviderCredentials::class)->config(PaymentProvider::CRYPTOMUS),
        ));
        $this->app->bind(CryptomusGateway::class, function ($app) {
            $config = $app->make(ProviderCredentials::class)->config(PaymentProvider::CRYPTOMUS);

            return new CryptomusGateway(new CryptomusClient($app->make(HttpFactory::class), $config), $config);
        });

        $this->app->bind(BinancePayClient::class, fn ($app) => new BinancePayClient(
            $app->make(HttpFactory::class), $app->make(CacheRepository::class), $app->make(ProviderCredentials::class)->config(PaymentProvider::BINANCE_PAY),
        ));
        $this->app->bind(BinancePayGateway::class, function ($app) {
            $config = $app->make(ProviderCredentials::class)->config(PaymentProvider::BINANCE_PAY);

            return new BinancePayGateway(new BinancePayClient($app->make(HttpFactory::class), $app->make(CacheRepository::class), $config), $config);
        });
    }

    public function boot(): void
    {
        TrustProxies::at(config('gateway.trusted_proxies'));

        Model::preventLazyLoading(! $this->app->isProduction());
        Model::preventSilentlyDiscardingAttributes(! $this->app->isProduction());

        Paginator::useBootstrapFive();

        Password::defaults(fn () => $this->app->isProduction()
            ? Password::min(12)->mixedCase()->numbers()->symbols()->uncompromised()
            : Password::min(10)->mixedCase()->numbers());

        $this->configureRateLimiting();
        $this->configureAuthorization();
        $this->configureLoginMonitoring();
    }

    private function configureRateLimiting(): void
    {
        // Per API key, using the limit configured on the key.
        RateLimiter::for('merchant-api', function (Request $request) {
            /** @var MerchantApiKey|null $key */
            $key = $request->attributes->get('api_key');

            return $key
                ? Limit::perMinute($key->rate_limit_per_minute)->by('api-key:'.$key->id)
                : Limit::perMinute(30)->by('api-ip:'.$request->ip());
        });

        RateLimiter::for('provider-webhooks', fn (Request $request) => Limit::perMinute(600)->by('provider-webhook:'.$request->ip()));
        RateLimiter::for('contact', fn (Request $request) => Limit::perHour(5)->by('contact:'.$request->ip()));
        RateLimiter::for('sensitive', fn (Request $request) => Limit::perMinute(10)->by('sensitive:'.($request->user()?->id ?? $request->ip())));
    }

    private function configureAuthorization(): void
    {
        // Admin abilities are permission names (e.g. "payouts.approve"), granted through roles.
        Gate::before(function (User $user, string $ability) {
            if (str_contains($ability, '.') && $user->isAdmin()) {
                return $user->hasPermission($ability) ?: null;
            }

            return null;
        });
    }

    private function configureLoginMonitoring(): void
    {
        $record = function (?User $user, ?string $email, bool $ok, string $event) {
            $request = request();
            LoginHistory::query()->create([
                'user_id' => $user?->id,
                'email' => $email ?? $user?->email,
                'ip_address' => $request->ip(),
                'user_agent' => mb_substr((string) $request->userAgent(), 0, 512),
                'successful' => $ok,
                'event' => $event,
                'created_at' => now(),
            ]);
        };

        Event::listen(Login::class, function (Login $e) use ($record) {
            if ($e->user instanceof User) {
                $record($e->user, null, true, 'login');
                $e->user->forceFill(['last_login_at' => now(), 'last_login_ip' => request()->ip()])->saveQuietly();
            }
        });

        Event::listen(Failed::class, function (Failed $e) use ($record) {
            $email = (string) ($e->credentials['email'] ?? '');
            $record($e->user instanceof User ? $e->user : null, $email, false, 'failed');
            if ($email !== '') {
                app(RiskMonitor::class)->checkFailedLogins($email, request()->ip());
            }
        });

        Event::listen(Lockout::class, function (Lockout $e) use ($record) {
            $record(null, (string) $e->request->input('email'), false, 'lockout');
        });

        Event::listen(Logout::class, function (Logout $e) use ($record) {
            if ($e->user instanceof User) {
                $record($e->user, null, true, 'logout');
            }
        });
    }
}
