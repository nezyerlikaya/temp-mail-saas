<?php

namespace App\Services\Seo;

use App\Enums\ContentStatus;
use App\Models\Content;
use App\Services\Service;
use Illuminate\Support\Facades\Schema;

final class ContentGrowthReadinessService extends Service
{
    public function report(): array
    {
        $checks = [
            $this->check('content_publication', Schema::hasTable('contents') && (bool) config('seo.growth_readiness.content.publication_ready', true), 'Content publication foundation is ready.', 'Content publication readiness needs review.', 'blocker'),
            $this->check('category_readiness', (bool) config('seo.growth_readiness.content.category_ready', true), 'Category readiness is documented.', 'Category readiness needs review.', 'warning'),
            $this->check('tag_readiness', (bool) config('seo.growth_readiness.content.tag_ready', true), 'Tag readiness is documented.', 'Tag readiness needs review.', 'warning'),
            $this->check('seo_content_readiness', $this->seoContentReady(), 'SEO content fields are available.', 'SEO content fields need review.', 'blocker'),
            $this->check('editorial_readiness', (bool) config('seo.growth_readiness.content.editorial_ready', true), 'Editorial readiness is documented.', 'Editorial readiness needs review.', 'warning'),
            $this->check('published_content_optional', ! (bool) config('seo.growth_readiness.content.require_published_content', false) || Content::query()->where('status', ContentStatus::Published)->exists(), 'Published content requirement is satisfied.', 'Published content is required before growth expansion.', 'warning'),
        ];

        return $this->summarize($checks);
    }

    private function seoContentReady(): bool
    {
        return Schema::hasTable('contents')
            && Schema::hasColumn('contents', 'meta_title')
            && Schema::hasColumn('contents', 'meta_description')
            && (int) config('content.seo.meta_title_max_length', 70) > 0
            && (int) config('content.seo.meta_description_max_length', 160) > 0;
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
