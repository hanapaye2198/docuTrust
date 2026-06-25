@extends('layouts.marketing')

@section('title', $feature['title'])

@section('content')
  <div class="container">
    <div class="feature-detail-hero">
      <a href="{{ route('home') }}#features" class="marketing-back">{{ __('← Back to features') }}</a>
      <div class="feature-detail-icon" style="margin-top:24px">
        @include('features.partials.icon', ['icon' => $feature['icon']])
      </div>
      <div class="feature-detail-label">{{ __('Feature') }}</div>
      <h1 class="feature-detail-title">{{ $feature['title'] }}</h1>
      <p class="feature-detail-summary">{{ $feature['summary'] }}</p>
      <div class="feature-detail-actions">
        <a href="{{ auth()->check() ? route(auth()->user()->homeRouteName()) : route('register') }}" class="btn-cta">
          {{ auth()->check() ? __('Open workspace') : __('Start Free Trial') }}
        </a>
        <a href="{{ route('home') }}#features" class="btn-secondary">{{ __('Compare all features') }}</a>
      </div>
      {{-- Animated hero illustration --}}
      <div class="feature-hero-illustration">
        @include('features.partials.hero-illustration', ['slug' => $feature['slug']])
      </div>
    </div>

    <div class="feature-detail-grid">
      <div class="feature-detail-card">
        <h2>{{ __('Overview') }}</h2>
        <p>{{ $feature['description'] }}</p>
        <h2>{{ __('Key capabilities') }}</h2>
        <ul class="feature-detail-list">
          @foreach ($feature['highlights'] as $highlight)
            <li>
              <svg fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
              <span>{{ $highlight }}</span>
            </li>
          @endforeach
        </ul>
      </div>
      <div class="feature-detail-card">
        <h2>{{ __('Common use cases') }}</h2>
        <ul class="feature-detail-list">
          @foreach ($feature['use_cases'] as $useCase)
            <li>
              <svg fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
              <span>{{ $useCase }}</span>
            </li>
          @endforeach
        </ul>
        @if ($feature['badge'])
          <p style="margin-top:20px;font-size:.85rem;color:var(--text-dim)">
            <strong style="color:var(--gold)">{{ $feature['badge'] }}</strong> — {{ __('One of the most adopted capabilities on the DocuTrust platform.') }}
          </p>
        @endif
      </div>
    </div>

    <div class="feature-related">
      <h2>{{ __('Explore other features') }}</h2>
      <div class="feature-related-grid">
        @foreach ($allFeatures as $related)
          @continue($related['slug'] === $feature['slug'])
          <a href="{{ route('features.show', $related['slug']) }}" class="feature-related-card">
            <h3>{{ $related['title'] }}</h3>
            <p>{{ $related['summary'] }}</p>
          </a>
        @endforeach
      </div>
    </div>
  </div>
@endsection
