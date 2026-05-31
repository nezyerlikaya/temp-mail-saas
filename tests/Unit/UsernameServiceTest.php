<?php

namespace Tests\Unit;

use App\Services\User\UsernameService;
use PHPUnit\Framework\TestCase;

class UsernameServiceTest extends TestCase
{
    public function test_username_normalization_is_consistent(): void
    {
        $service = new UsernameService();

        $this->assertSame('john-doe_42', $service->normalize('  John Doe_42  '));
        $this->assertSame('john-doe', $service->publicSlugSuggestion(' John Doe '));
    }

    public function test_reserved_usernames_are_blocked(): void
    {
        $service = new UsernameService();

        $this->assertTrue($service->isReserved('ADMIN'));
        $this->assertFalse($service->isValid('admin'));
        $this->assertNull($service->publicSlugSuggestion('support'));
    }
}
