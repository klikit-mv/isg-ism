<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

class ActivityService
{
    public function __construct(
        private ActivityRosterService $roster,
        private SettingsService $settings,
        private AuditLogService $audit,
        private NotificationService $notifications,
    ) {}

    /**
     * @param  array{name: string, date: string, details?: ?string, all_students?: bool, sections?: list<string>, groups?: list<int>, charge_fee?: bool, fee_amount?: ?string, certificate_template_id?: ?int}  $data
     */
    public function create(array $data, User $actor): Activity
    {
        $activity = DB::transaction(function () use ($data, $actor): Activity {
            $activity = Activity::query()->create($this->attributes($data) + ['created_by' => $actor->id]);
            $activity->syncSections($data['sections'] ?? []);
            $activity->groups()->sync($data['groups'] ?? []);
            $this->linkTemplate($activity, $data['certificate_template_id'] ?? null);

            $this->audit->record('activity.created', $activity, ['name' => $activity->name, 'date' => $activity->date->toDateString()], $actor);

            return $activity;
        });

        $this->notifications->activityCreated($activity, $this->roster->resolveStudentIds($activity), $actor);

        return $activity;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Activity $activity, array $data, User $actor): Activity
    {
        return DB::transaction(function () use ($activity, $data, $actor): Activity {
            $activity->update($this->attributes($data));
            $activity->syncSections($data['sections'] ?? []);
            $activity->groups()->sync($data['groups'] ?? []);
            $this->linkTemplate($activity, $data['certificate_template_id'] ?? null);

            $this->audit->record('activity.updated', $activity, ['name' => $activity->name], $actor);

            return $activity;
        });
    }

    public function delete(Activity $activity, User $actor): void
    {
        $this->audit->record('activity.deleted', $activity, ['name' => $activity->name], $actor);
        $activity->delete();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function attributes(array $data): array
    {
        $charge = (bool) ($data['charge_fee'] ?? false);

        return [
            'name' => $data['name'],
            'date' => $data['date'],
            'details' => $data['details'] ?? null,
            'all_students' => (bool) ($data['all_students'] ?? false),
            'charge_fee' => $charge,
            'fee_amount' => $charge ? Money::normalize(filled($data['fee_amount'] ?? null) ? $data['fee_amount'] : $this->settings->defaultClassFee()) : null,
            'certificate_template_id' => $data['certificate_template_id'] ?? null,
        ];
    }

    private function linkTemplate(Activity $activity, ?int $templateId): void
    {
        if ($templateId === null) {
            DB::table('certificate_templates')->where('activity_id', $activity->id)->update(['activity_id' => null]);

            return;
        }

        DB::table('certificate_templates')->where('activity_id', $activity->id)->where('id', '!=', $templateId)->update(['activity_id' => null]);
        DB::table('certificate_templates')->where('id', $templateId)->update(['activity_id' => $activity->id]);
    }
}
