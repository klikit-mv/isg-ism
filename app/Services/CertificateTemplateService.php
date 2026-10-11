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
use Illuminate\Support\Facades\DB;
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
                'google_slide_id' => $this->slideId($data['google_slide'] ?? null, $type),
                'template_content' => $this->defaultContent($type),
                'active' => $data['active'] ?? true,
            ]);

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
                'google_slide_id' => $this->slideId($data['google_slide'] ?? null, $type),
                'template_content' => $this->defaultContent($type),
                'active' => $data['active'] ?? $template->active,
            ]);

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
