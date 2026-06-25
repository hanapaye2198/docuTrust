@php
    $slug = $slug ?? 'secure-digital-signing';

    $featureArt = [
        'secure-digital-signing' => ['eyebrow' => 'SIGNATURE ENGINE',  'title' => 'Secure Digital Signing',        'metric' => 'PAdES-B',  'status' => 'OTP verified',           'type' => 'signing'],
        'blockchain-verification'=> ['eyebrow' => 'POLYGON ANCHOR',    'title' => 'Blockchain Verification',       'metric' => 'SHA-256',  'status' => 'Hash anchored',          'type' => 'blockchain'],
        'multi-signer-workflow'  => ['eyebrow' => 'ROUTING WORKFLOW',  'title' => 'Multi-Signer Workflow',         'metric' => '3 / 4',    'status' => 'Next signer queued',     'type' => 'signers'],
        'real-time-tracking'     => ['eyebrow' => 'LIVE MONITORING',   'title' => 'Real-Time Tracking',            'metric' => 'Live',     'status' => 'Polling every 5s',       'type' => 'tracking'],
        'audit-trail-logs'       => ['eyebrow' => 'EVIDENCE RECORD',   'title' => 'Audit Trail Logs',              'metric' => 'Immutable','status' => 'Court-ready log',        'type' => 'audit'],
        'smart-document-management'=>['eyebrow'=> 'DOCUMENT VAULT',    'title' => 'Smart Document Management',    'metric' => 'Indexed',  'status' => 'Smart search ready',     'type' => 'documents'],
        'templates'              => ['eyebrow' => 'REUSABLE FLOWS',    'title' => 'Templates & Contacts',          'metric' => 'Ready',    'status' => 'Roles mapped',           'type' => 'templates'],
        'enotary'                => ['eyebrow' => 'REMOTE NOTARY',     'title' => 'e-Notary Workflows',            'metric' => 'RON',      'status' => 'Identity confirmed',     'type' => 'enotary'],
        'notary-portal'          => ['eyebrow' => 'ATTORNEY HUB',      'title' => 'Notary / Attorney Portal',      'metric' => '24',       'status' => 'Cases active',           'type' => 'portal'],
        'trust-profile'          => ['eyebrow' => 'TRUST LAYER',       'title' => 'Trust Profile & Onboarding',   'metric' => 'MFA',      'status' => 'Profile verified',       'type' => 'trust'],
        'verification'           => ['eyebrow' => 'PUBLIC CHECK',      'title' => 'Verification & Compliance',    'metric' => 'Valid',    'status' => 'Authenticity confirmed', 'type' => 'verification'],
        'admin'                  => ['eyebrow' => 'CONTROL CENTER',    'title' => 'Admin & Billing',               'metric' => '1.2k',     'status' => 'Users monitored',        'type' => 'admin'],
        'payments'               => ['eyebrow' => 'GATEWAYHUB',        'title' => 'Payments & Billing',            'metric' => 'Paid',     'status' => 'Webhook received',       'type' => 'payments'],
        'pki'                    => ['eyebrow' => 'REMOTE SIGNING',    'title' => 'PKI / Remote Signing',          'metric' => 'CSC v2',   'status' => 'SAD authorized',         'type' => 'pki'],
    ];

    $art = $featureArt[$slug] ?? $featureArt['secure-digital-signing'];
@endphp

{{--
    LAYOUT GRID (absolute coordinates, viewBox 760 × 420)
    ─────────────────────────────────────────────────────
    Outer shell:     x=72   y=38   w=616  h=344  (to x=688, y=382)
    Inner border:    x=92   y=58   w=576  h=304
    Title-bar dots:  y=80   cx=124,144,164
    Accent line:     y=104
    LEFT COLUMN      x=92..370  (width ≈ 278px)
      Eyebrow text:  x=124  y=135
      Feature title: x=124  y=163
      Status text:   x=124  y=186
      Card A:        x=124  y=220  w=158  h=68  (bottom y=288)
      Card B:        x=124  y=300  w=178  h=68  (bottom y=368)
    RIGHT PANEL      x=386..668  (width ≈ 282px, centre x=527)
      Centred at x=527, usable y=120..370
      All case illustrations use ABSOLUTE coords in this zone.
    METRIC BADGE:    x=526  y=50   w=134  h=54   (floats top-right)
    ─────────────────────────────────────────────────────
--}}

