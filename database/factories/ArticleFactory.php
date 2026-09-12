<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ArticleStatus;
use App\Enums\ContentType;
use App\Enums\EntityRole;
use App\Models\Article;
use App\Models\Category;
use App\Models\Company;
use App\Models\Person;
use App\Models\User;
use Database\Factories\Support\ArabicContent;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * @extends Factory<Article>
 */
class ArticleFactory extends Factory
{
    protected $model = Article::class;

    public function definition(): array
    {
        $headline = $this->faker->randomElement(ArabicContent::HEADLINES);

        return [
            'locale' => 'ar',
            'translation_group_id' => (string) Str::uuid(),
            'category_id' => Category::factory(),
            'author_id' => User::factory(),
            'editor_id' => null,
            'fact_checker_id' => null,

            'status' => ArticleStatus::Idea,
            'content_type' => ContentType::News,

            'title' => $headline,
            'subtitle' => $this->faker->randomElement(ArabicContent::SUBTITLES),
            // Transliterated Latin, never URL-encoded Arabic.
            'slug' => Str::slug($this->faker->unique()->words(4, true)),

            'summary' => $this->faker->randomElements(ArabicContent::SUMMARY_POINTS, 3),
            'body' => implode("\n\n", $this->faker->randomElements(ArabicContent::PARAGRAPHS, 4)),

            'why_it_matters' => $this->faker->randomElement(ArabicContent::WHY_IT_MATTERS),
            'business_impact' => $this->faker->randomElement(ArabicContent::BUSINESS_IMPACT),
            'opportunity' => $this->faker->randomElement(ArabicContent::OPPORTUNITY),
            'key_numbers' => [
                ['value' => $this->faker->numberBetween(2, 90).'%', 'label' => $this->faker->randomElement(ArabicContent::NUMBER_LABELS)],
                ['value' => $this->faker->numberBetween(1, 40).' مليار ريال', 'label' => $this->faker->randomElement(ArabicContent::NUMBER_LABELS)],
            ],

            'hero_media_id' => null,
            'hero_alt' => 'صورة تعبيرية توضح نشاطًا اقتصاديًا مرتبطًا بموضوع المادة.',
            'hero_credit' => null,

            'reading_time' => null,
            'is_featured' => false,
            'is_sponsored' => false,
            'sponsor_name' => null,

            'meta_title' => null,
            'meta_description' => null,
            'canonical_url' => null,
            'og_image_path' => null,
            'noindex' => false,

            'distribution' => null,

            'published_at' => null,
            'scheduled_for' => null,
            'updated_content_at' => null,
            'fact_checked_at' => null,

            'views_count' => 0,
        ];
    }

    /**
     * A fully gate-passing, live article: fact-checked, sourced, summarised.
     */
    public function published(): static
    {
        return $this->state(fn (): array => [
            'status' => ArticleStatus::Published,
            'published_at' => $this->faker->dateTimeBetween('-6 months', '-1 hour'),
            'fact_checked_at' => $this->faker->dateTimeBetween('-7 months', '-2 hours'),
            'editor_id' => User::factory(),
            'fact_checker_id' => User::factory(),
            'views_count' => $this->faker->numberBetween(50, 25_000),
        ])->withSources()->withHeroImage();
    }

    public function draft(): static
    {
        return $this->state([
            'status' => ArticleStatus::Writing,
            'published_at' => null,
            'fact_checked_at' => null,
        ]);
    }

    public function scheduled(): static
    {
        return $this->state(fn (): array => [
            'status' => ArticleStatus::Scheduled,
            'scheduled_for' => $this->faker->dateTimeBetween('+1 hour', '+3 weeks'),
            'fact_checked_at' => $this->faker->dateTimeBetween('-2 weeks', '-1 day'),
            'published_at' => null,
        ])->withSources()->withHeroImage();
    }

    public function sponsored(): static
    {
        return $this->state([
            'content_type' => ContentType::Sponsored,
            'is_sponsored' => true,
            'sponsor_name' => $this->faker->randomElement(ArabicContent::COMPANY_NAMES),
        ]);
    }

    public function featured(): static
    {
        return $this->state(['is_featured' => true]);
    }

    public function inLocale(string $locale): static
    {
        return $this->state(['locale' => $locale]);
    }

