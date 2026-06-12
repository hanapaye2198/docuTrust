<?php

namespace App\Http\Controllers;

use App\Models\NotaryRequest;
use App\Models\User;
use App\Services\AttorneyNotarialRegistryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class NotaryRegisterEvidenceImageController extends Controller
{
    public function __invoke(Request $request, NotaryRequest $notaryRequest, int $evidenceIndex): StreamedResponse
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 401);
        abort_unless($user->role->value === 'notary', 403);
        abort_unless((int) $notaryRequest->notary_user_id === (int) $user->id, 403);

        $path = app(AttorneyNotarialRegistryService::class)->evidenceImagePathForIndex($notaryRequest, $evidenceIndex);
        abort_if($path === null || $path === '', 404);

        $disk = (string) config('filesystems.docutrust_disk', 'local');
        abort_unless(Storage::disk($disk)->exists($path), 404);

        return Storage::disk($disk)->response($path);
    }
}
