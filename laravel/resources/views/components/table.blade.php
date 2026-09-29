@props(['headers' => []])

<div class="md:overflow-hidden md:rounded-xl md:border md:border-gray-200 md:bg-white md:shadow-sm md:dark:border-gray-700 md:dark:bg-gray-800">
    <div class="md:overflow-x-auto">
        <table {{ $attributes->merge(['class' => 'rtable']) }}>
            @if ($headers)
                <thead>
                    <tr>
                        @foreach ($headers as $header)
                            <th scope="col">{{ $header }}</th>
                        @endforeach
                    </tr>
                </thead>
            @endif
            <tbody>
                {{ $slot }}
            </tbody>
        </table>
    </div>
</div>
