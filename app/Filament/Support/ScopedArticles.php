<?php

declare(strict_types=1);

namespace App\Filament\Support;

use App\Models\Article;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * The article scope every dashboard widget must start from.
 *
 * A writer sees their own work, not the newsroom's — a pipeline widget that
 * silently reports 48 articles to someone who can see 3 is worse than no widget.
 * Centralised so a new widget inherits the rule instead of re-deciding it.
 */
class ScopedArticles
{
    public static function for(?User $user): Builder
    {
        $query = Article::query();

        if ($user === null) {
            return $query->whereRaw('1 = 0');
        }

        if ($user->can('article.update.any')) {
            return $query;
        }

        return $query->where(function (Builder $inner) use ($user): void {
            $inner->where('author_id', $user->getKey())
                ->orWhereHas('coAuthors', fn (Builder $c) => $c->whereKey($user->getKey()));
        });
    }

    /**
     * Whether the viewer sees the whole newsroom or only their own desk.
     */
    public static function isNewsroomWide(?User $user): bool
    {
        return $user?->can('article.update.any') ?? false;
    }
}
