<?php

namespace Tests\Feature;

use Tests\TestCase;

class FoundationRoutesTest extends TestCase
{
    public function test_homepage_loads(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Temp Mail SaaS')
            ->assertSee('STEP02');
    }

    public function test_health_returns_safe_json(): void
    {
        $response = $this->getJson('/health')
            ->assertOk()
            ->assertJsonStructure([
                'status',
                'checks' => [
                    'application',
                    'environment',
                    'cache',
                    'storage',
                    'configuration',
                ],
            ]);

        $payload = $response->getContent();

        $this->assertStringNotContainsString('APP_KEY', $payload);
        $this->assertStringNotContainsString('DB_PASSWORD', $payload);
        $this->assertStringNotContainsString('MAIL_PASSWORD', $payload);
    }

    public function test_status_page_loads(): void
    {
        $this->get('/status')
            ->assertOk()
            ->assertSee('Public Status')
            ->assertSee('Service status');
    }
}
