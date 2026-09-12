<?php

declare(strict_types=1);

namespace App\Filament\Resources\Articles\Pages;

use App\Actions\Articles\CreateArticleRevision;
use App\Actions\Articles\EvaluatePublishGate;
use App\Actions\Articles\SyncArticleEntities;
use App\Actions\Articles\SyncArticleHeroMedia;
use App\Actions\Articles\SyncArticleTopics;
use App\Filament\Resources\Articles\ArticleResource;
use App\Filament\Resources\Articles\Support\ArticleTransitionActions;
use App\Filament\Resources\Articles\Support\GateTabMap;
use App\Models\Article;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditArticle extends EditRecord
{
    protected static string $resource = ArticleResource::class;

    /**
     * Entity tagging and topics are collected by the form but written by their
     * Actions, so they are lifted out of the payload before Filament saves and
     * replayed afterwards.
     *
     * @var array<string, mixed>
     */
    private array $deferred = [];

    public function getHeaderActions(): array
    {
        return [
            ...ArticleTransitionActions::for($this->getRecord()),
            DeleteAction::make()->label('حذف'),
        ];
    }

    /**
     * The gate panel lives beside the form, not behind the publish button.
     */
    public function getFooterWidgets(): array
    {
        return [];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var Article $article */
        $article = $this->getRecord();

        $data['topic_ids'] = $article->topics()->pluck('topics.id')->all();
        $data['entities'] = [];

        foreach ($article->mentions()->get() as $mention) {
            $data['entities'][$mention->entity_type][] = [
                'id' => (int) $mention->entity_id,
                'role' => $mention->role?->value,
                'prominence' => (int) $mention->prominence,
            ];
        }

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->deferred = [
            'topic_ids' => $data['topic_ids'] ?? [],
            'entities' => $data['entities'] ?? [],
        ];

        unset($data['topic_ids'], $data['entities']);

        return $data;
    }

    protected function beforeSave(): void
    {
        // A snapshot before every meaningful update, so a bad edit is recoverable.
        app(CreateArticleRevision::class)($this->getRecord(), auth()->user());
    }

    protected function afterSave(): void
    {
        /** @var Article $article */
        $article = $this->getRecord();

        app(SyncArticleHeroMedia::class)($article);

        app(SyncArticleEntities::class)($article, self::flattenEntities($this->deferred['entities'] ?? []));
        app(SyncArticleTopics::class)($article, array_map('intval', $this->deferred['topic_ids'] ?? []));

        $article->forceFill(['updated_content_at' => now()])->save();
    }

    /**
     * Turn the per-kind repeater state into the flat list SyncArticleEntities wants.
     *
     * @param  array<string, array<int, array{id?: mixed, role?: mixed, prominence?: mixed}>>  $entities
     * @return array<int, array<string, mixed>>
     */
    public static function flattenEntities(array $entities): array
    {
        $rows = [];

        foreach ($entities as $type => $items) {
            foreach ((array) $items as $item) {
                if (blank($item['id'] ?? null)) {
                    continue;
                }

                $rows[] = [
                    'type' => $type,
                    'id' => (int) $item['id'],
                    'role' => $item['role'] ?? null,
                    'prominence' => (int) ($item['prominence'] ?? 0),
                ];
            }
        }

        return $rows;
    }

    /**
     * Gate failures, each paired with the tab that fixes it.
     *
     * @return array<int, array{message: string, tab: string, tab_label: string}>
     */
    public function gateFailures(): array
    {
        return GateTabMap::decorate(
            app(EvaluatePublishGate::class)->detailed($this->getRecord()),
        );
    }

    public function focusGateTab(string $tab): void
    {
        $this->redirect(
            ArticleResource::getUrl('edit', ['record' => $this->getRecord(), 'tab' => $tab]),
        );
    }
}
