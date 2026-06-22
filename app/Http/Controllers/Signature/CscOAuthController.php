<?php

namespace App\Http\Controllers\Signature;

use App\Http\Controllers\Controller;
use App\Models\DocumentSigner;
use App\Services\Signature\CscApiClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CscOAuthController extends Controller
{
    public function redirect(Request $request): RedirectResponse
    {
        $state = Str::random(40);
        $request->session()->put('csc_oauth_state', $state);
        $request->session()->put('csc_oauth_document_id', $request->query('document_id'));
        $request->session()->put('csc_oauth_signer_id', $request->query('signer_id'));

        $url = rtrim((string) config('services.csc.base_url', ''), '/').'/oauth2/authorize?'.http_build_query([
            'response_type' => 'code',
            'client_id' => (string) config('services.csc.client_id', ''),
            'redirect_uri' => (string) config('services.csc.redirect_uri', ''),
            'scope' => 'credential',
            'state' => $state,
        ]);

        return redirect()->away($url);
    }

    public function callback(Request $request): RedirectResponse
    {
        $expectedState = (string) $request->session()->get('csc_oauth_state', '');
        $actualState = (string) $request->query('state', '');

        if ($expectedState === '' || ! hash_equals($expectedState, $actualState)) {
            abort(403, 'Invalid OAuth state — possible CSRF');
        }

        $code = $request->query('code');
        if (! is_string($code) || $code === '') {
            abort(422, 'Missing CSC OAuth authorization code.');
        }

        $response = (new CscApiClient)->getAccessToken(
            $code,
            (string) config('services.csc.redirect_uri', ''),
        );

        $request->session()->put([
            'csc_access_token' => $response['access_token'],
            'csc_refresh_token' => $response['refresh_token'] ?? null,
            'csc_token_expires_at' => now()->addSeconds((int) ($response['expires_in'] ?? 3600))->toIso8601String(),
        ]);
        $request->session()->forget('csc_oauth_state');

        $documentId = $request->session()->pull('csc_oauth_document_id');
        $signerId = $request->session()->pull('csc_oauth_signer_id');

        if ($documentId && $signerId) {
            $signer = DocumentSigner::query()->find($signerId);
            if ($signer?->access_token) {
                return redirect()
                    ->route('sign.show', ['token' => $signer->access_token])
                    ->with('success', 'CSC credentials connected. Please proceed to sign.')
                    ->with('status', 'CSC credentials connected. Please proceed to sign.');
            }
        }

        return redirect()
            ->route('documents.index')
            ->with('success', 'CSC credentials connected successfully.');
    }
}
