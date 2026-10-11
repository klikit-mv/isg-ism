<?php

namespace App\Livewire;

use App\Models\Student;
use App\Services\ParentLinkService;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Livewire\Component;

/**
 * Live child lookup on the public parent registration form.
 */
class ParentChildrenLookup extends Component
{
    public string $query = '';

    /** @var list<array{national_id: string, name: string, section: string}> */
    public array $children = [];

    public ?string $message = null;

    /**
     * @param  list<string>  $initial
     */
    public function mount(array $initial = []): void
    {
        foreach ($initial as $nationalId) {
            $this->query = (string) $nationalId;
            $this->lookup();
        }

        $this->query = '';
        $this->message = null;
    }

    public function updatedQuery(): void
    {
        $this->lookup();
    }

    public function lookup(): void
    {
        $nationalId = Str::upper(trim($this->query));
        $this->message = null;

        if (strlen($nationalId) < 4) {
            return;
        }

        $key = 'child-lookup:'.request()->ip();

        if (RateLimiter::tooManyAttempts($key, 40)) {
            $this->message = 'Too many lookups. Please wait a minute and try again.';

            return;
        }

        RateLimiter::hit($key, 60);

        $student = Student::query()->where('national_id', $nationalId)->first();

        if ($student === null) {
            if (strlen($nationalId) >= 6) {
                $this->message = 'No scout matches that National ID.';
            }

            return;
        }

        if (collect($this->children)->contains('national_id', $student->national_id)) {
            $this->message = 'This scout is already in your list.';

            return;
        }

        if (app(ParentLinkService::class)->activeLinkFor($student) !== null) {
            $this->message = 'This scout is already added under another parent.';

            return;
        }

        $this->children[] = [
            'national_id' => $student->national_id,
            'name' => $student->name,
            'section' => (string) $student->section?->value,
        ];
        $this->query = '';
    }

    public function remove(string $nationalId): void
    {
        $this->children = array_values(array_filter($this->children, fn ($c) => $c['national_id'] !== $nationalId));
    }

    public function render(): View
    {
        return view('livewire.parent-children-lookup');
    }
}
