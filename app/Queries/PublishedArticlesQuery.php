<?php

declare(strict_types=1);

namespace App\Queries;

use App\Models\Article;
use App\Models\Category;
use App\Models\Topic;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Every public-facing list of articles goes through here.
 *
 * The eager-load set is fixed and deliberate: a listing renders a headline, a
 * category name, a byline and a hero image, and nothing else. Blocks, revisions
 * and sources are never loaded — they are the heaviest relations we have and no
 * list has ever needed them.
 */
class PublishedArticlesQuery
{
    private Builder $query;

    public function __construct()
    {
        $this->query = Article::query()
            ->published()
            ->with([
                // category.translations, not just category: the listing shows the
                // category *name*, and loading the parent alone is an N+1 in disguise.
                'category:id,slug,color',
                'category.translations',
                'author:id,name',
                'heroMedia',
            ]);
    }

    public static function make(): self
    {
        return new self;
    }

    public function forLocale(string $locale): self
    {
        $this->query->where('locale', $locale);

        return $this;
    }

    public function inCategory(Category|int $category): self
    {
        $this->query->where(
            'category_id',
            $category instanceof Category ? $category->getKey() : $category,
        );

        return $this;
    }

    public function withTopic(Topic|int $topic): self
    {
        $id = $topic instanceof Topic ? $topic->getKey() : $topic;

        $this->query->whereHas('topics', fn (Builder $q) => $q->whereKey($id));

        return $this;
    }

    /**
     * Articles mentioning a given entity, using the (entity_type, entity_id,
     * created_at) index on entity_mentions.
     */
    public function mentioning(Model $entity): self
    {
        $this->query->whereHas(
            'mentions',
            fn (Builder $q) => $q
                ->where('entity_type', $entity->getMorphClass())
                ->where('entity_id', $entity->getKey()),
        );

        return $this;
    }

    public function featured(bool $featured = true): self
    {
        $this->query->where('is_featured', $featured);

        return $this;
    }

    /**
     * @param  array<int, int>|int  $ids
     */
    public function excluding(array|int $ids): self
    {
        $ids = array_filter((array) $ids);

        if ($ids !== []) {
            $this->query->whereKeyNot($ids);
        }

        return $this;
    }

    public function latest(string $column = 'published_at'): self
    {
        $this->query->orderByDesc($column)->orderByDesc('id');

        return $this;
    }

    public function limit(int $limit): self
    {
        $this->query->limit($limit);

        return $this;
    }

    /**
     * @return Collection<int, Article>
     */
    public function get(): Collection
    {
        return $this->query->get();
    }

    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return $this->query->paginate($perPage);
    }

    public function count(): int
    {
        return $this->query->count();
    }

    /**
     * Escape hatch for callers that need one more condition. Prefer adding a
     * named method here over reaching for this.
     */
    public function builder(): Builder
    {
        return $this->query;
    }
}
