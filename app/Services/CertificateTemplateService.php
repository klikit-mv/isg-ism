<?php

namespace App\Services;

use App\Enums\CertificateType;
use App\Exceptions\ScoutException;
use App\Models\Activity;
use App\Models\Badge;
use App\Models\CertificateTemplate;
use App\Models\User;
use App\Support\CertificateTemplateDefaults;
use App\Support\GoogleSlide;
use App\Support\Uploads;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CertificateTemplateService
{
    public function __construct(private AuditLogService $audit) {}

    public function defaultContent(CertificateType $type): string
    {
        return CertificateTemplateDefaults::for($type);
    }

    /**
     * @param  array{name: string, type: string, google_slide: ?string, activity_id?: ?int, active?: bool}  $data
     */
    public function create(array $data, User $actor): CertificateTemplate
    {
        return DB::transaction(function () use ($data, $actor): CertificateTemplate {
            $type = CertificateType::from($data['type']);
            $template = CertificateTemplate::query()->create([
                'template_id' => 'TPL-'.strtoupper(Str::random(6)),
                'name' => $data['name'],
                'type' => $type,
                'template_content' => $this->defaultContent($type),
                'active' => $data['active'] ?? true,
            ] + $this->sourceAttributes($data, $type, null));

            $this->linkActivity($template, $data['activity_id'] ?? null);
            $this->audit->record('certificate_template.created', $template, ['name' => $template->name, 'type' => $type], $actor);

            return $template;
        });
    }

    /**
     * @param  array{name: string, type: string, google_slide: ?string, activity_id?: ?int, active?: bool}  $data
     */
    public function update(CertificateTemplate $template, array $data, User $actor): CertificateTemplate
    {
        return DB::transaction(function () use ($template, $data, $actor): CertificateTemplate {
            $type = CertificateType::from($data['type']);
            $template->update([
                'name' => $data['name'],
                'type' => $type,
                'template_content' => $this->defaultContent($type),
                'active' => $data['active'] ?? $template->active,
            ] + $this->sourceAttributes($data, $type, $template));

            $this->linkActivity($template, $data['activity_id'] ?? null);
            $this->audit->record('certificate_template.updated', $template, ['name' => $template->name], $actor);

            return $template;
        });
    }

    public function setActive(CertificateTemplate $template, bool $active, User $actor): void
    {
        $template->update(['active' => $active]);
        $this->audit->record('certificate_template.'.($active ? 'activated' : 'deactivated'), $template, [], $actor);
    }

    public function delete(CertificateTemplate $template, User $actor): void
    {
        if ($template->certificates()->exists()) {
            throw new ScoutException('This template has been used for certificates and cannot be deleted. Deactivate it instead.');
        }

        DB::transaction(function () use ($template, $actor): void {
            Activity::query()->where('certificate_template_id', $template->id)->update(['certificate_template_id' => null]);
            Badge::query()->where('certificate_template_id', $template->id)->update(['certificate_template_id' => null]);
            $this->audit->record('certificate_template.deleted', $template, ['name' => $template->name], $actor);
            $template->delete();
        });
    }

    /**
     * Where the design comes from: the built-in layout, a Google Slides presentation, or an exported Canva image.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function sourceAttributes(array $data, CertificateType $type, ?CertificateTemplate $existing): array
    {
        $source = $data['source'] ?? (filled($data['google_slide'] ?? null) ? 'slides' : 'builtin');
        $keep = $existing?->background_path;
        $attributes = ['google_slide_id' => 'local-'.$type->value, 'canva_url' => null, 'background_path' => null];

        if ($source === 'slides') {
            $attributes['google_slide_id'] = $this->slideId($data['google_slide'] ?? null, $type);
        } elseif ($source === 'canva') {
            $upload = $data['background'] ?? null;

            if ($upload instanceof UploadedFile) {
                $extension = strtolower($upload->getClientOriginalExtension() ?: $upload->extension());
                $path = Uploads::store($upload, 'certificate-templates', Str::uuid().'.'.($extension === 'png' ? 'png' : 'jpg'), 'local');
                $attributes['background_path'] = $path;
            } else {
                $attributes['background_path'] = $keep;
            }

            if (blank($attributes['background_path'])) {
                throw new ScoutException('Upload the design exported from Canva (a PNG or JPG image).');
            }

            $attributes['canva_url'] = filled($data['canva_url'] ?? null) ? $data['canva_url'] : null;
            $attributes['text_top'] = (int) ($data['text_top'] ?? 38);
            $attributes['text_color'] = (string) ($data['text_color'] ?? '#1f2937');
        }

        if ($keep !== null && $keep !== ($attributes['background_path'] ?? null)) {
            Storage::disk('local')->delete($keep);
        }

        return $attributes;
    }

    private function slideId(?string $input, CertificateType $type): string
    {
        if (blank($input)) {
            return 'local-'.$type->value;
        }

        $id = GoogleSlide::idFrom($input);

        if ($id === null) {
            throw new ScoutException('That does not look like a Google Slides link or presentation ID.');
        }

        return $id;
    }

    /**
     * Linking a template to an activity also sets the activity's template and unlinks any other activity.
     */
    private function linkActivity(CertificateTemplate $template, ?int $activityId): void
    {
        if ($template->activity_id && $template->activity_id !== $activityId) {
            Activity::query()->whereKey($template->activity_id)->where('certificate_template_id', $template->id)->update(['certificate_template_id' => null]);
        }

        $template->update(['activity_id' => $activityId]);

        if ($activityId !== null) {
            CertificateTemplate::query()->where('activity_id', $activityId)->whereKeyNot($template->id)->update(['activity_id' => null]);
            Activity::query()->whereKey($activityId)->update(['certificate_template_id' => $template->id]);
        }
    }
}
