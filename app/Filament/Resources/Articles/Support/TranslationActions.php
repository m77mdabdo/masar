<?php

declare(strict_types=1);

namespace App\Filament\Resources\Articles\Support;

use App\Actions\Translation\CreateTranslationDraft;
use App\Actions\Translation\MarkTranslationReviewed;
use App\Actions\Translation\RetryTranslation;
use App\Exceptions\TranslationNotAllowed;
use App\Filament\Resources\Articles\ArticleResource;
use App\Models\Article;
use App\Support\Locales;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * The translation buttons on an article. Each one only asks the policy
 * whether to show itself; the Action it calls enforces the rule again, so a
 * button that should not have been there still cannot do anything.
 */
final class TranslationActions
{
    /** @return array<int, Action> */
    public static function for(Article $article): array
    {
        return [self::translate($article), self::review($article), self::retry($article)];
    }

    private static function translate(Article $article): Action
    {
        return Action::make('translate')
            ->label(__('admin.translation.action_translate'))
            ->icon('heroicon-o-language')
            ->color('gray')
            ->visible(fn (): bool => ! $article->isTranslation() && auth()->user()?->can('translate', $article) && self::missing($article) !== [])
            ->schema([
                Select::make('locale')
                    ->label(__('admin.translation.target'))
                    ->options(fn (): array => self::missing($article))
                    ->required(),
            ])
            ->action(function (array $data) use ($article): void {
                try {
                    $draft = app(CreateTranslationDraft::class)($article, (string) $data['locale'], auth()->user());
                } catch (TranslationNotAllowed|AuthorizationException $e) {
                    Notification::make()->danger()->title($e->getMessage())->send();

                    return;
                }

                Notification::make()->success()->title(__('admin.translation.opened'))->send();
                redirect(ArticleResource::getUrl('edit', ['record' => $draft]));
            });
    }

    private static function review(Article $article): Action
    {
        return Action::make('reviewTranslation')
            ->label(__('admin.translation.action_review'))
            ->icon('heroicon-o-check-badge')
            ->color('success')
            ->requiresConfirmation()
            ->modalDescription(__('admin.translation.review_confirm'))
            ->visible(fn (): bool => $article->isTranslation()
                && ! $article->translation_status?->isProtected()
                && auth()->user()?->can('reviewTranslation', $article))
            ->action(function () use ($article): void {
                try {
                    app(MarkTranslationReviewed::class)($article, auth()->user());
                } catch (TranslationNotAllowed|AuthorizationException $e) {
                    Notification::make()->danger()->title($e->getMessage())->send();

                    return;
                }

                Notification::make()->success()->title(__('admin.translation.reviewed'))->send();
            });
    }

    private static function retry(Article $article): Action
    {
        return Action::make('retryTranslation')
            ->label(__('admin.translation.action_retry'))
            ->icon('heroicon-o-arrow-path')
            ->color('gray')
            ->visible(fn (): bool => $article->isTranslation()
                && ($article->translation_status?->canRetry() ?? false)
                && auth()->user()?->can('translate', $article))
            ->action(function () use ($article): void {
                try {
                    app(RetryTranslation::class)($article, auth()->user());
                } catch (TranslationNotAllowed|AuthorizationException $e) {
                    Notification::make()->danger()->title($e->getMessage())->send();

                    return;
                }

                Notification::make()->success()->title(__('admin.translation.requeued'))->send();
            });
    }

    /**
     * Editions with no version of this story yet, by native name. Disabled
     * editions are offered too: translating ahead of a launch is the point.
     *
     * @return array<string, string>
     */
    private static function missing(Article $article): array
    {
        $taken = Article::withTrashed()->where('translation_group_id', $article->translation_group_id)->pluck('locale')->all();

        return collect(Locales::codes())
            ->reject(fn (string $code): bool => in_array($code, $taken, true))
            ->mapWithKeys(fn (string $code): array => [$code => Locales::name($code)])
            ->all();
    }
}
