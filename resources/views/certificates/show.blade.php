<x-app-layout :title="$certificate->cert_number">
    <x-page-header :title="$certificate->displayTitle()" :description="$certificate->cert_number.' · '.$certificate->student_name">
        <x-slot:actions>
            <a href="{{ route('certificates.download', $certificate) }}" class="btn-primary">Download PDF</a>
            @can('manage', $certificate)
                <form method="POST" action="{{ route('certificates.regenerate', $certificate) }}">@csrf<button class="btn-secondary">Regenerate</button></form>
                @if ($certificate->status === \App\Enums\CertificateStatus::Issued)
                    <x-confirm :action="route('certificates.sign', $certificate)" label="Verify and sign" variant="accent" size="md" title="Sign certificate" message="Your signature will be added to the PDF and the certificate marked verified." confirm="Sign"/>
                @endif
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="card lg:col-span-2 !p-0 overflow-hidden">
            <iframe src="{{ route('certificates.preview', $certificate) }}" title="Certificate preview" class="h-[480px] w-full bg-white" sandbox></iframe>
        </div>
        <div class="card">
            <dl class="space-y-3 text-sm">
                <div><dt class="text-gray-500">Type</dt><dd><x-badge :value="$certificate->type"/></dd></div>
                <div><dt class="text-gray-500">Status</dt><dd><x-badge :value="$certificate->status"/></dd></div>
                <div><dt class="text-gray-500">Scout</dt><dd>{{ $certificate->student_name }}</dd></div>
                <div><dt class="text-gray-500">Awarded</dt><dd>{{ scout_long_date($certificate->date_awarded) }}</dd></div>
                @if ($certificate->activity)<div><dt class="text-gray-500">Activity</dt><dd>{{ $certificate->activity->name }}</dd></div>@endif
                @if ($certificate->verifier)<div><dt class="text-gray-500">Signed by</dt><dd>{{ $certificate->verifier->name }} · {{ scout_datetime($certificate->verified_at) }}</dd></div>@endif
                <div><dt class="text-gray-500">Generated</dt><dd>{{ scout_datetime($certificate->generated_at) }}</dd></div>
                <div><dt class="text-gray-500">Public check</dt><dd><a class="link" href="{{ route('certificates.verify', ['cert_number' => $certificate->cert_number]) }}">Verification page</a></dd></div>
            </dl>
        </div>
    </div>
</x-app-layout>
