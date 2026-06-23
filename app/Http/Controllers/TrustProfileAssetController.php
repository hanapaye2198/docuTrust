<?php

namespace App\Http\Controllers;

use App\Http\Requests\UploadNotarySealRequest;
use App\Services\Notary\NotarySealProfileService;
use App\Services\OnboardingAuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;

class TrustProfileAssetController extends Controller
{
    public function photo(Request $request): Response
    {
        $user = $request->user();
        $path = $user?->profile_photo_path;

        if (! is_string($path) || $path === '') {
            abort(404);
        }

        return $this->streamPrivateAsset($path);
    }

    public function signature(Request $request): Response
    {
        $user = $request->user();
        $path = $user?->signature_image_path;

        if (! is_string($path) || $path === '') {
            abort(404);
        }

        return $this->streamPrivateAsset($path);
    }

    public function seal(Request $request, NotarySealProfileService $sealProfile): Response
    {
        $user = $request->user();
        $path = $user !== null ? $sealProfile->sealImagePath($user) : null;

        if (! is_string($path) || $path === '') {
            abort(404);
        }

        return $this->streamPrivateAsset($path);
    }

    public function storeSeal(UploadNotarySealRequest $request, NotarySealProfileService $sealProfile): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user !== null, 401);
        $file = $request->file('notary_seal_upload');
        abort_unless($file instanceof UploadedFile, 422);

        try {
            $sealProfile->storeSeal($user, $file);
        } catch (InvalidArgumentException $exception) {
            return redirect()
                ->to(route('settings.trust-profile').'#notary-seal')
                ->withErrors(['notary_seal_upload' => $exception->getMessage()]);
        }

        app(OnboardingAuditLogger::class)->log($user, 'trust_profile.notary_seal_updated');

        return redirect()
            ->to(route('settings.trust-profile').'#notary-seal')
            ->with('trust-status', __('Notary seal saved. It will be used on all cases.'));
    }

    private function streamPrivateAsset(string $path): Response
    {
        $disk = Storage::disk((string) config('filesystems.docutrust_disk', 'local'));

        if (! $disk->exists($path)) {
            abort(404);
        }

        return response($disk->get($path), 200, [
            'Content-Type' => $disk->mimeType($path) ?: 'application/octet-stream',
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }
}
