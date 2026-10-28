<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Article;
use Normalizer;

/**
 * What in an article is language, and what is not.
 *
 * Every translatable string is a segment with a stable path — `title`,
 * `summary.1`, `body.3`, `blocks.4.text`, `key_numbers.0.label` — so a
 * provider receives a flat list of plain strings and never the article's
 * structure. Block types, media ids, URLs, dates, sort order and the
 * four-question sections are structure: they are copied, never sent.
 *
 * The same list is what the source hash is computed over, so "outdated"
 * means exactly "a string a reader would see in another language changed".
 * A re-ordered block or a swapped photograph does not make a translation
 * stale; an edited sentence does.
 *
 * Long bodies are segmented by paragraph, which is also the unit a provider
 * can translate without losing context mid-sentence.
 */
final class TranslatableContent
{
    /** Article columns that hold reader-facing language. */
    private const FIELDS = ['title', 'subtitle', 'what_happened', 'why_it_matters', 'business_impact', 'opportunity', 'hero_alt', 'meta_title', 'meta_description'];

    /** Keys inside a block's JSON that hold language; anything else is structure. */
    private const BLOCK_KEYS = ['text', 'caption', 'title', 'attribution', 'role'];

    /** Keys inside a key-number entry that hold language. */
    private const NUMBER_KEYS = ['label', 'value', 'source'];

    /**
     * @return array<string, string>
     */
    public function segments(Article $article): array
    {
        $out = [];

        foreach (self::FIELDS as $field) {
            if (filled($article->{$field})) {
                $out[$field] = (string) $article->{$field};
            }
        }

        foreach (array_values((array) $article->summary) as $i => $point) {
            if (filled($point)) {
                $out["summary.{$i}"] = (string) $point;
            }
        }

        foreach ($this->paragraphs((string) $article->body) as $i => $paragraph) {
            $out["body.{$i}"] = $paragraph;
        }

        foreach (array_values((array) $article->key_numbers) as $i => $number) {
            foreach (self::NUMBER_KEYS as $key) {
                if (filled($number[$key] ?? null)) {
                    $out["key_numbers.{$i}.{$key}"] = (string) $number[$key];
                }
            }
        }

        foreach ($article->blocks as $i => $block) {
            foreach (self::BLOCK_KEYS as $key) {
                if (is_string($block->data[$key] ?? null) && filled($block->data[$key])) {
                    $out["blocks.{$i}.{$key}"] = $block->data[$key];
                }
            }
        }

        return $out;
    }

    public function hash(Article $article): string
    {
        return hash('sha256', json_encode($this->segments($article), JSON_UNESCAPED_UNICODE));
    }

    /**
     * Writes translated segments onto a draft. Paths not in $segments are left
     * as they are, so a partial result can never blank a field.
     *
     * @param  array<string, string>  $segments
     */
    public function apply(Article $draft, array $segments): void
    {
        $segments = array_map(fn (string $s): string => $this->clean($s), $segments);

        foreach (self::FIELDS as $field) {
            if (array_key_exists($field, $segments)) {
                $draft->{$field} = $segments[$field];
            }
        }

        $summary = array_values((array) $draft->summary);
        foreach ($summary as $i => $point) {
            $summary[$i] = $segments["summary.{$i}"] ?? $point;
        }
        $draft->summary = $summary;

        $paragraphs = $this->paragraphs((string) $draft->body);
        foreach ($paragraphs as $i => $paragraph) {
            $paragraphs[$i] = $segments["body.{$i}"] ?? $paragraph;
        }
        if ($paragraphs !== []) {
            $draft->body = implode("\n\n", $paragraphs);
        }

        $numbers = array_values((array) $draft->key_numbers);
        foreach ($numbers as $i => $number) {
            foreach (self::NUMBER_KEYS as $key) {
                if (isset($segments["key_numbers.{$i}.{$key}"])) {
                    $numbers[$i][$key] = $segments["key_numbers.{$i}.{$key}"];
                }
            }
        }
        $draft->key_numbers = $numbers ?: $draft->key_numbers;

        foreach ($draft->blocks as $i => $block) {
            $data = $block->data;
            foreach (self::BLOCK_KEYS as $key) {
                if (isset($segments["blocks.{$i}.{$key}"])) {
                    $data[$key] = $segments["blocks.{$i}.{$key}"];
                }
            }
            if ($data !== $block->data) {
                $block->data = $data;
                $block->save();
            }
        }
    }

    /**
     * Output is plain text in every segment: tags are stripped rather than
     * escaped-and-kept, because no segment was sent as markup. NFC so the same
     * word typed and translated compares equal in search.
     */
    private function clean(string $text): string
    {
        $text = trim(strip_tags($text));

        return class_exists(Normalizer::class) ? (Normalizer::normalize($text, Normalizer::FORM_C) ?: $text) : $text;
    }

    /**
     * @return list<string>
     */
    private function paragraphs(string $body): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/\n\s*\n/', trim($body)) ?: []), 'filled'));
    }
}
