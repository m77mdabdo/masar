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
     * Editor-facing messages only. This is the contract the publish Action and
     * the model's isPublishable() rely on.
     *
     * @return array<int, string>
     */
    public function __invoke(Article $article): array
    {
        return array_column($this->detailed($article), 'message');
    }

    /**
     * The same failures, each tagged with the rule that produced it.
     *
     * The admin panel uses the rule key to point an editor at the tab that fixes
     * the problem; nothing else should need to branch on it.
     *
     * @return array<int, array{rule: string, message: string}>
     */
    public function detailed(Article $article): array
    {
        $gate = config('masar.publish_gate');
        $failures = [];

        $this->checkSummary($article, $gate, $failures);
        $this->checkSource($article, $gate, $failures);
        $this->checkHero($article, $gate, $failures);
        $this->checkFactCheck($article, $gate, $failures);
        $this->checkWhyItMatters($article, $gate, $failures);
        $this->checkSponsorship($article, $gate, $failures);

        return $failures;
    }

    /**
     * @param  array<string, mixed>  $gate
     * @param  array<int, array{rule: string, message: string}>  $failures
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
            $failures[] = ['rule' => 'summary', 'message' => "أضف ملخص الثلاثين ثانية ({$min} نقاط على الأقل)."];

            return;
        }

        if ($count < $min) {
            $failures[] = ['rule' => 'summary', 'message' => "أضف نقاطًا إلى ملخص الثلاثين ثانية: المطلوب {$min} على الأقل والحالي {$count}."];

            return;
        }

        if ($count > $max) {
            $failures[] = ['rule' => 'summary', 'message' => "اختصر ملخص الثلاثين ثانية إلى {$max} نقاط كحد أقصى؛ الحالي {$count}."];
        }
    }

    /**
     * @param  array<string, mixed>  $gate
     * @param  array<int, array{rule: string, message: string}>  $failures
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
            $failures[] = ['rule' => 'source', 'message' => 'أضف مصدرًا واحدًا على الأقل مع رابط يمكن التحقق منه.'];
        }
    }

    /**
     * The image and its alt text are one rule with two failure modes.
     *
     * The image is required because every listing surface renders one — an
     * article without a hero leaves a hole in the homepage grid. The alt text is
     * required unconditionally alongside it, because a hero image that no
     * screen reader can describe fails the accessibility floor.
     *
     * @param  array<string, mixed>  $gate
     * @param  array<int, array{rule: string, message: string}>  $failures
     */
    private function checkHero(Article $article, array $gate, array &$failures): void
    {
        if (($gate['require_hero_image'] ?? true) && $article->hero_media_id === null) {
            $failures[] = ['rule' => 'hero_image', 'message' => 'أضف صورة غلاف للمادة؛ كل بطاقات العرض والقوائم تعتمد عليها.'];
        }

        if (($gate['require_hero_alt'] ?? true) && blank($article->hero_alt)) {
            $failures[] = ['rule' => 'hero_alt', 'message' => 'اكتب نصًا بديلًا يصف صورة الغلاف لقارئ لا يراها.'];
        }
    }

    /**
     * @param  array<string, mixed>  $gate
     * @param  array<int, array{rule: string, message: string}>  $failures
     */
    private function checkFactCheck(Article $article, array $gate, array &$failures): void
    {
        if (! ($gate['require_fact_check'] ?? false)) {
            return;
        }

        if ($article->fact_checked_at === null) {
            $failures[] = ['rule' => 'fact_check', 'message' => 'مرّر المادة على مرحلة تدقيق المعلومات قبل النشر.'];
        }
    }

    /**
     * Not a nice-to-have. "Why does it matter?" is the product promise — an
     * article without it is a wire story, which is explicitly not what we make.
     *
     * @param  array<string, mixed>  $gate
     * @param  array<int, array{rule: string, message: string}>  $failures
     */
    private function checkWhyItMatters(Article $article, array $gate, array &$failures): void
    {
        if (! ($gate['require_why_it_matters'] ?? true)) {
            return;
        }

        if (blank($article->why_it_matters)) {
            $failures[] = ['rule' => 'why_it_matters', 'message' => 'اكتب فقرة "لماذا يهم هذا؟" — هي وعد المنصة للقارئ وليست حقلًا اختياريًا.'];
        }
    }

    /**
     * @param  array<string, mixed>  $gate
     * @param  array<int, array{rule: string, message: string}>  $failures
     */
    private function checkSponsorship(Article $article, array $gate, array &$failures): void
    {
        if (! ($gate['require_sponsor_name'] ?? true)) {
            return;
        }

        if ($article->is_sponsored && blank($article->sponsor_name)) {
            $failures[] = ['rule' => 'sponsor_name', 'message' => 'حدّد اسم الجهة الراعية؛ المحتوى المدفوع يجب أن يُفصح عنه للقارئ.'];
        }
    }
}
