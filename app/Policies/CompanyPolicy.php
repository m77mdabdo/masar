<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Company;
use App\Models\User;

class CompanyPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('article.update.any');
    }

    public function view(User $user, Company $model): bool
    {
        return $user->can('article.update.any');
    }

    public function create(User $user): bool
    {
        return $user->can('article.update.any');
    }

    public function update(User $user, Company $model): bool
    {
        return $user->can('article.update.any');
    }

    public function delete(User $user, Company $model): bool
    {
        return $user->can('article.update.any');
    }
}
