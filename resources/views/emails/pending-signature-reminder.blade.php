<p>{{ __('Hello :name, this is a reminder to sign ":title".', ['name' => $signer->name, 'title' => $document->title]) }}</p>
<p><a href="{{ $signUrl }}">{{ __('Open signing link') }}</a></p>
