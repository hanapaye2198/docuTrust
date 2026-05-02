@props(['current' => 'email'])

@php
    $steps = [
        ['key' => 'email', 'label' => '1. Email'],
        ['key' => 'phone', 'label' => '2. Phone'],
        ['key' => 'ekyc', 'label' => '3. eKYC'],
        ['key' => 'mfa', 'label' => '4. Security'],
    ];
    $currentIndex = collect($steps)->search(fn (array $step): bool => $step['key'] === $current);
    $currentIndex = $currentIndex === false ? 0 : $currentIndex;
@endphp

<nav class="mb-6 grid grid-cols-2 gap-2 text-xs sm:grid-cols-4 sm:gap-3">
    @foreach ($steps as $index => $step)
        @php
            $isCurrent = $index === $currentIndex;
            $isDone = $index < $currentIndex;
        @endphp
        <div @class([
            'rounded-lg border p-2 text-center transition',
            'border-[#2EC4B6] bg-[#2EC4B6] text-white' => $isCurrent,
            'border-emerald-300 bg-emerald-50 text-emerald-700' => $isDone && ! $isCurrent,
            'border-gray-200 bg-gray-100 text-[#1F2937]' => ! $isCurrent && ! $isDone,
        ])>
            {{ __($step['label']) }}
        </div>
    @endforeach
</nav>
