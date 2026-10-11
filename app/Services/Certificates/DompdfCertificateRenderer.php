<?php

namespace App\Services\Certificates;

use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * Local HTML renderer: A4 landscape, remote assets off, DejaVu Sans.
 */
class DompdfCertificateRenderer implements CertificateDocumentRenderer
{
    public function render(string $html): string
    {
        $options = new Options;
        $options->setIsRemoteEnabled(false);
        $options->setDefaultFont('DejaVu Sans');
        $options->setIsHtml5ParserEnabled(true);
        $options->setChroot(base_path());
        $options->setTempDir(storage_path('framework/cache'));
        $options->setFontDir(storage_path('fonts'));
        $options->setFontCache(storage_path('fonts'));

        if (! is_dir(storage_path('fonts'))) {
            @mkdir(storage_path('fonts'), 0775, true);
        }

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();

        return (string) $dompdf->output();
    }
}
