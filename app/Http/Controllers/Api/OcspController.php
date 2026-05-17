<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\OcspResponder;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * OCSP Controller
 *
 * Implements RFC 6960 OCSP responder endpoints.
 * Supports both GET and POST methods as required by the standard.
 */
class OcspController extends Controller
{
    public function __construct(private readonly OcspResponder $ocspResponder) {}

    /**
     * Handle OCSP POST request (RFC 6960 Section A.1).
     *
     * Content-Type: application/ocsp-request
     * Response Content-Type: application/ocsp-response
     */
    public function post(Request $request): Response
    {
        $contentType = $request->header('Content-Type', '');

        if (!str_contains($contentType, 'application/ocsp-request')) {
            return response('', 415)
                ->header('Content-Type', 'text/plain');
        }

        $requestDer = $request->getContent();

        if (empty($requestDer)) {
            return $this->errorResponse();
        }

        $responseDer = $this->ocspResponder->handleRequest($requestDer);

        return response($responseDer, 200)
            ->header('Content-Type', 'application/ocsp-response')
            ->header('Cache-Control', 'no-cache, no-store, must-revalidate')
            ->header('Pragma', 'no-cache');
    }

    /**
     * Handle OCSP GET request (RFC 6960 Section A.1).
     *
     * URL: /ocsp/{base64EncodedRequest}
     */
    public function get(string $encodedRequest): Response
    {
        $requestDer = base64_decode(urldecode($encodedRequest), true);

        if ($requestDer === false || empty($requestDer)) {
            return $this->errorResponse();
        }

        $responseDer = $this->ocspResponder->handleRequest($requestDer);

        return response($responseDer, 200)
            ->header('Content-Type', 'application/ocsp-response')
            ->header('Cache-Control', 'max-age=600'); // Cache GET responses for 10 min
    }

    /**
     * Check certificate status by serial number (convenience JSON endpoint).
     */
    public function checkStatus(Request $request): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            'serial_number' => 'required|string',
        ]);

        $result = $this->ocspResponder->checkBySerial($request->serial_number);

        $statusLabel = match ($result['status']) {
            0 => 'good',
            1 => 'revoked',
            default => 'unknown',
        };

        return response()->json([
            'serial_number' => $result['serial'],
            'status' => $statusLabel,
            'revoked_at' => $result['revoked_at'],
            'revocation_reason' => $result['reason'],
            'checked_at' => now()->toIso8601String(),
        ]);
    }

    private function errorResponse(): Response
    {
        // Return malformedRequest OCSP response
        $malformed = "\x30\x03\x0A\x01\x01"; // SEQUENCE { ENUMERATED(1) }

        return response($malformed, 200)
            ->header('Content-Type', 'application/ocsp-response');
    }
}
