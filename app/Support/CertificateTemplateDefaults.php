<?php

namespace App\Support;

use App\Enums\CertificateType;

/**
 * Built-in A4 landscape HTML layouts used by the dompdf fallback renderer.
 */
final class CertificateTemplateDefaults
{
    public static function for(CertificateType $type): string
    {
        [$heading, $body] = match ($type) {
            CertificateType::Badge => [
                'Certificate of Proficiency',
                '<p class="lead">This is to certify that</p>
                <p class="name">{{name}}</p>
                <p class="lead">has successfully completed the requirements for the</p>
                <p class="title">{{badge}}</p>
                <p class="lead">awarded on {{date}}</p>',
            ],
            CertificateType::General => [
                'Certificate',
                '<p class="lead">This certificate is proudly presented to</p>
                <p class="name">{{name}}</p>
                <p class="small">ID card no. {{id_card_no}}</p>
                <p class="title">{{title}}</p>
                <p class="lead">awarded on {{date}}</p>',
            ],
            CertificateType::Leadership => [
                'Certificate of Leadership',
                '<p class="lead">This is to certify that</p>
                <p class="name">{{name}}</p>
                <p class="lead">is hereby appointed to function as</p>
                <p class="title">{{post}}</p>
                <p class="lead">of {{patrol_or_six}} in {{troop_or_group}}</p>
                <p class="lead">from {{start_date}}</p>',
            ],
        };

        return <<<HTML
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
    @page { size: A4 landscape; margin: 0; }
    body { margin: 0; font-family: 'DejaVu Sans', sans-serif; color: #2e1065; }
    .frame { position: absolute; top: 18px; left: 18px; right: 18px; bottom: 18px; border: 10px solid #6b21a8; }
    .inner { position: absolute; top: 34px; left: 34px; right: 34px; bottom: 34px; border: 2px solid #10b981; text-align: center; }
    .logo { margin-top: 26px; height: 70px; }
    .org { font-size: 15px; letter-spacing: 3px; text-transform: uppercase; color: #6b21a8; margin: 6px 0 0; }
    h1 { font-size: 40px; margin: 10px 0 6px; color: #3b0764; }
    .lead { font-size: 16px; margin: 8px 0; }
    .name { font-size: 34px; font-weight: bold; margin: 8px 0; color: #065f46; }
    .title { font-size: 24px; font-weight: bold; margin: 8px 0; }
    .small { font-size: 12px; margin: 2px 0; color: #6b7280; }
    .footer { position: absolute; bottom: 30px; left: 60px; right: 60px; }
    .footer td { width: 50%; vertical-align: bottom; font-size: 12px; }
    .sig { height: 55px; }
    .line { border-top: 1px solid #3b0764; padding-top: 4px; margin: 0 40px; }
    .certno { font-size: 12px; color: #6b7280; }
</style>
</head>
<body>
<div class="frame"></div>
<div class="inner">
    <img class="logo" src="{{logo}}" alt="">
    <p class="org">{{organisation}}</p>
    <h1>{$heading}</h1>
    {$body}
    <table class="footer">
        <tr>
            <td style="text-align:left"><span class="certno">Certificate no. {{certno}}</span></td>
        </tr>
    </table>
</div>
</body>
</html>
HTML;
    }

    /**
     * The organisation logo as a data URI.
     */
    public static function logoDataUri(): string
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48" width="96" height="96"><circle cx="24" cy="24" r="23" fill="#6b21a8"/><path d="M24 8c2.5 4 6 6.5 6 11.5 0 3.5-2.2 6-4.5 7.2L28 38h-8l2.5-11.3C20.2 25.5 18 23 18 19.5 18 14.5 21.5 12 24 8z" fill="#34d399"/></svg>';

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }
}
