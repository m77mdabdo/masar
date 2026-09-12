<?php

declare(strict_types=1);

namespace App\Filament\Resources\Articles\Support;

use App\Actions\Articles\PublishArticle;
use App\Actions\Articles\SchedulePublication;
use App\Actions\Articles\TransitionArticleStatus;
use App\Enums\ArticleStatus;
use App\Exceptions\InvalidStatusTransition;
use App\Exceptions\PublishGateFailed;
use App\Models\Article;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * Workflow buttons, generated from the transition map.
 *
 * The list is read from ArticleStatus::allowedTransitions() every time, so a
 * change to the map changes the UI with it. A hardcoded button list is how a
 * panel ends up offering a transition the domain refuses.
 */
class ArticleTransitionActions
{
    /**
     * Transitions that deserve an explanation from whoever makes them.
     *
     * @var array<int, string>
     */
    private const REQUIRES_NOTE = [
        'needs_revision',
        'rejected',
        'on_hold',
        'archived',
    ];

    /**
     * @return array<int, Action>
     */
    public static function for(Article $article): array
    {
        return collect($article->status->allowedTransitions())
            ->map(fn (ArticleStatus $to): Action => match ($to) {
                ArticleStatus::Published => self::publishAction($article),
                ArticleStatus::Scheduled => self::scheduleAction($article),
                default => self::transitionAction($article, $to),
            })
            ->all();
    }

    private static function transitionAction(Article $article, ArticleStatus $to): Action
    {
        $needsNote = in_array($to->value, self::REQUIRES_NOTE, true);

        return Action::make('transition_'.$to->value)
            ->label($to->label())
            ->color(self::colour($to))
            ->icon(self::icon($to))
            ->requiresConfirmation($needsNote)
            ->schema($needsNote ? [
                Textarea::make('note')
                    ->label('السبب')
                    ->required()
                    ->rows(3)
                    ->helperText('سيظهر في سجل التدقيق ولمن يستلم المادة بعدك.'),
            ] : [])
            ->action(function (array $data) use ($article, $to): void {
                self::run(
                    fn () => app(TransitionArticleStatus::class)(
                        $article,
                        $to,
                        auth()->user(),
                        $data['note'] ?? null,
                    ),
                    $to->label(),
                );
            });
    }

    /**
     * Publishing is disabled while the gate has anything to say, and the reasons
     * are in the tooltip — an editor must never click publish and be told no
     * without being told why.
     */
    private static function publishAction(Article $article): Action
    {
        $failures = $article->isPublishable();

        return Action::make('publish')
            ->label('نشر')
            ->color('success')
            ->icon('heroicon-o-rocket-launch')
            ->disabled($failures !== [])
            ->tooltip($failures === []
                ? null
                : 'لا يمكن النشر: '.implode(' · ', $failures))
            ->requiresConfirmation()
            ->modalHeading('نشر المادة')
            ->modalDescription('ستصبح المادة مرئية للقراء فورًا.')
            ->action(function () use ($article): void {
                self::run(
                    fn () => app(PublishArticle::class)($article, auth()->user()),
                    'النشر',
                );
            });
    }

    private static function scheduleAction(Article $article): Action
    {
        return Action::make('schedule')
            ->label('جدولة')
            ->color('warning')
            ->icon('heroicon-o-clock')
            ->schema([
                DateTimePicker::make('scheduled_for')
                    ->label('موعد النشر')
                    ->required()
                    ->seconds(false)
                    ->minDate(now()->addMinute())
                    ->default(now()->addDay()->startOfHour())
                    ->helperText('بتوقيت الرياض.'),
            ])
            ->action(function (array $data) use ($article): void {
                self::run(
                    fn () => app(SchedulePublication::class)(
                        $article,
                        $data['scheduled_for'],
                        auth()->user(),
                    ),
                    'الجدولة',
                );
            });
    }

    /**
     * Domain failures become notifications carrying the actual reason, never a
     * generic "something went wrong".
     */
    private static function run(callable $operation, string $label): void
    {
        try {
            $operation();

            Notification::make()
                ->success()
                ->title("تمت العملية: {$label}")
                ->send();
        } catch (PublishGateFailed $e) {
            Notification::make()
                ->danger()
                ->title('بوابة النشر رفضت المادة')
                ->body(implode("\n", $e->reasons()))
                ->persistent()
                ->send();
        } catch (InvalidStatusTransition $e) {
            Notification::make()
                ->danger()
                ->title('انتقال غير مسموح')
                ->body($e->forEditor())
                ->send();
        } catch (AuthorizationException) {
            Notification::make()
                ->danger()
                ->title('لا تملك صلاحية تنفيذ هذا الإجراء')
                ->send();
        }
    }

    private static function colour(ArticleStatus $status): string
    {
        return match ($status) {
            ArticleStatus::Published => 'success',
            ArticleStatus::Rejected, ArticleStatus::NeedsRevision => 'danger',
            ArticleStatus::OnHold, ArticleStatus::Scheduled => 'warning',
            ArticleStatus::Ready, ArticleStatus::EditorReview => 'primary',
            default => 'gray',
        };
    }

    private static function icon(ArticleStatus $status): string
    {
        return match ($status) {
            ArticleStatus::Assigned => 'heroicon-o-user-plus',
            ArticleStatus::Research => 'heroicon-o-magnifying-glass',
            ArticleStatus::Writing => 'heroicon-o-pencil',
            ArticleStatus::FactCheck => 'heroicon-o-shield-check',
            ArticleStatus::EditorReview => 'heroicon-o-eye',
            ArticleStatus::Seo => 'heroicon-o-chart-bar',
            ArticleStatus::Ready => 'heroicon-o-check-badge',
            ArticleStatus::NeedsRevision => 'heroicon-o-arrow-uturn-left',
            ArticleStatus::OnHold => 'heroicon-o-pause-circle',
            ArticleStatus::Rejected => 'heroicon-o-x-circle',
            ArticleStatus::Archived => 'heroicon-o-archive-box',
            ArticleStatus::Idea => 'heroicon-o-light-bulb',
            default => 'heroicon-o-arrow-right',
        };
    }
}
