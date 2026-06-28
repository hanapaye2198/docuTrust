<?php

use Illuminate\Support\Str;
use Livewire\Volt\Component;

new class extends Component {
    public string $activeFeature = 'signing';

    /**
     * @var array<string, array{name: string, description: string, icon: string, badge: string|null, badge_style?: string, url: string, tags: list<string>, category: string}>
     */
    public array $features = [
        'signing' => [
            'name' => 'Document signing workspace',
            'description' => 'Upload and manage documents, prepare PDFs with drag-and-drop field placement, assign signers, send signing links via email or SMS, capture electronic signatures, download signed PDFs, and generate legally compliant completion certificates.',
            'icon' => 'heroicon-o-pencil-square',
            'badge' => 'Core',
            'badge_style' => 'bg-blue-50 text-blue-700',
            'url' => '/features/secure-digital-signing',
            'tags' => ['SignDocumentController.php', 'DocumentPrepareController.php', 'resources/views/sign/show.blade.php'],
            'category' => 'workflows',
        ],
        'templates' => [
            'name' => 'Templates & contacts',
            'description' => 'Create reusable document templates with pre-defined signer roles and field placements. Manage your contact book for fast recipient assignment. Templates can be tagged and filtered for quick retrieval.',
            'icon' => 'heroicon-o-document-duplicate',
            'badge' => null,
            'url' => '/features/templates',
            'tags' => ['resources/views/livewire/templates', 'resources/views/livewire/contacts'],
            'category' => 'workflows',
        ],
        'enotary' => [
            'name' => 'e-Notary workflows',
            'description' => 'End-to-end notary request creation covering signer and witness management, document preparation, live Jitsi video verification sessions, signing progress tracking, settlement fee collection via payment links, and a full completion flow with certificates.',
            'icon' => 'heroicon-o-video-camera',
            'badge' => 'Core',
            'badge_style' => 'bg-blue-50 text-blue-700',
            'url' => '/features/enotary',
            'tags' => ['NotaryRequest.php', 'NotarySession.php', 'resources/views/livewire/notary-requests'],
            'category' => 'workflows',
        ],
        'multisigner' => [
            'name' => 'Multi-signer workflow',
            'description' => 'Define signing order, approval chains, and roles with ease. Supports both sequential and parallel signing flows with automated reminders to keep signers on track.',
            'icon' => 'heroicon-o-user-group',
            'badge' => null,
            'url' => '/features/multi-signer-workflow',
            'tags' => ['SignDocumentController.php', 'routes/web.php'],
            'category' => 'workflows',
        ],
        'portal' => [
            'name' => 'Notary / attorney portal',
            'description' => 'A dedicated portal for notaries and attorneys covering case management, calendar scheduling, client records, credential management, payment tracking, attorney registries, and official notarial register entries.',
            'icon' => 'heroicon-o-briefcase',
            'badge' => 'Core',
            'badge_style' => 'bg-blue-50 text-blue-700',
            'url' => '/features/notary-portal',
            'tags' => ['resources/views/livewire/notary', 'resources/views/components/layouts/app/sidebar.blade.php'],
            'category' => 'portal',
        ],
        'trust' => [
            'name' => 'Trust profile & onboarding',
            'description' => 'Multi-step identity verification including email confirmation, mobile OTP, eKYC via Sumsub and Tesseract OCR, MFA setup, profile photo, signature capture, seal upload, and the attorney application review flow.',
            'icon' => 'heroicon-o-shield-check',
            'badge' => null,
            'url' => '/features/trust-profile',
            'tags' => ['routes/auth.php', 'resources/views/livewire/settings/trust-profile.blade.php'],
            'category' => 'portal',
        ],
        'tracking' => [
            'name' => 'Real-time tracking',
            'description' => 'Monitor every document\'s status from sent to fully signed in real time. Live dashboards with Livewire polling, push notifications, and automated follow-up reminders keep all parties informed.',
            'icon' => 'heroicon-o-bolt',
            'badge' => null,
            'url' => '/features/real-time-tracking',
            'tags' => ['wire:poll.5s', 'NotaryVideoSession'],
            'category' => 'portal',
        ],
        'audit' => [
            'name' => 'Audit trail logs',
            'description' => 'A complete, court-admissible history of every action — who signed, when, from which IP — stored as immutable audit events for full transparency and compliance.',
            'icon' => 'heroicon-o-clipboard-document-list',
            'badge' => null,
            'url' => '/features/audit-trail-logs',
            'tags' => ['SignatureComplianceController.php', 'DocumentCertificateController.php'],
            'category' => 'compliance',
        ],
        'verify' => [
            'name' => 'Verification & compliance',
            'description' => 'Public-facing document and notary verification, PAdES-aligned signature compliance reports, and automated signer certificate validity checks including revocation status via OCSP.',
            'icon' => 'heroicon-o-check-badge',
            'badge' => null,
            'url' => '/features/verification',
            'tags' => ['verify routes', 'SignatureComplianceController.php', 'DocumentCertificateController.php'],
            'category' => 'compliance',
        ],
        'admin' => [
            'name' => 'Admin & billing',
            'description' => 'Platform-wide dashboard covering user management, signing activity, attorney application review, compliance monitoring, BIR EIS e-invoice submission and recovery, and billing profile administration.',
            'icon' => 'heroicon-o-cog-6-tooth',
            'badge' => null,
            'url' => '/features/admin',
            'tags' => ['resources/views/livewire/admin', 'resources/views/livewire/notary-admin'],
            'category' => 'compliance',
        ],
        'payments' => [
            'name' => 'Payments & billing',
            'description' => 'GatewayHub webhook handling, public notary payment link generation, settlement fee processing, billing profile management, and e-invoice recovery and resubmission.',
            'icon' => 'heroicon-o-credit-card',
            'badge' => null,
            'url' => '/features/payments',
            'tags' => ['PublicNotaryPaymentController.php', 'GatewayHubWebhookController.php', 'routes/api.php'],
            'category' => 'compliance',
        ],
        'blockchain' => [
            'name' => 'Blockchain anchoring',
            'description' => 'Every completed document hash is anchored to the Polygon blockchain via a Node.js/Express sidecar. The on-chain record is publicly verifiable and tamper-proof.',
            'icon' => 'heroicon-o-link',
            'badge' => 'Infra',
            'badge_style' => 'bg-violet-50 text-violet-700',
            'url' => '/features/blockchain-verification',
            'tags' => ['blockchain-service/src/index.js', 'Hardhat 2.24', 'Ethers 6.15', 'Polygon'],
            'category' => 'infra',
        ],
        'pki' => [
            'name' => 'PKI / remote signing',
            'description' => 'Full CSC API v2 pipeline with OAuth 2.0 credential authorization, SAD lifecycle, PAdES-LTV timestamp embedding, SCEP certificate provisioning, and HSM-backed key operations.',
            'icon' => 'heroicon-o-lock-closed',
            'badge' => 'Infra',
            'badge_style' => 'bg-violet-50 text-violet-700',
            'url' => '/features/pki',
            'tags' => ['routes/scep.php', 'routes/ocsp.php', 'routes/crl.php', 'routes/hsm.php', 'CSC API v2'],
            'category' => 'infra',
        ],
        'docmgmt' => [
            'name' => 'Smart document management',
            'description' => 'Organize, tag, version, and retrieve documents with intelligent folder structures. Quickly find any file by signer, date, status, or custom tag.',
            'icon' => 'heroicon-o-folder-open',
            'badge' => null,
            'url' => '/features/smart-document-management',
            'tags' => ['resources/views/livewire/templates', 'routes/web.php'],
            'category' => 'infra',
        ],
    ];

    public function selectFeature(string $key): void
    {
        if (array_key_exists($key, $this->features)) {
            $this->activeFeature = $key;
        }
    }

    public function iconPath(string $icon): string
    {
        return match ($icon) {
            'heroicon-o-pencil-square' => 'm16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10',
            'heroicon-o-document-duplicate' => 'M15.75 17.25v3.375c0 .621-.504 1.125-1.125 1.125h-9.75a1.125 1.125 0 0 1-1.125-1.125V7.875c0-.621.504-1.125 1.125-1.125H6.75m9 10.5h3.375c.621 0 1.125-.504 1.125-1.125V11.25c0-4.556-3.694-8.25-8.25-8.25H9.375c-.621 0-1.125.504-1.125 1.125v3.375m7.5 9.75H9.375A1.125 1.125 0 0 1 8.25 16.125v-8.25c0-.621.504-1.125 1.125-1.125h4.875a5.25 5.25 0 0 1 5.25 5.25v4.125c0 .621-.504 1.125-1.125 1.125Z',
            'heroicon-o-video-camera' => 'm15.75 10.5 4.72-4.72a.75.75 0 0 1 1.28.53v11.38a.75.75 0 0 1-1.28.53l-4.72-4.72M4.5 18.75h9a2.25 2.25 0 0 0 2.25-2.25v-9a2.25 2.25 0 0 0-2.25-2.25h-9A2.25 2.25 0 0 0 2.25 7.5v9a2.25 2.25 0 0 0 2.25 2.25Z',
            'heroicon-o-user-group' => 'M18 18.72a9.094 9.094 0 0 0 3.741-.479 3 3 0 0 0-4.682-2.72m.94 3.198.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0 1 12 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 0 1 6 18.719m12 0a5.971 5.971 0 0 0-.941-3.197m0 0A5.995 5.995 0 0 0 12 12.75a5.995 5.995 0 0 0-5.058 2.772m0 0a3 3 0 0 0-4.681 2.72 9.094 9.094 0 0 0 3.74.477m.94-3.197a5.971 5.971 0 0 0-.94 3.197M15 6.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 3a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Zm-13.5 0a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Z',
            'heroicon-o-briefcase' => 'M20.25 14.15v4.25c0 1.094-.787 2.036-1.872 2.18-2.087.277-4.216.42-6.378.42s-4.291-.143-6.378-.42c-1.085-.144-1.872-1.086-1.872-2.18v-4.25m16.5 0a2.18 2.18 0 0 0 .75-1.661V8.706c0-1.081-.768-2.015-1.837-2.175a48.114 48.114 0 0 0-3.413-.387m4.5 8.006c-.194.165-.42.295-.673.38A23.978 23.978 0 0 1 12 15.75c-2.648 0-5.195-.429-7.577-1.22a2.016 2.016 0 0 1-.673-.38m0 0A2.18 2.18 0 0 1 3 12.489V8.706c0-1.081.768-2.015 1.837-2.175a48.111 48.111 0 0 1 3.413-.387m7.5 0V5.25A2.25 2.25 0 0 0 13.5 3h-3a2.25 2.25 0 0 0-2.25 2.25v.894m7.5 0a48.667 48.667 0 0 0-7.5 0M12 12.75h.008v.008H12v-.008Z',
            'heroicon-o-shield-check' => 'M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.75c0 5.592 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.57-.598-3.75h-.152c-3.196 0-6.1-1.25-8.25-3.286Z',
            'heroicon-o-bolt' => 'm3.75 13.5 10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75Z',
            'heroicon-o-clipboard-document-list' => 'M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 0 0 2.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 0 0-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H5.625c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25Z',
            'heroicon-o-check-badge' => 'M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z',
            'heroicon-o-cog-6-tooth' => 'M4.5 12a7.5 7.5 0 0 0 .17 1.59l-1.44 1.11a.75.75 0 0 0-.16.99l1.36 2.36a.75.75 0 0 0 .95.32l1.7-.68a7.6 7.6 0 0 0 2.75 1.59l.26 1.82a.75.75 0 0 0 .74.64h2.74a.75.75 0 0 0 .74-.64l.26-1.82a7.6 7.6 0 0 0 2.75-1.59l1.7.68a.75.75 0 0 0 .95-.32l1.36-2.36a.75.75 0 0 0-.16-.99l-1.44-1.11A7.5 7.5 0 0 0 19.5 12c0-.543-.058-1.072-.17-1.59l1.44-1.11a.75.75 0 0 0 .16-.99l-1.36-2.36a.75.75 0 0 0-.95-.32l-1.7.68a7.6 7.6 0 0 0-2.75-1.59l-.26-1.82a.75.75 0 0 0-.74-.64h-2.74a.75.75 0 0 0-.74.64l-.26 1.82A7.6 7.6 0 0 0 6.68 6.31l-1.7-.68a.75.75 0 0 0-.95.32L2.67 8.31a.75.75 0 0 0 .16.99l1.44 1.11A7.5 7.5 0 0 0 4.5 12Zm7.5 3.25a3.25 3.25 0 1 0 0-6.5 3.25 3.25 0 0 0 0 6.5Z',
            'heroicon-o-credit-card' => 'M2.25 8.25h19.5M4.5 5.25h15A2.25 2.25 0 0 1 21.75 7.5v9A2.25 2.25 0 0 1 19.5 18.75h-15A2.25 2.25 0 0 1 2.25 16.5v-9A2.25 2.25 0 0 1 4.5 5.25Zm.75 10.5h3v.008h-3v-.008Zm4.5 0h1.5v.008h-1.5v-.008Z',
            'heroicon-o-link' => 'M13.19 8.688a4.5 4.5 0 0 1 1.242 7.244l-4.5 4.5a4.5 4.5 0 0 1-6.364-6.364l1.757-1.757m13.35-.622 1.757-1.757a4.5 4.5 0 0 0-6.364-6.364l-4.5 4.5a4.5 4.5 0 0 0 1.242 7.244',
            'heroicon-o-lock-closed' => 'M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 0h10.5A2.25 2.25 0 0 1 19.5 12.75v6A2.25 2.25 0 0 1 17.25 21H6.75A2.25 2.25 0 0 1 4.5 18.75v-6A2.25 2.25 0 0 1 6.75 10.5Z',
            default => 'M3.75 9.75h4.5l2.25 2.25h9.75M3.75 9.75v7.5A2.25 2.25 0 0 0 6 19.5h12a2.25 2.25 0 0 0 2.25-2.25V12a2.25 2.25 0 0 0-2.25-2.25H10.5L8.25 7.5H6a2.25 2.25 0 0 0-2.25 2.25Z',
        };
    }
}; ?>

