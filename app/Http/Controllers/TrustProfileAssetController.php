<?php

namespace App\Http\Controllers;

use App\Http\Requests\UploadNotarySealRequest;
use App\Http\Requests\UploadTrustProfilePhotoRequest;
use App\Http\Requests\UploadTrustProfileSignatureRequest;
use App\Services\Notary\NotarySealProfileService;
use App\Services\OnboardingAuditLogger;
use Illuminate\Filesystem\FilesystemAdapter;
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
                ->to(route('settings.trust-profile', [], false).'#notary-seal')
                ->withErrors(['notary_seal_upload' => $exception->getMessage()]);
        }

        app(OnboardingAuditLogger::class)->log($user, 'trust_profile.notary_seal_updated');

        return redirect()
            ->to(route('settings.trust-profile', [], false).'#notary-seal')
            ->with('trust-status', __('Notary seal saved. It will be used on all cases.'));
    }

    public function storePhoto(UploadTrustProfilePhotoRequest $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user !== null, 401);
        $file = $request->file('profile_photo');
        abort_unless($file instanceof UploadedFile, 422);

        $disk = (string) config('filesystems.docutrust_disk', 'local');
        $path = $file->store('trust-profile/'.$user->id.'/photos', $disk);

        if (filled($user->profile_photo_path) && Storage::disk($disk)->exists($user->profile_photo_path)) {
            Storage::disk($disk)->delete($user->profile_photo_path);
        }

        $user->update(['profile_photo_path' => $path]);

        return redirect()
            ->to(route('settings.trust-profile', [], false))
            ->with('trust-status', __('Profile photo updated.'));
    }

    public function storeSignature(UploadTrustProfileSignatureRequest $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user !== null, 401);
        $file = $request->file('signature_upload');
        abort_unless($file instanceof UploadedFile, 422);

        $disk = (string) config('filesystems.docutrust_disk', 'local');
        $path = $file->store('trust-profile/'.$user->id.'/signatures', $disk);

        if (filled($user->signature_image_path) && Storage::disk($disk)->exists($user->signature_image_path)) {
            Storage::disk($disk)->delete($user->signature_image_path);
        }

        $user->update([
            'signature_image_path' => $path,
            'signature_type' => 'uploaded',
        ]);

        return redirect()
            ->to(route('settings.trust-profile', [], false).'#signature')
            ->with('trust-status', __('Signature uploaded.'));
    }

    private function streamPrivateAsset(string $path): Response
    {
        /** @var FilesystemAdapter $disk */
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
