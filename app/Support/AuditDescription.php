<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\ArticleStatus;
use Spatie\Activitylog\Models\Activity;

/**
 * An audit entry in the reader's panel language, without touching the entry.
 *
 * The stored description is evidence and stays as written: Spatie's event
 * codes (`created`, `updated`), the Arabic sentences our listeners wrote, and
 * a transition whose labels were Arabic when it was logged. What a person
 * reads is rebuilt from what the entry carries — a status transition from
 * its stored enum values, a known sentence through the catalogue — and
 * anything this does not recognise is shown exactly as stored.
 */
final class AuditDescription
{
    public static function present(Activity $activity): string
    {
        $transition = self::transition($activity->properties?->all() ?? []);
        if ($transition !== null) {
            return $transition;
        }

        return match ((string) $activity->description) {
            'created' => __('أُنشئ'),
            'updated' => __('عُدّل'),
            'deleted' => __('حُذف'),
            'reviewed' => __('رُوجعت الترجمة'),
            'translated' => __('تُرجمت'),
            'نُشرت المادة' => __('نُشرت المادة'),
            'فات موعد النشر المجدول — أُعيدت للمراجعة دون نشر' => __('فات موعد النشر المجدول — أُعيدت للمراجعة دون نشر'),
            default => (string) $activity->description,
        };
    }

    /**
     * "Idea → Assigned" in a left-to-right panel, "مُسندة ← فكرة" in a
     * right-to-left one: the arrow always points at the status it moved to.
     *
     * @param  array<string, mixed>  $properties
     */
    public static function transition(array $properties): ?string
    {
        $from = ArticleStatus::tryFrom((string) ($properties['from'] ?? ''));
        $to = ArticleStatus::tryFrom((string) ($properties['to'] ?? ''));
        if ($from === null || $to === null) {
            return null;
        }

        $line = Locales::dir(app()->getLocale()) === 'rtl'
            ? $to->label().' ← '.$from->label()
            : $from->label().' → '.$to->label();

        return filled($properties['note'] ?? null) ? $line.' · '.$properties['note'] : $line;
    }
}
