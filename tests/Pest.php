<?php

declare(strict_types=1);

use App\Models\Article;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class)->in('Feature');
uses(TestCase::class)->in('Unit');

/**
 * A user carrying a real seeded role, so permission checks exercise the same
 * role/permission map the application ships with.
 */
function staff(string $role): User
{
    if (Role::query()->doesntExist()) {
        test()->seed(RoleSeeder::class);
    }

    $user = User::factory()->create();
    $user->syncRoles([$role]);

    return $user->fresh();
}

/**
 * An article that satisfies every publish-gate rule.
 *
 * @param  array<string, mixed>  $attributes
 */
function publishableArticle(array $attributes = []): Article
{
    $article = Article::factory()->create([
        'summary' => ['نقطة أولى', 'نقطة ثانية', 'نقطة ثالثة'],
        'why_it_matters' => 'هذا التحول يعيد تعريف المنافسة في القطاع.',
        'fact_checked_at' => now()->subDay(),
        'hero_media_id' => null,
        'hero_alt' => null,
        'is_sponsored' => false,
        ...$attributes,
    ]);

    $article->sources()->create([
        'title' => 'بيان رسمي',
        'url' => 'https://example.test/statement',
        'source_type' => 'official_document',
        'sort_order' => 0,
    ]);

    return $article->fresh();
}
