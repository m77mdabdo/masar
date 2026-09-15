<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\Article;
use App\Models\Category;
use App\Models\Company;
use App\Models\Country;
use App\Models\HomepageLayout;
use App\Models\Industry;
use App\Models\Market;
use App\Models\MenuItem;
use App\Models\Opportunity;
use App\Models\Person;
use App\Models\Setting;
use App\Models\Source;
use App\Models\Topic;
use App\Models\User;
use App\Policies\ArticlePolicy;
use App\Policies\CompanyPolicy;
use App\Policies\MenuItemPolicy;
use App\Policies\OpportunityPolicy;
use App\Policies\SettingPolicy;
use App\Services\Ai\AiProvider;
use App\Services\Ai\NullAiProvider;
use App\Support\Settings;
use App\View\Composers\NavigationComposer;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // No provider is selected. The null implementation ships so everything
        // downstream can be written and tested, and so the unclassified rate
        // measures exactly what an AI provider would be paid to do.
        $this->app->bind(AiProvider::class, NullAiProvider::class);

        // One instance per request: settings are read on nearly every page and
        // the day-long cache should be consulted once, not per call site.
        $this->app->singleton(Settings::class);
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
            'source' => Source::class,
            'homepage_layout' => HomepageLayout::class,

            // Spatie's activity log (causer) and permission tables store User
            // morphs, so it must be mapped or enforceMorphMap() will reject them.
            'user' => User::class,
        ]);

        // Laravel's bundled pagination markup uses physical margin utilities,
        // which break RTL. Ours uses logical properties.
        Paginator::defaultView('vendor.pagination.masar');
        Paginator::defaultSimpleView('vendor.pagination.masar');

        $this->registerPolicies();
        $this->registerRateLimiters();

        // Navigation is needed by the public layout and nothing else, so it is
        // composed onto those views rather than shared globally.
        View::composer(
            ['components.layout.public'],
            NavigationComposer::class,
        );
    }

    /**
     * Rate limits that protect a person rather than the server.
     *
     * The newsletter limiter is the one that matters: double opt-in means an
     * unauthenticated stranger can cause mail to be sent to an address they do
     * not own, so the cost of abuse lands on a third party. Keyed by IP because
     * there is no account to key on, and deliberately low — a human subscribes
     * once, not five times a minute.
     */
    private function registerRateLimiters(): void
    {
        RateLimiter::for('newsletter', fn (Request $request) => [
            Limit::perMinute(5)->by($request->ip()),
            Limit::perDay(20)->by($request->ip()),
        ]);

        // Search is a read, so this is a capacity control, not a safety one:
        // every query is a LIKE scan until Scout replaces it.
        RateLimiter::for('search', fn (Request $request) => Limit::perMinute(30)->by($request->ip()));
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
