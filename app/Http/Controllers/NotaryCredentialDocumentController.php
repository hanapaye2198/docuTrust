<?php

namespace App\Http\Controllers;

use App\Models\NotaryCredential;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class NotaryCredentialDocumentController extends Controller
{
    public function __invoke(Request $request, NotaryCredential $credential, string $document): StreamedResponse
    {
        Gate::authorize('downloadDocument', $credential);

        $path = match ($document) {
            'commission' => $credential->commission_document_path,
            'ibp' => $credential->ibp_document_path,
            'ptr' => $credential->ptr_document_path,
            'mcle' => $credential->mcle_document_path,
            'seal' => $credential->seal_image_path,
            'signature' => $credential->signature_image_path,
            default => null,
        };

        abort_if($path === null || $path === '', 404);

        $disk = (string) config('filesystems.docutrust_disk', 'local');
        abort_unless(Storage::disk($disk)->exists($path), 404);

        return Storage::disk($disk)->response($path);
    }
}
