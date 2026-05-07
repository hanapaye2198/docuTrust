@php
    $docutrustLogoDefault = asset('images/docutrust-logo.png');
    $docutrustLogoLight = file_exists(public_path('images/docutrust-logo-light.png'))
        ? asset('images/docutrust-logo-light.png')
        : null;
@endphp
<span {{ $attributes->class(['docutrust-logo-tile']) }}>
    @if ($docutrustLogoLight)
        <picture>
            <source media="(prefers-color-scheme: light)" srcset="{{ $docutrustLogoLight }}">
            <img
                src="{{ $docutrustLogoDefault }}"
                alt="{{ config('app.name', 'DocuTrust') }} logo"
            />
        </picture>
    @else
        <img
            src="{{ $docutrustLogoDefault }}"
            alt="{{ config('app.name', 'DocuTrust') }} logo"
        />
    @endif
</span>
