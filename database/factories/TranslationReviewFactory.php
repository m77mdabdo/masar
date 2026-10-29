<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\TranslationReviewStatus;
use App\Models\TranslationReview;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<TranslationReview> */
class TranslationReviewFactory extends Factory
{
    protected $model = TranslationReview::class;

    public function definition(): array
    {
        return [
            'locale' => 'fr',
            'path' => 'lang/fr/site.php',
            'content_hash' => hash('sha256', $this->faker->uuid()),
            'total_strings' => 50,
            'reviewed_strings' => 0,
            'pending_strings' => 50,
            'status' => TranslationReviewStatus::PendingReview,
        ];
    }
}
