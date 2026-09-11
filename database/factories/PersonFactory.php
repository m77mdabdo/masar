<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Person;
use Database\Factories\Support\ArabicContent;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Person>
 */
class PersonFactory extends Factory
{
    protected $model = Person::class;

    public function definition(): array
    {
        return [
            'slug' => Str::slug($this->faker->unique()->words(3, true)),
            'photo_path' => null,
            'company_id' => null,
            'linkedin' => null,
            // Deliberately false by default: `is_expert` asserts that a real,
            // consenting person is quotable. A factory must never assert that.
            'is_expert' => false,
            'mentions_count' => 0,
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Person $person): void {
            if ($person->translations()->where('locale', 'ar')->doesntExist()) {
                $person->translations()->create([
                    'locale' => 'ar',
                    'name' => fake()->name(),
                    'title' => fake()->randomElement(ArabicContent::JOB_TITLES),
                ]);
            }
        });
    }
}
