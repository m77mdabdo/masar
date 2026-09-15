<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /**
     * Guards against two suites sharing one test database.
     *
     * Every test uses RefreshDatabase against a single `masar_test` schema, so a
     * second concurrent run truncates tables underneath the first. The failures
     * that produces are scattered and look like real bugs — it cost a debugging
     * cycle once already.
     *
     * The lock is advisory and held by the OS: it is released when the process
     * exits, however it exits, so a killed run never leaves a stale lock behind.
     *
     * Set MASAR_ALLOW_CONCURRENT_TESTS=1 to opt out — Pest's --parallel gives
     * each process its own database (masar_test_1, _2, …) and does not collide.
     */
    private static mixed $lock = null;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::acquireSuiteLock();
    }

    protected function setUp(): void
    {
        parent::setUp();

        self::refuseToRunOutsideTheTestDatabase();
    }

    /**
     * Refuses to touch anything that is not a test database.
     *
     * RefreshDatabase runs `migrate:fresh`, so pointing the suite at the wrong
     * schema does not fail — it silently destroys it. That happened once, for
     * real: `php artisan test` launched as a subprocess inherited
     * DB_DATABASE=masar from the parent's environment, PHPUnit declined to
     * override an already-set variable, and the suite wiped the development
     * database. `force="true"` in phpunit.xml closes that specific hole; this
     * closes the class of it, because the next way in will not look the same.
     */
    private static function refuseToRunOutsideTheTestDatabase(): void
    {
        $database = (string) config('database.connections.'.config('database.default').'.database');

        if ($database === '' || str_contains($database, 'test')) {
            return;
        }

        throw new RuntimeException(
            "The test suite is pointed at the database [{$database}], which is not a test database. "
            .'Refusing to run: RefreshDatabase would destroy it. '
            .'Check that DB_DATABASE is not set in the environment that launched this run.',
        );
    }

    private static function acquireSuiteLock(): void
    {
        if (self::$lock !== null) {
            return;
        }

        if (env('MASAR_ALLOW_CONCURRENT_TESTS') || env('LARAVEL_PARALLEL_TESTING')) {
            return;
        }

        $path = sys_get_temp_dir().'/masar-test-suite.lock';
        $handle = fopen($path, 'c');

        if ($handle === false) {
            return;
        }

        if (! flock($handle, LOCK_EX | LOCK_NB)) {
            fclose($handle);

            throw new RuntimeException(
                'Another test run is already using the test database. '
                .'Wait for it to finish, run with --parallel, or set '
                .'MASAR_ALLOW_CONCURRENT_TESTS=1 if you know the runs use separate schemas.',
            );
        }

        self::$lock = $handle;
    }
}
