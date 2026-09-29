<?php

namespace App\Services\Certificates;

interface CertificateDocumentRenderer
{
    /**
     * Render filled HTML to PDF bytes.
     */
    public function render(string $html): string;
}
