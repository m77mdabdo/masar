<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Support\DemoMode;
use Illuminate\Http\Response;

class RobotsController
{
    public function __invoke(DemoMode $demo): Response
    {
        /*
         * Demo mode disallows everything and advertises no sitemap. It ignores
         * the editable setting entirely rather than merging with it: a partial
         * robots.txt assembled from two sources is how a demo site ends up
         * half-indexed, and this is the one file where that cannot be risked.
         */
        if ($demo->isOn()) {
            return response(
                "# نسخة تجريبية — لا يُفهرس أي شيء\nUser-agent: *\nDisallow: /\n",
                200,
                ['Content-Type' => 'text/plain; charset=UTF-8'],
            );
        }

        $body = (string) setting('seo.robots');

        // The sitemap reference is appended rather than stored, so an owner
        // editing robots.txt can never accidentally delete it.
        if (! str_contains($body, 'Sitemap:')) {
            $body = rtrim($body)."\n\nSitemap: ".route('web.sitemap')."\n";
        }

        return response($body, 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }
}
