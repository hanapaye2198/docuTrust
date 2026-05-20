<?php

namespace App\Services\Compliance;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ComplianceReportExporter
{
    public function __construct(
        private readonly SignatureComplianceService $complianceService,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->complianceService->assess();
    }

    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT);
    }

    public function toPdfBinary(): string
    {
        $report = $this->toArray();

        return Pdf::loadView('compliance.report', ['report' => $report])
            ->setPaper('a4')
            ->output();
    }

    public function storePdf(): string
    {
        $path = 'compliance/reports/'.Str::uuid()->toString().'.pdf';
        Storage::disk('local')->put($path, $this->toPdfBinary());

        return $path;
    }
}
