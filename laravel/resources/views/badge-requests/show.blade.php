<x-app-layout :title="$badgeRequest->request_id">
    <x-page-header :title="$badgeRequest->badge_name" :description="$badgeRequest->request_id.' · '.$badgeRequest->student_name">
        <x-slot:actions><a href="{{ route('badge-requests.index') }}" class="btn-secondary">All requests</a></x-slot:actions>
    </x-page-header>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="card lg:col-span-2">
            <dl class="grid gap-4 text-sm sm:grid-cols-2">
                <div><dt class="text-gray-500">Status</dt><dd><x-badge :value="$badgeRequest->status"/></dd></div>
                <div><dt class="text-gray-500">Requested by</dt><dd>{{ $badgeRequest->requester?->name }} · {{ scout_datetime($badgeRequest->created_at) }}</dd></div>
                <div><dt class="text-gray-500">Reviewed by</dt><dd>{{ $badgeRequest->reviewer?->name ?? '—' }} {{ $badgeRequest->reviewed_at ? '· '.scout_datetime($badgeRequest->reviewed_at) : '' }}</dd></div>
                <div><dt class="text-gray-500">Review note</dt><dd>{{ $badgeRequest->review_note ?: '—' }}</dd></div>
                <div><dt class="text-gray-500">Certificate</dt><dd>
                    @if ($badgeRequest->certificate)
                        <a href="{{ route('certificates.show', $badgeRequest->certificate) }}" class="link font-mono">{{ $badgeRequest->certificate_number }}</a>
                    @else — @endif
                </dd></div>
                <div><dt class="text-gray-500">Date awarded</dt><dd>{{ scout_long_date($badgeRequest->date_awarded) ?: '—' }}</dd></div>
            </dl>
        </div>

        @can('decide', $badgeRequest)
            <div class="space-y-4">
                @if ($badgeRequest->status === \App\Enums\BadgeRequestStatus::Requested)
                    <form method="POST" action="{{ route('badge-requests.approve', $badgeRequest) }}" class="card space-y-3">
                        @csrf
                        <h2 class="font-semibold">Review</h2>
                        <x-form.input name="note" label="Note (optional)" id="approve-note"/>
                        <div class="flex gap-2">
                            <button class="btn-accent">Approve</button>
                            <button class="btn-danger" formaction="{{ route('badge-requests.reject', $badgeRequest) }}">Reject</button>
                        </div>
                    </form>
                @elseif ($badgeRequest->status === \App\Enums\BadgeRequestStatus::Approved)
                    <form method="POST" action="{{ route('badge-requests.generate', $badgeRequest) }}" class="card space-y-3">
                        @csrf
                        <h2 class="font-semibold">Generate certificate</h2>
                        <x-form.input name="date_awarded" label="Date awarded" type="date" :value="now()->toDateString()" required/>
                        <x-form.select name="template" label="Template" :options="$templates" placeholder="The badge's template (default)"/>
                        <button class="btn-primary">Generate</button>
                    </form>
                @endif
            </div>
        @endcan
    </div>
</x-app-layout>
