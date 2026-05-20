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
                'summary' => 'Legally binding digital signatures with AES-256 encryption, PKI certificates, and blockchain-anchored tamper-proof verification.',
                'description' => 'DocuTrust delivers enterprise-grade electronic signatures backed by PKI certificates, strong encryption, and standards-aligned signature formats. Every signature is cryptographically bound to the document so alterations are immediately detectable.',
                'icon' => 'lock',
                'featured' => true,
                'badge' => 'Most Used',
                'highlights' => [
                    'Advanced Electronic Signatures (AES) aligned with eIDAS-style requirements',
                    'AES-256 encryption for documents at rest and in transit',
                    'PKI-backed signer identity tied to trust profiles and certificates',
                    'PAdES and XAdES output for long-term validation and archival',
                    'Blockchain anchoring for tamper-evident proof after signing',
                ],
                'use_cases' => [
                    'Contracts and vendor agreements',
                    'Board resolutions and corporate approvals',
                    'Government and regulated-industry submissions',
                ],
            ],
            [
                'slug' => 'blockchain-verification',
                'title' => 'Blockchain Verification',
                'summary' => 'Every document is cryptographically anchored to the blockchain — immutable, verifiable, and impossible to alter post-signing.',
                'description' => 'After signing, DocuTrust records a cryptographic fingerprint on-chain so anyone can confirm the document has not changed since completion. Verification links and audit views make integrity checks straightforward for legal and compliance teams.',
                'icon' => 'link',
                'featured' => false,
                'badge' => null,
                'highlights' => [
                    'Unique document hash generated at completion',
                    'On-chain anchor with transaction reference for auditors',
                    'Public verification flow without exposing document contents',
                    'Instant invalidation if file bytes no longer match the anchor',
                    'Works alongside certificate and timestamp validation',
                ],
                'use_cases' => [
                    'Dispute resolution and court-ready evidence',
                    'Third-party audits and regulator requests',
                    'Customer-facing “verify this document” portals',
                ],
            ],
            [
                'slug' => 'multi-signer-workflow',
                'title' => 'Multi-Signer Workflow',
                'summary' => 'Define signing order, approval chains, and roles with ease. Support for sequential and parallel signing with automated reminders.',
                'description' => 'Coordinate complex approvals across departments, counterparties, and witnesses. DocuTrust supports ordered and parallel paths, role-based fields, and automatic nudges so deals close without manual chasing.',
                'icon' => 'users',
                'featured' => false,
                'badge' => null,
                'highlights' => [
                    'Sequential or parallel signing paths per envelope',
                    'Signer roles, routing rules, and field-level permissions',
                    'Automated email and reminder schedules for pending signers',
                    'In-person and remote signers on the same workflow',
                    'Template-driven reuse for recurring agreement types',
                ],
                'use_cases' => [
                    'HR offer letters with candidate and HR approval',
                    'Real-estate packages with buyer, seller, and broker',
                    'Loan and KYC packets with multiple internal approvers',
                ],
            ],
            [
                'slug' => 'real-time-tracking',
                'title' => 'Real-Time Tracking',
                'summary' => 'Monitor document status from sent to signed in real time. Live dashboards, push notifications, and automated follow-ups.',
                'description' => 'Operations and deal teams get a live view of every envelope—who has opened, who is waiting, and what is blocking completion. Status updates reduce support tickets and keep revenue-critical documents moving.',
                'icon' => 'bolt',
                'featured' => false,
                'badge' => null,
                'highlights' => [
                    'Live status: draft, sent, viewed, signed, completed, declined',
                    'Per-signer progress and timestamps in one timeline',
                    'Automated reminders based on your escalation policy',
                    'Completion notifications to owners and watchers',
                    'Dashboard-friendly metrics for volume and turnaround time',
                ],
                'use_cases' => [
                    'Sales operations tracking proposal signatures',
                    'Legal ops monitoring high-value contract pipelines',
                    'Shared services centers handling bulk signing queues',
                ],
            ],
            [
                'slug' => 'audit-trail-logs',
                'title' => 'Audit Trail Logs',
                'summary' => 'Complete, court-admissible history of every action — who signed, when, from what IP — for full transparency and compliance.',
                'description' => 'Every view, field change, authentication step, and signature event is captured in a tamper-resistant audit log. Export-ready records support internal investigations, regulatory exams, and litigation discovery.',
                'icon' => 'clipboard',
                'featured' => false,
                'badge' => null,
                'highlights' => [
                    'Immutable event stream per document and signer',
                    'IP address, user agent, and authentication method captured',
                    'Certificate and timestamp details stored with each signature',
                    'Configurable retention aligned with your compliance program',
                    'Downloadable evidence packages for auditors',
                ],
                'use_cases' => [
                    'Financial services regulatory examinations',
                    'Public-sector transparency and FOIA-ready records',
                    'Internal security investigations and access reviews',
                ],
            ],
            [
                'slug' => 'smart-document-management',
                'title' => 'Smart Document Management',
                'summary' => 'Organize, search, and retrieve files instantly with AI-powered intelligent tagging, version control, and smart folders.',
                'description' => 'Beyond signing, DocuTrust helps teams structure agreements with smart folders, metadata, and AI-assisted classification. Find the right executed contract in seconds instead of searching shared drives.',
                'icon' => 'folder',
                'featured' => false,
                'badge' => null,
                'highlights' => [
                    'Workspace folders and tags for teams and matters',
                    'Version history and executed-copy preservation',
                    'AI-assisted extraction of parties, dates, and clauses',
                    'Full-text and metadata search across completed envelopes',
                    'Templates linked to prepared fields and workflows',
                ],
                'use_cases' => [
                    'Legal matter files and closing binders',
                    'Procurement contract repositories',
                    'HR personnel records and policy acknowledgments',
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
