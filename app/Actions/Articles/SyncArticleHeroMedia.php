<?php

declare(strict_types=1);

namespace App\Actions\Articles;

use App\Models\Article;

/**
 * Mirrors the `hero` media collection onto `articles.hero_media_id`.
 *
 * The denormalised column exists so the publish gate and every listing query can
 * answer "does this have a hero?" without touching the media table. It is only
 * correct if something keeps it in step with the collection — that is this
 * Action, called from the Filament pages after a save.
 */
class SyncArticleHeroMedia
{
    public function __invoke(Article $article): Article
    {
        $mediaId = $article->getFirstMedia('hero')?->getKey();

        if ((int) $article->hero_media_id === (int) $mediaId) {
            return $article;
        }

        $article->forceFill(['hero_media_id' => $mediaId])->save();

        return $article;
    }
}
