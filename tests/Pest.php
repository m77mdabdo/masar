<?php

declare(strict_types=1);

use App\Actions\Articles\UpdateGateFailuresCount;
use App\Models\Article;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
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
    $article = Article::factory()->withHeroImage()->create([
        'summary' => ['نقطة أولى', 'نقطة ثانية', 'نقطة ثالثة'],
        'why_it_matters' => 'هذا التحول يعيد تعريف المنافسة في القطاع.',
        'fact_checked_at' => now()->subDay(),
        'is_sponsored' => false,
    ]);

    $article->sources()->create([
        'title' => 'بيان رسمي',
        'url' => 'https://example.test/statement',
        'source_type' => 'official_document',
        'sort_order' => 0,
    ]);

    // Applied after the factory's afterCreating hooks, which would otherwise
    // overwrite an override — withHeroImage() sets hero_media_id last.
    if ($attributes !== []) {
        $article->forceFill($attributes)->save();
    }

    $article = $article->fresh();

    // Every real save path evaluates the gate, so the fixture does too —
    // otherwise gate_failures_count stays NULL ("never evaluated") and the
    // article is invisible to the blocked queue, which is correct behaviour but
    // not what a test arranging a blocked article means.
    app(UpdateGateFailuresCount::class)($article);

    return $article->fresh();
}

/**
 * A bare media row. Media is a vendor model with no factory; nothing here reads
 * the bytes, only the presence of the record.
 */
function fakeMediaId(): int
{
    return (int) DB::table('media')->insertGetId([
        'model_type' => 'article',
        'model_id' => 1,
        'uuid' => (string) Str::uuid(),
        'collection_name' => 'hero',
        'name' => 'hero',
        'file_name' => 'hero.webp',
        'mime_type' => 'image/webp',
        'disk' => 'public',
        'size' => 0,
        'manipulations' => '[]',
        'custom_properties' => '[]',
        'generated_conversions' => '[]',
        'responsive_images' => '[]',
        'order_column' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}
