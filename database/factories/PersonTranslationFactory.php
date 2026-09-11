<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Person;
use App\Models\PersonTranslation;
use Database\Factories\Support\ArabicContent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PersonTranslation>
 */
class PersonTranslationFactory extends Factory
{
    protected $model = PersonTranslation::class;

    public function definition(): array
    {
        return [
            'person_id' => Person::factory(),
            'locale' => 'ar',
            'name' => $this->faker->name(),
            'title' => $this->faker->randomElement(ArabicContent::JOB_TITLES),
            'bio' => null,
        ];
    }
}
