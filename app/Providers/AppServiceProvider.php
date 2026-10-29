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
use App\Models\Subscriber;
use App\Models\Topic;
use App\Models\User;
use App\Policies\ArticlePolicy;
use App\Policies\MenuItemPolicy;
use App\Policies\SettingPolicy;
use App\Policies\SubscriberPolicy;
use App\Policies\TaxonomyPolicy;
use App\Services\Ai\AiProvider;
use App\Services\Ai\NullAiProvider;
use App\Support\DemoContent;
use App\Support\Settings;
use App\View\Composers\NavigationComposer;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Spatie\Activitylog\Models\Activity;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Package translations under our edition codes (ur-pk → ur, zh-hans →
        // zh_CN). extend(), not a binding: the translation provider is
        // deferred and would re-bind the loader after this one, silently.
        $this->app->extend('translation.loader', function ($loader, $app) {
            $edition = new \App\Support\EditionTranslationLoader(
                $app['files'],
                [base_path('vendor/laravel/framework/src/Illuminate/Translation/lang'), $app['path.lang']],
            );

            // The admin panel's text, keyed by its Arabic source so the code
            // still reads in Arabic: lang/admin/{code}.json.
            $edition->addJsonPath(lang_path('admin'));

            return $edition;
        });

        // No provider is selected. The null implementation ships so everything
        // downstream can be written and tested, and so the unclassified rate
        // measures exactly what an AI provider would be paid to do.
        $this->app->bind(AiProvider::class, NullAiProvider::class);

        // One driver ships, by ruling: no outside translation service. A real
        // one is added to this match and selected in config, never hard-wired.
        $this->app->bind(\App\Services\Translation\TranslationProvider::class, fn () => match (config('masar.translation.driver')) {
            default => new \App\Services\Translation\NullTranslationProvider,
        });

        // One instance per request: settings are read on nearly every page and
        // the day-long cache should be consulted once, not per call site.
        $this->app->singleton(Settings::class);
    }

    public function boot(): void
    {
        // FilePond, inside Filament's file upload, ships no Hindi or Urdu and
        // keys Chinese as zh_CN, so those three panels showed "Remove" and
        // "Cancel" in English. FilePond reads its options from data-* on the
        // input it is created from; these are ours, in every edition.
        \Filament\Forms\Components\FileUpload::configureUsing(fn (\Filament\Forms\Components\FileUpload $upload) => $upload->extraInputAttributes(
            fn (): array => collect((array) __('admin.filepond'))
                ->mapWithKeys(fn (string $label, string $option): array => ['data-'.\Illuminate\Support\Str::kebab($option) => $label])
                ->all(),
            merge: true,
        ));

        // Production is HTTPS only; generate every URL that way even when the
        // edge terminates TLS and forwards plain HTTP.
        if ($this->app->environment('production')) {
            \Illuminate\Support\Facades\URL::forceScheme('https');
        }

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

        // Audit entries written while demo content is seeded are demo too, so
        // masar:purge-demo can return the log to what it was before the seed.
        Activity::creating(function (Activity $activity): void {
            if (DemoContent::isRecording()) {
                $activity->setAttribute('is_demo', true);
            }
        });

        // Navigation is needed by the public layout and nothing else, so it is
        // composed onto those views rather than shared globally.
        View::composer(
            ['components.layout.public'],
            NavigationComposer::class,
        );

        // The 404 offers the main sections as a way back. Rescued, because an
        // error page that throws while listing sections turns a 404 into a 500.
        View::composer('errors.page', function ($view): void {
            $view->with('sections', rescue(
                fn () => Category::query()->active()->ordered()->whereNull('parent_id')->with('translations')->get(),
                collect(),
                report: false,
            ));
        });
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

        // Provider quota, shared by every worker: one key, not per request.
        RateLimiter::for('translation', fn () => Limit::perMinute(max(1, (int) config('masar.translation.per_minute', 10)))->by('translation-provider'));
    }

    /**
     * Registered explicitly rather than by convention discovery, so that moving
     * or renaming a policy fails loudly.
     *
     * The line this docblock used to carry — that the fallback is "no policy,
     * permission denied" — was **wrong, and expensively so**. A model with no
     * policy is not denied; it is *allowed*, because Filament skips
     * authorisation it cannot resolve. Seven resources sat open on exactly that
     * misreading, the subscriber list among them. Any model reachable from the
     * panel is listed here.
     */
    private function registerPolicies(): void
    {
        Gate::policy(Article::class, ArticlePolicy::class);
        Gate::policy(MenuItem::class, MenuItemPolicy::class);
        Gate::policy(Setting::class, SettingPolicy::class);
        Gate::policy(Subscriber::class, SubscriberPolicy::class);

        // The structured half of the content graph. One rule, one class.
        foreach ([
            Category::class,
            Company::class,
            Country::class,
            Industry::class,
            Market::class,
            Opportunity::class,
            Person::class,
            Topic::class,
        ] as $model) {
            Gate::policy($model, TaxonomyPolicy::class);
        }
    }
}
