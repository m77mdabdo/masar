<?php

declare(strict_types=1);

namespace App\Actions\Articles;

use App\Enums\ArticleSection;
use App\Models\Article;
use Illuminate\Support\Facades\DB;

/**
 * Writes the body. The only place that does.
 *
 * Filament's Builder hands back a flat array of `{type, data}`; `article_blocks`
 * is a table with its own ids, ordering and a section column. Bridging the two
 * is a business operation — the body is the article — so it lives in an Action
 * rather than in a page's afterSave closure, and the seeder can call the same
 * code the admin does.
 *
 * The section travels inside the block's form state because it is a field on
 * every block's schema; it is lifted out to its column here so the renderer can
 * group on it without unpacking JSON for every block on the page.
 */
class SyncArticleBlocks
{
    /**
     * @param  array<int, array{type?: string, data?: array<string, mixed>}>  $blocks
     */
    public function __invoke(Article $article, array $blocks): void
    {
        DB::transaction(function () use ($article, $blocks): void {
            // Replace rather than reconcile: block ids are not stable across a
            // Builder round-trip, so matching on them would reorder the body
            // the first time an editor dragged anything.
            $article->blocks()->delete();

            $order = 0;

            foreach ($blocks as $block) {
                $type = $block['type'] ?? null;

                if (! is_string($type) || $type === '') {
                    continue;
                }

                $data = (array) ($block['data'] ?? []);
                $section = $data['section'] ?? null;
                unset($data['section']);

                $article->blocks()->create([
                    'type' => $type,
                    'section' => ArticleSection::tryFrom((string) $section)?->value,
                    'data' => $data,
                    'sort_order' => $order++,
                ]);
            }
        });
    }

    /**
     * The inverse: the relation as the Builder wants to receive it.
     *
     * @return array<int, array{type: string, data: array<string, mixed>}>
     */
    public static function toFormState(Article $article): array
    {
        return $article->blocks()
            ->orderBy('sort_order')
            ->get()
            ->map(fn ($block): array => [
                'type' => $block->type,
                'data' => ['section' => $block->section?->value] + (array) $block->data,
            ])
            ->all();
    }
}
