<?php

namespace App\Contracts\Media;

use App\Models\Media;

interface MediaProcessorContract
{
    /**
     * Placeholder for future media processing pipelines.
     */
    public function process(Media $media): void;
}