<svg class="feature-illustration-svg"
     viewBox="0 0 760 420" width="100%"
     xmlns="http://www.w3.org/2000/svg"
     role="img"
     aria-label="{{ $art['title'] }} feature illustration">

    <defs>
        <radialGradient id="fh-glow-primary" cx="35%" cy="20%" r="75%">
            <stop offset="0%"   stop-color="var(--feature-illustration-glow-info)"    stop-opacity="1"/>
            <stop offset="48%"  stop-color="var(--feature-illustration-glow-info)"    stop-opacity=".32"/>
            <stop offset="100%" stop-color="var(--feature-illustration-glow-info)"    stop-opacity="0"/>
        </radialGradient>
        <radialGradient id="fh-glow-success" cx="75%" cy="70%" r="75%">
            <stop offset="0%"   stop-color="var(--feature-illustration-glow-success)" stop-opacity="1"/>
            <stop offset="55%"  stop-color="var(--feature-illustration-glow-success)" stop-opacity=".3"/>
            <stop offset="100%" stop-color="var(--feature-illustration-glow-success)" stop-opacity="0"/>
        </radialGradient>
        <linearGradient id="fh-panel" x1="72" y1="38" x2="688" y2="382" gradientUnits="userSpaceOnUse">
            <stop offset="0%"   stop-color="var(--feature-illustration-panel-start)" stop-opacity="1"/>
            <stop offset="100%" stop-color="var(--feature-illustration-panel-end)"   stop-opacity="1"/>
        </linearGradient>
        <linearGradient id="fh-accent-line" x1="124" y1="104" x2="636" y2="104" gradientUnits="userSpaceOnUse">
            <stop offset="0%"   stop-color="var(--color-text-info)"    stop-opacity=".05"/>
            <stop offset="48%"  stop-color="var(--color-text-info)"    stop-opacity=".9"/>
            <stop offset="100%" stop-color="var(--color-text-success)" stop-opacity=".05"/>
        </linearGradient>
        <filter id="fh-soft-shadow" x="-10%" y="-10%" width="120%" height="140%">
            <feDropShadow dx="0" dy="14" stdDeviation="16"
                flood-color="var(--feature-illustration-shadow-info)" flood-opacity=".45"/>
        </filter>
        <filter id="fh-badge-shadow" x="-30%" y="-30%" width="160%" height="160%">
            <feDropShadow dx="0" dy="8" stdDeviation="10"
                flood-color="var(--feature-illustration-shadow-success)" flood-opacity=".45"/>
        </filter>
    </defs>

    <style>
        .fh-muted          { fill: var(--color-text-secondary); }
        .fh-primary        { fill: var(--color-text-primary); }
        .fh-info           { fill: var(--color-text-info); }
        .fh-success        { fill: var(--color-text-success); }
        .fh-card           { fill: var(--color-background-secondary); stroke: var(--feature-illustration-panel-border); stroke-width: 1; }
        .fh-soft           { fill: var(--color-background-tertiary);  stroke: var(--feature-illustration-inner-border); stroke-width: 1; }
        .fh-line           { stroke: var(--feature-illustration-line); fill: none; }
        .fh-info-stroke    { stroke: var(--color-text-info);    fill: none; }
        .fh-success-stroke { stroke: var(--color-text-success); fill: none; }
        .fh-mono { font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", monospace; }
        .fh-sans { font-family: var(--font-body, system-ui, sans-serif); }

        @media (prefers-reduced-motion: no-preference) {
            .fh-reveal      { animation: fhReveal .65s cubic-bezier(.2,.8,.2,1) both; }
            .fh-float       { animation: fhFloat  5.8s ease-in-out infinite; }
            .fh-float-alt   { animation: fhFloatAlt 6.4s ease-in-out infinite; }
            .fh-pulse-ring  { animation: fhPulseRing 2.4s ease-in-out infinite; }
            .fh-pulse-fill  { animation: fhPulseFill 2.4s ease-in-out infinite; }
            .fh-draw        { stroke-dasharray: 500; stroke-dashoffset: 500; animation: fhDraw 1.6s ease .5s forwards; }
            .fh-scan        { animation: fhScan 2.8s ease-in-out infinite; }
            .fh-s1          { animation: fhStep .5s cubic-bezier(.2,.8,.2,1) .08s both; }
            .fh-s2          { animation: fhStep .5s cubic-bezier(.2,.8,.2,1) .20s both; }
            .fh-s3          { animation: fhStep .5s cubic-bezier(.2,.8,.2,1) .32s both; }
            .fh-s4          { animation: fhStep .5s cubic-bezier(.2,.8,.2,1) .44s both; }
            .fh-p1          { animation: fhParticle 4.8s ease-in-out 0s    infinite; }
            .fh-p2          { animation: fhParticle 4.8s ease-in-out .8s   infinite; }
            .fh-p3          { animation: fhParticle 4.8s ease-in-out 1.6s  infinite; }

            @keyframes fhReveal     { from { opacity:0; transform:translateY(16px) scale(.988); } to { opacity:1; transform:none; } }
            @keyframes fhFloat      { 0%,100%{transform:translateY(0);}        50%{transform:translateY(-9px);} }
            @keyframes fhFloatAlt   { 0%,100%{transform:translateY(0) translateX(0);} 50%{transform:translateY(7px) translateX(-5px);} }
            @keyframes fhPulseRing  { 0%,100%{opacity:.5;  r:50;} 50%{opacity:.95; r:56;} }
            @keyframes fhPulseFill  { 0%,100%{opacity:.65; transform:scale(1);} 50%{opacity:1; transform:scale(1.05);} }
            @keyframes fhDraw       { to{stroke-dashoffset:0;} }
            @keyframes fhScan       { 0%{transform:translateY(0);opacity:0;} 15%,70%{opacity:.85;} 100%{transform:translateY(120px);opacity:0;} }
            @keyframes fhStep       { from{opacity:0;transform:translateY(10px);} to{opacity:1;transform:none;} }
            @keyframes fhParticle   { 0%,100%{opacity:.28;transform:translateY(0);} 50%{opacity:.82;transform:translateY(-14px);} }
        }
    </style>

    {{-- Background glow layers --}}
    <rect width="760" height="420" rx="42" fill="url(#fh-glow-primary)"/>
    <rect width="760" height="420" rx="42" fill="url(#fh-glow-success)"/>

    {{-- Ambient particles --}}
    <circle class="fh-p1" cx="92"  cy="96"  r="4" fill="var(--color-text-info)"    opacity=".42"/>
    <circle class="fh-p2" cx="670" cy="78"  r="5" fill="var(--color-text-success)" opacity=".38"/>
    <circle class="fh-p3" cx="660" cy="326" r="3" fill="var(--color-text-info)"    opacity=".36"/>
    <circle class="fh-p2" cx="128" cy="322" r="5" fill="var(--color-text-success)" opacity=".32"/>
    <circle class="fh-p1" cx="704" cy="208" r="3" fill="var(--color-text-info)"    opacity=".48"/>
    <circle class="fh-p3" cx="72"  cy="218" r="3" fill="var(--color-text-success)" opacity=".40"/>

    {{-- ═══════════════════════════════════════════
         SHELL — outer panel frame (left + shared)
         ═══════════════════════════════════════════ --}}
    <g class="fh-reveal" filter="url(#fh-soft-shadow)">

        {{-- Outer card --}}
        <rect x="72" y="38" width="616" height="344" rx="34"
              fill="url(#fh-panel)"
              stroke="var(--feature-illustration-panel-border)" stroke-width="1.2"/>

        {{-- Inner inset border --}}
        <rect x="92" y="58" width="576" height="304" rx="26"
              fill="none"
              stroke="var(--feature-illustration-inner-border)" stroke-width="1"/>

        {{-- Accent rule below title bar --}}
        <line x1="124" y1="104" x2="636" y2="104"
              stroke="url(#fh-accent-line)" stroke-width="1.5"/>

        {{-- macOS-style traffic dots --}}
        <circle cx="124" cy="80" r="5" fill="var(--color-background-danger)"  stroke="var(--color-border-danger)"  stroke-width="1"/>
        <circle cx="144" cy="80" r="5" fill="var(--color-background-warning)" stroke="var(--color-border-warning)" stroke-width="1"/>
        <circle cx="164" cy="80" r="5" fill="var(--color-background-success)" stroke="var(--color-border-success)" stroke-width="1"/>

        {{-- ── Left column copy ── --}}
        <text x="124" y="135"
              class="fh-mono fh-info" font-size="11" font-weight="700" letter-spacing="1.8">{{ $art['eyebrow'] }}</text>

        <text x="124" y="163"
              class="fh-sans fh-primary" font-size="24" font-weight="800">{{ $art['title'] }}</text>

        <text x="124" y="186"
              class="fh-sans fh-muted" font-size="13">{{ $art['status'] }}</text>

        {{-- ── Left stat card A — Trust score ── --}}
        <g transform="translate(124 210)">
            <rect width="158" height="66" rx="16" class="fh-card"/>
            <text x="18" y="26" class="fh-mono fh-muted"    font-size="9">TRUST SCORE</text>
            <text x="18" y="50" class="fh-sans fh-primary"  font-size="22" font-weight="800">98%</text>
            {{-- mini sparkline --}}
            <path d="M98 48 C108 34 118 42 138 22"
                  class="fh-success-stroke" stroke-width="2.5" stroke-linecap="round"/>
        </g>

        {{-- ── Left stat card B — Security layer ── --}}
        <g transform="translate(124 292)">
            <rect width="178" height="66" rx="16" class="fh-card"/>
            <text x="18" y="26" class="fh-mono fh-muted"   font-size="9">SECURITY LAYER</text>
            <text x="18" y="50" class="fh-sans fh-primary" font-size="18" font-weight="800">Audit ready</text>
            <circle class="fh-pulse-fill" cx="150" cy="33" r="16"
                    fill="var(--color-background-info)" stroke="var(--color-border-info)"/>
        </g>

    </g>{{-- /fh-reveal --}}

    {{-- ═══════════════════════════════════════════
         METRIC BADGE — floats top-right
         x=526 y=50  w=134 h=54
         ═══════════════════════════════════════════ --}}
    <g class="fh-reveal fh-float" filter="url(#fh-badge-shadow)">
        <rect x="526" y="50" width="134" height="54" rx="18"
              fill="var(--color-background-success)" stroke="var(--color-border-success)" stroke-width="1.2"/>
        <text x="593" y="76" class="fh-sans fh-success"
              font-size="18" font-weight="800" text-anchor="middle">{{ $art['metric'] }}</text>
        <text x="593" y="94" class="fh-mono fh-muted"
              font-size="9" text-anchor="middle">VERIFIED</text>
    </g>

    {{-- ═══════════════════════════════════════════
         RIGHT PANEL ILLUSTRATIONS
         Absolute coordinate zone: x=390..668, y=118..370
         Centre x=529, centre y=244
         Each case fills this rectangle consistently.
         ═══════════════════════════════════════════ --}}
    <g class="fh-reveal">
    @switch($art['type'])

        {{-- ────────────────────────────────────────────
             SIGNING — document card with drawn signature
             ──────────────────────────────────────────── --}}
        @case('signing')
        <g class="fh-float">
            {{-- Document card: x=398 y=124 w=240 h=210 --}}
            <rect x="398" y="124" width="240" height="210" rx="22" class="fh-card"/>
            {{-- Text lines (content placeholders) --}}
            <rect x="424" y="156" width="170" height="8" rx="4" fill="var(--color-border-secondary)"/>
            <rect x="424" y="176" width="138" height="8" rx="4" fill="var(--color-border-secondary)"/>
            <rect x="424" y="196" width="156" height="8" rx="4" fill="var(--color-border-secondary)"/>
            {{-- Animated signature stroke --}}
            <path class="fh-draw fh-info-stroke"
                  d="M418 268 Q438 244 460 269 Q482 296 506 252 Q526 220 550 246 Q568 268 588 244"
                  stroke-width="3.5" stroke-linecap="round"/>
            {{-- Verified check badge --}}
            <circle cx="614" cy="148" r="22"
                    fill="var(--color-background-success)" stroke="var(--color-border-success)" stroke-width="1.5"/>
            <path d="M602 148 L610 157 L626 138"
                  class="fh-success-stroke" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
        </g>
        @break

        {{-- ────────────────────────────────────────────
             BLOCKCHAIN — 4 blocks in a chain
             ──────────────────────────────────────────── --}}
        @case('blockchain')
        <g class="fh-float">
            {{-- Dashed connector line behind blocks --}}
            <path class="fh-draw" fill="none"
                  d="M430 244 H482 M510 244 H562 M590 244 H642"
                  stroke="var(--color-text-info)" stroke-width="2.5"
                  stroke-linecap="round" stroke-dasharray="6 7"/>

            {{-- Block 1 --}}
            <g class="fh-s1">
                <rect x="392" y="206" width="76" height="76" rx="18"
                      fill="var(--color-background-info)" stroke="var(--color-border-info)" stroke-width="1.4"/>
                <text x="430" y="241" text-anchor="middle" class="fh-mono fh-info"    font-size="11" font-weight="800">0xA3</text>
                <text x="430" y="258" text-anchor="middle" class="fh-mono fh-muted"   font-size="9">BLOCK</text>
            </g>
            {{-- Block 2 --}}
            <g class="fh-s2">
                <rect x="474" y="206" width="76" height="76" rx="18"
                      fill="var(--color-background-info)" stroke="var(--color-border-info)" stroke-width="1.4"/>
                <text x="512" y="241" text-anchor="middle" class="fh-mono fh-info"    font-size="11" font-weight="800">0xB7</text>
                <text x="512" y="258" text-anchor="middle" class="fh-mono fh-muted"   font-size="9">BLOCK</text>
            </g>
            {{-- Block 3 --}}
            <g class="fh-s3">
                <rect x="556" y="206" width="76" height="76" rx="18"
                      fill="var(--color-background-info)" stroke="var(--color-border-info)" stroke-width="1.4"/>
                <text x="594" y="241" text-anchor="middle" class="fh-mono fh-info"    font-size="11" font-weight="800">0xD9</text>
                <text x="594" y="258" text-anchor="middle" class="fh-mono fh-muted"   font-size="9">BLOCK</text>
            </g>
            {{-- Block 4 (anchored, success) --}}
            <g class="fh-s4">
                <rect x="638" y="206" width="76" height="76" rx="18"
                      fill="var(--color-background-success)" stroke="var(--color-border-success)" stroke-width="1.4"/>
                <text x="676" y="241" text-anchor="middle" class="fh-mono fh-success" font-size="11" font-weight="800">0xF1</text>
                <text x="676" y="258" text-anchor="middle" class="fh-mono fh-muted"   font-size="9">ANCHORED</text>
                {{-- Pulse ring on last block --}}
                <circle class="fh-pulse-ring" cx="676" cy="244" r="50"
                        fill="none" stroke="var(--color-border-success)" stroke-width="1.8"/>
            </g>
        </g>
        @break

        {{-- ────────────────────────────────────────────
             SIGNERS — 3 avatar circles in sequence
             ──────────────────────────────────────────── --}}
        @case('signers')
        <g class="fh-float">
            {{-- Buyer (done — success) --}}
            <g class="fh-s1">
                <circle cx="434" cy="238" r="44"
                        fill="var(--color-background-success)" stroke="var(--color-border-success)" stroke-width="1.5"/>
                <circle cx="434" cy="226" r="14" fill="var(--color-text-success)" opacity=".72"/>
                <path d="M410 262 Q434 240 458 262" fill="none"
                      stroke="var(--color-text-success)" stroke-width="5" stroke-linecap="round"/>
                <text x="434" y="298" text-anchor="middle" class="fh-sans fh-muted" font-size="11">Buyer</text>
                {{-- Check badge --}}
                <circle cx="466" cy="206" r="12"
                        fill="var(--color-background-success)" stroke="var(--color-border-success)" stroke-width="1"/>
                <path d="M459 206 L464 211 L473 200" fill="none"
                      class="fh-success-stroke" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            </g>

            {{-- Connector A→B --}}
            <path class="fh-draw" fill="none"
                  d="M478 238 H524"
                  stroke="var(--color-text-info)" stroke-width="3" stroke-linecap="round"/>

            {{-- Seller (active — info) --}}
            <g class="fh-s2">
                <circle cx="556" cy="238" r="44"
                        fill="var(--color-background-info)" stroke="var(--color-border-info)" stroke-width="1.5"/>
                <circle cx="556" cy="226" r="14" fill="var(--color-text-info)" opacity=".72"/>
                <path d="M532 262 Q556 240 580 262" fill="none"
                      stroke="var(--color-text-info)" stroke-width="5" stroke-linecap="round"/>
                <text x="556" y="298" text-anchor="middle" class="fh-sans fh-muted" font-size="11">Seller</text>
                {{-- Pending badge --}}
                <circle class="fh-pulse-fill" cx="588" cy="206" r="12"
                        fill="var(--color-background-info)" stroke="var(--color-border-info)" stroke-width="1"/>
                <text x="588" y="210" text-anchor="middle" class="fh-mono fh-info" font-size="11">…</text>
            </g>

            {{-- Connector B→C (dashed = waiting) --}}
            <path fill="none"
                  d="M600 238 H646"
                  stroke="var(--color-border-secondary)" stroke-width="2"
                  stroke-dasharray="4 4" stroke-linecap="round"/>

            {{-- Notary (waiting — muted) --}}
            <g class="fh-s3">
                <circle cx="678" cy="238" r="44"
                        fill="var(--color-background-secondary)" stroke="var(--color-border-tertiary)" stroke-width="1.5"/>
                <circle cx="678" cy="226" r="14" fill="var(--color-text-secondary)" opacity=".35"/>
                <path d="M654 262 Q678 240 702 262" fill="none"
                      stroke="var(--color-text-secondary)" stroke-width="5" stroke-linecap="round" opacity=".4"/>
                <text x="678" y="298" text-anchor="middle" class="fh-sans fh-muted" font-size="11">Notary</text>
            </g>
        </g>
        @break

        {{-- ────────────────────────────────────────────
             TRACKING — vertical event timeline
             ──────────────────────────────────────────── --}}
        @case('tracking')
        <g class="fh-float">
            {{-- Card background --}}
            <rect x="392" y="120" width="272" height="224" rx="22" class="fh-card"/>
            {{-- Vertical spine --}}
            <line x1="438" y1="152" x2="438" y2="318" class="fh-line" stroke-width="2.5"/>

            {{-- Event 1: Sent --}}
            <g class="fh-s1">
                <circle cx="438" cy="158" r="10"
                        fill="var(--color-background-success)" stroke="var(--color-border-success)" stroke-width="1.4"/>
                <text x="460" y="163" class="fh-sans fh-primary" font-size="12" font-weight="700">Sent</text>
                <rect x="560" y="150" width="82" height="16" rx="8" class="fh-soft"/>
            </g>
            {{-- Event 2: Opened --}}
            <g class="fh-s2">
                <circle cx="438" cy="208" r="10"
                        fill="var(--color-background-success)" stroke="var(--color-border-success)" stroke-width="1.4"/>
                <text x="460" y="213" class="fh-sans fh-primary" font-size="12" font-weight="700">Opened</text>
                <rect x="560" y="200" width="82" height="16" rx="8" class="fh-soft"/>
            </g>
            {{-- Event 3: OTP --}}
            <g class="fh-s3">
                <circle cx="438" cy="258" r="10"
                        fill="var(--color-background-success)" stroke="var(--color-border-success)" stroke-width="1.4"/>
                <text x="460" y="263" class="fh-sans fh-primary" font-size="12" font-weight="700">OTP</text>
                <rect x="560" y="250" width="82" height="16" rx="8" class="fh-soft"/>
            </g>
            {{-- Event 4: Live (pulsing) --}}
            <g class="fh-s4">
                <circle class="fh-pulse-fill" cx="438" cy="308" r="10"
                        fill="var(--color-background-info)" stroke="var(--color-border-info)" stroke-width="1.4"/>
                <text x="460" y="313" class="fh-sans fh-info" font-size="12" font-weight="700">Signing live</text>
                <rect x="560" y="300" width="82" height="16" rx="8"
                      fill="var(--color-background-info)" stroke="var(--color-border-info)"/>
            </g>
        </g>
        @break

        {{-- ────────────────────────────────────────────
             AUDIT — log terminal card
             ──────────────────────────────────────────── --}}
        @case('audit')
        <g class="fh-float">
            {{-- Card --}}
            <rect x="392" y="120" width="278" height="224" rx="22" class="fh-card"/>
            {{-- Header bar --}}
            <rect x="392" y="120" width="278" height="44" rx="22" class="fh-soft"/>
            <rect x="392" y="142" width="278" height="22"          class="fh-soft"/>
            <text x="416" y="148" class="fh-mono fh-info" font-size="11" font-weight="800">AUDIT LOG</text>

            {{-- Row 1 --}}
            <g class="fh-s1">
                <text x="416" y="188" class="fh-mono fh-muted"    font-size="10">09:14</text>
                <text x="476" y="188" class="fh-sans fh-primary"  font-size="12" font-weight="600">Created</text>
                <line x1="416" y1="196" x2="648" y2="196" class="fh-line" stroke-width="1"/>
            </g>
            {{-- Row 2 --}}
            <g class="fh-s2">
                <text x="416" y="218" class="fh-mono fh-muted"    font-size="10">09:22</text>
                <text x="476" y="218" class="fh-sans fh-primary"  font-size="12" font-weight="600">OTP verified</text>
                <line x1="416" y1="226" x2="648" y2="226" class="fh-line" stroke-width="1"/>
            </g>
            {{-- Row 3 (success highlight) --}}
            <g class="fh-s3">
                <text x="416" y="248" class="fh-mono fh-muted"    font-size="10">09:24</text>
                <text x="476" y="248" class="fh-sans fh-success"  font-size="12" font-weight="600">Signed</text>
                <line x1="416" y1="256" x2="648" y2="256" class="fh-line" stroke-width="1"/>
            </g>
            {{-- Row 4 --}}
            <g class="fh-s4">
                <text x="416" y="278" class="fh-mono fh-muted"    font-size="10">09:25</text>
                <text x="476" y="278" class="fh-sans fh-info"     font-size="12" font-weight="600">Blockchain anchored</text>
                <line x1="416" y1="286" x2="648" y2="286" class="fh-line" stroke-width="1"/>
            </g>
            {{-- Immutable badge --}}
            <rect x="416" y="304" width="232" height="24" rx="12"
                  fill="var(--color-background-success)" stroke="var(--color-border-success)"/>
            <text x="532" y="321" text-anchor="middle"
                  class="fh-mono fh-success" font-size="10" font-weight="700">IMMUTABLE · COURT-ADMISSIBLE</text>
        </g>
        @break

        {{-- ────────────────────────────────────────────
             DOCUMENTS — folder tree
             ──────────────────────────────────────────── --}}
        @case('documents')
        <g class="fh-float">
            {{-- Tree connector lines --}}
            <path class="fh-draw fh-info-stroke"
                  d="M432 166 V256 M432 206 H470 M432 256 H470"
                  stroke-width="2.5" stroke-linecap="round"/>

            {{-- Root node --}}
            <g class="fh-s1">
                <rect x="392" y="140" width="172" height="46" rx="16"
                      fill="var(--color-background-info)" stroke="var(--color-border-info)"/>
                <text x="418" y="169" class="fh-sans fh-info" font-size="13" font-weight="800">All Documents</text>
            </g>
            {{-- Child: Real Estate --}}
            <g class="fh-s2">
                <rect x="470" y="184" width="158" height="42" rx="14" class="fh-card"/>
                <text x="490" y="210" class="fh-sans fh-primary" font-size="12" font-weight="700">📁 Real Estate</text>
            </g>
            {{-- Grandchild: Deed of Sale --}}
            <g class="fh-s3">
                <line x1="549" y1="226" x2="549" y2="244" class="fh-line" stroke-width="1.5"/>
                <line x1="549" y1="244" x2="580" y2="244" class="fh-line" stroke-width="1.5"/>
                <rect x="580" y="232" width="172" height="36" rx="10" class="fh-soft"/>
                <text x="596" y="255" class="fh-sans fh-muted" font-size="11">Deed of Sale 2024</text>
            </g>
            {{-- Child: Employment --}}
            <g class="fh-s3">
                <rect x="470" y="234" width="158" height="42" rx="14" class="fh-card"/>
                <text x="490" y="260" class="fh-sans fh-primary" font-size="12" font-weight="700">📁 Employment</text>
            </g>
            {{-- Grandchild: Offer Letter --}}
            <g class="fh-s4">
                <line x1="549" y1="276" x2="549" y2="294" class="fh-line" stroke-width="1.5"/>
                <line x1="549" y1="294" x2="580" y2="294" class="fh-line" stroke-width="1.5"/>
                <rect x="580" y="282" width="172" height="36" rx="10" class="fh-soft"/>
                <text x="596" y="305" class="fh-sans fh-muted" font-size="11">Offer Letter · Reyes J.</text>
            </g>
            {{-- Search pill --}}
            <rect x="392" y="318" width="360" height="32" rx="16" class="fh-soft"/>
            <text x="412" y="339" class="fh-sans fh-muted" font-size="11">🔍  Search by signer, date, or status…</text>
        </g>
        @break

        {{-- ────────────────────────────────────────────
             TEMPLATES — PDF with field chips
             ──────────────────────────────────────────── --}}
        @case('templates')
        <g class="fh-float">
            {{-- Document background --}}
            <rect x="406" y="122" width="230" height="226" rx="22" class="fh-card"/>
            {{-- Content lines --}}
            <rect x="430" y="154" width="174" height="8"  rx="4" fill="var(--color-border-secondary)"/>
            <rect x="430" y="174" width="140" height="8"  rx="4" fill="var(--color-border-secondary)"/>
            <rect x="430" y="194" width="160" height="8"  rx="4" fill="var(--color-border-secondary)"/>
            {{-- Separator --}}
            <line x1="430" y1="218" x2="604" y2="218" class="fh-line" stroke-width="1"/>
            {{-- Chip: Signature --}}
            <g class="fh-s1">
                <rect x="430" y="228" width="108" height="32" rx="12"
                      fill="var(--color-background-info)" stroke="var(--color-border-info)"/>
                <text x="484" y="249" text-anchor="middle"
                      class="fh-sans fh-info" font-size="11" font-weight="800">✍ Signature</text>
            </g>
            {{-- Chip: Date --}}
            <g class="fh-s2">
                <rect x="548" y="228" width="74" height="32" rx="12"
                      fill="var(--color-background-warning)" stroke="var(--color-border-warning)"/>
                <text x="585" y="249" text-anchor="middle"
                      class="fh-sans" fill="var(--color-text-warning)" font-size="11" font-weight="800">📅 Date</text>
            </g>
            {{-- Chip: Full name --}}
            <g class="fh-s3">
                <rect x="430" y="274" width="196" height="32" rx="12"
                      fill="var(--color-background-success)" stroke="var(--color-border-success)"/>
                <text x="528" y="295" text-anchor="middle"
                      class="fh-sans fh-success" font-size="11" font-weight="800">📝 Full name (Buyer)</text>
            </g>
            {{-- Template badge --}}
            <rect x="430" y="320" width="182" height="20" rx="10" class="fh-soft"/>
            <text x="521" y="334" text-anchor="middle"
                  class="fh-mono fh-muted" font-size="9">Deed of Sale v3</text>
        </g>
        @break

        {{-- ────────────────────────────────────────────
             ENOTARY — video frame + identity badge
             ──────────────────────────────────────────── --}}
        @case('enotary')
        <g class="fh-float">
            {{-- Main video frame --}}
            <rect x="392" y="128" width="204" height="162" rx="22"
                  fill="var(--color-background-tertiary)" stroke="var(--color-border-secondary)" stroke-width="1.5"/>
            {{-- Avatar in video --}}
            <circle cx="494" cy="196" r="36"
                    fill="var(--color-background-secondary)" stroke="var(--color-border-tertiary)"/>
            <circle cx="494" cy="183" r="14" fill="var(--color-text-secondary)" opacity=".6"/>
            <path d="M466 224 Q494 200 522 224"
                  fill="none" class="fh-line" stroke-width="6" stroke-linecap="round"/>
            {{-- REC dot --}}
            <circle class="fh-pulse-fill" cx="414" cy="146" r="7"
                    fill="var(--color-background-danger)" stroke="var(--color-border-danger)"/>
            <text x="428" y="151" class="fh-mono fh-muted" font-size="9">REC</text>
            {{-- Signer label --}}
            <text x="494" y="308" text-anchor="middle" class="fh-sans fh-muted" font-size="11">Signer · Live</text>

            {{-- Attorney pip (small frame) --}}
            <g class="fh-float-alt">
                <rect x="556" y="226" width="88" height="66" rx="14" class="fh-card"/>
                <circle cx="600" cy="250" r="15" fill="var(--color-background-secondary)"/>
                <text x="600" y="256" text-anchor="middle" class="fh-sans fh-muted" font-size="13">⚖</text>
                <text x="600" y="282" text-anchor="middle" class="fh-mono fh-muted" font-size="9">Attorney</text>
            </g>

            {{-- eKYC verified badge --}}
            <g class="fh-s4">
                <circle class="fh-pulse-ring" cx="622" cy="164" r="44"
                        fill="none" stroke="var(--color-border-success)" stroke-width="1.8"/>
                <circle cx="622" cy="164" r="38"
                        fill="var(--color-background-success)" stroke="var(--color-border-success)" stroke-width="1.5"/>
                <path d="M606 164 L618 176 L640 146"
                      fill="none" class="fh-success-stroke"
                      stroke-width="5" stroke-linecap="round" stroke-linejoin="round"/>
                <text x="622" y="194" text-anchor="middle" class="fh-mono fh-success" font-size="9">eKYC OK</text>
            </g>
        </g>
        @break

        {{-- ────────────────────────────────────────────
             PORTAL — stat cards + session list
             ──────────────────────────────────────────── --}}
        @case('portal')
        <g class="fh-float">
            {{-- Stat card: Cases --}}
            <g class="fh-s1">
                <rect x="392" y="124" width="92" height="80" rx="18"
                      fill="var(--color-background-info)" stroke="var(--color-border-info)"/>
                <text x="438" y="158" text-anchor="middle" class="fh-sans fh-info"   font-size="22" font-weight="900">24</text>
                <text x="438" y="176" text-anchor="middle" class="fh-mono fh-muted"  font-size="9">CASES</text>
            </g>
            {{-- Stat card: Fees --}}
            <g class="fh-s2">
                <rect x="496" y="124" width="92" height="80" rx="18"
                      fill="var(--color-background-success)" stroke="var(--color-border-success)"/>
                <text x="542" y="158" text-anchor="middle" class="fh-sans fh-success" font-size="18" font-weight="900">₱8.4k</text>
                <text x="542" y="176" text-anchor="middle" class="fh-mono fh-muted"  font-size="9">FEES</text>
            </g>
            {{-- Stat card: PKI --}}
            <g class="fh-s3">
                <rect x="600" y="124" width="92" height="80" rx="18"
                      fill="var(--color-background-warning)" stroke="var(--color-border-warning)"/>
                <text x="646" y="158" text-anchor="middle" class="fh-sans"
                      fill="var(--color-text-warning)" font-size="18" font-weight="900">PKI</text>
                <text x="646" y="176" text-anchor="middle" class="fh-mono fh-muted" font-size="9">VALID</text>
            </g>

            {{-- Session list card --}}
            <rect x="392" y="220" width="300" height="118" rx="18" class="fh-card"/>
            <text x="414" y="244" class="fh-sans fh-primary" font-size="13" font-weight="800">Upcoming sessions</text>
            <line x1="414" y1="252" x2="668" y2="252" class="fh-line"/>
            <text x="414" y="274" class="fh-sans fh-muted"    font-size="12">Today 2:00 PM</text>
            <text x="560" y="274" class="fh-sans fh-info"     font-size="12" font-weight="700">Video</text>
            <text x="414" y="298" class="fh-sans fh-muted"    font-size="12">Thu 10:00 AM</text>
            <text x="560" y="298" class="fh-sans fh-muted"    font-size="12">Pending</text>
            <text x="414" y="322" class="fh-sans fh-muted"    font-size="12">Fri 3:30 PM</text>
            <text x="560" y="322" class="fh-sans fh-muted"    font-size="12">Scheduled</text>
        </g>
        @break

        {{-- ────────────────────────────────────────────
             TRUST — 4-node verification ring
             ──────────────────────────────────────────── --}}
        @case('trust')
        <g class="fh-float">
            {{-- Outer pulse ring --}}
            <circle class="fh-pulse-ring" cx="527" cy="244" r="108"
                    fill="none" stroke="var(--color-border-info)" stroke-width="1.8"/>
            {{-- Inner filled circle --}}
            <circle cx="527" cy="244" r="80"
                    fill="var(--color-background-info)" stroke="var(--color-border-info)" stroke-width="1.5"/>
            {{-- Central check --}}
            <path d="M505 244 L520 259 L552 224"
                  fill="none" class="fh-success-stroke"
                  stroke-width="7" stroke-linecap="round" stroke-linejoin="round"/>

            {{-- Node: Email (top-left) --}}
            <g class="fh-s1">
                <circle cx="434" cy="170" r="30" class="fh-card"/>
                <text x="434" y="175" text-anchor="middle" class="fh-mono fh-info" font-size="11" font-weight="800">Email</text>
            </g>
            {{-- Node: OTP (top-right) --}}
            <g class="fh-s2">
                <circle cx="620" cy="170" r="30" class="fh-card"/>
                <text x="620" y="175" text-anchor="middle" class="fh-mono fh-info" font-size="11" font-weight="800">OTP</text>
            </g>
            {{-- Node: ID (bottom-left) --}}
            <g class="fh-s3">
                <circle cx="434" cy="318" r="30" class="fh-card"/>
                <text x="434" y="323" text-anchor="middle" class="fh-mono fh-info" font-size="11" font-weight="800">ID</text>
            </g>
            {{-- Node: MFA (bottom-right) --}}
            <g class="fh-s4">
                <circle cx="620" cy="318" r="30" class="fh-card"/>
                <text x="620" y="323" text-anchor="middle" class="fh-mono fh-info" font-size="11" font-weight="800">MFA</text>
            </g>
        </g>
        @break

        {{-- ────────────────────────────────────────────
             VERIFICATION — scan + result badge
             ──────────────────────────────────────────── --}}
        @case('verification')
        <g class="fh-float">
            {{-- Document card to scan --}}
            <rect x="392" y="130" width="156" height="190" rx="20" class="fh-card"/>
            <rect x="416" y="160" width="104" height="8" rx="4" fill="var(--color-border-secondary)"/>
            <rect x="416" y="180" width="82"  height="8" rx="4" fill="var(--color-border-secondary)"/>
            <rect x="416" y="200" width="96"  height="8" rx="4" fill="var(--color-border-secondary)"/>
            {{-- Scan line (animates downward) --}}
            <line class="fh-scan"
                  x1="392" y1="162" x2="548" y2="162"
                  stroke="var(--color-text-info)" stroke-width="3" opacity=".8"/>
            {{-- Hash tag --}}
            <rect x="408" y="284" width="124" height="24" rx="12" class="fh-soft"/>
            <text x="470" y="300" text-anchor="middle" class="fh-mono fh-muted" font-size="9">SHA-256: a3f1b7…</text>

            {{-- Arrow --}}
            <path class="fh-draw fh-info-stroke"
                  d="M552 224 H582" stroke-width="3" stroke-linecap="round"/>

            {{-- Result circle --}}
            <g class="fh-s4">
                <circle class="fh-pulse-ring" cx="634" cy="224" r="56"
                        fill="none" stroke="var(--color-border-success)" stroke-width="1.8"/>
                <circle cx="634" cy="224" r="50"
                        fill="var(--color-background-success)" stroke="var(--color-border-success)" stroke-width="1.5"/>
                <path d="M612 224 L628 240 L658 200"
                      fill="none" class="fh-success-stroke"
                      stroke-width="6" stroke-linecap="round" stroke-linejoin="round"/>
                <text x="634" y="258" text-anchor="middle" class="fh-mono fh-success" font-size="9">AUTHENTIC</text>
            </g>
        </g>
        @break

        {{-- ────────────────────────────────────────────
             ADMIN — bar chart + KPI pills
             ──────────────────────────────────────────── --}}
        @case('admin')
        <g class="fh-float">
            {{-- Chart card --}}
            <rect x="392" y="124" width="216" height="210" rx="22" class="fh-card"/>
            {{-- X-axis baseline --}}
            <line x1="416" y1="302" x2="584" y2="302" class="fh-line" stroke-width="1.5"/>
            {{-- Bars (growing heights) --}}
            <g class="fh-s1"><rect x="424" y="256" width="22" height="46" rx="6"
                fill="var(--color-background-info)" stroke="var(--color-border-info)"/></g>
            <g class="fh-s2"><rect x="458" y="236" width="22" height="66" rx="6"
                fill="var(--color-background-info)" stroke="var(--color-border-info)"/></g>
            <g class="fh-s3"><rect x="492" y="214" width="22" height="88" rx="6"
                fill="var(--color-background-info)" stroke="var(--color-border-info)"/></g>
            <g class="fh-s4"><rect x="526" y="188" width="22" height="114" rx="6"
                fill="var(--color-background-success)" stroke="var(--color-border-success)"/></g>
            <g class="fh-s4"><rect x="560" y="166" width="22" height="136" rx="6"
                fill="var(--color-background-success)" stroke="var(--color-border-success)"/></g>
            {{-- X labels --}}
            <text x="435" y="318" text-anchor="middle" class="fh-mono fh-muted" font-size="9">Feb</text>
            <text x="469" y="318" text-anchor="middle" class="fh-mono fh-muted" font-size="9">Mar</text>
            <text x="503" y="318" text-anchor="middle" class="fh-mono fh-muted" font-size="9">Apr</text>
            <text x="537" y="318" text-anchor="middle" class="fh-mono fh-muted" font-size="9">May</text>
            <text x="571" y="318" text-anchor="middle" class="fh-mono fh-muted" font-size="9">Jun</text>

            {{-- KPI pills on the right --}}
            <g class="fh-s2">
                <rect x="622" y="138" width="50" height="50" rx="14"
                      fill="var(--color-background-success)" stroke="var(--color-border-success)"/>
                <text x="647" y="162" text-anchor="middle" class="fh-sans fh-success" font-size="14" font-weight="800">47</text>
                <text x="647" y="178" text-anchor="middle" class="fh-mono fh-muted"   font-size="8">NOTARIES</text>
            </g>
            <g class="fh-s3">
                <rect x="622" y="202" width="50" height="50" rx="14"
                      fill="var(--color-background-warning)" stroke="var(--color-border-warning)"/>
                <text x="647" y="226" text-anchor="middle" class="fh-sans"
                      fill="var(--color-text-warning)" font-size="14" font-weight="800">3</text>
                <text x="647" y="242" text-anchor="middle" class="fh-mono fh-muted"  font-size="8">PENDING</text>
            </g>
            <g class="fh-s4">
                <rect x="622" y="266" width="50" height="50" rx="14"
                      fill="var(--color-background-info)" stroke="var(--color-border-info)"/>
                <text x="647" y="290" text-anchor="middle" class="fh-sans fh-info"   font-size="11" font-weight="800">1.2k</text>
                <text x="647" y="306" text-anchor="middle" class="fh-mono fh-muted"  font-size="8">USERS</text>
            </g>
        </g>
        @break

        {{-- ────────────────────────────────────────────
             PAYMENTS — 3-step flow with confirmation bar
             ──────────────────────────────────────────── --}}
        @case('payments')
        <g class="fh-float">
            {{-- Step 1: Link --}}
            <g class="fh-s1">
                <rect x="392" y="164" width="90" height="90" rx="22"
                      fill="var(--color-background-info)" stroke="var(--color-border-info)"/>
                <circle cx="437" cy="196" r="17" fill="var(--color-text-info)" opacity=".7"/>
                <text x="437" y="240" text-anchor="middle" class="fh-sans fh-info" font-size="12" font-weight="800">Link</text>
            </g>
            {{-- Connector 1→2 --}}
            <path class="fh-draw fh-info-stroke"
                  d="M482 209 H510" stroke-width="3.5" stroke-linecap="round" stroke-dasharray="6 7"/>
            {{-- Step 2: Pay (GatewayHub) --}}
            <g class="fh-s2">
                <rect x="510" y="164" width="90" height="90" rx="22"
                      fill="var(--color-background-warning)" stroke="var(--color-border-warning)"/>
                <circle cx="555" cy="196" r="17" fill="var(--color-text-warning)" opacity=".7"/>
                <text x="555" y="240" text-anchor="middle" class="fh-sans"
                      fill="var(--color-text-warning)" font-size="12" font-weight="800">Pay</text>
            </g>
            {{-- Connector 2→3 --}}
            <path class="fh-draw fh-info-stroke"
                  d="M600 209 H628" stroke-width="3.5" stroke-linecap="round" stroke-dasharray="6 7"/>
            {{-- Step 3: Receipt --}}
            <g class="fh-s3">
                <rect x="628" y="164" width="90" height="90" rx="22"
                      fill="var(--color-background-success)" stroke="var(--color-border-success)"/>
                <circle cx="673" cy="196" r="17" fill="var(--color-text-success)" opacity=".7"/>
                <text x="673" y="240" text-anchor="middle" class="fh-sans fh-success" font-size="12" font-weight="800">Receipt</text>
            </g>
            {{-- Confirmation banner --}}
            <g class="fh-s4">
                <rect x="392" y="278" width="326" height="36" rx="14"
                      fill="var(--color-background-success)" stroke="var(--color-border-success)"/>
                <text x="555" y="302" text-anchor="middle"
                      class="fh-sans fh-success" font-size="12" font-weight="800">✓ Webhook confirmed · BIR receipt issued</text>
            </g>
        </g>
        @break

        {{-- ────────────────────────────────────────────
             PKI — 4-step pipeline with TSA footer
             ──────────────────────────────────────────── --}}
        @case('pki')
        <g class="fh-float">
            {{-- Step 1: Auth --}}
            <g class="fh-s1">
                <rect x="392" y="154" width="68" height="92" rx="18"
                      fill="var(--color-background-info)" stroke="var(--color-border-info)"/>
                <rect x="412" y="174" width="28" height="28" rx="8"
                      fill="var(--color-text-info)" opacity=".68"/>
                <text x="426" y="226" text-anchor="middle" class="fh-sans fh-info"   font-size="12" font-weight="800">Auth</text>
                <text x="426" y="240" text-anchor="middle" class="fh-mono fh-muted"  font-size="9">SAD</text>
            </g>
            {{-- Connector 1→2 --}}
            <path class="fh-draw fh-info-stroke" d="M460 200 H476" stroke-width="3" stroke-linecap="round"/>
            {{-- Step 2: Hash --}}
            <g class="fh-s2">
                <rect x="476" y="154" width="68" height="92" rx="18"
                      fill="var(--color-background-info)" stroke="var(--color-border-info)"/>
                <rect x="496" y="174" width="28" height="28" rx="8"
                      fill="var(--color-text-info)" opacity=".68"/>
                <text x="510" y="226" text-anchor="middle" class="fh-sans fh-info"   font-size="12" font-weight="800">Hash</text>
                <text x="510" y="240" text-anchor="middle" class="fh-mono fh-muted"  font-size="9">256</text>
            </g>
            {{-- Connector 2→3 --}}
            <path class="fh-draw fh-info-stroke" d="M544 200 H560" stroke-width="3" stroke-linecap="round"/>
            {{-- Step 3: Sign (HSM) --}}
            <g class="fh-s3">
                <rect x="560" y="154" width="68" height="92" rx="18"
                      fill="var(--color-background-info)" stroke="var(--color-border-info)"/>
                <rect x="580" y="174" width="28" height="28" rx="8"
                      fill="var(--color-text-info)" opacity=".68"/>
                <text x="594" y="226" text-anchor="middle" class="fh-sans fh-info"   font-size="12" font-weight="800">Sign</text>
                <text x="594" y="240" text-anchor="middle" class="fh-mono fh-muted"  font-size="9">HSM</text>
            </g>
            {{-- Connector 3→4 --}}
            <path class="fh-draw fh-success-stroke" d="M628 200 H644" stroke-width="3" stroke-linecap="round"/>
            {{-- Step 4: Embed (success) --}}
            <g class="fh-s4">
                <rect x="644" y="154" width="68" height="92" rx="18"
                      fill="var(--color-background-success)" stroke="var(--color-border-success)"/>
                <rect x="664" y="174" width="28" height="28" rx="8"
                      fill="var(--color-text-success)" opacity=".68"/>
                <text x="678" y="226" text-anchor="middle" class="fh-sans fh-success" font-size="12" font-weight="800">Embed</text>
                <text x="678" y="240" text-anchor="middle" class="fh-mono fh-muted"   font-size="9">LTV</text>
            </g>
            {{-- TSA footer bar --}}
            <rect x="392" y="268" width="320" height="30" rx="12" class="fh-soft"/>
            <text x="552" y="288" text-anchor="middle"
                  class="fh-mono fh-muted" font-size="10">GlobalSign TSA · OCSP · CRL validation</text>
        </g>
        @break

        {{-- ── Default fallback ── --}}
        @default
        <g class="fh-float">
            <rect x="406" y="140" width="230" height="190" rx="24" class="fh-card"/>
            <circle class="fh-pulse-fill" cx="521" cy="235" r="54"
                    fill="var(--color-background-info)" stroke="var(--color-border-info)"/>
        </g>

    @endswitch
    </g>{{-- /fh-reveal (right panel) --}}

</svg>
