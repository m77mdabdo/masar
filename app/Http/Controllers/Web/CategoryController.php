<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Models\Category;
use App\Queries\PublishedArticlesQuery;
use Illuminate\Contracts\View\View;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class CategoryController
{
    public function __invoke(string $locale, string $category): View
    {
        $model = Category::query()
            ->where('slug', $category)
            ->where('is_active', true)
            ->with('translations')
            ->first();

        if ($model === null) {
            throw new NotFoundHttpException;
        }

        return view('pages.category', [
            'category' => $model,
            'featured' => PublishedArticlesQuery::make()
                ->forLocale($locale)->inCategory($model)->featured()->latest()->limit(3)->get(),
            'articles' => PublishedArticlesQuery::make()
                ->forLocale($locale)->inCategory($model)->latest()
                ->paginate((int) setting('editorial.articles_per_page')),
        ]);
    }
}
