<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * The structured half of the content graph: categories, topics, companies,
 * people, countries, industries, markets, opportunities.
 *
 * One class for all of them on purpose. The rule is identical — these are the
 * newsroom's own reference data, edited by whoever may edit anyone's article —
 * and `CompanyPolicy` and `OpportunityPolicy` were two verbatim copies of it
 * before this existed. A rule with more than one implementation has no
 * implementation (CLAUDE.md §6).
 *
 * Registered per model in AppServiceProvider rather than discovered by name,
 * because convention discovery would want eight near-empty files to say one
 * thing.
 */
class TaxonomyPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('article.update.any');
    }

    public function view(User $user, Model $model): bool
    {
        return $user->can('article.update.any');
    }

    public function create(User $user): bool
    {
        return $user->can('article.update.any');
    }

    public function update(User $user, Model $model): bool
    {
        return $user->can('article.update.any');
    }

    public function delete(User $user, Model $model): bool
    {
        return $user->can('article.update.any');
    }
}
