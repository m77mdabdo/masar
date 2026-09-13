<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use Illuminate\Http\Response;

class RobotsController
{
    public function __invoke(): Response
    {
        $body = (string) setting('seo.robots');

        // The sitemap reference is appended rather than stored, so an owner
        // editing robots.txt can never accidentally delete it.
        if (! str_contains($body, 'Sitemap:')) {
            $body = rtrim($body)."\n\nSitemap: ".route('web.sitemap')."\n";
        }

        return response($body, 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }
}
