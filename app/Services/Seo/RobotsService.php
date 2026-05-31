<?php

namespace App\Services\Seo;

use App\Services\Service;

final class RobotsService extends Service
{
    public function content(): string
    {
        $lines = ['User-agent: *'];

        if (app()->environment('production')) {
            $lines[] = 'Allow: /';
        } else {
            $lines[] = 'Disallow: /';
        }

        if (config('seo.sitemap.enabled', true)) {
            $lines[] = 'Sitemap: '.url('/sitemap.xml');
        }

        return implode("\n", $lines)."\n";
    }
}
