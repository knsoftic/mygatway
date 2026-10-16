<?php

use App\Exceptions\GatewayException;
use App\Http\Middleware\AssignRequestId;
use App\Http\Middleware\AuthenticateApiKey;
use App\Http\Middleware\EnsureAdmin;
use App\Http\Middleware\EnsureIdempotency;
use App\Http\Middleware\EnsureMerchantUser;
use App\Http\Middleware\LogApiRequest;
use App\Http\Middleware\RequireApiScope;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\TrackDashboardMode;
use App\Http\Responses\ApiError;
use App\Integrations\Exceptions\ProviderException;
use App\Integrations\Exceptions\UnsupportedOperationException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Trusted proxy IPs are applied in AppServiceProvider from config('gateway.trusted_proxies').
        $middleware->prepend(AssignRequestId::class);
        $middleware->append(SecurityHeaders::class);

        $middleware->alias([
            'api.key' => AuthenticateApiKey::class,
            'api.scope' => RequireApiScope::class,
            'api.idempotent' => EnsureIdempotency::class,
            'api.log' => LogApiRequest::class,
            'admin' => EnsureAdmin::class,
            'merchant' => EnsureMerchantUser::class,
            'dashboard.mode' => TrackDashboardMode::class,
        ]);

        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn (Request $request) => $request->user()?->merchant_id ? route('dashboard') : route('admin.dashboard'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->dontReport([GatewayException::class]);

        // Structured, non-leaking error envelope for the merchant API.
        $exceptions->render(function (Throwable $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return match (true) {
                $e instanceof GatewayException => ApiError::make($e->errorCode, $e->getMessage(), $e->httpStatus, $e->details, $request),
                $e instanceof ValidationException => ApiError::make('validation_error', 'The request parameters are invalid.', 422, ['fields' => $e->errors()], $request),
                $e instanceof ProviderException => ApiError::make($e->errorCode, $e->safeMessage, $e->retryable ? 503 : 502, ['provider' => $e->provider, 'retryable' => $e->retryable], $request),
                $e instanceof UnsupportedOperationException => ApiError::make('unsupported_operation', $e->getMessage(), 422, ['provider' => $e->provider], $request),
                $e instanceof AuthenticationException => ApiError::make('authentication_required', 'Authentication required.', 401, [], $request),
                $e instanceof AuthorizationException => ApiError::make('forbidden', 'You are not allowed to perform this action.', 403, [], $request),
                $e instanceof ThrottleRequestsException => ApiError::make('rate_limited', 'Too many requests. Slow down and retry later.', 429, [], $request),
                $e instanceof ModelNotFoundException, $e instanceof NotFoundHttpException => ApiError::make('resource_not_found', 'The requested resource does not exist.', 404, [], $request),
                $e instanceof HttpExceptionInterface => ApiError::make('http_error', $e->getStatusCode() === 405 ? 'Method not allowed.' : 'Request could not be processed.', $e->getStatusCode(), [], $request),
                default => (function () use ($e, $request) {
                    Log::error('Unhandled API exception', ['exception' => $e]);

                    return ApiError::make('internal_error', 'An unexpected error occurred. Please retry or contact support with the request_id.', 500, [], $request);
                })(),
            };
        });

        // Business-rule errors on web forms become flash messages instead of error pages.
        $exceptions->render(function (GatewayException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return null;
            }

            return back()->withInput()->with('error', $e->getMessage());
        });
        $exceptions->render(function (ProviderException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return null;
            }

            return back()->withInput()->with('error', $e->safeMessage);
        });
    })->create();
