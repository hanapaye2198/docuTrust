<?php

namespace App\Support;

use Illuminate\Support\Collection;

class MarketingFeatures
{
    /**
     * @return list<array{
     *     slug: string,
     *     title: string,
     *     summary: string,
     *     description: string,
     *     icon: string,
     *     featured: bool,
     *     badge: string|null,
     *     highlights: list<string>,
     *     use_cases: list<string>
     * }>
     */
    public static function all(): array
    {
        return [
            [
                'slug' => 'secure-digital-signing',
                'title' => 'Secure Digital Signing',
                'summary' => 'Collect legally binding electronic signatures on any document — with full audit trail, OTP verification, and PDF/A compliance.',
                'description' => 'DocuTrust\'s signing engine supports draw, type, and image upload signature modes. Every signature is validated against the signer\'s identity, timestamped, and embedded directly into the PDF using PAdES-B standards. Completion certificates are auto-generated after the final signature is captured.',
                'icon' => 'pen-tool',
                'featured' => true,
                'badge' => 'Core',
                'highlights' => [
                    'Draw, type, or upload signature input',
                    'PAdES-B compliant PDF embedding',
                    'OTP verification per signer',
                    'Auto-generated completion certificate',
                    'Download signed PDF immediately after completion',
                ],
                'use_cases' => [
                    'Real estate contracts requiring buyer and seller signatures',
                    'Employment agreements with HR countersignature',
                    'Client engagement letters for law firms',
                    'Government form submissions requiring witnessed signatures',
                ],
            ],
            [
                'slug' => 'blockchain-verification',
                'title' => 'Blockchain Verification',
                'summary' => 'Every completed document hash is anchored to the Polygon blockchain — giving anyone a tamper-proof, independently verifiable record.',
                'description' => 'After all parties sign, DocuTrust computes a SHA-256 hash of the final PDF and submits it to the Polygon network via a Node.js sidecar service. The on-chain transaction ID and block number are stored alongside the document. Any third party can verify document authenticity without accessing DocuTrust.',
                'icon' => 'link',
                'featured' => false,
                'badge' => 'Infra',
                'highlights' => [
                    'SHA-256 hash anchored to Polygon mainnet',
                    'On-chain transaction ID stored per document',
                    'Public verification endpoint — no login required',
                    'Tamper-evident: any post-sign alteration is detectable',
                    'Audit-ready blockchain receipts',
                ],
                'use_cases' => [
                    'Legal documents requiring court-admissible proof of integrity',
                    'Insurance claims where document authenticity is disputed',
                    'Academic credential verification',
                    'Cross-border contracts needing independent verification',
                ],
            ],
            [
                'slug' => 'multi-signer-workflow',
                'title' => 'Multi-Signer Workflow',
                'summary' => 'Define signing order, set approval chains, and track every signer\'s progress in real time — sequentially or in parallel.',
                'description' => 'DocuTrust supports complex signing configurations: sequential (signer B only receives the document after signer A completes), parallel (all signers receive simultaneously), and mixed. Each signer is assigned specific fields. Automated reminders are sent at configurable intervals, and the document owner can monitor live progress from the dashboard.',
                'icon' => 'users',
                'featured' => false,
                'badge' => null,
                'highlights' => [
                    'Sequential and parallel signing order modes',
                    'Per-signer field assignment',
                    'Automated email and SMS reminders',
                    'Live progress tracking per signer',
                    'Conditional routing — skip or require signers based on rules',
                ],
                'use_cases' => [
                    'Board resolutions requiring all directors to sign in order',
                    'Loan agreements with borrower, co-borrower, and bank officer',
                    'Property deeds with buyer, seller, and notary',
                    'Franchise agreements with multiple approval levels',
                ],
            ],
            [
                'slug' => 'real-time-tracking',
                'title' => 'Real-Time Tracking',
                'summary' => 'Monitor every document\'s status from sent to fully signed — with live dashboards, push notifications, and a complete event timeline.',
                'description' => 'DocuTrust uses Livewire polling to surface live updates without page refreshes. Every action — document opened, OTP sent, field completed, signature captured — is recorded with a timestamp and IP address. Attorneys and document owners receive instant notifications when a signer completes their step.',
                'icon' => 'activity',
                'featured' => false,
                'badge' => null,
                'highlights' => [
                    'Live status updates via Livewire polling (5s interval)',
                    'Per-signer event timeline with timestamps',
                    'Email and SMS notifications on completion',
                    'IP address and device logged per action',
                    'Dashboard view filterable by status, date, and signer',
                ],
                'use_cases' => [
                    'High-volume law firms tracking dozens of active documents',
                    'HR teams monitoring onboarding document completion rates',
                    'Real estate agents following up on pending buyer signatures',
                    'Notaries verifying signer participation in real time',
                ],
            ],
            [
                'slug' => 'audit-trail-logs',
                'title' => 'Audit Trail Logs',
                'summary' => 'A complete, court-admissible history of every action — who signed, when, from which IP, and what they agreed to.',
                'description' => 'Every interaction with a document is recorded immutably: views, OTP requests, field completions, signatures, downloads, and forwarding events. Audit logs are exported as a structured PDF report that is embedded into the final signed document package. Logs cannot be edited or deleted, even by platform administrators.',
                'icon' => 'shield-check',
                'featured' => false,
                'badge' => null,
                'highlights' => [
                    'Immutable log — no admin can edit or delete entries',
                    'Exported as embedded PDF report in the document package',
                    'Captures IP address, device, and timestamp per event',
                    'Covers views, OTPs, signatures, downloads, and shares',
                    'Court-admissible format compliant with Philippine e-commerce law',
                ],
                'use_cases' => [
                    'Legal disputes where signing intent must be proven',
                    'Regulatory audits requiring documented consent trails',
                    'Insurance fraud investigations',
                    'Corporate governance requiring board action records',
                ],
            ],
            [
                'slug' => 'smart-document-management',
                'title' => 'Smart Document Management',
                'summary' => 'Organise, tag, version, and retrieve documents with intelligent folder structures — and find any file instantly by signer, date, or status.',
                'description' => 'DocuTrust\'s document library supports custom folder hierarchies, automatic tagging by document type and status, and full-text search across all document metadata. Version history is preserved for every document that goes through revision. Documents can be shared with read-only links or recalled at any time.',
                'icon' => 'folder',
                'featured' => false,
                'badge' => null,
                'highlights' => [
                    'Custom folder and tagging system',
                    'Search by signer name, date range, document type, and status',
                    'Full version history per document',
                    'Read-only share links with expiry',
                    'Bulk download and archive export',
                ],
                'use_cases' => [
                    'Law firms managing hundreds of client documents per month',
                    'HR departments organising contracts by department and date',
                    'Property managers filing lease agreements by unit',
                    'Government offices maintaining public record archives',
                ],
            ],
            [
                'slug' => 'templates',
                'title' => 'Templates & Contacts',
                'summary' => 'Create reusable document templates with pre-defined signer roles and drag-and-drop field placement — ready to send in seconds.',
                'description' => 'DocuTrust\'s template builder lets you define a document once and reuse it across unlimited signings. Assign placeholder signer roles (e.g. "Buyer", "Witness"), place signature, date, and text fields, and set required vs optional rules. When sending, simply map roles to real contacts from your address book.',
                'icon' => 'layout-template',
                'featured' => false,
                'badge' => null,
                'highlights' => [
                    'Drag-and-drop field placement on any PDF',
                    'Reusable signer role placeholders',
                    'Required vs optional field rules per role',
                    'Contact address book with autofill',
                    'Template versioning and duplication',
                ],
                'use_cases' => [
                    'Law firms sending the same contract type to new clients weekly',
                    'HR departments issuing standard offer letters at scale',
                    'Property managers distributing identical lease agreements',
                    'Franchisors sending uniform agreements to all franchisees',
                ],
            ],
            [
                'slug' => 'enotary',
                'title' => 'e-Notary Workflows',
                'summary' => 'End-to-end notary request creation covering signer and witness management, video verification, and notarial certificate generation.',
                'description' => 'DocuTrust\'s e-Notary module guides attorneys through the full RON (Remote Online Notarization) workflow as defined under Philippine law. Signers complete eKYC, join a Jitsi video session for live identity confirmation, provide their electronic signature, and receive a notarized document with a court-admissible notarial certificate.',
                'icon' => 'stamp',
                'featured' => true,
                'badge' => 'Core',
                'highlights' => [
                    'Guided RON workflow from case creation to certificate',
                    'Signer and witness role management',
                    'Jitsi video session with join-timestamp tracking',
                    'eKYC via Sumsub identity verification',
                    'Auto-generated notarial certificate on completion',
                ],
                'use_cases' => [
                    'Notarizing affidavits and sworn statements remotely',
                    'Real estate deed acknowledgment with remote parties',
                    'Last wills and testaments with witness verification',
                    'Power of attorney documents for overseas principals',
                ],
            ],
            [
                'slug' => 'notary-portal',
                'title' => 'Notary / Attorney Portal',
                'summary' => 'A dedicated portal for notaries and attorneys covering case management, calendar scheduling, client records, and credential management.',
                'description' => 'The attorney portal gives accredited notaries a central hub for all their DocuTrust activity. Manage active cases, track upcoming video sessions, maintain a client CRM, review fees and payment history, and monitor your notary readiness status — including PKI certificate expiry and eKYC approval.',
                'icon' => 'briefcase',
                'featured' => true,
                'badge' => 'Core',
                'highlights' => [
                    'Unified case list with status filters',
                    'Integrated appointment calendar',
                    'Client CRM with document history per contact',
                    'Fee tracking and GatewayHub payment overview',
                    'Notary readiness panel — PKI, eKYC, and credential status',
                ],
                'use_cases' => [
                    'Solo notaries managing their full caseload in one dashboard',
                    'Law firm partners overseeing associate notary activity',
                    'Notaries tracking certificate expiry and renewal deadlines',
                    'Attorneys billing clients with itemised notarial fees',
                ],
            ],
            [
                'slug' => 'trust-profile',
                'title' => 'Trust Profile & Onboarding',
                'summary' => 'Multi-step identity verification including email confirmation, mobile OTP, eKYC via Sumsub and Tesseract OCR, and MFA setup.',
                'description' => 'Before any user can sign or notarise a document on DocuTrust, they complete a trust-building onboarding flow. This covers email and mobile verification, government ID upload with OCR extraction via Tesseract, facial liveness check via Sumsub, and MFA configuration. Trust scores are maintained and can be re-evaluated if suspicious activity is detected.',
                'icon' => 'user-check',
                'featured' => false,
                'badge' => null,
                'highlights' => [
                    'Email and mobile OTP verification',
                    'Government ID upload with Tesseract OCR extraction',
                    'Facial liveness check via Sumsub',
                    'MFA setup (TOTP or SMS)',
                    'Continuous trust score monitoring',
                ],
                'use_cases' => [
                    'Onboarding signers who have never used the platform before',
                    'Re-verifying users after a long period of inactivity',
                    'Enforcing elevated identity checks for high-value documents',
                    'Compliance with BSP and SEC digital identity requirements',
                ],
            ],
            [
                'slug' => 'verification',
                'title' => 'Verification & Compliance',
                'summary' => 'Public-facing document and notary verification, PAdES-aligned signature compliance, and Philippine e-commerce law reporting.',
                'description' => 'DocuTrust provides a public verification portal where anyone can paste a document hash or transaction ID to confirm authenticity — without logging in. Internally, every signature is validated against PAdES-B standards, and compliance reports are generated for BIR EIS e-invoicing and Philippine e-commerce regulatory requirements.',
                'icon' => 'badge-check',
                'featured' => false,
                'badge' => null,
                'highlights' => [
                    'Public document hash verification portal',
                    'PAdES-B signature conformance per CSC API v2',
                    'BIR EIS e-invoicing integration',
                    'Compliance report export per document',
                    'Notary credential public lookup page',
                ],
                'use_cases' => [
                    'Counterparties verifying a signed document without a DocuTrust account',
                    'Auditors confirming document integrity for regulatory review',
                    'Courts validating electronic signatures in legal proceedings',
                    'BIR audits requiring e-invoice submission records',
                ],
            ],
            [
                'slug' => 'admin',
                'title' => 'Admin & Billing',
                'summary' => 'Platform-wide dashboard covering user management, signing activity, attorney approvals, subscription plans, and system configuration.',
                'description' => 'The DocuTrust admin panel gives platform operators full visibility and control. Manage user accounts, approve or suspend notary credentials, monitor platform-wide signing activity, configure subscription tiers, and access system health metrics. Role-based access ensures admin functions are scoped to authorised staff only.',
                'icon' => 'settings',
                'featured' => false,
                'badge' => null,
                'highlights' => [
                    'User and role management across all tenants',
                    'Notary credential approval and suspension',
                    'Platform-wide signing activity metrics',
                    'Subscription plan and billing configuration',
                    'System health and API status monitoring',
                ],
                'use_cases' => [
                    'Platform operators approving new notary registrations',
                    'Finance teams reconciling subscription revenue',
                    'Compliance officers auditing platform-wide activity',
                    'Support staff assisting users with account issues',
                ],
            ],
            [
                'slug' => 'payments',
                'title' => 'Payments & Billing',
                'summary' => 'GatewayHub webhook handling, public notary payment link generation, and itemised billing for notarial services.',
                'description' => 'DocuTrust integrates with GatewayHub to handle payment collection for notarial services. Attorneys can generate payment links per case, and clients pay before the notarization session begins. Webhook events update case status automatically on payment confirmation. Receipts are issued as BIR EIS-compliant e-invoices.',
                'icon' => 'credit-card',
                'featured' => false,
                'badge' => null,
                'highlights' => [
                    'GatewayHub payment link generation per case',
                    'Webhook-driven case status updates on payment',
                    'BIR EIS-compliant e-invoice receipts',
                    'Itemised fee breakdown per notarial act',
                    'Payment history and export for attorneys',
                ],
                'use_cases' => [
                    'Notaries collecting fees before a video session begins',
                    'Clients paying for remote notarization via online banking',
                    'Attorneys issuing official receipts for tax compliance',
                    'Finance teams reconciling notarial income per month',
                ],
            ],
            [
                'slug' => 'pki',
                'title' => 'PKI / Remote Signing',
                'summary' => 'Full CSC API v2 pipeline with OAuth 2.0 credential authorization, SAD lifecycle, PAdES-LTV timestamp embedding, and SCEP/CMP support.',
                'description' => 'DocuTrust\'s PKI infrastructure supports hardware-backed remote signing via the Cloud Signature Consortium API v2. Notary credentials are issued by a Certificate Authority backed by an HSM. Signing uses a full SAD (Signature Activation Data) lifecycle — authorize, hash, sign, embed — with LTV timestamps from GlobalSign TSA and OCSP/CRL validation built in.',
                'icon' => 'key',
                'featured' => false,
                'badge' => 'Infra',
                'highlights' => [
                    'CSC API v2 compliant remote signing pipeline',
                    'HSM-backed Certificate Authority for notary credentials',
                    'Full SAD lifecycle — authorize, hash, sign, embed',
                    'LTV timestamp embedding via GlobalSign TSA',
                    'OCSP and CRL validation per signature',
                ],
                'use_cases' => [
                    'Notaries applying a legally valid digital signature to PDFs',
                    'Platform operators managing PKI certificate issuance',
                    'Integrators connecting to CSC-compliant signing services',
                    'Compliance with Philippine DICT and BSP digital signature rules',
                ],
            ],
        ];
    }

    /**
     * @return Collection<int, array{
     *     slug: string,
     *     title: string,
     *     summary: string,
     *     description: string,
     *     icon: string,
     *     featured: bool,
     *     badge: string|null,
     *     highlights: list<string>,
     *     use_cases: list<string>
     * }>
     */
    public static function collection(): Collection
    {
        return collect(self::all());
    }

    /**
     * @return array{
     *     slug: string,
     *     title: string,
     *     summary: string,
     *     description: string,
     *     icon: string,
     *     featured: bool,
     *     badge: string|null,
     *     highlights: list<string>,
     *     use_cases: list<string>
     * }|null
     */
    public static function find(string $slug): ?array
    {
        return self::collection()->firstWhere('slug', $slug);
    }

    /**
     * @return list<string>
     */
    public static function slugs(): array
    {
        return self::collection()->pluck('slug')->all();
    }
}
