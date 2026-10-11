{{-- Scout fields shared by public registration and enrolment. --}}
@php $student ??= new \App\Models\Student; @endphp
<div class="grid gap-4 sm:grid-cols-2">
    <x-form.input name="name" label="Full name" :value="$student->name" required/>
    <x-form.input name="index_number" label="Index number" :value="$student->index_number" required/>
    <x-form.input name="national_id" label="National ID" :value="$student->national_id" required class="uppercase"/>
    <x-form.input name="email" label="Email{{ app(\App\Services\SettingsService::class)->studentEmailRequired() ? '' : ' (optional)' }}" type="email" :value="$student->email" :required="app(\App\Services\SettingsService::class)->studentEmailRequired()" help="A welcome email with the sign-in details is sent to this address."/>
    <x-form.select name="gender" label="Gender" :options="\App\Enums\Gender::options()" :value="$student->gender" placeholder="Choose" required/>
    <x-form.input name="date_of_birth" label="Date of birth" type="date" :value="$student->date_of_birth?->format('Y-m-d')" required/>
    <x-form.select name="section" label="Section" :options="\App\Enums\ScoutSection::options()" :value="$student->section" placeholder="Choose" required/>
    <x-form.input name="parent_name" label="Parent name" :value="$student->parent_name" required/>
    <x-form.input name="primary_mobile" label="Primary mobile" :value="$student->primary_mobile" required/>
    <x-form.input name="secondary_mobile" label="Secondary mobile (optional)" :value="$student->secondary_mobile"/>
    <x-form.input name="permanent_address" label="Permanent address" :value="$student->permanent_address" required/>
    <x-form.input name="present_address" label="Present address" :value="$student->present_address" required/>
</div>
