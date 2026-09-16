<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\IntelligenceItem;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Something arrived that does not wait.
 *
 * The inbox is a queue an editor clears on their own rhythm. This is for the
 * cases where that rhythm is too slow — a regulator decision scoring above the
 * alert threshold is a story with a deadline, and finding it at 4pm is finding
 * it after everyone else.
 *
 * Deliberately narrow. A channel that fires constantly is one people mute, and
 * a muted alert is worse than no alert because everyone believes it works.
 */
class HighImportanceItem extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly IntelligenceItem $item) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('عنصر مهم في صندوق الرصد: '.mb_substr($this->item->title, 0, 60))
            ->line($this->item->title)
            ->line('المصدر: '.($this->item->source?->name ?? 'غير معروف'))
            ->line('الأهمية: '.$this->item->liveImportance().' من 100')
            ->action('افتح المصدر', (string) $this->item->url)
            // The publisher's own page, not ours: the raw text is internal for
            // its whole life and does not travel into an email either.
            ->line('صندوق الرصد يحمل بقية التفاصيل.');
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'عنصر مهم في صندوق الرصد',
            'body' => mb_substr($this->item->title, 0, 140),
            'importance' => $this->item->liveImportance(),
            'source' => $this->item->source?->name,
            'intelligence_item_id' => $this->item->getKey(),
        ];
    }
}
