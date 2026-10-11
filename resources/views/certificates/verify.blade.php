<x-guest-layout title="Verify a certificate" width="lg">
    <h1 class="mb-1 text-xl font-bold">Verify a certificate</h1>
    <p class="mb-4 text-sm text-gray-500 dark:text-gray-400">Enter the certificate number printed on the certificate.</p>

    <form method="GET" action="{{ route('certificates.verify') }}" class="mb-6 flex gap-2">
        <input name="cert_number" value="{{ $number }}" class="input uppercase" placeholder="e.g. FLHSG-PB-2026-001" aria-label="Certificate number" required>
        <button class="btn-primary">Check</button>
    </form>

    @if ($number !== '')
        @if ($certificate)
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 dark:border-emerald-800 dark:bg-emerald-900/30" data-testid="valid">
                <p class="mb-3 font-semibold text-emerald-800 dark:text-emerald-200">This certificate is valid.</p>
                <dl class="grid gap-2 text-sm sm:grid-cols-2">
                    <div><dt class="text-gray-500">Number</dt><dd class="font-mono">{{ $certificate->cert_number }}</dd></div>
                    <div><dt class="text-gray-500">Name</dt><dd>{{ $certificate->student_name }}</dd></div>
                    @if ($showNationalId)<div><dt class="text-gray-500">National ID</dt><dd>{{ $certificate->id_card_no }}</dd></div>@endif
                    <div><dt class="text-gray-500">Type</dt><dd>{{ $certificate->type->label() }}</dd></div>
                    <div><dt class="text-gray-500">Title</dt><dd>{{ $certificate->displayTitle() }}</dd></div>
                    <div><dt class="text-gray-500">Awarded</dt><dd>{{ scout_long_date($certificate->date_awarded) }}</dd></div>
                    <div><dt class="text-gray-500">Status</dt><dd>{{ $certificate->status->label() }}</dd></div>
                </dl>
                <div class="mt-4 flex flex-wrap gap-2">
                    <a href="{{ route('certificates.verify.view', ['cert_number' => $certificate->cert_number]) }}" class="btn-secondary btn-sm" target="_blank" rel="noopener">View</a>
                    <a href="{{ route('certificates.verify.download', ['cert_number' => $certificate->cert_number]) }}" class="btn-secondary btn-sm">Download PDF</a>
                    @if ($canSign && $certificate->status === \App\Enums\CertificateStatus::Issued)
                        <form method="POST" action="{{ route('certificates.sign', $certificate) }}">@csrf<button class="btn-accent btn-sm">Verify and sign</button></form>
                    @endif
                </div>
            </div>
        @else
            <div class="rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800 dark:border-rose-800 dark:bg-rose-900/30 dark:text-rose-200" data-testid="unknown">
                We could not find a certificate with the number <span class="font-mono">{{ strtoupper($number) }}</span>.
            </div>
        @endif
    @endif

    <p class="mt-6 text-center text-sm"><a href="{{ route('login') }}" class="link">Back to sign in</a></p>
</x-guest-layout>
