<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The editorial workflow spine.
 *
 * Every status change in MASAR must pass through this map. A transition that is not
 * listed here does not exist — there is no "force" path, because an article that
 * jumps from `writing` straight to `published` has skipped fact-checking, and the
 * publish gate depends on that stage having actually happened.
 */
enum ArticleStatus: string
{
    case Idea = 'idea';
    case Assigned = 'assigned';
    case Research = 'research';
    case Writing = 'writing';
    case FactCheck = 'fact_check';
    case EditorReview = 'editor_review';
    case Seo = 'seo';
    case Ready = 'ready';
    case Scheduled = 'scheduled';
    case Published = 'published';
    case NeedsRevision = 'needs_revision';
    case OnHold = 'on_hold';
    case Rejected = 'rejected';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Idea => 'فكرة',
            self::Assigned => 'مُسندة',
            self::Research => 'بحث',
            self::Writing => 'كتابة',
            self::FactCheck => 'تدقيق المعلومات',
            self::EditorReview => 'مراجعة التحرير',
            self::Seo => 'تحسين محركات البحث',
            self::Ready => 'جاهزة للنشر',
            self::Scheduled => 'مجدولة',
            self::Published => 'منشورة',
            self::NeedsRevision => 'تحتاج مراجعة',
            self::OnHold => 'معلّقة',
            self::Rejected => 'مرفوضة',
            self::Archived => 'مؤرشفة',
        };
    }

    /**
     * @return array<int, self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Idea => [self::Assigned, self::Rejected],
            self::Assigned => [self::Research, self::Writing, self::OnHold, self::Rejected],
            self::Research => [self::Writing, self::OnHold],
            self::Writing => [self::FactCheck, self::OnHold],
            self::FactCheck => [self::EditorReview, self::NeedsRevision],
            self::EditorReview => [self::Seo, self::NeedsRevision],
            self::Seo => [self::Ready, self::NeedsRevision],
            self::Ready => [self::Scheduled, self::Published, self::NeedsRevision],
            self::Scheduled => [self::Published, self::Ready],
            self::Published => [self::Archived, self::NeedsRevision],
            self::NeedsRevision => [self::Writing, self::EditorReview],
            self::OnHold => [self::Writing, self::Rejected],
            self::Rejected => [self::Idea],
            self::Archived => [self::Published],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), strict: true);
    }

    /**
     * Stages where the article is still the writer's to edit.
     *
     * Once it reaches editor_review the copy is being worked on by someone
     * else, and a writer editing underneath that is how two people silently
     * overwrite each other.
     */
    public function isBeforeEditorialReview(): bool
    {
        return in_array($this, [
            self::Idea,
            self::Assigned,
            self::Research,
            self::Writing,
            self::FactCheck,
            self::NeedsRevision,
            self::OnHold,
        ], strict: true);
    }

    /**
     * Statuses that represent content visible to the public.
     */
    public function isPublic(): bool
    {
        return $this === self::Published;
    }

    /**
     * @return array<string, string> value => label, for select inputs
     */
    public static function options(): array
    {
        return array_reduce(
            self::cases(),
            static fn (array $carry, self $case): array => $carry + [$case->value => $case->label()],
            [],
        );
    }
}
