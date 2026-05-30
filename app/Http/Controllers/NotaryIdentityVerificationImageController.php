<?php

namespace App\Http\Controllers;

use App\Models\NotaryIdentityVerification;
use App\Models\NotaryRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class NotaryIdentityVerificationImageController extends Controller
{
    public function __invoke(Request $request, NotaryRequest $notaryRequest, NotaryIdentityVerification $verification): StreamedResponse
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 401);

        abort_unless((int) $verification->notary_request_id === (int) $notaryRequest->id, 404);

        if ($user->role->value === 'notary') {
            abort_unless((int) $notaryRequest->notary_user_id === (int) $user->id, 403);
        }

        $path = $verification->id_image_path;
        abort_if($path === null || $path === '', 404);

        $disk = (string) config('filesystems.docutrust_disk', 'local');
        abort_unless(Storage::disk($disk)->exists($path), 404);

        return Storage::disk($disk)->response($path);
    }
}
