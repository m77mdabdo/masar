<?php

declare(strict_types=1);

use App\Support\Settings;

if (! function_exists('setting')) {
    /**
     * Typed settings accessor: setting('identity.site_name').
     *
     * Resolved from the container so the day-long cache is shared across the
     * request rather than rebuilt per call.
     */
    function setting(?string $key = null, mixed $default = null): mixed
    {
        $settings = app(Settings::class);

        return $key === null ? $settings : $settings->get($key, $default);
    }
}
