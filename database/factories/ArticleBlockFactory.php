<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Article;
use App\Models\ArticleBlock;
use Database\Factories\Support\ArabicContent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ArticleBlock>
 */
class ArticleBlockFactory extends Factory
{
    protected $model = ArticleBlock::class;

    public function definition(): array
    {
        return [
            'article_id' => Article::factory(),
            'type' => 'paragraph',
            'data' => ['text' => $this->faker->randomElement(ArabicContent::PARAGRAPHS)],
            'sort_order' => 0,
        ];
    }

    public function heading(): static
    {
        return $this->state(['type' => 'heading', 'data' => ['level' => 2, 'text' => 'لماذا يهم هذا؟']]);
    }

    public function quote(): static
    {
        return $this->state([
            'type' => 'quote',
            // No attribution: a factory must never invent a quote from a person.
            'data' => ['text' => 'اقتباس توضيحي لأغراض العرض فقط.', 'attribution' => null],
        ]);
    }
}
