<?php

declare(strict_types=1);

namespace App\Actions\Intelligence;

use App\Actions\Articles\SyncArticleEntities;
use App\Enums\ArticleStatus;
use App\Enums\ReviewState;
use App\Models\Article;
use App\Models\Category;
use App\Models\IntelligenceItem;
use App\Models\User;
use App\Support\EntityMatcher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Turns an inbox item into an empty article, and nothing more.
 *
 * **The frame, never the prose.** What carries over is the fact that a
 * publisher published something, where to read it, and who is writing about it.
 * What does not carry over is a single word of theirs: not the body, which is
 * internal for its whole life, and not the headline either. MASAR's headline is
 * MASAR's writing (CLAUDE.md §5), so the draft opens with a placeholder that
 * says out loud it needs one rather than a borrowed line an editor might leave
 * in by accident.
 *
 * The publisher's headline does appear in exactly one place — the source
 * citation — because that is what attribution is. A citation naming the
 * original piece is the opposite of passing it off as ours.
 *
 * **Entity mentions travel. Topics deliberately do not.** The distinction is
 * the point, and it is a knowing departure from the plan's wording rather than
 * an oversight: that a headline names Aramco is a *fact about the headline*, so
 * it carries over; what the story says about Aramco is *writing*, so it does
 * not. A topic has no signal on an intelligence item at all — there is no field
 * to derive one from — and picking one from the source's category would be an
 * editorial decision taken inside an action, which is precisely what this
 * action exists not to do. Leave it to the editor. Do not "fix" this.
 */
class CreateDraftFromIntelligenceItem
{
    public function __construct(
        private readonly EntityMatcher $entities,
        private readonly SyncArticleEntities $syncEntities,
    ) {}

    /** Said in Arabic, in the field, so the requirement is visible not implied. */
    private const TITLE_PLACEHOLDER = 'مسودة بحاجة إلى عنوان';

    public function __invoke(IntelligenceItem $item, User $actor): Article
    {
        if ($item->article_id !== null) {
            throw new RuntimeException('لهذا العنصر مسودة بالفعل.');
        }

        return DB::transaction(function () use ($item, $actor): Article {
            $article = Article::query()->create([
                // We write in the site's language, whatever the source's was.
                // A Fed release in English can inform an Arabic story.
                'locale' => (string) config('masar.default_locale', 'ar'),
                'status' => ArticleStatus::Research,
                'category_id' => $this->category($item)->getKey(),
                'author_id' => $actor->getKey(),
                'title' => self::TITLE_PLACEHOLDER,
                // A slug is an editorial decision and this is not one. It is a
                // unique placeholder that will not collide and must be replaced.
                'slug' => 'draft-'.Str::lower((string) Str::ulid()),
            ]);

            $article->sources()->create([
                // Attribution. The publisher's own headline belongs here and
                // only here.
                'title' => mb_substr($item->title, 0, 255),
                'url' => $item->url,
                'publisher' => $item->source?->name,
                'source_type' => 'press_release',
                'accessed_at' => $item->detected_at ?? now(),
                'sort_order' => 0,
            ]);

            // Entity mentions carry over: that a headline names a company is a
            // fact about the headline. What the story says about it is writing,
            // and that does not travel. Topics do not carry over at all —
            // an intelligence item holds no topic signal, and inventing one
            // would be making an editorial call inside an action.
            $mentions = ($this->entities)($item->title, $item->summary);

            if ($mentions !== []) {
                ($this->syncEntities)($article, $mentions);
            }

            $item->forceFill([
                'article_id' => $article->getKey(),
                'review_state' => ReviewState::Drafted,
                'reviewed_by_id' => $actor->getKey(),
                'reviewed_at' => now(),
            ])->save();

            return $article->fresh();
        });
    }

    /**
     * A source's own category is a hint, not an answer.
     *
     * `sources.category` uses words like "economy" and "markets" that are not
     * MASAR's section slugs, so it is matched where it happens to line up and
     * otherwise left to the editor on the first category. Guessing harder would
     * be making an editorial decision in an action.
     */
    private function category(IntelligenceItem $item): Category
    {
        $hint = (string) ($item->source?->category ?? '');

        $match = $hint === '' ? null : Category::query()->where('slug', $hint)->first();

        return $match ?? Category::query()->orderBy('id')->firstOrFail();
    }
}
