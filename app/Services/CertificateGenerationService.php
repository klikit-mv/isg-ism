<?php

namespace App\Services;

use App\Enums\CertificateStatus;
use App\Enums\CertificateType;
use App\Exceptions\ScoutException;
use App\Models\Activity;
use App\Models\Badge;
use App\Models\Certificate;
use App\Models\CertificateTemplate;
use App\Models\LeadershipRecord;
use App\Models\Student;
use App\Models\User;
use App\Services\Certificates\CertificateDocumentRenderer;
use App\Services\Google\GoogleSlideExporter;
use App\Support\CertificateTemplateDefaults;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * One pipeline for every certificate: template → number → placeholders →
 * PDF (Slides, else dompdf) → storage → certificates row.
 */
class CertificateGenerationService
{
    public const RAW_PLACEHOLDERS = ['logo', 'signature'];

    public function __construct(
        private CertificateNumberService $numbers,
        private CertificateDocumentRenderer $renderer,
        private GoogleSlideExporter $slides,
        private GoogleDriveCertificateService $storage,
        private AuditLogService $audit,
        private SettingsService $settings,
    ) {}

    public function activeTemplate(CertificateType $type): ?CertificateTemplate
    {
        return CertificateTemplate::query()->where('type', $type->value)->where('active', true)->orderBy('id')->first();
    }

    public function generateBadgeCertificate(Student $student, Badge $badge, CarbonInterface|string $date, ?CertificateTemplate $template, User $actor, ?int $badgeRequestId = null): Certificate
    {
        $template ??= $badge->certificateTemplate?->active ? $badge->certificateTemplate : $this->activeTemplate(CertificateType::Badge);
        $this->assertTemplate($template, CertificateType::Badge);

        return $this->issue(CertificateType::Badge, $student, $template, $actor, fn () => [
            'cert_number' => $this->numbers->nextBadgeNumber($badge),
            'date_awarded' => Carbon::parse($date)->toDateString(),
            'badge_id' => $badge->id,
            'badge_name' => $badge->name,
            'title' => $badge->name,
            'badge_request_id' => $badgeRequestId,
        ]);
    }

    public function generateGeneralCertificate(Student $student, string $title, CarbonInterface|string $date, ?CertificateTemplate $template, User $actor, ?Activity $activity = null): Certificate
    {
        $template ??= $activity?->certificateTemplate;

        if ($template === null) {
            throw new ScoutException('Choose a general certificate template.');
        }

        $this->assertTemplate($template, CertificateType::General);

        if (trim($title) === '') {
            throw new ScoutException('A general certificate needs a title.');
        }

        return $this->issue(CertificateType::General, $student, $template, $actor, fn () => [
            'cert_number' => $this->numbers->nextGeneralNumber(),
            'date_awarded' => Carbon::parse($date)->toDateString(),
            'title' => $title,
            'activity_id' => $activity?->id,
        ]);
    }

    /**
     * First time: a new LEAD number. Again: refresh the same certificate and number.
     */
    public function generateLeadershipCertificate(LeadershipRecord $record, User $actor, ?CertificateTemplate $template = null): Certificate
    {
        $template ??= $this->activeTemplate(CertificateType::Leadership);
        $this->assertTemplate($template, CertificateType::Leadership);
        $student = $record->student;

        if ($record->certificate) {
            $certificate = $record->certificate;
            $certificate->update([
                'student_name' => $student->name,
                'id_card_no' => $student->national_id,
                'date_awarded' => $record->start_date->toDateString(),
                'template_id' => $template?->id,
            ]);

            return $this->regenerate($certificate, $actor);
        }

        $certificate = $this->issue(CertificateType::Leadership, $student, $template, $actor, fn () => [
            'cert_number' => $this->numbers->nextLeadershipNumber(),
            'date_awarded' => $record->start_date->toDateString(),
            'title' => 'Leadership — '.$record->patrol_or_six,
        ], function (Certificate $certificate) use ($record): void {
            $record->update(['certificate_id' => $certificate->id]);
        });

        return $certificate;
    }

    /**
     * Re-render under the same number with the current template.
     */
    public function regenerate(Certificate $certificate, User $actor): Certificate
    {
        $template = $certificate->template?->active ? $certificate->template : $this->activeTemplate($certificate->type);
        $certificate->template_id = $template?->id;
        $pdf = $this->renderPdf($certificate, $template);
        $path = $this->storage->put($certificate->student, $certificate->cert_number, $pdf, $certificate->path);

        $certificate->forceFill(['path' => $path, 'generated_by' => $actor->id, 'generated_at' => now()])->save();
        $this->audit->record('certificate.regenerated', $certificate, ['cert_number' => $certificate->cert_number], $actor);

        return $certificate;
    }

    public function previewHtml(Certificate $certificate): string
    {
        $template = $certificate->template ?? $this->activeTemplate($certificate->type);

        return $this->fillTemplate($this->htmlFor($template, $certificate->type), $this->valuesFor($certificate));
    }

    public function previewTemplate(CertificateTemplate $template): string
    {
        $sample = new Certificate([
            'type' => $template->type,
            'student_name' => 'Aishath Sample',
            'title' => 'Sample Achievement',
            'badge_name' => 'Sample Badge',
            'cert_number' => match ($template->type) {
                CertificateType::Leadership => 'FLHSG-LEAD-'.date('Y').'-001',
                CertificateType::General => 'FLHSG-CERT-'.date('Y').'-001',
                default => 'FLHSG-PB-'.date('Y').'-001',
            },
            'id_card_no' => 'A000000',
            'date_awarded' => now()->toDateString(),
        ]);

        $values = $this->valuesFor($sample);
        $values['post'] = 'Patrol Leader';
        $values['patrol_or_six'] = $values['patrol'] = 'Eagle Patrol';
        $values['troop_or_group'] = (string) config('scout.organisation');
        $values['start_date'] = scout_long_date(now());

        return $this->fillTemplate($this->htmlFor($template, $template->type), $values);
    }

