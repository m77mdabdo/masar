<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Article;
use App\Models\ArticleRevision;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ArticleRevision>
 */
class ArticleRevisionFactory extends Factory
{
    protected $model = ArticleRevision::class;

    public function definition(): array
    {
        return [
            'article_id' => Article::factory(),
            'user_id' => User::factory(),
            'snapshot' => ['title' => 'عنوان سابق للمادة', 'status' => 'writing'],
            'change_note' => 'تحديث العنوان وإعادة ترتيب الفقرات.',
        ];
    }
}
