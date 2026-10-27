<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Support\DemoContent;
use Illuminate\Database\Seeder;

/**
 * Everything that is demo content, and nothing that is not.
 *
 * Every row these seeders create — articles, media, audit entries, sample
 * market figures, the intelligence corpus — is flagged `is_demo` by
 * DemoContent::record(), and `masar:purge-demo` removes exactly that set,
 * returning the database to what RoleSeeder, CategorySeeder and
 * SiteStructureSeeder made.
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        DemoContent::record(fn () => $this->call([
            DemoContentSeeder::class,
            FlagshipStoriesSeeder::class,

            // After the articles: the image library needs the homepage pinned
            // before it can promise that no hero repeats inside one section.
            ImageLibrarySeeder::class,
            VideoLibrarySeeder::class,
            MarketFiguresSeeder::class,
            IntelligenceSeeder::class,
        ]));
    }
}
