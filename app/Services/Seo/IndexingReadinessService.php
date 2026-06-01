<?php

namespace App\Services\Seo;

use App\Services\Service;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

final class IndexingReadinessService extends Service
{
    public function __construct(private readonly SeoService $seo) {}

    public function review(): array
    {
        $checks = [
            $this->check('sitemap_coverage', ! (bool) config('seo.growth_readiness.indexing.sitemap_coverage_required', true) || ((bool) config('seo.sitemap.enabled', true) && Route::has('sitemap')), 'Sitemap coverage is available.', 'Sitemap coverage needs review.', 'blocker'),
            $this->check('robots_coverage', ! (bool) config('seo.growth_readiness.indexing.robots_coverage_required', true) || Route::has('robots'), 'Robots coverage is available.', 'Robots coverage needs review.', 'blocker'),
            $this->check('canonical_coverage', ! (bool) config('seo.growth_readiness.indexing.canonical_coverage_required', true) || $this->canonicalReady(), 'Canonical coverage is available.', 'Canonical coverage needs review.', 'blocker'),
            $this->check('crawl_readiness', (bool) config('seo.growth_readiness.indexing.crawl_ready', true), 'Crawl readiness is documented.', 'Crawl readiness needs review.', 'warning'),
        ];

        return $this->summarize($checks);
    }

    private function canonicalReady(): bool
    {
        return rtrim($this->seo->canonicalUrl(Request::create('/status?utm_source=ignored')), '/') === rtrim(url('/status'), '/');
    }

    private function check(string $name, bool $passed, string $passedMessage, string $failedMessage, string $classification): array
    {
        return [
            'name' => $name,
            'passed' => $passed,
            'classification' => $passed ? 'passed' : $classification,
            'message' => $passed ? $passedMessage : $failedMessage,
        ];
    }

    private function summarize(array $checks): array
    {
        return [
            'status' => collect($checks)->where('classification', 'blocker')->isNotEmpty() ? 'blocked' : (collect($checks)->where('classification', 'warning')->isNotEmpty() ? 'warning' : 'ready'),
            'passed' => collect($checks)->where('classification', 'passed')->values()->all(),
            'warnings' => collect($checks)->where('classification', 'warning')->values()->all(),
            'blockers' => collect($checks)->where('classification', 'blocker')->values()->all(),
            'checks' => $checks,
        ];
    }
}
