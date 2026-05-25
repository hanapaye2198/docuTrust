<?php

namespace App\Http\Controllers;

use App\Models\Document;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DocumentSignerPagesController extends Controller
{
    /**
     * Save the allowed page assignments for each signer on a document.
     *
     * POST /documents/{document}/signer-pages
     *
     * Expected payload:
     * {
     *   "assignments": {
     *     "42": null,        // signer_id 42 → all pages
     *     "43": [1, 2]       // signer_id 43 → pages 1 and 2 only
     *   }
     * }
     */
    public function __invoke(Request $request, Document $document): JsonResponse
    {
        $this->authorize('update', $document);

        $validated = $request->validate([
            'assignments' => ['required', 'array'],
            'assignments.*' => ['nullable', 'array'],
            'assignments.*.*' => ['integer', 'min:1'],
        ]);

        $assignments = $validated['assignments'];

        foreach ($assignments as $signerId => $pages) {
            $signer = $document->documentSigners()
                ->where('id', (int) $signerId)
                ->first();

            if ($signer === null) {
                continue;
            }

            // null or empty array means all pages
            $allowedPages = is_array($pages) && $pages !== [] ? array_values(array_unique(array_map('intval', $pages))) : null;

            $signer->update(['allowed_pages' => $allowedPages]);
        }

        return response()->json(['status' => 'ok']);
    }
}
