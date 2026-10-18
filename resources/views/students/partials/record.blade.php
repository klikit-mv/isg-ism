{{-- The four-tab student record, used by staff, family and self pages. --}}
@php
    $isPending = $student->status === \App\Enums\StudentStatus::Pending;
@endphp

<div class="card mb-6 flex flex-col gap-4 sm:flex-row sm:items-center">
    @include('students.partials.avatar', ['student' => $student, 'size' => 'h-20 w-20 text-xl'])
    <div class="flex-1">
        <h1 class="text-2xl font-bold">{{ $student->name }}</h1>
        <div class="mt-1 flex flex-wrap items-center gap-2 text-sm text-gray-500 dark:text-gray-400">
            <span>{{ $student->index_number }}</span>
            <span>·</span>
            <span>{{ $student->national_id }}</span>
            <x-badge :value="$student->section"/>
            <x-badge :value="$student->status"/>
        </div>
    </div>
    @if ($context === 'students')
        <div class="flex flex-wrap gap-2">
            @if ($isPending && $viewer->can('verify', $student))
                <form method="POST" action="{{ route('students.verify', $student) }}">@csrf<button class="btn-accent">Verify</button></form>
                <x-confirm :action="route('students.reject', $student)" label="Decline" size="md" message="Decline this registration? The account stays inactive." confirm="Decline"/>
            @endif
            @can('update', $student)
                <a href="{{ route('students.edit', $student) }}" class="btn-secondary">Edit</a>
            @endcan
            @can('delete', $student)
                <x-confirm :action="route('students.destroy', $student)" method="DELETE" label="Delete" size="md" message="Delete {{ $student->name }}? Their history is kept, but the scout and account are removed from lists." confirm="Delete"/>
            @endcan
        </div>
    @endif
</div>

<nav class="mb-6 flex gap-1 overflow-x-auto border-b border-gray-200 dark:border-gray-700" aria-label="Record tabs">
    @foreach ($tabs as $key => $label)
        <a href="{{ $tabUrls[$key] }}" @class([
            '-mb-px whitespace-nowrap border-b-2 px-4 py-2 text-sm font-medium',
            'border-navy-600 text-navy-700 dark:border-navy-400 dark:text-navy-200' => $tab === $key,
            'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400' => $tab !== $key,
        ])>{{ $label }}</a>
    @endforeach
</nav>

@if ($tab === 'profile')
    <div class="grid gap-6 lg:grid-cols-3">
        <div class="card lg:col-span-2">
            <dl class="grid gap-4 sm:grid-cols-2">
                @foreach ([
                    'Email' => $student->email,
                    'Gender' => $student->gender?->label(),
                    'Date of birth' => scout_date($student->date_of_birth),
                    'Parent name' => $student->parent_name,
                    'Primary mobile' => $student->primary_mobile,
                    'Secondary mobile' => $student->secondary_mobile,
                    'Permanent address' => $student->permanent_address,
                    'Present address' => $student->present_address,
                    'Groups' => implode(', ', $groups),
                    'Linked parent' => $parent?->name,
                    'Verified' => $student->verified_at ? scout_datetime($student->verified_at) : null,
                ] as $label => $value)
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ $label }}</dt>
                        <dd class="mt-1 text-sm">{{ $value ?: '—' }}</dd>
                    </div>
                @endforeach
            </dl>
        </div>
        @if ($context === 'students' && $viewer->can('photo', $student))
            <div class="card space-y-3">
                <h2 class="font-semibold">Photo</h2>
                <form method="POST" action="{{ route('students.photo', $student) }}" enctype="multipart/form-data" class="space-y-3">
                    @csrf
                    <x-form.file-drop name="photo" accept="image/png,image/jpeg,image/webp" help="PNG, JPEG or WebP, up to 5 MB."/>
                    <button class="btn-primary btn-sm">Upload photo</button>
                </form>
                @if ($student->photo_path)
                    <form method="POST" action="{{ route('students.photo', $student) }}">@csrf<input type="hidden" name="remove" value="1"><button class="btn-secondary btn-sm">Remove photo</button></form>
                @endif
            </div>
        @endif
    </div>
@elseif ($tab === 'certificates')
    @if ($certificates->isEmpty())
        <x-empty message="No certificates yet."/>
    @else
        <x-table :headers="['Number', 'Certificate', 'Type', 'Awarded', 'Status', '']">
            @foreach ($certificates as $certificate)
                <tr>
                    <td data-label="Number" class="font-mono text-xs">{{ $certificate->cert_number }}</td>
                    <td data-label="Certificate">{{ $certificate->displayTitle() }}</td>
                    <td data-label="Type"><x-badge :value="$certificate->type"/></td>
                    <td data-label="Awarded">{{ scout_date($certificate->date_awarded) }}</td>
                    <td data-label="Status"><x-badge :value="$certificate->status"/></td>
                    <td class="whitespace-nowrap text-right">
                        @if (Route::has('certificates.show'))
                            <a href="{{ route('certificates.show', $certificate) }}" class="link">View</a>
                            <a href="{{ route('certificates.download', $certificate) }}" class="link ml-2">PDF</a>
                        @endif
                    </td>
                </tr>
            @endforeach
        </x-table>
        <div class="mt-4">{{ $certificates->links() }}</div>
    @endif
@elseif ($tab === 'badge-requests')
    @if (Route::has('badge-requests.create'))
        <div class="mb-4"><a href="{{ route('badge-requests.create', ['student' => $student->uuid]) }}" class="btn-primary btn-sm">Request a badge</a></div>
    @endif
    @if ($badgeRequests->isEmpty())
        <x-empty message="No badge requests yet."/>
    @else
        <x-table :headers="['Request', 'Badge', 'Status', 'Certificate', 'Requested']">
            @foreach ($badgeRequests as $request)
                <tr>
                    <td data-label="Request" class="font-mono text-xs">
                        @if (Route::has('badge-requests.show'))<a class="link" href="{{ route('badge-requests.show', $request) }}">{{ $request->request_id }}</a>@else{{ $request->request_id }}@endif
                    </td>
                    <td data-label="Badge">{{ $request->badge_name }}</td>
                    <td data-label="Status"><x-badge :value="$request->status"/></td>
                    <td data-label="Certificate" class="font-mono text-xs">{{ $request->certificate_number ?: '—' }}</td>
                    <td data-label="Requested">{{ scout_date($request->created_at) }}</td>
                </tr>
            @endforeach
        </x-table>
        <div class="mt-4">{{ $badgeRequests->links() }}</div>
    @endif
@elseif ($tab === 'leadership')
    @if ($leadershipRecords->isEmpty())
        <x-empty message="No records."/>
    @else
        <x-table :headers="['Patrol or six', 'Troop or group', 'Start', 'End', 'Certificate']">
            @foreach ($leadershipRecords as $record)
                <tr>
                    <td data-label="Patrol or six">
                        @if (Route::has('leadership.show'))<a class="link" href="{{ route('leadership.show', $record) }}">{{ $record->patrol_or_six }}</a>@else{{ $record->patrol_or_six }}@endif
                    </td>
                    <td data-label="Troop or group">{{ $record->troop_or_group }}</td>
                    <td data-label="Start">{{ scout_date($record->start_date) }}</td>
                    <td data-label="End">{{ scout_date($record->end_date) ?: '—' }}</td>
                    <td data-label="Certificate" class="font-mono text-xs">{{ $record->certificate?->cert_number ?? '—' }}</td>
                </tr>
            @endforeach
        </x-table>
        <div class="mt-4">{{ $leadershipRecords->links() }}</div>
    @endif
@endif
