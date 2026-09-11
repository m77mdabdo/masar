<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Redirect;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Redirect>
 */
class RedirectFactory extends Factory
{
    protected $model = Redirect::class;

    public function definition(): array
    {
        return [
            'from_path' => '/ar/'.$this->faker->unique()->slug(3),
            'to_path' => '/ar/'.$this->faker->slug(3),
            'status_code' => 301,
            'hits' => 0,
        ];
    }
}
