<?php

declare(strict_types=1);

namespace App\Actions\Redirects;

use App\Models\Redirect;
use App\Support\EntityUrl;

/**
 * Structural checks on a redirect before it is saved.
 *
 * Two of these matter more than they look:
 *
 *  - **Shadowing.** A redirect whose source is a live URL silently removes that
 *    page from the site. Nothing errors, traffic simply stops arriving, and the
 *    cause is invisible from the article screen.
 *  - **Chains.** Each hop costs a round trip and search engines stop following
 *    after a few. One hop is a redirect; three is an outage with extra steps.
 */
class ValidateRedirect
{
    public function __construct(
        private readonly NormalisePath $normalise,
        private readonly EntityUrl $urls,
    ) {}

    /**
     * @return array<int, string> empty means valid
     */
    public function __invoke(string $from, string $to, ?int $ignoreId = null): array
    {
        $from = ($this->normalise)($from);
        $to = ($this->normalise)($to);

        $problems = [];

        if ($from === $to) {
            $problems[] = 'المسار المصدر والوجهة متطابقان.';

            return $problems;
        }

        if ($from === '/') {
            $problems[] = 'لا يمكن تحويل الصفحة الرئيسية.';
        }

        if ($this->shadowsLiveContent($from)) {
            $problems[] = 'هذا المسار يخدم محتوى منشورًا بالفعل — التحويل سيخفيه عن القراء.';
        }

        if ($this->duplicates($from, $ignoreId)) {
            $problems[] = 'يوجد تحويل بهذا المسار المصدر.';
        }

        if ($this->createsLoop($from, $to, $ignoreId)) {
            $problems[] = 'هذا التحويل يُنشئ حلقة مغلقة.';
        }

        if ($this->extendsChain($to, $ignoreId)) {
            $problems[] = 'الوجهة نفسها محوّلة — السلسلة يجب ألا تتجاوز خطوة واحدة.';
        }

        return $problems;
    }

    private function duplicates(string $from, ?int $ignoreId): bool
    {
        return Redirect::query()
            ->where('from_path', $from)
            ->when($ignoreId !== null, fn ($query) => $query->whereKeyNot($ignoreId))
            ->exists();
    }

    /**
     * A → B where B → A already exists.
     */
    private function createsLoop(string $from, string $to, ?int $ignoreId): bool
    {
        return Redirect::query()
            ->where('from_path', $to)
            ->where('to_path', $from)
            ->when($ignoreId !== null, fn ($query) => $query->whereKeyNot($ignoreId))
            ->exists();
    }

    /**
     * A → B where B → C already exists: the reader would take two hops.
     */
    private function extendsChain(string $to, ?int $ignoreId): bool
    {
        return Redirect::query()
            ->where('from_path', $to)
            ->when($ignoreId !== null, fn ($query) => $query->whereKeyNot($ignoreId))
            ->exists();
    }

    /**
     * Does this path already resolve to published content?
     *
     * Delegated to EntityUrl, which builds the answer with route() — the same
     * call the header and the canonical tag use. When this class kept its own
     * copy of the path shapes, a redirect could be validated against one
     * spelling of a URL while the site served another.
     */
    public function shadowsLiveContent(string $path): bool
    {
        return $this->urls->pathServesLiveContent($path);
    }
}
