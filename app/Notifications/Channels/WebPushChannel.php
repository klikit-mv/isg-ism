<?php

namespace App\Notifications\Channels;

use App\Models\User;
use App\Notifications\ScoutAlert;
use App\Services\WebPushService;

class WebPushChannel
{
    public function __construct(private WebPushService $push) {}

    public function send(User $notifiable, ScoutAlert $notification): void
    {
        $this->push->send($notifiable, $notification->title, $notification->body, $notification->url);
    }
}
