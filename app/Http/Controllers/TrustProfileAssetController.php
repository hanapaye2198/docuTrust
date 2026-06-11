<?php

namespace App\Http\Controllers;

use App\Services\Notary\NotarySealProfileService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
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
