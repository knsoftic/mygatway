<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Dashboard (database) + email notification about a payment or payout status change.
 */
class TransactionStatusNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $object,
        public readonly string $publicId,
        public readonly string $title,
        public readonly string $message,
        public readonly string $url,
    ) {
        $this->onQueue('notifications');
    }

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->title.' - '.config('app.name'))
            ->line($this->message)
            ->action('View details', $this->url)
            ->line('You can change notification preferences in your account settings.');
    }

    /** @return array<string, string> */
    public function toArray(object $notifiable): array
    {
        return [
            'object' => $this->object,
            'id' => $this->publicId,
            'title' => $this->title,
            'message' => $this->message,
            'url' => $this->url,
        ];
    }
}
