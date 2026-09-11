<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Opportunity;
use App\Models\User;

class OpportunityPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('article.update.any');
    }

    public function view(User $user, Opportunity $model): bool
    {
        return $user->can('article.update.any');
    }

    public function create(User $user): bool
    {
        return $user->can('article.update.any');
    }

    public function update(User $user, Opportunity $model): bool
    {
        return $user->can('article.update.any');
    }

    public function delete(User $user, Opportunity $model): bool
    {
        return $user->can('article.update.any');
    }
}
