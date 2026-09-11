<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\EntityRole;
use App\Models\Article;
use App\Models\Company;
use App\Models\EntityMention;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EntityMention>
 */
class EntityMentionFactory extends Factory
{
    protected $model = EntityMention::class;

    public function definition(): array
    {
        return [
            'mentionable_type' => (new Article)->getMorphClass(),
            'mentionable_id' => Article::factory(),
            'entity_type' => (new Company)->getMorphClass(),
            'entity_id' => Company::factory(),
            'role' => EntityRole::Mentioned,
            'prominence' => $this->faker->numberBetween(0, 100),
            'created_at' => now(),
        ];
    }

    public function primary(): static
    {
        return $this->state([
            'role' => EntityRole::Primary,
            'prominence' => $this->faker->numberBetween(70, 100),
        ]);
    }

    /**
     * Point the mention at a concrete model on either side.
     */
    public function for_(string $side, object $model): static
    {
        return $this->state([
            "{$side}_type" => $model->getMorphClass(),
            "{$side}_id" => $model->getKey(),
        ]);
    }
}
