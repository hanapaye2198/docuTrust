<?php

namespace Database\Seeders;

use App\Enums\DocumentSignerStatus;
use App\Enums\DocumentStatus;
use App\Models\Document;
use App\Models\DocumentSigner;
use App\Models\User;
use Illuminate\Database\Seeder;

class SignerSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::query()->where('email', 'demo@docutrust.com')->firstOrFail();

        $signersByDocument = [
            'Employment Contract' => [
                ['name' => 'John Carter', 'email' => 'john.carter@acme.test', 'status' => DocumentSignerStatus::Signed],
                ['name' => 'Mary Lewis', 'email' => 'mary.lewis@acme.test', 'status' => DocumentSignerStatus::Pending],
                ['name' => 'Legal Ops', 'email' => 'legal.ops@acme.test', 'status' => DocumentSignerStatus::Pending],
            ],
            'NDA Agreement' => [
                ['name' => 'Olivia Reed', 'email' => 'olivia.reed@nova.test', 'status' => DocumentSignerStatus::Signed],
                ['name' => 'Daniel West', 'email' => 'daniel.west@nova.test', 'status' => DocumentSignerStatus::Signed],
            ],
            'Freelance Agreement' => [
                ['name' => 'Amelia Stone', 'email' => 'amelia.stone@studio.test', 'status' => DocumentSignerStatus::Pending],
                ['name' => 'Noah Blake', 'email' => 'noah.blake@studio.test', 'status' => DocumentSignerStatus::Pending],
            ],
            'Lease Contract' => [
                ['name' => 'Tenant Manager', 'email' => 'tenant.manager@harbor.test', 'status' => DocumentSignerStatus::Pending],
                ['name' => 'Property Owner', 'email' => 'owner@harbor.test', 'status' => DocumentSignerStatus::Signed],
                ['name' => 'Witness Team', 'email' => 'witness@harbor.test', 'status' => DocumentSignerStatus::Pending],
            ],
            'Service Agreement' => [
                ['name' => 'Client Admin', 'email' => 'client.admin@aurora.test', 'status' => DocumentSignerStatus::Signed],
                ['name' => 'Vendor Lead', 'email' => 'vendor.lead@aurora.test', 'status' => DocumentSignerStatus::Signed],
            ],
            'Vendor Contract' => [
                ['name' => 'Procurement Head', 'email' => 'procurement@atlas.test', 'status' => DocumentSignerStatus::Pending],
                ['name' => 'Vendor Director', 'email' => 'director@atlas.test', 'status' => DocumentSignerStatus::Pending],
            ],
            'Partnership Memorandum' => [
                ['name' => 'Partner A', 'email' => 'partner.a@bridge.test', 'status' => DocumentSignerStatus::Signed],
                ['name' => 'Partner B', 'email' => 'partner.b@bridge.test', 'status' => DocumentSignerStatus::Pending],
            ],
            'Offer Letter' => [
                ['name' => 'HR Manager', 'email' => 'hr.manager@orbit.test', 'status' => DocumentSignerStatus::Signed],
                ['name' => 'Candidate', 'email' => 'candidate@orbit.test', 'status' => DocumentSignerStatus::Signed],
            ],
        ];

        foreach ($signersByDocument as $title => $signers) {
            $document = Document::query()
                ->where('user_id', $user->id)
                ->where('title', $title)
                ->firstOrFail();

            foreach ($signers as $index => $signerData) {
                DocumentSigner::query()->create([
                    'document_id' => $document->id,
                    'role_name' => 'Signer '.($index + 1),
                    'name' => $signerData['name'],
                    'email' => $signerData['email'],
                    'status' => $signerData['status'],
                    'signing_order' => $index + 1,
                    'signed_at' => $signerData['status'] === DocumentSignerStatus::Signed ? now()->subDays($index + 1) : null,
                ]);
            }

            if ($document->status === DocumentStatus::Completed) {
                $document->update(['sent_at' => now()->subDays(7)]);
            }
        }
    }
}
