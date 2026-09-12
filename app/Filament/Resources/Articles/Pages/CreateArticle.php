<?php

declare(strict_types=1);

namespace App\Filament\Resources\Articles\Pages;

use App\Actions\Articles\SyncArticleEntities;
use App\Actions\Articles\SyncArticleHeroMedia;
use App\Actions\Articles\SyncArticleTopics;
use App\Filament\Resources\Articles\ArticleResource;
use App\Models\Article;
use Filament\Resources\Pages\CreateRecord;

class CreateArticle extends CreateRecord
{
    protected static string $resource = ArticleResource::class;

    /** @var array<string, mixed> */
    private array $deferred = [];

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->deferred = [
            'topic_ids' => $data['topic_ids'] ?? [],
            'entities' => $data['entities'] ?? [],
        ];

        unset($data['topic_ids'], $data['entities']);

        // A new article always belongs to whoever created it, and always starts
        // as an idea — `status` is never taken from the form.
        $data['author_id'] ??= auth()->id();

        return $data;
    }

    protected function afterCreate(): void
    {
        /** @var Article $article */
        $article = $this->getRecord();

        app(SyncArticleHeroMedia::class)($article);
        app(SyncArticleEntities::class)($article, EditArticle::flattenEntities($this->deferred['entities'] ?? []));
        app(SyncArticleTopics::class)($article, array_map('intval', $this->deferred['topic_ids'] ?? []));
    }

    protected function getRedirectUrl(): string
    {
        return ArticleResource::getUrl('edit', ['record' => $this->getRecord()]);
    }
}
