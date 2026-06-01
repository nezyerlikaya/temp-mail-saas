<?php

namespace App\Services\Seo;

use App\Services\Service;
use Illuminate\Support\Facades\Route;

final class LandingPageReadinessService extends Service
{
    public function review(): array
    {
        $route = (string) config('seo.growth_readiness.landing_pages.homepage_route', 'home');

        $checks = [
            $this->check('homepage_seo', Route::has($route), 'Homepage route is available for SEO review.', 'Homepage route is unavailable.', 'blocker'),
            $this->check('metadata_coverage', ! (bool) config('seo.growth_readiness.landing_pages.metadata_required', true) || $this->metadataReady(), 'Landing page metadata defaults are available.', 'Landing page metadata coverage needs review.', 'blocker'),
            $this->check('structured_data_coverage', ! (bool) config('seo.growth_readiness.landing_pages.structured_data_required', true) || (bool) config('seo.structured_data.enabled', true), 'Landing page structured data coverage is enabled.', 'Landing page structured data coverage needs review.', 'warning'),
            $this->check('content_discoverability', ! (bool) config('seo.growth_readiness.landing_pages.discoverability_required', true) || $this->discoverabilityReady($route), 'Landing page is discoverable through sitemap and routing.', 'Landing page discoverability needs review.', 'blocker'),
        ];

        return $this->summarize($checks);
    }

    private function metadataReady(): bool
    {
        return filled((string) config('seo.defaults.title', config('seo.title')))
            && filled((string) config('seo.defaults.description', config('seo.description')));
    }

    private function discoverabilityReady(string $route): bool
    {
        return Route::has($route)
            && in_array($route, config('seo.sitemap.static_pages', []), true)
            && Route::has('sitemap');
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