    /**
     * @return array<string, string>
     */
    public function valuesFor(Certificate $certificate): array
    {
        $record = $certificate->exists && $certificate->type === CertificateType::Leadership
            ? LeadershipRecord::query()->where('certificate_id', $certificate->id)->first()
            : null;

        return [
            'name' => (string) $certificate->student_name,
            'badge' => (string) $certificate->badge_name,
            'certno' => (string) $certificate->cert_number,
            'date' => scout_long_date($certificate->date_awarded),
            'id_card_no' => (string) $certificate->id_card_no,
            'title' => (string) $certificate->title,
            'post' => (string) $record?->post,
            'patrol_or_six' => (string) $record?->patrol_or_six,
            'patrol' => (string) $record?->patrol_or_six,
            'troop_or_group' => (string) $record?->troop_or_group,
            'start_date' => $record ? scout_long_date($record->start_date) : '',
            'organisation' => (string) config('scout.organisation'),
            'logo' => $this->settings->logoDataUri() ?? CertificateTemplateDefaults::logoDataUri(),
            'signature' => '',
            'verifier' => '',
            'verified_at' => '',
        ];
    }

    /**
     * @param  array<string, string>  $values
     */
    public function fillTemplate(string $html, array $values): string
    {
        return $this->mergePlaceholders($html, $values);
    }

    /**
     * @param  array<string, string>  $values
     */
    public function mergePlaceholders(string $html, array $values): string
    {
        $replacements = [];

        foreach ($values as $key => $value) {
            $replacements['{{'.$key.'}}'] = in_array($key, self::RAW_PLACEHOLDERS, true) ? $value : e($value);
        }

        return strtr($html, $replacements);
    }

    /**
     * @param  callable(): array<string, mixed>  $attributes
     * @param  (callable(Certificate): void)|null  $after
     */
    private function issue(CertificateType $type, Student $student, ?CertificateTemplate $template, User $actor, callable $attributes, ?callable $after = null): Certificate
    {
        return DB::transaction(function () use ($type, $student, $template, $actor, $attributes, $after): Certificate {
            $certificate = Certificate::query()->create($attributes() + [
                'cert_id' => 'C'.strtoupper(Str::random(8)),
                'type' => $type,
                'student_id' => $student->id,
                'student_name' => $student->name,
                'id_card_no' => $student->national_id,
                'status' => CertificateStatus::Issued,
                'template_id' => $template?->id,
                'created_by' => $actor->id,
            ]);

            if ($after) {
                $after($certificate);
            }

            $pdf = $this->renderPdf($certificate, $template);
            $path = $this->storage->put($student, $certificate->cert_number, $pdf);
            $certificate->forceFill(['path' => $path, 'generated_by' => $actor->id, 'generated_at' => now()])->save();

            $this->audit->record('certificate.issued', $certificate, ['type' => $type, 'cert_number' => $certificate->cert_number], $actor);

            return $certificate;
        });
    }

    private function renderPdf(Certificate $certificate, ?CertificateTemplate $template): string
    {
        $values = $this->valuesFor($certificate);

        if ($template?->usesGoogleSlides()) {
            $text = [];

            foreach ($values as $key => $value) {
                if (! in_array($key, self::RAW_PLACEHOLDERS, true)) {
                    foreach ($this->slidePlaceholders($key, $certificate->type === CertificateType::Leadership) as $placeholder) {
                        $text[$placeholder] = $value;
                    }
                }
            }

            $pdf = $this->slides->exportPdf((string) $template->google_slide_id, $text);

            if ($pdf !== null && str_starts_with($pdf, '%PDF')) {
                return $pdf;
            }

            session()->flash('warning', 'Google Slides could not make this certificate, so the built-in layout was used. '.$this->slides->lastError());
        }

        return $this->renderer->render($this->fillTemplate($this->htmlFor($template, $certificate->type), $values));
    }

    /**
     * The ways a value can be written in a Slides template: {{name}}, or the angle-bracket style many templates use
     * (<Name>, <name>, <post>, <start date>…).
     *
     * @return list<string>
     */
    private function slidePlaceholders(string $key, bool $leadership): array
    {
        $words = str_replace('_', ' ', $key);
        $variants = [$key, $words, str_replace(' ', '', $words), ucfirst($key), ucfirst($words), ucwords($words), strtoupper($words)];

        if ($leadership && $key === 'start_date') {
            $variants[] = 'date';
            $variants[] = 'Date';
        }

        $placeholders = ['{{'.$key.'}}'];

        foreach (array_unique($variants) as $variant) {
            $placeholders[] = '<'.$variant.'>';
        }

        return $placeholders;
    }

    private function htmlFor(?CertificateTemplate $template, CertificateType $type): string
    {
        return filled($template?->template_content) ? (string) $template->template_content : CertificateTemplateDefaults::for($type);
    }

    private function assertTemplate(?CertificateTemplate $template, CertificateType $type): void
    {
        if ($template === null) {
            return;
        }

        if (! $template->active) {
            throw new ScoutException('That certificate template is not active.');
        }

        if ($template->type !== $type) {
            throw new ScoutException("That template is for {$template->type->value} certificates, not {$type->value}.");
        }
    }
}
