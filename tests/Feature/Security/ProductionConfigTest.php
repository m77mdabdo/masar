<?php

declare(strict_types=1);

/*
 * The production defaults, observed rather than read: secure session cookies,
 * HTTPS URLs, and the example environment carrying no secret.
 */
it('marks the session cookie secure in production unless told otherwise', function (): void {
    $config = fn (string $env) => (function () use ($env) {
        putenv("APP_ENV={$env}");
        $_ENV['APP_ENV'] = $env;
        $_SERVER['APP_ENV'] = $env;
        putenv('SESSION_SECURE_COOKIE');
        unset($_ENV['SESSION_SECURE_COOKIE'], $_SERVER['SESSION_SECURE_COOKIE']);

        return (require config_path('session.php'))['secure'];
    })();

    try {
        expect($config('production'))->toBeTrue()
            ->and($config('local'))->toBeFalse();
    } finally {
        putenv('APP_ENV=testing');
        $_ENV['APP_ENV'] = $_SERVER['APP_ENV'] = 'testing';
    }
});

it('generates HTTPS URLs in production', function (): void {
    app()->detectEnvironment(fn () => 'production');
    (new \App\Providers\AppServiceProvider(app()))->boot();

    expect(url('/ar'))->toStartWith('https://');
    app()->detectEnvironment(fn () => 'testing');
    \Illuminate\Support\Facades\URL::forceScheme(null);
});

it('ships a production environment example with debug off and no secret in it', function (): void {
    $example = file_get_contents(base_path('deploy/env.production.example'));

    expect($example)->toContain("APP_DEBUG=false\n")->toContain("APP_ENV=production\n")
        ->toContain("SESSION_SECURE_COOKIE=true\n")->toContain("MASAR_TRANSLATION_ALLOW_EXTERNAL=false\n");

    foreach (['APP_KEY', 'DB_PASSWORD', 'REDIS_PASSWORD', 'R2_SECRET_ACCESS_KEY', 'MAIL_PASSWORD'] as $secret) {
        expect($example)->toMatch('/^'.$secret.'=$/m');
    }
});

it('trusts no proxy unless told to', function (): void {
    $this->get('/ar', ['X-Forwarded-For' => '203.0.113.9'])->assertOk();

    expect(request()->ip())->not->toBe('203.0.113.9');
});

it('limits each reader separately behind a trusted proxy, and all of them together without one', function (): void {
    // TRUSTED_PROXIES=* (Cloudflare, origin firewalled to it): the limiter is
    // keyed on the forwarded client address, so one reader's burst does not
    // lock out another arriving through the same edge.
    \Illuminate\Http\Middleware\TrustProxies::at('*');
    $this->app->instance('request', request());

    try {
        $as = fn (string $ip) => $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.1'])
            ->get('/ar/search?q=x', ['X-Forwarded-For' => $ip]);

        for ($i = 0; $i < 30; $i++) {
            $as('203.0.113.10')->assertOk();
        }
        $as('203.0.113.10')->assertStatus(429);
        $as('203.0.113.20')->assertOk();
    } finally {
        \Illuminate\Http\Middleware\TrustProxies::flushState();
    }
});
