<?php

namespace App\Http\Controllers;

use App\Services\CertificateGenerationService;
use App\Services\CertificateService;
use App\Services\LeaderScopeService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

/**
 * Public certificate verification (no sign-in).
 */
class CertificateVerificationController extends Controller
{
    public function __construct(private CertificateService $certificates) {}

    public function verify(Request $request, LeaderScopeService $scope): View
    {
        $number = trim((string) $request->query('cert_number'));
        $certificate = $number !== '' ? $this->certificates->findByNumber($number) : null;
        $user = $request->user();

        return view('certificates.verify', [
            'number' => $number,
            'certificate' => $certificate,
            'showNationalId' => $certificate && $user && $scope->canAccessStudent($user, $certificate->student),
            'canSign' => $certificate && $user && $user->can('manage', $certificate),
        ]);
    }

    public function view(Request $request, CertificateGenerationService $generator): Response
    {
        $certificate = $this->certificates->findByNumber((string) $request->query('cert_number'));
        abort_if($certificate === null, 404);

        return app(CertificateController::class)->previewResponse($certificate);
    }

    public function download(Request $request): Response
    {
        $certificate = $this->certificates->findByNumber((string) $request->query('cert_number'));
        abort_if($certificate === null, 404);

        return app(CertificateController::class)->pdfResponse($certificate);
    }
}
