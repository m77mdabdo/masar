<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Source;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * A source has stopped working and has been taken out of the rotation.
 *
 * This notification is the whole reason auto-disable is safe. Disabling a
 * broken feed without telling anyone would turn a loud failure into a silent
 * one — which is the failure mode this subsystem exists to avoid, not create.
 */
class SourceDisabled extends Notification
{
    use Queueable;

    public function __construct(public readonly Source $source) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('توقّف مصدر: '.$this->source->name)
            ->line($this->source->name.' فشل '.$this->source->consecutive_failures.' مرات متتالية وتم تعطيله.')
            ->line('آخر خطأ: '.($this->source->error_message ?: 'غير معروف'))
            ->line('آخر نجاح: '.($this->source->last_success_at?->diffForHumans() ?? 'لا يوجد'))
            ->line('لن نرى أي خبر من هذا المصدر حتى يُعاد تفعيله.');
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'source_id' => $this->source->getKey(),
            'source_name' => $this->source->name,
            'consecutive_failures' => $this->source->consecutive_failures,
            'error_message' => $this->source->error_message,
            'last_success_at' => $this->source->last_success_at?->toIso8601String(),
        ];
    }
}
