<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Setting;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;

/**
 * Typed access to site settings.
 *
 * Settings are read on nearly every request and written a few times a year, so
 * the whole table is cached as one array for a day. Views and Blade components
 * must never touch the model directly — one uncached `Setting::where(...)` in a
 * partial becomes a query on every page.
 *
 * Secrets listed in `masar.settings.encrypted` are encrypted at rest here rather
 * than by a model cast, because the cast would have to apply to the whole `value`
 * column and most settings are not secret.
 */
class Settings
{
    private const CACHE_KEY = 'masar.settings.all';

    /**
     * @var array<string, mixed>|null
     */
    private ?array $memo = null;

    public function get(string $key, mixed $default = null): mixed
    {
        $all = $this->all();

        if (array_key_exists($key, $all)) {
            return $all[$key];
        }

        return $default ?? config("masar.settings.defaults.{$key}");
    }

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        if ($this->memo !== null) {
            return $this->memo;
        }

        $ttl = (int) config('masar.settings.cache_ttl', 86400);

        return $this->memo = Cache::tags('settings')->remember(
            self::CACHE_KEY,
            $ttl,
            fn (): array => $this->loadFromDatabase(),
        );
    }

    /**
     * Writes through the model so the change is audited — a site that changes
     * behaviour with no record of who changed it is a support nightmare.
     */
    public function set(string $key, mixed $value): void
    {
        Setting::updateOrCreate(
            ['key' => $key],
            [
                'value' => ['data' => $this->encodeForStorage($key, $value)],
                'group' => str_contains($key, '.') ? explode('.', $key)[0] : 'general',
            ],
        );

        $this->flush();
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public function setMany(array $values): void
    {
        foreach ($values as $key => $value) {
            $this->set($key, $value);
        }
    }

    public function flush(): void
    {
        $this->memo = null;

        Cache::tags('settings')->flush();
    }

    public function isEncrypted(string $key): bool
    {
        return in_array($key, (array) config('masar.settings.encrypted', []), true);
    }

    /**
     * @return array<string, mixed>
     */
    private function loadFromDatabase(): array
    {
        $stored = [];

        foreach (Setting::query()->get(['key', 'value']) as $setting) {
            $stored[$setting->key] = $this->decodeFromStorage(
                $setting->key,
                $setting->value['data'] ?? null,
            );
        }

        // Defaults underneath, so a key that has never been saved still resolves.
        return array_merge((array) config('masar.settings.defaults', []), $stored);
    }

    private function encodeForStorage(string $key, mixed $value): mixed
    {
        if (! $this->isEncrypted($key) || blank($value)) {
            return $value;
        }

        return Crypt::encryptString((string) $value);
    }

    private function decodeFromStorage(string $key, mixed $value): mixed
    {
        if (! $this->isEncrypted($key) || blank($value) || ! is_string($value)) {
            return $value;
        }

        try {
            return Crypt::decryptString($value);
        } catch (DecryptException) {
            // A rotated APP_KEY makes old ciphertext unreadable. Returning null
            // is the honest answer; throwing would take the whole site down
            // because settings are read on every request.
            return null;
        }
    }
}
