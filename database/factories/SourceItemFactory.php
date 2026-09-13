<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Source;
use App\Models\SourceItem;
use App\Services\Intelligence\SimHash;
use App\Services\Intelligence\UrlNormaliser;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SourceItem>
 */
class SourceItemFactory extends Factory
{
    public function definition(): array
    {
        $title = 'هيئة تنظيمية تعتمد تعديلات على لوائح '.$this->faker->unique()->word();
        $url = 'https://example.test/news/'.$this->faker->unique()->slug();

        return [
            'source_id' => Source::factory(),
            'external_id' => null,
            'url' => $url,
            'canonical_url' => null,
            'raw_title' => $title,
            'raw_summary' => null,
            'raw_body' => null,
            'author' => null,
            'published_at' => now()->subHour(),
            'fetched_at' => now(),
            // Computed exactly as the ingestion path computes them, so a test
            // fixture cannot drift from what the pipeline would have produced.
            'url_hash' => app(UrlNormaliser::class)->hash($url),
            'canonical_hash' => null,
            'title_simhash' => app(SimHash::class)($title),
            'payload' => null,
        ];
    }
}