<section class="features-section" id="features">
    <style>
        .feature-category-group{margin-bottom:36px}
        .feature-category-label{
            display:flex;align-items:center;gap:12px;
            margin-bottom:18px;
            font-size:.7rem;font-weight:800;letter-spacing:.15em;text-transform:uppercase;
            color:var(--text-dim);
        }
        .feature-category-label::after{
            content:'';height:1px;flex:1;
            background:var(--border2);
        }
        .features-expanded-grid{
            display:grid;
            grid-template-columns:repeat(4,1fr);
            gap:20px;
        }
        .features-expanded-grid.three{
            grid-template-columns:repeat(3,1fr);
        }
        .feature-card-control{
            display:block;
            width:100%;
            border:0;
            background:transparent;
            color:inherit;
            font:inherit;
            text-align:left;
            padding:0;
            cursor:pointer;
        }
        .feature-card.active{
            border-color:rgba(46,196,182,0.55);
            background:linear-gradient(135deg, rgba(13, 148, 136, 0.16), rgba(22, 163, 74, 0.1));
            box-shadow:0 22px 70px rgba(15,23,42,.1),0 0 0 1px rgba(46,196,182,.18);
        }
        html.dark-scheme .feature-card.active{
            background:linear-gradient(135deg,rgba(46,196,182,.14),rgba(27,94,32,.12));
            border-color:rgba(46,196,182,.32);
        }
        .features-expanded-grid .feature-card{
            min-height:260px;
            display:flex;
            flex-direction:column;
        }
        .features-expanded-grid .feature-card p{
            display:-webkit-box;
            -webkit-line-clamp:3;
            -webkit-box-orient:vertical;
            overflow:hidden;
        }
        .feature-detail-panel{
            background:var(--surface);
            border:1px solid var(--border);
            border-left:4px solid var(--teal);
            border-radius:22px;
            padding:34px;
            box-shadow:0 20px 60px rgba(15,23,42,.08);
        }
        html.dark-scheme .feature-detail-panel{
            box-shadow:0 20px 60px rgba(0,0,0,.35);
        }
        .feature-detail-kicker{
            font-size:.7rem;font-weight:800;letter-spacing:.14em;text-transform:uppercase;
            color:var(--teal);
            margin-bottom:10px;
        }
        .feature-detail-title{
            font-family:var(--font-display);
            font-weight:800;
            font-size:1.8rem;
            line-height:1.2;
            color:var(--headline);
            margin-bottom:12px;
        }
        .feature-detail-text{
            color:var(--text-muted);
            font-size:1rem;
            line-height:1.8;
            max-width:900px;
            margin-bottom:22px;
        }
        .feature-detail-tags{
            display:flex;
            flex-wrap:wrap;
            gap:8px;
            margin-bottom:24px;
        }
        .feature-detail-tags code{
            background:var(--chip-bg);
            border:1px solid var(--border2);
            border-radius:8px;
            color:var(--text-muted);
            font-size:.76rem;
            padding:5px 9px;
        }
        .feature-detail-cta{
            display:inline-flex;align-items:center;gap:8px;
            background:linear-gradient(135deg,var(--teal),var(--green-mid));
            color:#fff;
            text-decoration:none;
            font-weight:800;
            font-size:.92rem;
            padding:12px 18px;
            border-radius:12px;
            box-shadow:0 14px 38px rgba(46,196,182,.25);
            transition:transform .2s,box-shadow .2s;
        }
        .feature-detail-cta:hover{
            transform:translateY(-2px);
            box-shadow:0 18px 46px rgba(46,196,182,.34);
        }
        .feature-detail-cta svg{width:16px;height:16px}
        @media (max-width:1024px){
            .features-expanded-grid,
            .features-expanded-grid.three{grid-template-columns:repeat(2,1fr)}
        }
        @media (max-width:640px){
            .features-expanded-grid,
            .features-expanded-grid.three{grid-template-columns:1fr}
            .feature-detail-panel{padding:26px}
            .feature-detail-title{font-size:1.5rem}
        }
    </style>

    <div class="container">
        <div class="section-head reveal">
            <div class="section-label">Platform capabilities</div>
            <h2 class="section-title">Everything You Need to Manage<br>Documents with Confidence</h2>
            <p class="section-sub">From legally binding e-notary workflows to PKI infrastructure and blockchain anchoring — one platform built for teams that cannot afford gaps in security or compliance.</p>
        </div>

        @foreach ([
            'workflows' => ['label' => 'Document workflows', 'grid' => ''],
            'portal' => ['label' => 'Attorney & notary portal', 'grid' => 'three'],
            'compliance' => ['label' => 'Compliance & security', 'grid' => ''],
            'infra' => ['label' => 'Infrastructure & PKI', 'grid' => 'three'],
        ] as $category => $group)
            <div class="feature-category-group reveal" wire:key="feature-category-{{ $category }}">
                <div class="feature-category-label">{{ $group['label'] }}</div>
                <div class="features-expanded-grid {{ $group['grid'] }}">
                @foreach (array_filter($features, fn ($f) => $f['category'] === $category) as $key => $feature)
                    <div
                        wire:key="feature-card-{{ $key }}"
                        class="feature-card {{ $activeFeature === $key ? 'active' : '' }}"
                    >
                        <button type="button" class="feature-card-control" wire:click="selectFeature('{{ $key }}')">
                        @if ($feature['badge'])
                            <div class="feature-badge">{{ $feature['badge'] }}</div>
                        @endif
                        <h3>{{ $feature['name'] }}</h3>
                        <p>{{ $feature['description'] }}</p>
                        </button>
                        <a class="feature-card-learn" href="{{ $feature['url'] }}" wire:click.stop>Learn more →</a>
                    </div>
                @endforeach
                </div>
            </div>
        @endforeach

        @if ($activeFeature && isset($features[$activeFeature]))
            @php $feature = $features[$activeFeature]; @endphp
            <div class="feature-detail-panel reveal">
                <div class="feature-detail-kicker">Selected capability</div>
                <h3 class="feature-detail-title">{{ $feature['name'] }}</h3>
                <p class="feature-detail-text">{{ $feature['description'] }}</p>
                <div class="feature-detail-tags">
                    @foreach ($feature['tags'] as $tag)
                        <code wire:key="feature-detail-tag-{{ $activeFeature }}-{{ $loop->index }}">{{ $tag }}</code>
                    @endforeach
                </div>
                <a class="feature-detail-cta" href="{{ $feature['url'] }}">
                    View full specification
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 0 1 0-1.414L10.586 10 7.293 6.707a1 1 0 0 1 1.414-1.414l4 4a1 1 0 0 1 0 1.414l-4 4a1 1 0 0 1-1.414 0z" clip-rule="evenodd" />
                    </svg>
                </a>
            </div>
        @endif
    </div>
</section>
