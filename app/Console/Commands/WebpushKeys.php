<?php

namespace App\Console\Commands;

use App\Services\WebPushService;
use Illuminate\Console\Command;
use Throwable;

class WebpushKeys extends Command
{
    protected $signature = 'webpush:keys';

    protected $description = 'Create a VAPID key pair for push notifications to put in .env';

    public function handle(WebPushService $push): int
    {
        try {
            $pair = $push->generateKeys();
        } catch (Throwable $e) {
            $this->error('This PHP cannot create the keys: '.$e->getMessage());
            $this->line('Run this command on another machine (for example the server), or fix the OpenSSL configuration (openssl.cnf) of this PHP.');

            return self::FAILURE;
        }

        $this->line('VAPID_PUBLIC_KEY='.$pair['publicKey']);
        $this->line('VAPID_PRIVATE_KEY='.$pair['privateKey']);
        $this->info('Add both lines to .env, then run php artisan config:clear.');

        return self::SUCCESS;
    }
}
