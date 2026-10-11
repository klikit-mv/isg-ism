<?php

namespace Tests\Feature\Certificates;

use App\Enums\CertificateType;
use App\Models\Badge;
use App\Models\CertificateTemplate;
use App\Support\CertificateTemplateDefaults;
use Illuminate\Support\Facades\Storage;

trait CertificateTestHelpers
{
    protected function setUpCertificates(): void
    {
        Storage::fake('certificates');
    }

    protected function template(CertificateType $type, array $attributes = []): CertificateTemplate
    {
        return CertificateTemplate::query()->create($attributes + [
            'template_id' => 'TPL-'.strtoupper(str()->random(6)),
            'name' => ucfirst($type->value).' template',
            'type' => $type,
            'google_slide_id' => 'local-'.$type->value,
            'template_content' => CertificateTemplateDefaults::for($type),
            'active' => true,
        ]);
    }

    protected function badge(array $attributes = []): Badge
    {
        return Badge::query()->create($attributes + [
            'badge_id' => 'B'.strtoupper(str()->random(5)),
            'name' => 'Camping',
            'code' => 'CAMP'.strtoupper(str()->random(3)),
            'section' => 'Scout',
            'category' => 'proficiency',
        ]);
    }
}
