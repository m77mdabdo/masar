<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\Article;
use App\Models\Category;
use App\Models\Company;
use App\Models\Country;
use App\Models\Industry;
use App\Models\Market;
use App\Models\MenuItem;
use App\Models\Opportunity;
use App\Models\Person;
use App\Models\Setting;
use App\Models\Topic;
use App\Models\User;
use App\Policies\ArticlePolicy;
use App\Policies\CompanyPolicy;
use App\Policies\MenuItemPolicy;
use App\Policies\OpportunityPolicy;
use App\Policies\SettingPolicy;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        /*
         * entity_mentions is the fastest-growing table in the schema, and every
         * row stores two morph type strings. Aliasing keeps them short and, more
         * importantly, decouples stored data from PHP class names — renaming or
         * moving a model must never require rewriting millions of rows.
         *
         * `report` and `video` join this map when those content types are built.
         */
        Relation::enforceMorphMap([
            'article' => Article::class,
            'opportunity' => Opportunity::class,
            'company' => Company::class,
            'person' => Person::class,
            'country' => Country::class,
            'industry' => Industry::class,
            'market' => Market::class,
            'category' => Category::class,
            'topic' => Topic::class,

            // Activity-logged models resolve their morph class when the audit
            // entry is written, so every LogsActivity model must be mapped.
            'menu_item' => MenuItem::class,
            'setting' => Setting::class,

            // Spatie's activity log (causer) and permission tables store User
            // morphs, so it must be mapped or enforceMorphMap() will reject them.
            'user' => User::class,
        ]);

        $this->registerPolicies();
    }

    /**
     * Registered explicitly rather than relying on convention discovery, so
     * that moving or renaming a policy fails loudly instead of silently
     * falling back to "no policy, permission denied".
     */
    private function registerPolicies(): void
    {
        Gate::policy(Article::class, ArticlePolicy::class);
        Gate::policy(Company::class, CompanyPolicy::class);
        Gate::policy(Opportunity::class, OpportunityPolicy::class);
        Gate::policy(MenuItem::class, MenuItemPolicy::class);
        Gate::policy(Setting::class, SettingPolicy::class);
    }
}
