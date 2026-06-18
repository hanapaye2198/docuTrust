@php
    $marketingNavPrefix = $marketingNavPrefix ?? '';
    $docutrustLogoDefault = asset('images/docutrust-logo.png');
    $docutrustLogoLight = file_exists(public_path('images/docutrust-logo-light.png'))
        ? asset('images/docutrust-logo-light.png')
        : null;
@endphp

<div class="mobile-nav" id="mobileNav" role="dialog" aria-modal="true" aria-label="{{ __('Site menu') }}" hidden>
  <button type="button" class="mobile-nav-close" id="mobileNavClose" aria-label="{{ __('Close menu') }}">
    <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M18 6L6 18M6 6l12 12"/></svg>
  </button>
  <a href="{{ $marketingNavPrefix }}#features" onclick="closeMobileNav()">{{ __('Features') }}</a>
  <a href="{{ $marketingNavPrefix }}#blockchain" onclick="closeMobileNav()">{{ __('Security') }}</a>
  <a href="{{ $marketingNavPrefix }}#ai" onclick="closeMobileNav()">{{ __('AI Engine') }}</a>
  <a href="{{ $marketingNavPrefix }}#about" onclick="closeMobileNav()">{{ __('About') }}</a>
  <a href="{{ $marketingNavPrefix }}#showcase" onclick="closeMobileNav()">{{ __('Advertisement') }}</a>
  <a href="{{ $marketingNavPrefix }}#industries" onclick="closeMobileNav()">{{ __('Industries') }}</a>
  <a href="{{ $marketingNavPrefix }}#faq" onclick="closeMobileNav()">{{ __('FAQ') }}</a>
  <a href="{{ $secondaryHeaderUrl }}" onclick="closeMobileNav()">{{ $secondaryHeaderLabel }}</a>
  <a href="{{ $primaryCtaUrl }}" class="btn-cta" style="font-size:.9rem" onclick="closeMobileNav()">{{ $primaryCtaLabel }}</a>
</div>

<header>
  <div class="header-inner">
    <a href="{{ route('home') }}" class="logo">
      <span class="logo-mark">
        <img
          src="{{ $docutrustLogoLight ?? $docutrustLogoDefault }}"
          alt=""
          class="logo-img"
          data-logo-default="{{ $docutrustLogoDefault }}"
          @if ($docutrustLogoLight) data-logo-light="{{ $docutrustLogoLight }}" @endif
          width="40"
          height="40"
          loading="eager"
          decoding="async"
        >
      </span>
      <span class="logo-text">{{ config('app.name') }}</span>
    </a>
    <nav>
      <a href="{{ $marketingNavPrefix }}#features">{{ __('Features') }}</a>
      <a href="{{ $marketingNavPrefix }}#blockchain">{{ __('Security') }}</a>
      <a href="{{ $marketingNavPrefix }}#ai">{{ __('AI Engine') }}</a>
      <a href="{{ $marketingNavPrefix }}#about">{{ __('About') }}</a>
      <a href="{{ $marketingNavPrefix }}#showcase">{{ __('Advertisement') }}</a>
      <a href="{{ $marketingNavPrefix }}#industries">{{ __('Industries') }}</a>
      <a href="{{ $marketingNavPrefix }}#faq">{{ __('FAQ') }}</a>
    </nav>
    <div class="header-actions">
      <button
        type="button"
        id="docutrustThemeToggle"
        class="theme-toggle"
        aria-label="{{ __('Toggle theme') }}"
        title="{{ __('Switch to dark mode') }}"
      >
        <svg class="theme-toggle-icon theme-toggle-icon--moon" viewBox="0 0 20 20" aria-hidden="true">
          <path d="M17.293 13.293A8 8 0 016.707 2.707a8.001 8.001 0 1010.586 10.586z"></path>
        </svg>
        <svg class="theme-toggle-icon theme-toggle-icon--sun" viewBox="0 0 20 20" aria-hidden="true">
          <path fill-rule="evenodd" clip-rule="evenodd" d="M10 2a1 1 0 011 1v1a1 1 0 11-2 0V3a1 1 0 011-1zm4 8a4 4 0 11-8 0 4 4 0 018 0zm-.464 4.95l.707.707a1 1 0 001.414-1.414l-.707-.707a1 1 0 00-1.414 1.414zm2.12-10.607a1 1 0 010 1.414l-.706.707a1 1 0 11-1.414-1.414l.707-.707a1 1 0 011.414 0zM17 11a1 1 0 100-2h-1a1 1 0 100 2h1zm-7 4a1 1 0 011 1v1a1 1 0 11-2 0v-1a1 1 0 011-1zM5.05 6.464A1 1 0 106.465 5.05l-.708-.707a1 1 0 00-1.414 1.414l.707.707zm1.414 8.486l-.707.707a1 1 0 01-1.414-1.414l.707-.707a1 1 0 011.414 1.414zM4 11a1 1 0 100-2H3a1 1 0 000 2h1z"></path>
        </svg>
        <span id="docutrustThemeToggleLabel" class="theme-toggle-label">{{ __('Dark mode') }}</span>
      </button>
      <a href="{{ $secondaryHeaderUrl }}" class="btn-ghost">{{ $secondaryHeaderLabel }}</a>
      <a href="{{ $primaryCtaUrl }}" class="btn-primary">{{ $primaryCtaLabel }}</a>
      <button type="button" class="nav-mobile-toggle" id="mobileNavToggle" aria-label="{{ __('Open menu') }}" aria-expanded="false" aria-controls="mobileNav">
        <svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
      </button>
    </div>
  </div>
</header>
