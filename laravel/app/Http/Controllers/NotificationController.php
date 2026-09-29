<?php

namespace App\Http\Controllers;

use App\Support\Pagination;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function index(Request $request): View
    {
        return view('notifications.index', [
            'notifications' => $request->user()->notifications()->latest()->paginate(Pagination::MAX),
        ]);
    }

    public function show(Request $request, string $id): View
    {
        $notification = $request->user()->notifications()->findOrFail($id);
        $notification->markAsRead();

        return view('notifications.show', ['notification' => $notification]);
    }

    /**
     * Mark as read and follow the stored link.
     */
    public function read(Request $request, string $id): RedirectResponse
    {
        $notification = $request->user()->notifications()->findOrFail($id);
        $notification->markAsRead();

        $url = $notification->data['url'] ?? null;

        return $url && str_starts_with($url, url('/')) ? redirect()->to($url) : redirect()->route('notifications.index');
    }
}
