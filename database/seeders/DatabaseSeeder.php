<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            CategorySeeder::class,
            DemoContentSeeder::class,

            // After the demo content: the image library needs the homepage
            // layout to exist before it can promise that no hero repeats
            // inside one of its sections.
            ImageLibrarySeeder::class,
            VideoLibrarySeeder::class,
            MarketFiguresSeeder::class,
            IntelligenceSeeder::class,
        ]);
    }
}
