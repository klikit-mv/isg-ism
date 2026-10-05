<?php

namespace App\Http\Controllers;

use App\Enums\CertificateType;
use App\Enums\ScoutSection;
use App\Models\Badge;
use App\Models\CertificateTemplate;
use App\Services\BadgeService;
use App\Services\CertificateNumberService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class BadgeController extends Controller
{
    public function __construct(private BadgeService $badges, private CertificateNumberService $numbers) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Badge::class);

        return view('badges.index', [
            'badges' => $this->badges->paginate($request->only('q', 'section')),
            'numbers' => $this->numbers,
            'sectionCodes' => collect(ScoutSection::cases())->mapWithKeys(fn (ScoutSection $s) => [$s->value => app(\App\Services\SettingsService::class)->sectionBadgeCode($s)])->all(),
            'templates' => $this->templateOptions(),
        ]);
    }

    public function sectionCodes(Request $request, \App\Services\SettingsService $settings): RedirectResponse
    {
        $this->authorize('create', Badge::class);
        $rules = [];

        foreach (ScoutSection::cases() as $section) {
            $rules['codes.'.$section->value] = ['nullable', 'string', 'max:20', 'alpha_dash'];
        }

        $data = $request->validate($rules);

        foreach (ScoutSection::cases() as $section) {
            $code = strtoupper(trim((string) ($data['codes'][$section->value] ?? '')));
            $settings->set('badge_code_'.\Illuminate\Support\Str::slug($section->value, '_'), $code === '' ? null : $code, $request->user());
        }

        return back()->with('success', 'The section badge codes were saved. New certificate numbers use them.');
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Badge::class);
        $badge = $this->badges->create($this->validated($request), $request->user(), $request->file('image'));

        return back()->with('success', "The {$badge->name} badge was created.");
    }

    public function update(Request $request, Badge $badge): RedirectResponse
    {
        $this->authorize('update', $badge);
        $this->badges->update($badge, $this->validated($request, $badge), $request->user(), $request->file('image'));

        return back()->with('success', 'The badge was saved.');
    }

    public function destroy(Request $request, Badge $badge): RedirectResponse
    {
        $this->authorize('delete', $badge);
        $this->badges->delete($badge, $request->user());

        return back()->with('success', 'The badge was deleted.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Badge $badge = null): array
    {
        $request->merge(['code' => strtoupper(trim((string) $request->input('code')))]);

        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:50', Rule::unique('badges', 'code')->ignore($badge?->id)],
            'section' => [Rule::requiredIf(fn () => in_array(strtolower((string) $request->input('category', Badge::CATEGORY_PROFICIENCY)), ['', Badge::CATEGORY_PROFICIENCY], true)), 'nullable', Rule::enum(ScoutSection::class)],
            'description' => ['nullable', 'string', 'max:2000'],
            'category' => ['nullable', Rule::in(array_keys(Badge::CATEGORIES))],
            'certificate_template_id' => ['nullable', 'integer', Rule::exists('certificate_templates', 'id')->where('type', CertificateType::Badge->value)],
            'number_prefix' => ['nullable', 'string', 'max:20', 'alpha_dash'],
            'next_number' => ['nullable', 'integer', 'min:1', 'max:999999'],
            'image' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:5120'],
        ], [
            'section.required' => 'Proficiency badges need a section: their certificate numbers continue in one sequence for that section all year.',
        ]);
    }

    /**
     * @return array<int, string>
     */
    private function templateOptions(): array
    {
        return CertificateTemplate::query()->where('type', CertificateType::Badge->value)->orderBy('name')->pluck('name', 'id')->all();
    }
}
