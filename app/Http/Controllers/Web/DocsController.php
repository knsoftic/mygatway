<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

/**
 * Developer portal. Only implemented, verified endpoints are documented.
 */
class DocsController extends Controller
{
    /** @var array<string, array<string, string>> section => [slug => title] */
    public const PAGES = [
        'Getting started' => [
            'getting-started' => 'Introduction',
            'authentication' => 'Authentication & API keys',
            'test-and-live-mode' => 'Test mode & live mode',
            'integration-guide' => 'Integration guide',
        ],
        'Guides' => [
            'pay-in' => 'Pay In integration',
            'pay-out' => 'Pay Out integration',
            'status-verification' => 'Status verification',
            'webhooks' => 'Webhooks',
        ],
        'Reference' => [
            'api-reference' => 'API reference',
            'errors' => 'Error codes',
            'rate-limits' => 'Rate limits & idempotency',
            'providers' => 'Provider capabilities',
            'security' => 'Security best practices',
            'code-examples' => 'Code examples (PHP, JS, Python)',
        ],
    ];

    public function index(): View
    {
        return $this->show('getting-started');
    }

    public function show(string $page): View
    {
        $title = collect(self::PAGES)->collapse()->get($page);
        abort_if($title === null || ! view()->exists('docs.pages.'.$page), 404);

        $flat = collect(self::PAGES)->collapse();
        $keys = $flat->keys()->values();
        $index = $keys->search($page);

        return view('docs.show', [
            'page' => $page,
            'title' => $title,
            'sections' => self::PAGES,
            'prev' => $index > 0 ? [$keys[$index - 1], $flat[$keys[$index - 1]]] : null,
            'next' => $index < $keys->count() - 1 ? [$keys[$index + 1], $flat[$keys[$index + 1]]] : null,
            'baseUrl' => rtrim(url('/api/v1'), '/'),
        ]);
    }
}
