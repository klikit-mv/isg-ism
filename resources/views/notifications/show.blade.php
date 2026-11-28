<x-app-layout title="Notification">
    <x-page-header :title="$notification->data['title'] ?? 'Notification'" :description="scout_datetime($notification->created_at)"/>
    <div class="card space-y-4">
        <p class="whitespace-pre-line text-gray-700 dark:text-gray-200">{{ $notification->data['body'] ?? '' }}</p>
        <div class="flex gap-2">
            @if (! empty($notification->data['url']))
                <form method="POST" action="{{ route('notifications.read', $notification->id) }}">
                    @csrf
                    <button type="submit" class="btn-primary">Open</button>
                </form>
            @endif
            <a href="{{ route('notifications.index') }}" class="btn-secondary">All notifications</a>
        </div>
    </div>
</x-app-layout>
