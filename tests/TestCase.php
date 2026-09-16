<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Redis;
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

    private static bool $redisReady = false;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::acquireSuiteLock();
    }

    /**
     * Give the suite its own Redis databases, and start from empty.
     *
     * The suite uses the real Redis connection, so without this it shares the
     * development buffers: test article ids turned up in the development view
     * buffer, and dedupe keys carrying a 24-hour TTL survived RefreshDatabase —
     * which means a run could pass or fail on what ran yesterday. Isolation
     * that depends on nobody noticing is not isolation.
     *
     * Guarded rather than trusted, for the same reason the database is: a
     * forced value in phpunit.xml does not always win over the environment.
     */
    private static function isolateRedis(): void
    {
        if (self::$redisReady) {
            return;
        }

        self::$redisReady = true;

        $default = (int) config('database.redis.default.database');
        $cache = (int) config('database.redis.cache.database');

        if ($default < 10 || $cache < 10) {
            throw new RuntimeException(
                "The test suite is pointed at Redis databases {$default}/{$cache}, which development uses. "
                .'Refusing to run: it would read and write the development buffers. '
                .'Check REDIS_DB and REDIS_CACHE_DB are not set in the environment that launched this run.',
            );
        }

        // Start from empty so yesterday's keys cannot decide today's result.
        Redis::connection()->flushdb();
        Redis::connection('cache')->flushdb();
    }

    protected function setUp(): void
    {
        parent::setUp();

        self::refuseToRunOutsideTheTestDatabase();
        // After the app boots: setUpBeforeClass has no container, so neither
        // config() nor the Redis facade exists there.
        self::isolateRedis();
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
