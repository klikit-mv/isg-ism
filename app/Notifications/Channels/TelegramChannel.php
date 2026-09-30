<?php

namespace App\Notifications\Channels;

use App\Models\User;
use App\Notifications\ScoutAlert;
use App\Services\TelegramService;

class TelegramChannel
{
    public function __construct(private TelegramService $telegram) {}

    public function send(User $notifiable, ScoutAlert $notification): void
    {
        if (! $notifiable->telegram_chat_id) {
            return;
        }

        $this->telegram->sendMessage($notifiable->telegram_chat_id, $notification->toTelegram($notifiable));
    }
}