    /**
     * Attach a hero image.
     *
     * Media is a vendor model with no factory of its own, so the row is written
     * directly. It carries no real file — nothing in these tests or the demo
     * seeder reads the bytes, only the presence of the record and its alt text,
     * which is what the publish gate asks about.
     */
    public function withHeroImage(?string $alt = null): static
    {
        return $this->afterCreating(function (Article $article) use ($alt): void {
            if ($article->hero_media_id !== null) {
                return;
            }

            $mediaId = DB::table('media')->insertGetId([
                'model_type' => $article->getMorphClass(),
                'model_id' => $article->getKey(),
                'uuid' => (string) Str::uuid(),
                'collection_name' => 'hero',
                'name' => 'hero-'.$article->getKey(),
                'file_name' => 'hero-'.$article->getKey().'.webp',
                'mime_type' => 'image/webp',
                'disk' => 'public',
                'conversions_disk' => 'public',
                'size' => 0,
                'manipulations' => '[]',
                'custom_properties' => '[]',
                'generated_conversions' => '[]',
                'responsive_images' => '[]',
                'order_column' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $article->forceFill([
                'hero_media_id' => $mediaId,
                'hero_alt' => $alt ?? $article->hero_alt ?? 'صورة تعبيرية توضح نشاطًا اقتصاديًا مرتبطًا بموضوع المادة.',
            ])->save();
        });
    }

    /**
     * Attach a realistic block structure. Blocks are the source of truth for the
     * body; `articles.body` is only a rendered fallback.
     */
    public function withBlocks(int $count = 6): static
    {
        return $this->afterCreating(function (Article $article) use ($count): void {
            $order = 0;

            $article->blocks()->create([
                'type' => 'heading',
                'data' => ['level' => 2, 'text' => 'ما الذي حدث؟'],
                'sort_order' => $order++,
            ]);

            foreach (fake()->randomElements(ArabicContent::PARAGRAPHS, min(3, $count)) as $paragraph) {
                $article->blocks()->create([
                    'type' => 'paragraph',
                    'data' => ['text' => $paragraph],
                    'sort_order' => $order++,
                ]);
            }

            $article->blocks()->create([
                'type' => 'numbers',
                'data' => ['items' => $article->key_numbers ?? []],
                'sort_order' => $order++,
            ]);

            $article->blocks()->create([
                'type' => 'callout',
                'data' => ['tone' => 'insight', 'text' => fake()->randomElement(ArabicContent::WHY_IT_MATTERS)],
                'sort_order' => $order++,
            ]);

            $article->blocks()->create([
                'type' => 'opportunity',
                'data' => ['text' => fake()->randomElement(ArabicContent::OPPORTUNITY)],
                'sort_order' => $order,
            ]);
        });
    }

    /**
     * At least one source with a URL — the publish gate requires it.
     */
    public function withSources(int $count = 2): static
    {
        return $this->afterCreating(function (Article $article) use ($count): void {
            if ($article->sources()->exists()) {
                return;
            }

            for ($i = 0; $i < $count; $i++) {
                $article->sources()->create([
                    'title' => 'بيان صادر عن الجهة المختصة',
                    'url' => fake()->url(),
                    'publisher' => fake()->randomElement(ArabicContent::SOURCE_PUBLISHERS),
                    'source_type' => fake()->randomElement([
                        'official_document', 'press_release', 'interview', 'report', 'data',
                    ]),
                    'accessed_at' => now()->subDays(fake()->numberBetween(1, 60)),
                    'sort_order' => $i,
                ]);
            }
        });
    }

    /**
     * Wire the article into the content graph. Uses existing entities when the
     * database already has them, so a seeder builds a connected graph rather
     * than one isolated company per article.
     */
    public function withMentions(int $companies = 2, int $people = 1): static
    {
        return $this->afterCreating(function (Article $article) use ($companies, $people): void {
            $companyPool = Company::query()->inRandomOrder()->take($companies)->get();

            while ($companyPool->count() < $companies) {
                $companyPool->push(Company::factory()->create());
            }

            foreach ($companyPool as $index => $company) {
                $article->mentions()->create([
                    'entity_type' => $company->getMorphClass(),
                    'entity_id' => $company->getKey(),
                    'role' => $index === 0 ? EntityRole::Primary : EntityRole::Secondary,
                    'prominence' => $index === 0 ? fake()->numberBetween(70, 100) : fake()->numberBetween(30, 69),
                    'created_at' => $article->published_at ?? $article->created_at ?? now(),
                ]);
            }

            $personPool = Person::query()->inRandomOrder()->take($people)->get();

            while ($personPool->count() < $people) {
                $personPool->push(Person::factory()->create());
            }

            foreach ($personPool as $person) {
                $article->mentions()->create([
                    'entity_type' => $person->getMorphClass(),
                    'entity_id' => $person->getKey(),
                    'role' => EntityRole::Mentioned,
                    'prominence' => fake()->numberBetween(10, 50),
                    'created_at' => $article->published_at ?? $article->created_at ?? now(),
                ]);
            }
        });
    }
}
