<p>{{ __('Hello :name,', ['name' => $signer->name]) }}</p>
<p>{{ __('You have been requested to sign the document ":title".', ['title' => $document->title]) }}</p>
<p><a href="{{ $signUrl }}">{{ __('Open signing link') }}</a></p>
@if ($requiresDocumentPassword)
<p>{{ __('This document requires a password before you can view or sign it.') }}</p>
    @if (is_string($documentPasswordHint) && $documentPasswordHint !== '')
<p>{{ __('Password hint: :hint', ['hint' => $documentPasswordHint]) }}</p>
    @else
<p>{{ __('Ask the sender for the document password if it was not shared with you separately.') }}</p>
    @endif
@endif
