<?php

declare(strict_types=1);

use Tests\TestCase;

/**
 * The name rule behind the guard in Tests\TestCase. The guard's placement —
 * before RefreshDatabase, not after it — was proved against a probe database
 * on 2026-10-27 and is recorded in tasks/STATUS-OCT.md; running the suite as a
 * subprocess from inside the suite hangs (CLAUDE.md §9), so it is not repeated
 * here.
 */
it('accepts the test database and its --parallel copies', function (string $name): void {
    expect(TestCase::isTestDatabaseName($name))->toBeTrue();
})->with(['masar_test', 'masar_test_test_1', 'masar_test_test_12']);

it('refuses every other name, including the ones "contains test" let through', function (string $name): void {
    expect(TestCase::isTestDatabaseName($name))->toBeFalse();
})->with(['masar', '', 'masar_guardprobe', 'latest', 'contest', 'masar_test_backup', 'test_masar', 'masar_testing']);

it('is the database this run is actually using', function (): void {
    expect(TestCase::isTestDatabaseName((string) config('database.connections.'.config('database.default').'.database')))
        ->toBeTrue();
});
