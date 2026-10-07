<?php

namespace App\Http\Controllers;

use App\Services\WebPushService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PushController extends Controller
{
    public function __construct(private WebPushService $push) {}

    public function key(): JsonResponse
    {
        $key = $this->push->publicKey();

        return $key ? response()->json(['key' => $key]) : response()->json(['message' => 'Push notifications are not available.'], 503);
    }

    public function subscribe(Request $request): JsonResponse
    {
        $data = $request->validate([
            'endpoint' => ['required', 'url', 'starts_with:https://', 'max:2000'],
            'keys.p256dh' => ['required', 'string', 'max:255'],
            'keys.auth' => ['required', 'string', 'max:255'],
            'contentEncoding' => ['nullable', 'in:aes128gcm,aesgcm'],
        ]);

        $this->push->subscribe($request->user(), $data, $request->userAgent());

        return response()->json(['subscribed' => true]);
    }

    public function unsubscribe(Request $request): JsonResponse
    {
        $data = $request->validate(['endpoint' => ['required', 'url', 'max:2000']]);
        $this->push->unsubscribe($request->user(), $data['endpoint']);

        return response()->json(['subscribed' => false]);
    }

    public function test(Request $request): JsonResponse
    {
        $reached = $this->push->send($request->user(), 'Test notification', 'Push notifications are working on this device.', route('dashboard'));

        return response()->json(['sent' => $reached]);
    }
}
