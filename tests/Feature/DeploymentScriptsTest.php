<?php

declare(strict_types=1);

/**
 * The deploy scripts are not covered by anything else, and two lines in them
 * exist because of specific incidents on this project. A test that only
 * asserted the files exist would be the kind of coverage that reads as safety
 * and is not, so these assert the properties that were actually wrong.
 */
function script(string $name): string
{
    $path = base_path("deploy/{$name}");

    expect(file_exists($path))->toBeTrue("missing deploy script: {$name}");

    return (string) file_get_contents($path);
}

it('clears the cache in the restore script, after the import', function (): void {
    // A raw SQL import runs no application code, so nothing flushes the caches
    // the previous contents left behind. That is how a populated database was
    // served through an empty cached snapshot — no navigation, no ticker, every
    // test passing. Order matters: clearing before the import would cache the
    // old data again on the first request.
    $restore = script('restore.sh');

    $import = strpos($restore, 'mysql \\');
    $clear = strpos($restore, 'cache:clear');

    expect($clear)->not->toBeFalse('restore.sh must clear the cache')
        ->and($import)->not->toBeFalse('restore.sh must import the dump')
        ->and($clear)->toBeGreaterThan($import, 'cache:clear must come after the import, not before');
});

it('verifies the restored site rather than trusting the import', function (): void {
    expect(script('restore.sh'))->toContain('masar:smoke');
});

it('refuses to deploy without the committed asset build', function (): void {
    // Assets are built locally and shipped. A deploy with no manifest serves no
    // CSS at all, which looks like a styling bug and is a missing step.
    expect(script('deploy.sh'))->toContain('public/build/manifest.json');
});

it('never caches config in the deploy script', function (): void {
    // `config:cache` stops env() being read, so a changed .env silently does
    // nothing — the worst entry in the stale-snapshot register.
    // Matched as a command, not as a mention: the script explains in a comment
    // why it does not do this, and a naive substring match flags its own
    // reasoning.
    $commands = array_filter(
        explode("\n", script('deploy.sh')),
        fn (string $line): bool => ! str_starts_with(ltrim($line), '#') && trim($line) !== '',
    );

    expect(implode("\n", $commands))->not->toContain('config:cache');
});

it('validates the nginx config before reloading it', function (): void {
    // An invalid config that reloads takes the site down; one that fails
    // validation takes nothing down.
    $deploy = script('deploy.sh');

    expect($deploy)->toContain('nginx -t')
        ->and(strpos($deploy, 'nginx -t'))->toBeLessThan(strpos($deploy, 'systemctl reload nginx'));
});

it('ends the deploy by observing the site, not by exiting zero', function (): void {
    expect(script('deploy.sh'))->toContain('masar:smoke');
});

it('keeps the built assets tracked in git', function (): void {
    // public/build was gitignored, which let two machines serve different sites
    // from one commit — the same class as a restored install differing from a
    // fresh one.
    expect((string) file_get_contents(base_path('.gitignore')))
        ->not->toMatch('/^\/public\/build$/m');
});
