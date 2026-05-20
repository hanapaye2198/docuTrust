<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Compliance\ComplianceReportExporter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SignatureComplianceController extends Controller
{
    public function json(ComplianceReportExporter $exporter): JsonResponse
    {
        return response()->json($exporter->toArray());
    }

    public function downloadJson(ComplianceReportExporter $exporter): StreamedResponse
    {
        $filename = 'docutrust-compliance-'.now()->format('Y-m-d-His').'.json';

        return response()->streamDownload(
            fn () => print ($exporter->toJson()),
            $filename,
            ['Content-Type' => 'application/json'],
        );
    }

    public function downloadPdf(ComplianceReportExporter $exporter): Response
    {
        return response($exporter->toPdfBinary(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="docutrust-compliance-'.now()->format('Y-m-d-His').'.pdf"',
        ]);
    }
}
