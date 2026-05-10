<?php

namespace Database\Seeders;

use App\Enums\DocumentStatus;
use App\Models\Document;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

class DocumentSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::query()->where('email', 'demo@docutrust.com')->firstOrFail();

        $documents = [
            ['title' => 'Employment Contract', 'status' => DocumentStatus::Pending],
            ['title' => 'NDA Agreement', 'status' => DocumentStatus::Completed],
            ['title' => 'Freelance Agreement', 'status' => DocumentStatus::Draft],
            ['title' => 'Lease Contract', 'status' => DocumentStatus::Pending],
            ['title' => 'Service Agreement', 'status' => DocumentStatus::Completed],
            ['title' => 'Vendor Contract', 'status' => DocumentStatus::Draft],
            ['title' => 'Partnership Memorandum', 'status' => DocumentStatus::Pending],
            ['title' => 'Offer Letter', 'status' => DocumentStatus::Completed],
        ];

        foreach ($documents as $index => $payload) {
            $filePath = sprintf('documents/demo-%02d.pdf', $index + 1);
            $this->storeDemoPdf($filePath);

            Document::query()->create([
                'user_id' => $user->id,
                'title' => $payload['title'],
                'file_path' => $filePath,
                'files' => [$filePath],
                'status' => $payload['status'],
                'sent_at' => $payload['status'] === DocumentStatus::Pending || $payload['status'] === DocumentStatus::Completed
                    ? now()->subDays($index + 1)
                    : null,
            ]);
        }
    }

    private function storeDemoPdf(string $path): void
    {
        if (Storage::disk('public')->exists($path)) {
            return;
        }

        $content = <<<'PDF'
%PDF-1.1
1 0 obj
<< /Type /Catalog /Pages 2 0 R >>
endobj
2 0 obj
<< /Type /Pages /Count 1 /Kids [3 0 R] >>
endobj
3 0 obj
<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>
endobj
4 0 obj
<< /Length 69 >>
stream
BT
/F1 24 Tf
72 720 Td
(DocuTrust Document) Tj
ET
endstream
endobj
5 0 obj
<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>
endobj
xref
0 6
0000000000 65535 f
0000000010 00000 n
0000000063 00000 n
0000000120 00000 n
0000000246 00000 n
0000000375 00000 n
trailer
<< /Size 6 /Root 1 0 R >>
startxref
445
%%EOF
PDF;

        Storage::disk('public')->put($path, $content);
    }
}
