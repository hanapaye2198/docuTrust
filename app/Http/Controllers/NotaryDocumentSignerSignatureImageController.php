<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\DocumentSigner;
use App\Models\NotaryRequest;
use App\Models\Signature;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class NotaryDocumentSignerSignatureImageController extends Controller
{
    public function __invoke(
        Request $request,
        NotaryRequest $notaryRequest,
        Document $document,
        DocumentSigner $documentSigner,
        Signature $signature,
    ): Response {
        $user = Auth::user();
        abort_unless($user instanceof User, 401);
        abort_unless($user->role->value === 'notary', 403);
        abort_unless((int) $notaryRequest->notary_user_id === (int) $user->id, 403);

        abort_unless((int) $document->notary_request_id === (int) $notaryRequest->id, 404);
        abort_unless((int) $documentSigner->document_id === (int) $document->id, 404);
        abort_unless((int) $signature->document_id === (int) $document->id, 404);
        abort_unless((int) $signature->signer_id === (int) $documentSigner->id, 404);

        $path = $signature->signature_path;
        abort_if($path === null || $path === '', 404);

        $disk = Storage::disk($this->secureDiskName());
        abort_unless($disk->exists($path), 404);

        $content = $disk->get($path);

        return response($content, 200, [
            'Content-Type' => $disk->mimeType($path) ?: 'application/octet-stream',
            'Cache-Control' => 'private, max-age=600, stale-while-revalidate=3600',
        ]);
    }

    private function secureDiskName(): string
    {
        return (string) config('filesystems.docutrust_disk', 'local');
    }
}
