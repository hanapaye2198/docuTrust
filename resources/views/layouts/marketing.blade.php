<!DOCTYPE html>
@php
  $authenticatedUser = auth()->user();
  $primaryCtaUrl = $authenticatedUser ? route($authenticatedUser->homeRouteName()) : route('register');
  $primaryCtaLabel = $authenticatedUser ? __('Open workspace') : __('Start Free Trial');
  $secondaryHeaderUrl = $authenticatedUser ? route('settings.profile') : route('login');
  $secondaryHeaderLabel = $authenticatedUser ? __('Settings') : __('Login');
  $marketingNavPrefix = route('home');
@endphp
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="docutrust-smooth-scroll">
<head>
@include('layouts.partials.marketing-color-scheme-script')
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="{{ csrf_token() }}">
<meta name="color-scheme" content="light dark">
<meta name="theme-color" content="#f0f7f5" id="docutrustThemeColor">
<title>@yield('title') | {{ config('app.name') }}</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&family=Source+Sans+3:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="icon" href="{{ asset('images/docutrust-logo.png') }}" type="image/png">
@include('layouts.partials.marketing-styles')
@stack('head')
</head>
<body>
<div class="orb orb1"></div>
<div class="orb orb2"></div>

@include('layouts.partials.marketing-header')

<main>
  @yield('content')
</main>

<footer class="marketing-footer">
  <div class="container marketing-footer-inner">
    <a href="{{ route('home') }}#features" class="marketing-back">{{ __('← All features') }}</a>
    <a href="{{ $primaryCtaUrl }}" class="btn-primary">{{ $primaryCtaLabel }}</a>
  </div>
</footer>

@include('layouts.partials.marketing-header-scripts')

<x-marketing-chatbot />
</body>
</html>
