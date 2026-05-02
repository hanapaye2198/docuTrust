<p>{{ __('Hello :name,', ['name' => $signer->name]) }}</p>
<p>{{ __('You have been requested to sign the document ":title".', ['title' => $document->title]) }}</p>
<p><a href="{{ $signUrl }}">{{ __('Open signing link') }}</a></p>
