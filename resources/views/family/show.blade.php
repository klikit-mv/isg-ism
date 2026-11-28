<x-app-layout :title="$student->name">
    <div class="mb-4"><a href="{{ route('family.index') }}" class="link text-sm">&larr; My students</a></div>
    @include('students.partials.record')
</x-app-layout>
