<?php

namespace Tests\Unit;

use App\Services\System\AppConfigService;
use App\Services\System\FeatureFlagService;
use Tests\TestCase;

class SystemServicesTest extends TestCase
{
    public function test_unknown_feature_flag_returns_false(): void
    {
        $features = app(FeatureFlagService::class);

        $this->assertFalse($features->enabled('missing.flag'));
        $this->assertTrue($features->disabled('missing.flag'));
    }

    public function test_feature_flags_support_nested_dot_notation(): void
    {
        config(['features.test_module.enabled' => true]);

        $this->assertTrue(app(FeatureFlagService::class)->enabled('test_module.enabled'));
    }

    public function test_app_config_service_returns_safe_defaults(): void
    {
        config([
            'tempmail.public_name' => null,
            'tempmail.support_email' => null,
            'tempmail.locale' => null,
            'tempmail.fallback_locale' => null,
            'tempmail.timezone' => null,
            'retention.default_mailbox_ttl_minutes' => null,
            'retention.cleanup_chunk_size' => null,
            'inbound.provider' => null,
        ]);

        $config = app(AppConfigService::class);

        $this->assertSame('Temp Mail SaaS', $config->publicName());
        $this->assertSame('support@example.com', $config->supportEmail());
        $this->assertSame('en', $config->defaultLocale());
        $this->assertSame('en', $config->fallbackLocale());
        $this->assertSame('UTC', $config->timezone());
        $this->assertSame(60, $config->defaultMailboxTtl());
        $this->assertSame(100, $config->cleanupChunkSize());
        $this->assertSame('null', $config->inboundProvider());
    }
}
