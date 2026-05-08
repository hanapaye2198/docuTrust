<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\TwoFactorAuthenticationService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RecoveryCodesDownloadController extends Controller
{
    public function __invoke(Request $request, TwoFactorAuthenticationService $twoFactor): StreamedResponse
    {
        $user = $request->user();
        abort_unless($user !== null, 403);

        if (! $twoFactor->hasAtLeastOneRecoveryCode($user)) {
            $twoFactor->regenerateRecoveryCodes($user);
            $user->refresh();
        }

        $codes = collect($user->two_factor_recovery_codes ?? [])
            ->filter(fn ($code) => is_string($code) && $code !== '')
            ->values()
            ->all();

        $content = "DocuTrust Recovery Codes\n";
        $content .= 'Generated at: '.now()->toDateTimeString()."\n\n";
        $content .= implode("\n", $codes)."\n";

        return response()->streamDownload(function () use ($content): void {
            echo $content;
        }, 'docutrust-recovery-codes.txt', [
            'Content-Type' => 'text/plain; charset=UTF-8',
        ]);
    }
}
