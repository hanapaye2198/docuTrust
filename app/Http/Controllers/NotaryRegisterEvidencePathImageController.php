<?php

namespace App\Http\Controllers;

use App\Models\NotaryRequest;
use App\Models\User;
use App\Services\AttorneyNotarialRegistryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class NotaryRegisterEvidencePathImageController extends Controller
{
    public function __invoke(Request $request, NotaryRequest $notaryRequest, string $encodedPath): StreamedResponse
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 401);
        abort_unless($user->role->value === 'notary', 403);
        abort_unless((int) $notaryRequest->notary_user_id === (int) $user->id, 403);

        $path = base64_decode(strtr($encodedPath, '-_', '+/'), true);
        abort_if(! is_string($path) || $path === '', 404);
        abort_unless(str_starts_with($path, 'notary/register-evidence/'.$notaryRequest->id.'/'), 404);
        abort_unless(app(AttorneyNotarialRegistryService::class)->isImagePath($path), 404);

        $disk = (string) config('filesystems.docutrust_disk', 'local');
        abort_unless(Storage::disk($disk)->exists($path), 404);

        return Storage::disk($disk)->response($path);
    }
}
