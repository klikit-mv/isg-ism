<?php

namespace App\Notifications;

use App\Models\User;
use App\Notifications\Channels\TelegramChannel;
use App\Notifications\Channels\WebPushChannel;
use App\Services\TelegramService;
use App\Services\WebPushService;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ScoutAlert extends Notification
{
    public function __construct(
        public string $title,
        public string $body,
        public ?string $url = null,
    ) {}

    /**
     * @return list<string>
     */
    public function via(User $notifiable): array
    {
        $channels = ['database'];

        if ($notifiable->email_notifications_enabled && filled($notifiable->email)) {
            $channels[] = 'mail';
        }

        if ($notifiable->telegram_notifications_enabled && filled($notifiable->telegram_chat_id) && app(TelegramService::class)->configured()) {
            $channels[] = TelegramChannel::class;
        }

        if (app(WebPushService::class)->hasSubscriptions($notifiable)) {
            $channels[] = WebPushChannel::class;
        }

        return $channels;
    }

    public function toMail(User $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject($this->title.' — '.config('scout.short_name'))
            ->greeting('Hello '.$notifiable->name.',')
            ->line($this->body);

        if ($this->url) {
            $mail->action('Open the portal', $this->url);
        }

        return $mail;
    }

    public function toTelegram(User $notifiable): string
    {
        return trim('<b>'.e($this->title).'</b>'."\n".e($this->body).($this->url ? "\n".e($this->url) : ''));
    }

    /**
     * @return array{title: string, body: string, url: string|null}
     */
    public function toArray(User $notifiable): array
    {
        return ['title' => $this->title, 'body' => $this->body, 'url' => $this->url];
    }
}
