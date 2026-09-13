<?php

declare(strict_types=1);

use App\Http\Controllers\Web;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public site
|--------------------------------------------------------------------------
| Every public URL carries its locale prefix. These names are the only place a
| MASAR URL is defined — EntityUrl resolves through route() and nothing else
| builds a path by hand.
|
| Order matters: the fixed sections must be declared before the {category}
| catch-all, or `/ar/topics/vision-2030` would be read as a category called
| "topics" containing an article called "vision-2030".
*/

/*
 * The locale segment is constrained to known codes at the router, not just
 * validated in middleware. Without this, `/{locale}` matches `sitemap.xml` and
 * swallows every top-level file route declared after it.
 */
Route::middleware('locale')
    ->prefix('{locale}')
    ->whereIn('locale', array_keys((array) config('masar.locales')))
    ->name('web.')
    ->group(function (): void {
        Route::get('/', Web\HomeController::class)->name('home');

        // Inside the group and before the {category} catch-all, or `/ar/rss.xml`
        // would be read as a category named "rss.xml".
        Route::get('/rss.xml', [Web\FeedController::class, 'locale'])->name('rss.locale');

        Route::get('/search', Web\SearchController::class)->name('search');
        Route::get('/markets', Web\MarketsController::class)->name('markets');
        Route::get('/video', [Web\VideoController::class, 'index'])->name('video.index');
        Route::get('/newsletter', Web\NewsletterController::class)->name('newsletter');
        Route::get('/about', [Web\PageController::class, 'about'])->name('about');
        Route::get('/contact', [Web\PageController::class, 'contact'])->name('contact');
        Route::get('/editorial-standards', [Web\PageController::class, 'editorialStandards'])->name('editorial-standards');

        Route::get('/opportunities', [Web\OpportunityController::class, 'index'])->name('opportunities.index');
        Route::get('/opportunities/{slug}', [Web\OpportunityController::class, 'show'])->name('opportunity.show');

        Route::get('/topics/{slug}', Web\TopicController::class)->name('topic.show');
        Route::get('/companies/{slug}', Web\CompanyController::class)->name('company.show');
        Route::get('/people/{slug}', Web\PersonController::class)->name('person.show');
        Route::get('/authors/{author}', Web\AuthorController::class)->name('author.show');
        Route::get('/issues/{slug}', Web\IssueController::class)->name('issue.show');

        // Catch-all last: a two-segment path is a category, three is an article.
        Route::get('/{category}', Web\CategoryController::class)->name('category.show');
        Route::get('/{category}/{slug}', Web\ArticleController::class)->name('article.show');
    });

/*
 * Feeds and robots sit outside the locale prefix where they are conventional,
 * and inside it where a reader-facing feed needs one.
 */
Route::get('/robots.txt', Web\RobotsController::class)->name('web.robots');
Route::get('/sitemap.xml', [Web\SitemapController::class, 'index'])->name('web.sitemap');
Route::get('/news-sitemap.xml', [Web\SitemapController::class, 'news'])->name('web.sitemap.news');
Route::get('/rss.xml', [Web\FeedController::class, 'index'])->name('web.rss');

/*
 * The bare root picks a locale from Accept-Language rather than assuming one —
 * and falls back to the default when the header offers nothing we publish.
 */
Route::get('/', Web\RootRedirectController::class)->name('web.root');

/*
 * Homepage preview for the composer. Admin-guarded and deliberately not part of
 * the public route set: it renders any layout, active or not, so an editor can
 * see a scheduled front page before it goes live.
 */
Route::get('/admin/homepage/preview/{layout}', Web\HomepagePreviewController::class)
    ->middleware(['web', 'auth'])
    ->name('admin.homepage.preview');
