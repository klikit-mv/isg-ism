<x-app-layout title="Notifications">
    <x-page-header title="Notifications"/>

    @if ($notifications->isEmpty())
        <x-empty message="You have no notifications."/>
    @else
        <div class="space-y-2">
            @foreach ($notifications as $notification)
                <a href="{{ route('notifications.show', $notification->id) }}" class="card block !p-4 hover:border-navy-300">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <div class="font-medium {{ $notification->read_at ? 'text-gray-600 dark:text-gray-300' : 'text-gray-900 dark:text-white' }}">
                                @unless ($notification->read_at)<span class="mr-1 inline-block h-2 w-2 rounded-full bg-gold-500"></span>@endunless
                                {{ $notification->data['title'] ?? 'Notification' }}
                            </div>
                            <div class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $notification->data['body'] ?? '' }}</div>
                        </div>
                        <div class="shrink-0 text-xs text-gray-400">{{ scout_datetime($notification->created_at) }}</div>
                    </div>
                </a>
            @endforeach
        </div>
        <div class="mt-4">{{ $notifications->links() }}</div>
    @endif
</x-app-layout>
