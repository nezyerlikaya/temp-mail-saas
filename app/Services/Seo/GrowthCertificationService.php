<?php

namespace App\Services\Seo;

use App\Services\Service;

final class GrowthCertificationService extends Service
{
    public function __construct(
        private readonly ContentGrowthReadinessService $content,
        private readonly IndexingReadinessService $indexing,
        private readonly LandingPageReadinessService $landing,
    ) {}

    public function certify(array $seo): array
    {
        $content = $this->content->report();
        $indexing = $this->indexing->review();
        $landing = $this->landing->review();
        $checks = [
            $this->check('seo_readiness', ! (bool) config('seo.growth_readiness.certification.seo', true) || $seo['blockers'] === [], 'SEO readiness is certified.', 'SEO readiness is blocked.', 'blocked'),
            $this->check('content_readiness', ! (bool) config('seo.growth_readiness.certification.content', true) || $content['blockers'] === [], 'Content growth readiness is certified.', 'Content growth readiness is blocked.', 'blocked'),
            $this->check('indexing_readiness', ! (bool) config('seo.growth_readiness.certification.indexing', true) || $indexing['blockers'] === [], 'Indexing readiness is certified.', 'Indexing readiness is blocked.', 'blocked'),
            $this->check('landing_page_readiness', ! (bool) config('seo.growth_readiness.certification.landing_page', true) || $landing['blockers'] === [], 'Landing page readiness is certified.', 'Landing page readiness is blocked.', 'blocked'),
        ];
        $blockers = collect($checks)->where('classification', 'blocked')->values()->all();
        $warnings = [
            ...$content['warnings'],
            ...$indexing['warnings'],
            ...$landing['warnings'],
        ];

        return [
            'status' => $blockers !== [] ? 'blocked' : ($warnings !== [] ? 'warning' : 'certified'),
            'blockers' => $blockers,
            'warnings' => $warnings,
            'checks' => $checks,
            'content' => $content,
            'indexing' => $indexing,
            'landing_page' => $landing,
        ];
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
}
