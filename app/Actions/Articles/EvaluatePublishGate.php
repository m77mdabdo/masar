<?php

declare(strict_types=1);

namespace App\Actions\Articles;

use App\Models\Article;
use App\Models\ArticleSource;

/**
 * The publish gate — the single definition of "ready to publish".
 *
 * TransitionArticleStatus and the Filament UI both call this, so a rule can
 * never be enforced in one place and forgotten in the other.
 *
 * Every message is written to the editor and says what to *do*. "الملخص مفقود"
 * tells them something is wrong; "أضف ملخص الثلاثين ثانية (نقطتان على الأقل)"
 * tells them how to fix it, which is the difference between a gate that guides
 * and a gate that nags.
 *
 * @return array<int, string> empty means publishable
 */
class EvaluatePublishGate
{
    /**
     * @return array<int, string>
     */
    public function __invoke(Article $article): array
    {
        $gate = config('masar.publish_gate');
        $failures = [];

        $this->checkSummary($article, $gate, $failures);
        $this->checkSource($article, $gate, $failures);
        $this->checkHeroAlt($article, $gate, $failures);
        $this->checkFactCheck($article, $gate, $failures);
        $this->checkWhyItMatters($article, $gate, $failures);
        $this->checkSponsorship($article, $gate, $failures);

        return $failures;
    }

    /**
     * @param  array<string, mixed>  $gate
     * @param  array<int, string>  $failures
     */
    private function checkSummary(Article $article, array $gate, array &$failures): void
    {
        if (! ($gate['require_summary'] ?? false)) {
            return;
        }

        $min = (int) ($gate['min_summary_points'] ?? 2);
        $max = (int) ($gate['max_summary_points'] ?? 5);

        $points = array_filter(
            (array) ($article->summary ?? []),
            static fn ($point): bool => filled($point),
        );
        $count = count($points);

        if ($count === 0) {
            $failures[] = "أضف ملخص الثلاثين ثانية ({$min} نقاط على الأقل).";

            return;
        }

        if ($count < $min) {
            $failures[] = "أضف نقاطًا إلى ملخص الثلاثين ثانية: المطلوب {$min} على الأقل والحالي {$count}.";

            return;
        }

        if ($count > $max) {
            $failures[] = "اختصر ملخص الثلاثين ثانية إلى {$max} نقاط كحد أقصى؛ الحالي {$count}.";
        }
    }

    /**
     * @param  array<string, mixed>  $gate
     * @param  array<int, string>  $failures
     */
    private function checkSource(Article $article, array $gate, array &$failures): void
    {
        if (! ($gate['require_source'] ?? false)) {
            return;
        }

        $withUrl = $article->relationLoaded('sources')
            ? $article->sources->filter(static fn (ArticleSource $s): bool => filled($s->url))->count()
            : $article->sources()->whereNotNull('url')->where('url', '!=', '')->count();

        if ($withUrl === 0) {
            $failures[] = 'أضف مصدرًا واحدًا على الأقل مع رابط يمكن التحقق منه.';
        }
    }

    /**
     * Alt text is required only when there is actually a hero image to describe.
     *
     * @param  array<string, mixed>  $gate
     * @param  array<int, string>  $failures
     */
    private function checkHeroAlt(Article $article, array $gate, array &$failures): void
    {
        if (! ($gate['require_hero_alt'] ?? false)) {
            return;
        }

        if ($article->hero_media_id === null) {
            return;
        }

        if (blank($article->hero_alt)) {
            $failures[] = 'اكتب نصًا بديلًا يصف صورة الغلاف لقارئ لا يراها.';
        }
    }

    /**
     * @param  array<string, mixed>  $gate
     * @param  array<int, string>  $failures
     */
    private function checkFactCheck(Article $article, array $gate, array &$failures): void
    {
        if (! ($gate['require_fact_check'] ?? false)) {
            return;
        }

        if ($article->fact_checked_at === null) {
            $failures[] = 'مرّر المادة على مرحلة تدقيق المعلومات قبل النشر.';
        }
    }

    /**
     * Not a nice-to-have. "Why does it matter?" is the product promise — an
     * article without it is a wire story, which is explicitly not what we make.
     *
     * @param  array<string, mixed>  $gate
     * @param  array<int, string>  $failures
     */
    private function checkWhyItMatters(Article $article, array $gate, array &$failures): void
    {
        if (! ($gate['require_why_it_matters'] ?? true)) {
            return;
        }

        if (blank($article->why_it_matters)) {
            $failures[] = 'اكتب فقرة "لماذا يهم هذا؟" — هي وعد المنصة للقارئ وليست حقلًا اختياريًا.';
        }
    }

    /**
     * @param  array<string, mixed>  $gate
     * @param  array<int, string>  $failures
     */
    private function checkSponsorship(Article $article, array $gate, array &$failures): void
    {
        if (! ($gate['require_sponsor_name'] ?? true)) {
            return;
        }

        if ($article->is_sponsored && blank($article->sponsor_name)) {
            $failures[] = 'حدّد اسم الجهة الراعية؛ المحتوى المدفوع يجب أن يُفصح عنه للقارئ.';
        }
    }
}
