<?php

namespace App\Services\Seo;

use App\Enums\OperationCategory;
use App\Enums\OperationSeverity;
use App\Enums\OperationStatus;
use App\Services\Operations\OperationsLoggerService;
use App\Services\Service;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

final class SeoGrowthReadinessService extends Service
{
    public function __construct(
        private readonly SeoService $seo,
        private readonly ContentGrowthReadinessService $content,
        private readonly LandingPageReadinessService $landing,
        private readonly IndexingReadinessService $indexing,
        private readonly GrowthCertificationService $certification,
        private readonly OperationsLoggerService $operations,
    ) {}

    public function report(): array
    {
        $this->record('growth_review_started');

        $seo = $this->seoReview();
        $content = $this->content->report();
        $landing = $this->landing->review();
        $indexing = $this->indexing->review();
        $certification = $this->certification->certify($seo);
        $sections = compact('seo', 'content', 'landing', 'indexing');
        $blockers = $this->issues($sections, 'blockers');
        $warnings = [
            ...$this->issues($sections, 'warnings'),
            ...$certification['warnings'],
        ];
        $status = $blockers !== [] ? 'blocked' : ($warnings !== [] ? 'warning' : 'ready');

        $this->record('growth_review_'.$status, $status === 'blocked' ? OperationSeverity::Warning : OperationSeverity::Info, [
            'blocker_count' => count($blockers),
            'warning_count' => count($warnings),
            'certification' => $certification['status'],
        ]);

        if ($certification['status'] === 'certified') {
            $this->record('growth_certified');
        }

        return [
            'status' => $status,
            'blockers' => $blockers,
            'warnings' => $warnings,
            'recommendations' => collect([...$blockers, ...$warnings])->pluck('message')->unique()->values()->all(),
            'certification' => $certification,
            'sections' => $sections,
        ];
    }

    private function seoReview(): array
    {
        $checks = [
            $this->check('sitemap_readiness', ! (bool) config('seo.growth_readiness.seo.require_sitemap', true) || ((bool) config('seo.sitemap.enabled', true) && Route::has('sitemap')), 'Sitemap readiness is available.', 'Sitemap readiness needs review.', 'blocker'),
            $this->check('robots_readiness', ! (bool) config('seo.growth_readiness.seo.require_robots', true) || Route::has('robots'), 'Robots readiness is available.', 'Robots readiness needs review.', 'blocker'),
            $this->check('structured_data_readiness', ! (bool) config('seo.growth_readiness.seo.require_structured_data', true) || ((bool) config('seo.structured_data.enabled', true) && filled((string) config('seo.structured_data.organization_name'))), 'Structured data readiness is available.', 'Structured data readiness needs review.', 'warning'),
            $this->check('canonical_readiness', ! (bool) config('seo.growth_readiness.seo.require_canonical', true) || $this->canonicalReady(), 'Canonical readiness is available.', 'Canonical readiness needs review.', 'blocker'),
            $this->check('metadata_readiness', ! (bool) config('seo.growth_readiness.seo.require_metadata', true) || $this->metadataReady(), 'Metadata readiness is available.', 'Metadata readiness needs review.', 'blocker'),
        ];

        return $this->summarize($checks);
    }

    private function canonicalReady(): bool
    {
        return rtrim($this->seo->canonicalUrl(Request::create('/?utm_campaign=ignored')), '/') === rtrim(url('/'), '/');
    }

    private function metadataReady(): bool
    {
        return filled((string) config('seo.defaults.title', config('seo.title')))
            && filled((string) config('seo.defaults.description', config('seo.description')))
            && filled((string) config('seo.defaults.robots', config('seo.robots')));
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

    private function issues(array $sections, string $type): array
    {
        return collect($sections)
            ->flatMap(fn (array $section, string $category): array => collect($section[$type])
                ->map(fn (array $issue): array => ['category' => $category, ...$issue])
                ->all())
            ->values()
            ->all();
    }

    private function record(string $eventType, OperationSeverity $severity = OperationSeverity::Info, array $metadata = []): void
    {
        $this->operations->log(
            OperationCategory::System,
            $eventType,
            $severity,
            OperationStatus::Detected,
            'growth-readiness',
            'Growth and SEO readiness event recorded.',
            $metadata,
        );
    }
}
