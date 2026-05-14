<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\CrlGenerator;
use Illuminate\Http\Request;

/**
 * CRL API Controller
 * 
 * Provides Certificate Revocation List (CRL) distribution endpoints.
 */
class CrlController extends Controller
{
    public function __construct(private readonly CrlGenerator $crlGenerator) {}

    /**
     * Get CRL in PEM format.
     */
    public function getPem(Request $request)
    {
        $crl = $this->crlGenerator->getPemFormat();

        return response($crl, 200)
            ->header('Content-Type', 'application/x-pem-file')
            ->header('Content-Disposition', 'attachment; filename="crl.pem"');
    }

    /**
     * Get CRL in DER format.
     */
    public function getDer(Request $request)
    {
        $crlDer = $this->crlGenerator->getDerFormat();

        return response($crlDer, 200)
            ->header('Content-Type', 'application/pkix-crl')
            ->header('Content-Disposition', 'attachment; filename="crl.der"');
    }

    /**
     * Get CRL distribution points.
     */
    public function getDistributionPoints()
    {
        return response()->json([
            'distributionPoints' => $this->crlGenerator->getDistributionPoints(),
            'nextUpdate' => $this->crlGenerator->getNextUpdate()->toIso8601String(),
        ]);
    }
}
