<p>{{ __('Hello :name, this is a reminder to sign ":title".', ['name' => $signer->name, 'title' => $document->title]) }}</p>
<p><a href="{{ $signUrl }}">{{ __('Open signing link') }}</a></p>
@if ($requiresDocumentPassword)
<p>{{ __('This document is password-protected.') }}</p>
    @if (is_string($documentPasswordHint) && $documentPasswordHint !== '')
<p>{{ __('Password hint: :hint', ['hint' => $documentPasswordHint]) }}</p>
    @else
<p>{{ __('If you do not have the password, request it from the sender before opening the link.') }}</p>
    @endif
@endif
