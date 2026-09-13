<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\SourceLegalMode;
use App\Enums\SourceType;
use App\Models\Source;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Source>
 */
class SourceFactory extends Factory
{
    public function definition(): array
    {
        $host = $this->faker->unique()->domainName();

        return [
            'name' => 'مصدر '.$this->faker->unique()->numberBetween(1, 9999),
            'url' => 'https://'.$host,
            'feed_url' => 'https://'.$host.'/feed.xml',
            'type' => SourceType::Rss,
            'category' => null,
            'locale' => 'ar',
            'trust_level' => 3,
            'is_active' => true,
            'poll_frequency_minutes' => 30,
            'parser_config' => null,
            // The restrictive mode is the default in the schema and the default
            // here, so a test has to opt in to retaining a publisher's prose.
            'legal_mode' => SourceLegalMode::Metadata,
        ];
    }

    public function retainingBody(): static
    {
        return $this->state(['legal_mode' => SourceLegalMode::FullText]);
    }

    public function failing(int $times): static
    {
        return $this->state([
            'consecutive_failures' => $times,
            'error_message' => 'http 500',
            'last_checked_at' => now()->subHour(),
        ]);
    }
}
