<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Horizon\Horizon;
use Laravel\Horizon\HorizonApplicationServiceProvider;

class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
    public function boot(): void
    {
        parent::boot();

        /*
         * The parent registers `Gate::check(...) || app()->environment('local')`,
         * so on a local machine the dashboard is open to anyone who can reach
         * the port — and it lists job payloads, which here means subscriber
         * email addresses. Replacing the callback drops the environment escape
         * hatch so the answer is the same everywhere.
         */
        Horizon::auth(fn ($request): bool => Gate::check('viewHorizon', [$request->user()]));
    }

    /**
     * Who may open the queue dashboard.
     *
     * The scaffolded gate matches an email against an empty array, which reads
     * as "nobody" but is not a lock — Horizon only consults this gate outside
     * the local environment, so the dashboard is open to anyone who can reach
     * /horizon while APP_ENV stays local. It exposes job payloads, which for
     * this application means subscriber email addresses and article contents.
     *
     * So: an authenticated super_admin, and the same answer in every
     * environment. Nothing here depends on APP_ENV.
     */
    protected function gate(): void
    {
        Gate::define('viewHorizon', fn (?User $user): bool => $user?->hasRole('super_admin') ?? false);
    }
}
