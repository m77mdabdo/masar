<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Subscriber;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The second half of double opt-in.
 *
 * This message is the only thing that turns a typed address into a subscriber,
 * so it says who is asking and what the reader gets, and it carries no tracking
 * pixel — the open rate is not worth a request to a third party from a message
 * the reader did not yet agree to receive.
 */
class ConfirmNewsletterSubscription extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        /** @var Subscriber $notifiable */
        $site = (string) setting('identity.site_name', 'MASAR');

        return (new MailMessage)
            ->subject("أكِّد اشتراكك في نشرة {$site}")
            ->greeting('أهلًا بك')
            ->line("طلب أحدهم الاشتراك بهذا البريد في النشرة الأسبوعية من {$site}.")
            ->line('إن كنت أنت، أكِّد اشتراكك من الزر أدناه. رسالة واحدة أسبوعيًا، ويمكنك إلغاء الاشتراك في أي وقت.')
            ->action('تأكيد الاشتراك', $notifiable->confirmationUrl())
            ->line('ينتهي هذا الرابط بعد '.Subscriber::CONFIRMATION_TTL_DAYS.' أيام.')
            // The address is not on the list yet, so "ignore this" is the whole
            // remedy: no confirmation, no mail, and the row expires unused.
            ->line('إن لم تطلب هذا الاشتراك، تجاهل هذه الرسالة ولن نرسل لك شيئًا آخر.');
    }
}
