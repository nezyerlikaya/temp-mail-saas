<?php

namespace Tests\Unit;

use App\Services\User\AvatarMetadataService;
use PHPUnit\Framework\TestCase;

class AvatarMetadataServiceTest extends TestCase
{
    public function test_avatar_metadata_has_a_safe_fallback(): void
    {
        $metadata = (new AvatarMetadataService())->metadata();

        $this->assertTrue($metadata['fallback']);
        $this->assertSame('/images/avatar-default.svg', $metadata['url']);
        $this->assertNull($metadata['path']);
    }
}
