<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            // Structure: what the site is made of. Not demo, never purged.
            RoleSeeder::class,
            CategorySeeder::class,
            SiteStructureSeeder::class,

            // Demo content, every row of it flagged and purgeable.
            DemoSeeder::class,
        ]);

        /*
         * The cache is not part of the database and `migrate:fresh` does not
         * touch it, so a reseed leaves whatever was cached from the previous
         * contents — including from an empty database.
         *
         * That is not hypothetical. After the development database was wiped
         * and restored, the site rendered with no main navigation and no market
         * ticker for hours: the settings and navigation caches held empty
         * snapshots taken while the tables were empty, both on 24-hour TTLs,
         * and every template involved degrades to rendering nothing. Every test
         * passed throughout, because tests run on the array cache driver.
         *
         * Flushing here is what makes a restored install and a fresh install
         * the same site.
         */
        Cache::flush();
        $this->command?->info('Cache flushed: a reseed without this leaves stale keys from the old contents.');
    }
}
