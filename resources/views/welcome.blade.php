<!DOCTYPE html>
@php
  $authenticatedUser = auth()->user();
  $primaryCtaUrl = $authenticatedUser ? route($authenticatedUser->homeRouteName()) : route('register');
  $primaryCtaLabel = $authenticatedUser ? __('Open workspace') : __('Start Free Trial');
  $secondaryHeaderUrl = $authenticatedUser ? route('settings.profile') : route('login');
  $secondaryHeaderLabel = $authenticatedUser ? __('Settings') : __('Login');
@endphp
<html lang="en" class="docutrust-smooth-scroll">
<head>
<script>
(function () {
  function docuTrustSyncColorScheme () {
    var m = window.matchMedia('(prefers-color-scheme: light)');
    document.documentElement.classList.toggle('light-scheme', m.matches);
  }
  docuTrustSyncColorScheme();
  var mq = window.matchMedia('(prefers-color-scheme: light)');
  if (typeof mq.addEventListener === 'function') {
    mq.addEventListener('change', docuTrustSyncColorScheme);
  } else if (typeof mq.addListener === 'function') {
    mq.addListener(docuTrustSyncColorScheme);
  }
})();
</script>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="{{ csrf_token() }}">
<meta name="color-scheme" content="dark light">
<meta name="theme-color" content="#060d10" media="(prefers-color-scheme: dark)">
<meta name="theme-color" content="#f0f7f5" media="(prefers-color-scheme: light)">
<title>DocuTrust | Secure & Tamper-Proof Digital Signatures</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&family=Source+Sans+3:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="icon" href="{{ asset('images/docutrust-logo.png') }}" type="image/png">
<style>
:root {
  --font-body: 'Source Sans 3', system-ui, sans-serif;
  --font-display: 'Outfit', system-ui, sans-serif;
  --teal: #2EC4B6;
  --teal-dark: #1a9e92;
  --teal-light: #7ce8dc;
  --green: #1B5E20;
  --green-mid: #2d7a35;
  --gold: #FFD166;
  --bg: #060d10;
  --surface: #0d1a1f;
  --surface2: #112028;
  --border: rgba(46,196,182,0.15);
  --border2: rgba(46,196,182,0.08);
  --text: #e8f4f2;
  --text-muted: #7a9e9b;
  --text-dim: #4a706d;
  --headline: #ffffff;
  --header-bg: rgba(6,13,16,0.88);
  --mobile-nav-bg: rgba(6,13,16,0.98);
  --trust-bar-bg: rgba(13,26,31,0.7);
  --badge-float2-bg: rgba(6,13,16,0.92);
  --footer-bg: rgba(6,13,16,0.9);
  --overlay-dark: rgba(0,0,0,0.3);
  --overlay-signer: rgba(0,0,0,0.25);
  --overlay-kpi: rgba(0,0,0,0.3);
  --progress-track: rgba(255,255,255,0.06);
  --chip-bg: rgba(255,255,255,0.04);
  --ai-bubble-bg: rgba(0,0,0,0.3);
  --logo-tile-bg: #0a0a0a;
  --logo-tile-shadow: 0 0 20px rgba(46,196,182,0.2);
}

@media (prefers-color-scheme: light) {
  :root {
    --teal: #0d9488;
    --teal-dark: #0f766e;
    --teal-light: #0f766e;
    --green: #166534;
    --green-mid: #15803d;
    --gold: #b45309;
    --bg: #f0f7f5;
    --surface: #ffffff;
    --surface2: #e8f3f0;
    --border: rgba(13, 148, 136, 0.2);
    --border2: rgba(13, 148, 136, 0.1);
    --text: #0f172a;
    --text-muted: #475569;
    --text-dim: #64748b;
    --headline: #0a1917;
    --header-bg: rgba(255, 255, 255, 0.92);
    --mobile-nav-bg: rgba(255, 255, 255, 0.98);
    --trust-bar-bg: rgba(255, 255, 255, 0.86);
    --badge-float2-bg: rgba(255, 255, 255, 0.96);
    --footer-bg: rgba(248, 252, 251, 0.97);
    --overlay-dark: rgba(15, 23, 42, 0.06);
    --overlay-signer: rgba(15, 23, 42, 0.05);
    --overlay-kpi: rgba(15, 23, 42, 0.05);
    --progress-track: rgba(15, 23, 42, 0.08);
    --chip-bg: rgba(15, 23, 42, 0.04);
    --ai-bubble-bg: rgba(15, 23, 42, 0.06);
    --logo-tile-bg: #ffffff;
    --logo-tile-shadow: 0 2px 10px rgba(0,0,0,0.06), 0 0 0 1px rgba(0,0,0,0.08);
  }

  body::before {
    opacity: 0.35;
  }

  body::after {
    background-image:
      linear-gradient(rgba(46,196,182,0.07) 1px,transparent 1px),
      linear-gradient(90deg,rgba(46,196,182,0.07) 1px,transparent 1px);
  }

  .doc-card {
    box-shadow: 0 24px 80px rgba(15, 23, 42, 0.08), 0 0 0 1px rgba(46,196,182,0.06);
  }

  .ai-visual {
    box-shadow: 0 24px 80px rgba(15, 23, 42, 0.08);
  }

  .orb {
    opacity: 0.12;
  }

  .feature-card:hover {
    box-shadow: 0 20px 60px rgba(15, 23, 42, 0.08), 0 0 0 1px rgba(46,196,182,0.12);
  }

  .about-media {
    box-shadow: 0 18px 44px rgba(15, 23, 42, 0.12);
  }

  .about-media::after {
    background: linear-gradient(to top,rgba(15,23,42,0.35),rgba(15,23,42,0.06) 45%,transparent 75%);
  }

  .about-surepay {
    background: rgba(255,255,255,0.86);
    border: 1px solid rgba(13, 148, 136, 0.24);
    box-shadow: 0 8px 20px rgba(15, 23, 42, 0.12);
  }

  .about-surepay-label {
    color: var(--green);
  }

  .feature-card {
    background: linear-gradient(180deg, rgba(13, 148, 136, 0.06), rgba(22, 163, 74, 0.04));
    border-color: rgba(13, 148, 136, 0.2);
  }

  .feature-card.featured {
    background: linear-gradient(135deg, rgba(13, 148, 136, 0.14), rgba(22, 163, 74, 0.1));
    border-color: rgba(13, 148, 136, 0.3);
  }

  .feature-icon {
    background: rgba(13, 148, 136, 0.12);
    border-color: rgba(13, 148, 136, 0.24);
  }

  .badge-float {
    background: rgba(13, 148, 136, 0.14);
    border: 1px solid rgba(13, 148, 136, 0.25);
    box-shadow: 0 10px 28px rgba(15, 23, 42, 0.12);
  }

  .badge-float-text {
    color: var(--green);
  }

  .badge-float-icon {
    background: rgba(13, 148, 136, 0.14);
  }

  .badge-float-icon svg {
    color: var(--teal-dark);
  }
}

*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
html{color-scheme:dark light;font-size:18px}
@media (prefers-reduced-motion:no-preference){
  html.docutrust-smooth-scroll{scroll-behavior:smooth}
}
body{
  font-family:var(--font-body);
  background:var(--bg);
  color:var(--text);
  overflow-x:hidden;
  line-height:1.72;
}

/* ── NOISE TEXTURE ── */
body::before{
  content:'';
  position:fixed;
  inset:0;
  background-image:url("data:image/svg+xml,%3Csvg viewBox='0 0 200 200' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)' opacity='0.04'/%3E%3C/svg%3E");
  pointer-events:none;
  z-index:0;
  opacity:.5;
}

/* ── GRID BG ── */
body::after{
  content:'';
  position:fixed;
  inset:0;
  background-image:
    linear-gradient(rgba(46,196,182,0.04) 1px,transparent 1px),
    linear-gradient(90deg,rgba(46,196,182,0.04) 1px,transparent 1px);
  background-size:60px 60px;
  pointer-events:none;
  z-index:0;
}

/* ── GLOW ORBS ── */
.orb{
  position:fixed;
  border-radius:50%;
  pointer-events:none;
  z-index:0;
  filter:blur(100px);
  opacity:.18;
  animation:orbFloat 12s ease-in-out infinite;
}
.orb1{width:500px;height:500px;background:var(--teal);top:-100px;right:-100px;animation-delay:0s}
.orb2{width:400px;height:400px;background:var(--green);bottom:-100px;left:-100px;animation-delay:-4s}
.orb3{width:300px;height:300px;background:var(--gold);top:50%;left:50%;transform:translate(-50%,-50%);animation-delay:-8s;opacity:.06}

@keyframes orbFloat{0%,100%{transform:translateY(0) scale(1)}50%{transform:translateY(-30px) scale(1.05)}}
.orb2{animation-name:orbFloat2}
@keyframes orbFloat2{0%,100%{transform:translateY(0)}50%{transform:translateY(25px)}}
.orb3{animation-name:orbFloat3}
@keyframes orbFloat3{0%,100%{transform:translate(-50%,-50%) scale(1)}50%{transform:translate(-50%,-50%) scale(1.2)}}

/* ── HEADER ── */
header{
  position:sticky;
  top:0;
  z-index:100;
  border-bottom:1px solid var(--border);
  background:var(--header-bg);
  backdrop-filter:blur(20px);
}
.header-inner{
  max-width:1280px;
  margin:0 auto;
  padding:0 24px;
  height:72px;
  display:flex;
  align-items:center;
  justify-content:space-between;
  gap:16px;
}
.logo{
  display:flex;
  align-items:center;
  gap:10px;
  text-decoration:none;
  flex-shrink:0;
}
/* Square tile + equal padding so the mark is centered with even inset (width:auto PNGs no longer widen the box). */
.logo-mark{
  display:grid;
  justify-items:stretch;
  align-items:stretch;
  line-height:0;
  background:var(--logo-tile-bg);
  border-radius:10px;
  padding:6px;
  box-sizing:border-box;
  width:52px;
  height:52px;
  box-shadow:var(--logo-tile-shadow);
}
.logo-mark picture{
  display:contents;
}
/* JS adds .light-scheme on <html> so tile + wordmark track system light mode reliably */
html.light-scheme{
  --logo-tile-bg:#ffffff;
  --logo-tile-shadow:0 2px 10px rgba(0,0,0,0.06),0 0 0 1px rgba(0,0,0,0.08);
}
html.light-scheme .feature-card{
  background: linear-gradient(180deg, rgba(13, 148, 136, 0.06), rgba(22, 163, 74, 0.04));
  border-color: rgba(13, 148, 136, 0.2);
}
html.light-scheme .feature-card.featured{
  background: linear-gradient(135deg, rgba(13, 148, 136, 0.14), rgba(22, 163, 74, 0.1));
  border-color: rgba(13, 148, 136, 0.3);
}
html.light-scheme .feature-icon{
  background: rgba(13, 148, 136, 0.12);
  border-color: rgba(13, 148, 136, 0.24);
}
html.light-scheme .about-media{
  box-shadow: 0 18px 44px rgba(15, 23, 42, 0.12);
}
html.light-scheme .about-media::after{
  background: linear-gradient(to top,rgba(15,23,42,0.35),rgba(15,23,42,0.06) 45%,transparent 75%);
}
html.light-scheme .about-surepay{
  background: rgba(255,255,255,0.86);
  border: 1px solid rgba(13, 148, 136, 0.24);
  box-shadow: 0 8px 20px rgba(15, 23, 42, 0.12);
}
html.light-scheme .about-surepay-label{
  color: var(--green);
}
html.light-scheme .badge-float{
  background: rgba(13, 148, 136, 0.14);
  border: 1px solid rgba(13, 148, 136, 0.25);
  box-shadow: 0 10px 28px rgba(15, 23, 42, 0.12);
}
html.light-scheme .badge-float-text{
  color: var(--green);
}
html.light-scheme .badge-float-icon{
  background: rgba(13, 148, 136, 0.14);
}
html.light-scheme .badge-float-icon svg{
  color: var(--teal-dark);
}
.logo-img{
  width:100%;
  height:100%;
  max-width:100%;
  max-height:100%;
  object-fit:contain;
  display:block;
  border-radius:8px;
}
/* Light theme: huwag gumamit ng screen blend — nagpapawala sa buong icon. Banayad na filter lang; pinakamaganda ay PNG na may transparent background. */
@media (prefers-color-scheme: light) {
  .logo-mark{
    background:#ffffff;
  }
  /* Banayad na ayos lang kung isang PNG lang; kung may docutrust-logo-light.png, walang filter */
  .logo-mark .logo-img{
    filter:brightness(1.12) contrast(1.05);
  }
  .logo-mark:has(picture) .logo-img{
    filter:none;
  }
}
html.light-scheme .logo-mark{
  background:#ffffff;
}
html.light-scheme .logo-mark .logo-img{
  filter:brightness(1.12) contrast(1.05);
}
html.light-scheme .logo-mark:has(picture) .logo-img{
  filter:none;
}
.logo-text{
  font-family:var(--font-display);
  font-weight:800;
  font-size:1.2rem;
  color:var(--text);
}
/* Light theme: solid brand color — mas mabasa kaysa gradient+transparent (iwas puti/hindi lumilitaw) */
@media (prefers-color-scheme: light) {
  .logo-text,
  .footer-logo > .logo-text {
    color:var(--teal-dark);
    background:none;
    -webkit-text-fill-color:currentColor;
    background-clip:border-box;
  }
}
html.light-scheme .logo-text,
html.light-scheme .footer-logo > .logo-text{
  color:var(--teal-dark);
  background:none;
  -webkit-text-fill-color:currentColor;
  background-clip:border-box;
}
.footer-logo .logo-mark{
  box-shadow:none;
  width:46px;
  height:46px;
  padding:5px;
}

nav{display:flex;align-items:center;gap:28px}
nav a{
  font-size:1rem;
  font-weight:500;
  color:var(--text-muted);
  text-decoration:none;
  transition:color .2s;
}
nav a:hover{color:var(--teal)}

.header-actions{display:flex;align-items:center;gap:10px;flex-shrink:1;min-width:0;justify-content:flex-end}
.btn-ghost{
  font-size:1rem;font-weight:500;
  color:var(--text-muted);
  text-decoration:none;
  padding:10px 18px;
  border-radius:8px;
  transition:color .2s;
}
.btn-ghost:hover{color:var(--teal)}
.btn-primary{
  font-size:1rem;font-weight:600;
  background:linear-gradient(135deg,var(--teal),var(--green-mid));
  color:#fff;
  text-decoration:none;
  padding:12px 22px;
  border-radius:10px;
  box-shadow:0 0 20px rgba(46,196,182,0.3);
  transition:all .25s;
  white-space:nowrap;
}
.btn-primary:hover{
  box-shadow:0 0 32px rgba(46,196,182,0.55);
  transform:translateY(-1px);
}

.nav-mobile-toggle{
  display:none;
  align-items:center;
  justify-content:center;
  flex-shrink:0;
  box-sizing:border-box;
  width:44px;
  height:44px;
  padding:0;
  margin:0;
  background:var(--chip-bg);
  border:1px solid var(--border);
  color:var(--text-muted);
  border-radius:12px;
  cursor:pointer;
  -webkit-tap-highlight-color:rgba(46,196,182,0.15);
  touch-action:manipulation;
  transition:background .15s,color .15s,border-color .15s,transform .1s;
  position:relative;
  z-index:5;
}
.nav-mobile-toggle:hover{
  border-color:rgba(46,196,182,0.35);
  color:var(--teal);
}
.nav-mobile-toggle:active{transform:scale(0.96)}
.nav-mobile-toggle:focus-visible{
  outline:2px solid var(--teal);
  outline-offset:2px;
}
body.mobile-nav-open{overflow:hidden;overscroll-behavior:none}

/* ── SECTIONS ── */
section{position:relative;z-index:1}
.container{max-width:1280px;margin:0 auto;padding:0 24px}

/* ── HERO ── */
.hero{
  min-height:85vh;
  display:flex;
  align-items:center;
  padding:64px 0 48px;
  overflow:hidden;
}
.hero-grid{
  display:grid;
  grid-template-columns:1fr 1fr;
  gap:48px;
  align-items:center;
  width:100%;
}
.hero-badge{
  display:inline-flex;
  align-items:center;
  gap:8px;
  background:rgba(46,196,182,0.1);
  border:1px solid rgba(46,196,182,0.25);
  color:var(--teal-light);
  font-size:.7rem;
  font-weight:600;
  letter-spacing:.08em;
  text-transform:uppercase;
  padding:5px 12px;
  border-radius:100px;
  margin-bottom:18px;
}
.hero-badge .dot{
  width:6px;height:6px;
  border-radius:50%;
  background:var(--teal);
  animation:pulse 2s ease-in-out infinite;
}
@keyframes pulse{0%,100%{opacity:1;transform:scale(1)}50%{opacity:.5;transform:scale(1.4)}}

.hero h1{
  font-family:var(--font-display);
  font-size:clamp(2.2rem,4.2vw,3.4rem);
  font-weight:800;
  line-height:1.18;
  color:var(--headline);
  margin-bottom:18px;
  letter-spacing:-0.01em;
}
.hero h1 .accent{
  background:linear-gradient(90deg,var(--teal),var(--teal-light));
  -webkit-background-clip:text;
  -webkit-text-fill-color:transparent;
  background-clip:text;
}
.hero h1 .accent2{
  background:linear-gradient(90deg,var(--gold),#f4a83a);
  -webkit-background-clip:text;
  -webkit-text-fill-color:transparent;
  background-clip:text;
}
.hero-sub{
  font-size:1.12rem;
  color:var(--text-muted);
  max-width:480px;
  margin-bottom:28px;
  line-height:1.7;
}
.hero-actions{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:28px}
.btn-cta{
  display:inline-flex;align-items:center;gap:8px;
  background:linear-gradient(135deg,var(--teal),var(--green-mid));
  color:#fff;
  font-size:1rem;font-weight:600;
  padding:14px 26px;
  border-radius:11px;
  text-decoration:none;
  box-shadow:0 0 32px rgba(46,196,182,0.4);
  transition:all .25s;
}
.btn-cta:hover{transform:translateY(-2px);box-shadow:0 0 48px rgba(46,196,182,0.6)}
.btn-secondary{
  display:inline-flex;align-items:center;gap:8px;
  background:transparent;
  color:var(--text);
  font-size:1rem;font-weight:500;
  padding:14px 26px;
  border-radius:11px;
  border:1px solid var(--border);
  text-decoration:none;
  transition:all .25s;
}
.btn-secondary:hover{border-color:var(--teal);color:var(--teal)}

.hero-trust{
  display:flex;
  flex-wrap:wrap;
  gap:20px;
  align-items:center;
}
.trust-item{
  display:flex;align-items:center;gap:7px;
  font-size:.95rem;color:var(--text-muted);
}
.trust-item svg{width:14px;height:14px;color:var(--teal);flex-shrink:0}

/* Hero visual */
.hero-visual{position:relative}
.doc-card{
  background:var(--surface);
  border:1px solid var(--border);
  border-radius:20px;
  padding:24px;
  box-shadow:0 24px 80px rgba(0,0,0,0.6),0 0 0 1px rgba(46,196,182,0.08);
  animation:cardFloat 5s ease-in-out infinite;
}
@keyframes cardFloat{0%,100%{transform:translateY(0)}50%{transform:translateY(-10px)}}
.doc-header{
  display:flex;
  align-items:center;
  justify-content:space-between;
  margin-bottom:20px;
}
.doc-title{
  font-family:var(--font-display);
  font-weight:700;
  font-size:1rem;
  color:var(--headline);
}
.doc-status{
  font-size:.7rem;font-weight:700;
  padding:4px 10px;
  border-radius:100px;
  background:rgba(46,196,182,0.15);
  color:var(--teal-light);
  border:1px solid rgba(46,196,182,0.3);
  text-transform:uppercase;
  letter-spacing:.06em;
}
.chain-vis{
  background:var(--overlay-dark);
  border:1px solid var(--border2);
  border-radius:12px;
  padding:16px;
  margin-bottom:16px;
  font-size:.75rem;
  color:var(--text-muted);
}
.chain-label{
  font-size:.65rem;letter-spacing:.1em;text-transform:uppercase;color:var(--text-dim);margin-bottom:8px
}
.chain-hash{
  font-family:monospace;
  font-size:.72rem;
  color:var(--teal);
  word-break:break-all;
  line-height:1.6;
}
.chain-blocks{
  display:flex;gap:6px;margin-top:10px;
}
.chain-block{
  flex:1;height:6px;border-radius:3px;
  background:linear-gradient(90deg,var(--teal),var(--green));
  animation:blockFill 3s ease-in-out infinite;
}
.chain-block:nth-child(2){animation-delay:.4s;opacity:.8}
.chain-block:nth-child(3){animation-delay:.8s;opacity:.6}
.chain-block:nth-child(4){animation-delay:1.2s;opacity:.3}
@keyframes blockFill{0%,100%{opacity:.3}50%{opacity:1}}

.signers-list{display:flex;flex-direction:column;gap:8px;margin-bottom:16px}
.signer{
  display:flex;
  align-items:center;
  justify-content:space-between;
  background:var(--overlay-signer);
  border:1px solid var(--border2);
  padding:10px 14px;
  border-radius:10px;
}
.signer-name{font-size:.85rem;color:var(--text)}
.signer-status{
  font-size:.72rem;font-weight:600;
  padding:3px 8px;border-radius:6px;
}
.status-signed{background:rgba(46,196,182,0.15);color:var(--teal-light)}
.status-pending{background:rgba(255,209,102,0.1);color:var(--gold)}

.progress-bar{
  height:4px;
  background:var(--progress-track);
  border-radius:2px;
  overflow:hidden;
  margin-bottom:8px;
}
.progress-fill{
  height:100%;
  width:67%;
  background:linear-gradient(90deg,var(--teal),var(--green-mid));
  border-radius:2px;
  animation:progressGlow 2s ease-in-out infinite;
}
@keyframes progressGlow{0%,100%{box-shadow:0 0 6px var(--teal)}50%{box-shadow:0 0 16px var(--teal)}}

.doc-meta{font-size:.75rem;color:var(--text-dim);display:flex;align-items:center;gap:6px}
.doc-meta svg{width:12px;height:12px;color:var(--teal)}

/* Floating badge */
.badge-float{
  position:absolute;
  top:-20px;
  right:-20px;
  background:rgba(27,94,32,0.9);
  border:1px solid rgba(46,196,182,0.3);
  backdrop-filter:blur(16px);
  border-radius:14px;
  padding:12px 16px;
  display:flex;
  align-items:center;
  gap:8px;
  box-shadow:0 8px 32px rgba(0,0,0,0.5);
  animation:badgeFloat 4.5s ease-in-out infinite;
  z-index:2;
}
@keyframes badgeFloat{0%,100%{transform:translateY(0) rotate(-1deg)}50%{transform:translateY(-8px) rotate(1deg)}}
.badge-float-text{font-size:.72rem;font-weight:600;color:var(--teal-light)}
.badge-float-icon{width:28px;height:28px;border-radius:8px;background:rgba(46,196,182,0.2);display:flex;align-items:center;justify-content:center}
.badge-float-icon svg{width:14px;height:14px;color:var(--teal)}

.badge-float2{
  position:absolute;
  bottom:-20px;
  left:-20px;
  background:var(--badge-float2-bg);
  border:1px solid var(--border);
  backdrop-filter:blur(16px);
  border-radius:14px;
  padding:12px 16px;
  box-shadow:0 8px 32px rgba(0,0,0,0.5);
  animation:badgeFloat2 5s ease-in-out infinite;
  z-index:2;
}
@keyframes badgeFloat2{0%,100%{transform:translateY(0) rotate(1deg)}50%{transform:translateY(8px) rotate(-1deg)}}
.badge-float2-label{font-size:.6rem;letter-spacing:.1em;text-transform:uppercase;color:var(--text-dim);margin-bottom:3px}
.badge-float2-val{font-family:var(--font-display);font-weight:700;font-size:.95rem;color:var(--teal)}

/* ── TRUST BAR ── */
.trust-bar{
  border-top:1px solid var(--border2);
  border-bottom:1px solid var(--border2);
  padding:20px 0;
  background:var(--trust-bar-bg);
}
.trust-bar-inner{
  display:flex;
  align-items:center;
  gap:12px;
  flex-wrap:wrap;
  justify-content:center;
}
.trust-bar-label{
  font-size:.7rem;letter-spacing:.15em;text-transform:uppercase;
  color:var(--text-dim);
  margin-right:12px;
  white-space:nowrap;
}
.trust-chip{
  background:var(--chip-bg);
  border:1px solid var(--border2);
  padding:6px 14px;
  border-radius:8px;
  font-size:.8rem;
  font-weight:600;
  color:var(--text-muted);
  white-space:nowrap;
  transition:all .2s;
}
.trust-chip:hover{border-color:var(--teal);color:var(--teal)}
.csc-chip{
  background:rgba(46,196,182,0.08);
  border-color:rgba(46,196,182,0.3);
  color:var(--teal-light);
}

/* ── CSC SECTION ── */
.csc-section{
  padding:80px 0;
  position:relative;
}
.csc-card{
  background:linear-gradient(135deg,rgba(46,196,182,0.08),rgba(27,94,32,0.12));
  border:1px solid rgba(46,196,182,0.2);
  border-radius:24px;
  padding:48px;
  display:grid;
  grid-template-columns:1fr 1fr;
  gap:48px;
  align-items:center;
  position:relative;
  overflow:hidden;
}
.csc-card::before{
  content:'';
  position:absolute;
  top:-1px;left:0;right:0;
  height:2px;
  background:linear-gradient(90deg,transparent,var(--teal),transparent);
}
.csc-badge{
  display:inline-flex;align-items:center;gap:8px;
  background:rgba(46,196,182,0.12);
  border:1px solid rgba(46,196,182,0.3);
  padding:6px 14px;
  border-radius:100px;
  font-size:.72rem;font-weight:700;
  letter-spacing:.08em;text-transform:uppercase;
  color:var(--teal);
  margin-bottom:20px;
}
.csc-badge svg{width:12px;height:12px}
.csc-card h2{
  font-family:var(--font-display);
  font-size:1.65rem;font-weight:800;color:var(--headline);
  line-height:1.2;margin-bottom:14px;
  letter-spacing:-0.01em;
}
.csc-card p{color:var(--text-muted);line-height:1.75;margin-bottom:20px}
.csc-link{
  display:inline-flex;align-items:center;gap:6px;
  color:var(--teal);font-size:.875rem;font-weight:600;
  text-decoration:none;
  border-bottom:1px solid rgba(46,196,182,0.3);
  padding-bottom:2px;
  transition:all .2s;
}
.csc-link:hover{color:var(--teal-light);border-color:var(--teal-light)}
.csc-stats{display:grid;grid-template-columns:1fr 1fr;gap:16px}
.csc-stat{
  background:var(--overlay-dark);
  border:1px solid var(--border);
  border-radius:14px;
  padding:20px;
  text-align:center;
}
.csc-stat-num{
  font-family:var(--font-display);font-weight:800;
  font-size:1.6rem;
  background:linear-gradient(90deg,var(--teal),var(--teal-light));
  -webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;
  display:block;margin-bottom:4px;
}
.csc-stat-label{font-size:.75rem;color:var(--text-dim)}
.csc-logo{
  margin:20px 0 24px;
  line-height:0;
}
.csc-logo-img{
  width:100%;
  max-width:min(100%,380px);
  height:auto;
  display:block;
  object-fit:contain;
}

/* ── FEATURES ── */
.features-section{padding:80px 0}
.section-label{
  font-size:.7rem;letter-spacing:.15em;text-transform:uppercase;
  color:var(--teal);font-weight:700;margin-bottom:12px;
}
.section-title{
  font-family:var(--font-display);font-weight:800;
  font-size:clamp(2rem,3.4vw,2.8rem);
  color:var(--headline);line-height:1.2;margin-bottom:14px;
  letter-spacing:-0.01em;
}
.section-sub{color:var(--text-muted);font-size:1.05rem;max-width:620px;line-height:1.75}
.section-head{margin-bottom:48px}

.features-grid{
  display:grid;
  grid-template-columns:repeat(3,1fr);
  gap:20px;
}
.feature-card{
  display:block;
  background:var(--surface);
  border:1px solid var(--border2);
  border-radius:18px;
  padding:28px;
  transition:all .3s;
  position:relative;
  overflow:hidden;
  text-decoration:none;
  color:inherit;
  cursor:pointer;
}
.feature-card:focus-visible{
  outline:2px solid var(--teal);
  outline-offset:3px;
}
.feature-card-learn{
  display:inline-block;
  margin-top:14px;
  font-size:.8rem;font-weight:600;
  color:var(--teal);
  letter-spacing:.02em;
}
.feature-card:hover .feature-card-learn{color:var(--teal-light)}
.feature-card::after{
  content:'';
  position:absolute;
  inset:0;
  background:linear-gradient(135deg,rgba(46,196,182,0.05),transparent);
  opacity:0;
  transition:opacity .3s;
  pointer-events:none;
}
.feature-card:hover{
  border-color:rgba(46,196,182,0.35);
  transform:translateY(-4px);
  box-shadow:0 20px 60px rgba(0,0,0,0.4),0 0 0 1px rgba(46,196,182,0.1);
}
.feature-card:hover::after{opacity:1}
.feature-card.featured{
  background:linear-gradient(135deg,rgba(46,196,182,0.1),rgba(27,94,32,0.1));
  border-color:rgba(46,196,182,0.25);
  grid-column:span 1;
}
.feature-icon{
  width:44px;height:44px;
  background:rgba(46,196,182,0.1);
  border:1px solid rgba(46,196,182,0.2);
  border-radius:12px;
  display:flex;align-items:center;justify-content:center;
  margin-bottom:18px;
}
.feature-icon svg{width:20px;height:20px;color:var(--teal)}
.feature-badge{
  display:inline-block;
  font-size:.62rem;font-weight:700;letter-spacing:.08em;text-transform:uppercase;
  background:rgba(255,209,102,0.15);color:var(--gold);
  border:1px solid rgba(255,209,102,0.25);
  padding:2px 8px;border-radius:4px;
  margin-bottom:10px;
}
.feature-card h3{
  font-family:var(--font-display);font-weight:700;
  font-size:1.2rem;color:var(--headline);margin-bottom:10px;
}
.feature-card p{font-size:1rem;color:var(--text-muted);line-height:1.75}

/* ── BLOCKCHAIN KPI ── */
.blockchain-section{padding:80px 0}
.blockchain-inner{
  background:var(--surface);
  border:1px solid var(--border);
  border-radius:24px;
  padding:48px;
  position:relative;
  overflow:hidden;
}
.blockchain-inner::before{
  content:'';
  position:absolute;inset:0;
  background:radial-gradient(circle at 80% 50%,rgba(46,196,182,0.06),transparent 60%);
  pointer-events:none;
}
.blockchain-grid{display:grid;grid-template-columns:1.2fr 1fr;gap:60px;align-items:center}
.kpi-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px}
.kpi-card{
  background:var(--overlay-kpi);
  border:1px solid var(--border2);
  border-radius:14px;
  padding:20px;
  text-align:center;
  transition:all .3s;
}
.kpi-card:hover{border-color:rgba(46,196,182,0.3);transform:translateY(-2px)}
.kpi-num{
  font-family:var(--font-display);font-weight:800;font-size:2rem;
  background:linear-gradient(90deg,var(--teal),var(--teal-light));
  -webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;
  display:block;margin-bottom:4px;
}
.kpi-label{font-size:.775rem;color:var(--text-dim);line-height:1.4}
.kpi-card.highlight{
  background:linear-gradient(135deg,rgba(46,196,182,0.1),rgba(27,94,32,0.1));
  border-color:rgba(46,196,182,0.25);
}
.kpi-card.highlight .kpi-num{
  background:linear-gradient(90deg,var(--gold),#f4a83a);
  -webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;
}

.blockchain-certs{display:flex;flex-direction:column;gap:12px;margin-top:24px}
.cert-item{
  display:flex;align-items:center;gap:12px;
  background:var(--overlay-signer);
  border:1px solid var(--border2);
  border-radius:10px;
  padding:12px 16px;
}
.cert-icon{
  width:32px;height:32px;
  background:rgba(46,196,182,0.12);
  border-radius:8px;
  display:flex;align-items:center;justify-content:center;
  flex-shrink:0;
}
.cert-icon svg{width:16px;height:16px;color:var(--teal)}
.cert-text{font-size:.8rem;color:var(--text-muted);font-weight:500}

/* ── AI SECTION ── */
.ai-section{padding:80px 0}
.ai-inner{
  display:grid;
  grid-template-columns:1fr 1fr;
  gap:60px;
  align-items:center;
}
.ai-feature-list{display:flex;flex-direction:column;gap:16px;margin-top:28px}
.ai-feature{
  display:flex;gap:14px;
  padding:16px 20px;
  background:var(--surface);
  border:1px solid var(--border2);
  border-radius:12px;
  transition:all .25s;
}
.ai-feature:hover{border-color:rgba(46,196,182,0.25);transform:translateX(4px)}
.ai-feature-icon{
  width:36px;height:36px;
  background:rgba(46,196,182,0.1);border-radius:8px;
  display:flex;align-items:center;justify-content:center;flex-shrink:0;
}
.ai-feature-icon svg{width:16px;height:16px;color:var(--teal)}
.ai-feature-title{font-size:.875rem;font-weight:600;color:var(--headline);margin-bottom:3px}
.ai-feature-desc{font-size:.8rem;color:var(--text-muted)}

.ai-visual{
  background:var(--surface);
  border:1px solid var(--border);
  border-radius:20px;
  padding:24px;
  box-shadow:0 24px 80px rgba(0,0,0,0.5);
}
.ai-chat{display:flex;flex-direction:column;gap:12px}
.ai-msg{
  display:flex;gap:10px;
}
.ai-msg-avatar{
  width:30px;height:30px;border-radius:8px;
  background:linear-gradient(135deg,var(--teal),var(--green));
  display:flex;align-items:center;justify-content:center;
  flex-shrink:0;font-size:.6rem;font-weight:700;color:#fff;
}
.ai-msg-bubble{
  background:var(--ai-bubble-bg);
  border:1px solid var(--border2);
  border-radius:10px;
  padding:10px 14px;
  font-size:.8rem;
  color:var(--text-muted);
  line-height:1.5;
  max-width:280px;
}
.ai-msg-bubble strong{color:var(--teal-light)}
.ai-msg.user .ai-msg-bubble{
  background:rgba(46,196,182,0.08);
  border-color:rgba(46,196,182,0.2);
  color:var(--text);
  margin-left:auto;
}
.ai-msg.user{flex-direction:row-reverse}
.ai-typing{
  display:flex;gap:4px;
  align-items:center;
  padding:4px 0;
}
.ai-dot{
  width:5px;height:5px;border-radius:50%;
  background:var(--teal);
  animation:aiType 1.2s ease-in-out infinite;
}
.ai-dot:nth-child(2){animation-delay:.2s}
.ai-dot:nth-child(3){animation-delay:.4s}
@keyframes aiType{0%,60%,100%{transform:translateY(0);opacity:.4}30%{transform:translateY(-4px);opacity:1}}

/* ── INDUSTRIES ── */
.industries-section{padding:80px 0}
.industries-grid{
  display:grid;
  grid-template-columns:repeat(3,1fr);
  gap:16px;
  margin-top:40px;
}
.industry-card{
  background:var(--surface);
  border:1px solid var(--border2);
  border-radius:16px;
  padding:24px;
  transition:all .3s;
  display:flex;align-items:flex-start;gap:14px;
}
.industry-card:hover{
  border-color:rgba(46,196,182,0.3);
  transform:translateY(-3px);
  background:rgba(46,196,182,0.04);
}
.industry-icon{
  width:40px;height:40px;
  background:rgba(46,196,182,0.08);
  border:1px solid rgba(46,196,182,0.15);
  border-radius:10px;
  display:flex;align-items:center;justify-content:center;flex-shrink:0;
}
.industry-icon svg{width:18px;height:18px;color:var(--teal)}
.industry-name{font-family:var(--font-display);font-weight:700;font-size:.9rem;color:var(--headline);margin-bottom:4px}
.industry-desc{font-size:.78rem;color:var(--text-dim)}

/* ── ABOUT ── */
.about-section{padding:80px 0}
.about-grid{display:grid;grid-template-columns:1fr 1fr;gap:60px;align-items:center}
.about-highlight{
  display:flex;align-items:center;gap:10px;
  background:rgba(46,196,182,0.06);
  border:1px solid rgba(46,196,182,0.15);
  border-radius:10px;
  padding:12px 16px;
  margin-bottom:16px;
}
.about-highlight svg{width:16px;height:16px;color:var(--teal);flex-shrink:0}
.about-highlight-text{font-size:.82rem;color:var(--text-muted)}
.about-highlight-text strong{color:var(--teal-light)}
.about-desc{color:var(--text-muted);line-height:1.75;margin-top:20px}
.about-desc + .about-desc{margin-top:12px}
.about-media{
  position:relative;
  border-radius:20px;
  overflow:hidden;
  border:1px solid var(--border);
  background:var(--surface);
  box-shadow:0 18px 50px rgba(0,0,0,0.35);
}
.about-media img{
  width:100%;
  height:320px;
  object-fit:cover;
  display:block;
}
.about-media::after{
  content:'';
  position:absolute;
  inset:0;
  background:linear-gradient(to top,rgba(6,13,16,0.62),rgba(6,13,16,0.12) 45%,transparent 75%);
  pointer-events:none;
}
.about-surepay{
  position:absolute;
  right:16px;
  bottom:16px;
  z-index:2;
  display:flex;
  align-items:center;
  gap:10px;
  max-width:280px;
  border-radius:14px;
  padding:10px 12px;
  backdrop-filter:blur(10px);
  background:rgba(6,13,16,0.68);
  border:1px solid rgba(46,196,182,0.24);
  box-shadow:0 8px 28px rgba(0,0,0,0.4);
}
.about-surepay img{
  width:90px;
  height:auto;
  object-fit:contain;
  display:block;
}
.about-surepay-label{
  font-size:.68rem;
  font-weight:600;
  letter-spacing:.06em;
  text-transform:uppercase;
  color:var(--teal-light);
  line-height:1.35;
}
.about-stats{
  display:grid;grid-template-columns:1fr 1fr;gap:16px;
  margin-top:28px;
}
.about-stat{
  background:var(--surface);
  border:1px solid var(--border2);
  border-radius:12px;
  padding:16px 20px;
}
.about-stat-num{
  font-family:var(--font-display);font-weight:800;font-size:1.5rem;
  color:var(--teal);display:block;margin-bottom:2px;
}
.about-stat-label{font-size:.75rem;color:var(--text-dim)}

/* ── ADVERTISEMENT ── */
.showcase-section{padding:80px 0}
.showcase-head{margin-bottom:18px}
.showcase-head .section-label{margin-bottom:8px}
.showcase-head h2{
  font-family:var(--font-display);
  font-size:clamp(1.9rem,3.2vw,2.6rem);
  color:var(--headline);
  line-height:1.2;
}
.showcase-card{
  position:relative;
  border-radius:24px;
  border:1px solid var(--border);
  background:linear-gradient(135deg,rgba(46,196,182,0.08),rgba(17,32,40,0.88));
  overflow:hidden;
}
.showcase-card::before{
  content:'';
  position:absolute;
  inset:0;
  background:radial-gradient(circle at 10% 15%,rgba(46,196,182,0.22),transparent 45%);
  pointer-events:none;
}
.showcase-grid{
  position:relative;
  display:grid;
  grid-template-columns:1.05fr 1fr;
  gap:28px;
  align-items:center;
  padding:36px;
}
.showcase-copy h3{
  font-family:var(--font-display);
  font-size:clamp(1.6rem,2.7vw,2.3rem);
  line-height:1.2;
  color:var(--headline);
  margin-bottom:14px;
}
.showcase-copy p{
  color:var(--text-muted);
  font-size:1rem;
  line-height:1.75;
  margin-bottom:18px;
}
.showcase-list{
  list-style:none;
  display:flex;
  flex-direction:column;
  gap:10px;
  margin-bottom:24px;
}
.showcase-list li{
  display:flex;
  align-items:center;
  gap:10px;
  color:var(--text);
  font-size:.95rem;
}
.showcase-list li svg{
  width:16px;
  height:16px;
  color:var(--teal);
  flex-shrink:0;
}
.showcase-actions{display:flex;gap:12px;flex-wrap:wrap}
.showcase-video-wrap{
  border:1px solid var(--border2);
  border-radius:14px;
  overflow:hidden;
  background:#000;
  box-shadow:0 14px 36px rgba(0,0,0,0.32);
  width:min(100%, 240px);
  margin:0 auto;
}
.showcase-video{
  width:100%;
  aspect-ratio:11 / 16;
  object-fit:cover;
  object-position:center top;
  display:block;
}

/* ── TESTIMONIALS ── */
.testimonials-section{padding:80px 0}
.testimonials-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:20px;margin-top:40px}
.testimonial{
  background:var(--surface);
  border:1px solid var(--border2);
  border-radius:18px;
  padding:28px;
  position:relative;
}
.testimonial::before{
  content:'"';
  position:absolute;top:16px;right:20px;
  font-family:var(--font-display);font-size:4rem;font-weight:800;
  color:rgba(46,196,182,0.1);line-height:1;
}
.testimonial-text{
  font-size:.9rem;color:var(--text-muted);
  line-height:1.7;margin-bottom:20px;
}
.testimonial-author{
  display:flex;align-items:center;gap:10px;
}
.testimonial-avatar{
  width:36px;height:36px;border-radius:10px;
  background:linear-gradient(135deg,var(--teal),var(--green));
  display:flex;align-items:center;justify-content:center;
  font-family:var(--font-display);font-weight:700;font-size:.8rem;color:#fff;
}
.testimonial-name{font-weight:600;font-size:.85rem;color:var(--headline)}
.testimonial-role{font-size:.75rem;color:var(--text-dim)}
.stars{display:flex;gap:3px;margin-bottom:14px}
.stars svg{width:13px;height:13px;color:var(--gold)}

/* ── FAQ ── */
.faq-section{padding:80px 0}
.faq-list{display:flex;flex-direction:column;gap:10px;max-width:760px;margin:40px auto 0}
.faq-item{
  background:var(--surface);
  border:1px solid var(--border2);
  border-radius:14px;
  overflow:hidden;
  transition:border-color .2s;
}
.faq-item:hover{border-color:rgba(46,196,182,0.25)}
.faq-item summary{
  padding:20px 22px;
  font-size:1.05rem;font-weight:600;color:var(--headline);
  cursor:pointer;list-style:none;
  display:flex;justify-content:space-between;align-items:center;
  transition:color .2s;
}
.faq-item summary:hover{color:var(--teal)}
.faq-item summary::after{
  content:'+';
  font-size:1.2rem;font-weight:300;
  color:var(--text-dim);
  transition:transform .2s,color .2s;
  flex-shrink:0;margin-left:12px;
}
.faq-item[open] summary::after{transform:rotate(45deg);color:var(--teal)}
.faq-body{
  padding:0 22px 20px;
  font-size:1rem;
  color:var(--text-muted);
  line-height:1.8;
}

/* ── CTA ── */
.cta-section{padding:80px 0}
.cta-inner{
  background:linear-gradient(135deg,rgba(46,196,182,0.12),rgba(27,94,32,0.18));
  border:1px solid rgba(46,196,182,0.2);
  border-radius:28px;
  padding:64px 48px;
  text-align:center;
  position:relative;
  overflow:hidden;
}
.cta-inner::before{
  content:'';
  position:absolute;top:-1px;left:0;right:0;
  height:2px;
  background:linear-gradient(90deg,transparent,var(--teal),transparent);
}
.cta-inner::after{
  content:'';
  position:absolute;
  width:400px;height:400px;
  border-radius:50%;
  background:radial-gradient(circle,rgba(46,196,182,0.1),transparent 70%);
  top:50%;left:50%;transform:translate(-50%,-50%);
  pointer-events:none;
}
.cta-inner h2{
  font-family:var(--font-display);font-weight:800;
  font-size:clamp(1.6rem,3.5vw,2.4rem);color:var(--headline);
  margin-bottom:14px;position:relative;z-index:1;
  letter-spacing:-0.01em;
}
.cta-talk-sales{
  border-color:color-mix(in srgb,var(--headline) 22%,transparent);
  color:color-mix(in srgb,var(--headline) 78%,transparent);
}
.cta-talk-sales:hover{
  border-color:var(--teal);
  color:var(--teal);
}
.text-on-body{color:var(--headline)}
.ai-msg-user-avatar{background:rgba(255,255,255,0.1)}
@media (prefers-color-scheme: light) {
  .ai-msg-user-avatar{background:rgba(15,23,42,0.08)}
}
.cta-inner p{color:var(--text-muted);max-width:520px;margin:0 auto 32px;position:relative;z-index:1}
.cta-actions{
  display:flex;gap:12px;justify-content:center;flex-wrap:wrap;
  position:relative;z-index:1;
}

/* ── FOOTER ── */
footer{
  border-top:1px solid var(--border2);
  padding:60px 0 32px;
  background:var(--footer-bg);
}
.footer-grid{
  display:grid;
  grid-template-columns:1.5fr 1fr 1fr 1fr;
  gap:40px;
  margin-bottom:40px;
}
.footer-logo{display:flex;align-items:center;gap:10px;margin-bottom:16px;text-decoration:none}
.footer-logo .logo-text{font-family:var(--font-display);font-weight:800;font-size:1.1rem;color:var(--headline)}
.footer-desc{font-size:.95rem;color:var(--text-dim);line-height:1.75;max-width:280px}
.footer-csc{
  display:inline-flex;align-items:center;gap:6px;
  margin-top:12px;
  background:rgba(46,196,182,0.08);
  border:1px solid rgba(46,196,182,0.2);
  padding:5px 10px;
  border-radius:8px;
  font-size:.7rem;font-weight:600;color:var(--teal);
  text-decoration:none;
}
.footer-csc:hover{background:rgba(46,196,182,0.14)}
.footer-col h4{
  font-family:var(--font-display);font-weight:700;
  font-size:.82rem;letter-spacing:.06em;text-transform:uppercase;
  color:var(--text-muted);margin-bottom:14px;
}
.footer-links{display:flex;flex-direction:column;gap:8px}
.footer-links a{
  font-size:.95rem;color:var(--text-dim);
  text-decoration:none;transition:color .2s;
}
.footer-links a:hover{color:var(--teal)}
.footer-bottom{
  border-top:1px solid var(--border2);
  padding-top:24px;
  display:flex;
  justify-content:space-between;
  align-items:center;
  flex-wrap:wrap;
  gap:12px;
}
.footer-copy{font-size:.9rem;color:var(--text-dim)}
.footer-powered{font-size:.9rem;color:var(--text-dim)}
.footer-powered a{color:var(--teal);text-decoration:none}

/* ── REVEAL ── */
.reveal{
  opacity:0;
  transform:translateY(20px);
  transition:opacity .7s ease,transform .7s ease;
}
.reveal.visible{opacity:1;transform:translateY(0)}

/* ── RESPONSIVE ── */
@media(max-width:1024px){
  .features-grid{grid-template-columns:1fr 1fr}
  .industries-grid{grid-template-columns:1fr 1fr}
  .footer-grid{grid-template-columns:1fr 1fr}
  nav{display:none}
  .nav-mobile-toggle{display:inline-flex}
  .header-inner{padding:0 16px}
  .logo-text{font-size:1.05rem}
  .header-actions{gap:8px}
  .btn-ghost{display:none}
  .btn-primary{padding:9px 14px;font-size:.8125rem}
}
@media(max-width:768px){
  .hero-grid{grid-template-columns:1fr;gap:40px}
  .hero{min-height:auto;padding:60px 0 50px}
  .badge-float,.badge-float2{display:none}
  .csc-card{grid-template-columns:1fr;gap:32px;padding:32px 24px}
  .blockchain-grid{grid-template-columns:1fr}
  .ai-inner{grid-template-columns:1fr}
  .about-grid{grid-template-columns:1fr}
  .showcase-head h2{font-size:2rem}
  .showcase-grid{grid-template-columns:1fr;padding:24px}
  .showcase-video-wrap{width:min(100%,190px)}
  .showcase-video{aspect-ratio:11 / 16}
  .showcase-actions .btn-cta,
  .showcase-actions .btn-secondary{width:100%;justify-content:center}
  .about-media img{height:260px}
  .about-surepay{
    right:12px;
    bottom:12px;
    padding:8px 10px;
    max-width:230px;
  }
  .about-surepay img{width:72px}
  .testimonials-grid{grid-template-columns:1fr}
  .features-grid{grid-template-columns:1fr}
  .industries-grid{grid-template-columns:1fr 1fr}
  .footer-grid{grid-template-columns:1fr 1fr}
  .cta-inner{padding:40px 24px}
  .blockchain-inner{padding:28px 20px}
}
@media(max-width:480px){
  .industries-grid{grid-template-columns:1fr}
  .footer-grid{grid-template-columns:1fr}
  .kpi-grid{grid-template-columns:1fr 1fr}
  .hero h1{font-size:2.25rem}
  .hero-actions{flex-direction:column}
  .btn-cta,.btn-secondary{text-align:center;justify-content:center}
  .showcase-video-wrap{width:min(100%,165px)}
}
@media(max-width:420px){
  .logo-text{font-size:.95rem}
  .btn-primary{padding:9px 12px}
}

/* mobile nav */
.mobile-nav{
  display:none;
  position:fixed;inset:0;z-index:200;
  background:var(--mobile-nav-bg);
  backdrop-filter:blur(20px);
  flex-direction:column;
  align-items:center;justify-content:center;
  gap:24px;
  padding:max(24px,env(safe-area-inset-top)) max(24px,env(safe-area-inset-right)) max(24px,env(safe-area-inset-bottom)) max(24px,env(safe-area-inset-left));
  -webkit-overflow-scrolling:touch;
  overflow-y:auto;
  overscroll-behavior:contain;
}
.mobile-nav.open{display:flex}
.mobile-nav a{
  font-family:var(--font-display);font-size:1.5rem;font-weight:700;
  color:var(--text-muted);text-decoration:none;transition:color .2s;
}
.mobile-nav a:hover{color:var(--teal)}
.mobile-nav-close{
  position:absolute;
  top:max(24px,env(safe-area-inset-top));
  right:max(24px,env(safe-area-inset-right));
  display:inline-flex;
  align-items:center;
  justify-content:center;
  width:44px;height:44px;padding:0;
  background:var(--chip-bg);
  border:1px solid var(--border);
  color:var(--text-muted);
  border-radius:12px;
  cursor:pointer;
  -webkit-tap-highlight-color:rgba(46,196,182,0.15);
  touch-action:manipulation;
  transition:background .15s,color .15s,border-color .15s;
  z-index:1;
}
.mobile-nav-close:active{transform:scale(0.96)}
.mobile-nav-close:focus-visible{outline:2px solid var(--teal);outline-offset:2px}
</style>
</head>
<body>
@php
    $docutrustLogoDefault = asset('images/docutrust-logo.png');
    $docutrustLogoLight = file_exists(public_path('images/docutrust-logo-light.png'))
        ? asset('images/docutrust-logo-light.png')
        : null;
    $cscLogo = asset('images/CSC logo light theme.png');
@endphp

<div class="orb orb1"></div>
<div class="orb orb2"></div>
<div class="orb orb3"></div>

<!-- Mobile Nav -->
<div class="mobile-nav" id="mobileNav" role="dialog" aria-modal="true" aria-label="{{ __('Site menu') }}" hidden>
  <button type="button" class="mobile-nav-close" id="mobileNavClose" aria-label="{{ __('Close menu') }}">
    <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M18 6L6 18M6 6l12 12"/></svg>
  </button>
  <a href="#features" onclick="closeMobileNav()">Features</a>
  <a href="#blockchain" onclick="closeMobileNav()">Security</a>
  <a href="#ai" onclick="closeMobileNav()">AI Engine</a>
  <a href="#about" onclick="closeMobileNav()">About</a>
  <a href="#showcase" onclick="closeMobileNav()">Advertisement</a>
  <a href="#industries" onclick="closeMobileNav()">Industries</a>
  <a href="#faq" onclick="closeMobileNav()">FAQ</a>
  <a href="{{ $secondaryHeaderUrl }}" onclick="closeMobileNav()">{{ $secondaryHeaderLabel }}</a>
  <a href="{{ route('register') }}" class="btn-cta" style="font-size:.9rem">Get Started Free</a>
</div>

<!-- HEADER -->
<header>
  <div class="header-inner">
    <a href="{{ route('home') }}" class="logo">
      <span class="logo-mark">
        @if ($docutrustLogoLight)
          <picture>
            <source media="(prefers-color-scheme: light)" srcset="{{ $docutrustLogoLight }}">
            <img
              src="{{ $docutrustLogoDefault }}"
              alt=""
              class="logo-img"
              width="40"
              height="40"
              loading="eager"
              decoding="async"
            >
          </picture>
        @else
          <img
            src="{{ $docutrustLogoDefault }}"
            alt=""
            class="logo-img"
            width="40"
            height="40"
            loading="eager"
            decoding="async"
          >
        @endif
      </span>
      <span class="logo-text">{{ config('app.name') }}</span>
    </a>
    <nav>
      <a href="#features">Features</a>
      <a href="#blockchain">Security</a>
      <a href="#ai">AI Engine</a>
      <a href="#about">About</a>
      <a href="#showcase">Advertisement</a>
      <a href="#industries">Industries</a>
      <a href="#faq">FAQ</a>
    </nav>
    <div class="header-actions">
      <a href="{{ $secondaryHeaderUrl }}" class="btn-ghost">{{ $secondaryHeaderLabel }}</a>
      <a href="{{ $primaryCtaUrl }}" class="btn-primary">{{ $primaryCtaLabel }}</a>
      <button type="button" class="nav-mobile-toggle" id="mobileNavToggle" aria-label="{{ __('Open menu') }}" aria-expanded="false" aria-controls="mobileNav">
        <svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
      </button>
    </div>
  </div>
</header>

<!-- HERO -->
<section class="hero">
  <div class="container">
    <div class="hero-grid">
      <div>
        <div class="hero-badge reveal">
          <span class="dot"></span>
          CSC Member · Blockchain-Certified · BSP-Licensed
        </div>
        <h1 class="reveal">
          Secure &amp; Tamper-Proof <span class="accent">Digital Signatures.</span>
          Smarter Documents, Powered by <span class="accent2">Agentic AI.</span>
        </h1>
        <p class="hero-sub reveal">
          Ditch paper contracts and give your customers a seamless eSigning experience — with full document automation, AI-powered processing, and blockchain-verified trust.
        </p>
        <div class="hero-actions reveal">
          <a href="{{ $primaryCtaUrl }}" class="btn-cta">
            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 5.25a3 3 0 013 3m3 0a6 6 0 01-7.029 5.912c-.563-.097-1.159.026-1.563.43L10.5 17.25H8.25v2.25H6v2.25H2.25v-2.818c0-.597.237-1.17.659-1.591l6.499-6.499c.404-.404.527-1 .43-1.563A6 6 0 1121.75 8.25z"/></svg>
            {{ $primaryCtaLabel }}
          </a>
          <a href="#features" class="btn-secondary">
            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5.25 5.653c0-.856.917-1.398 1.667-.986l11.54 6.348a1.125 1.125 0 010 1.971l-11.54 6.347a1.125 1.125 0 01-1.667-.985V5.653z"/></svg>
            See Product Tour
          </a>
        </div>
        <div class="hero-trust reveal">
          <span class="trust-item">
            <svg fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
            No credit card needed
          </span>
          <span class="trust-item">
            <svg fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
            14-day free trial
          </span>
          <span class="trust-item">
            <svg fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
            24/7 live support
          </span>
          <span class="trust-item">
            <svg fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
            ISO 9001:2015 Certified
          </span>
        </div>
      </div>

      <div class="hero-visual reveal">
        <div class="badge-float">
          <div class="badge-float-icon">
            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.1-1.1"/><path stroke-linecap="round" stroke-linejoin="round" d="M10.172 13.828a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/></svg>
          </div>
          <span class="badge-float-text">Blockchain Verified ✓</span>
        </div>
        <div class="badge-float2">
          <div class="badge-float2-label">Docs Signed Today</div>
          <div class="badge-float2-val">4,291</div>
        </div>
        <div class="doc-card">
          <div class="doc-header">
            <div>
              <div style="font-size:.65rem;letter-spacing:.1em;text-transform:uppercase;color:var(--text-dim);margin-bottom:4px">New Request</div>
              <div class="doc-title">Vendor Agreement.pdf</div>
            </div>
            <span class="doc-status">Ready to Sign</span>
          </div>
          <div class="chain-vis">
            <div class="chain-label">Blockchain Hash · Block #847,291</div>
            <div class="chain-hash">0x4f8a2b1c9d3e7f6a2b8c4d1e9f3a7b2c<br>5d8e4f1a6b3c9d2e7f4a1b8c5d3e9f2a</div>
            <div class="chain-blocks">
              <div class="chain-block"></div>
              <div class="chain-block"></div>
              <div class="chain-block"></div>
              <div class="chain-block"></div>
            </div>
          </div>
          <div class="signers-list">
            <div class="signer">
              <span class="signer-name">Maya Turner</span>
              <span class="signer-status status-signed">✓ Signed</span>
            </div>
            <div class="signer">
              <span class="signer-name">Aron Diaz</span>
              <span class="signer-status status-pending">⟳ Pending</span>
            </div>
          </div>
          <div class="progress-bar"><div class="progress-fill"></div></div>
          <div class="doc-meta">
            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
            67% complete · Live reminders sent automatically
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- TRUST BAR -->
<div class="trust-bar">
  <div class="container">
    <div class="trust-bar-inner">
      <span class="trust-bar-label">Trusted by</span>
      <span class="trust-chip">CivicCore</span>
      <span class="trust-chip">UniTrust</span>
      <span class="trust-chip">LegalGrid</span>
      <span class="trust-chip">HomeAxis</span>
      <span class="trust-chip">FinPulse</span>
      <span class="trust-chip csc-chip">☁ Cloud Signature Consortium Member</span>
      <span class="trust-chip csc-chip">🔒 BSP-Licensed</span>
    </div>
  </div>
</div>

<!-- CSC MEMBERSHIP -->
<section class="csc-section">
  <div class="container">
    <div class="csc-card reveal">
      <div>
        <div class="csc-badge">
          <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"/></svg>
          Official Member
        </div>
        <h2>Cloud Signature Consortium Member</h2>
        <div class="csc-logo">
          <img
            src="{{ $cscLogo }}"
            alt="Cloud Signature Consortium"
            class="csc-logo-img"
            width="380"
            height="95"
            loading="lazy"
            decoding="async"
          >
        </div>
        <p>DocuTrust is a proud member of the <strong style="color:var(--teal-light)">Cloud Signature Consortium (CSC)</strong> — the global standards body for cloud-based digital signatures trusted by enterprises, governments, and regulators worldwide.</p>
        <p style="margin-top:12px;color:var(--text-muted);font-size:.875rem;line-height:1.7">CSC membership means our digital signature infrastructure is built on internationally recognized open standards — including the CSC API, PAdES, and XAdES — ensuring interoperability, legal compliance, and verifiable trust across borders.</p>
        <a href="https://cloudsignatureconsortium.org/" target="_blank" rel="noopener" class="csc-link" style="margin-top:20px;display:inline-flex">
          Visit cloudsignatureconsortium.org
          <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 003 8.25v10.5A2.25 2.25 0 005.25 21h10.5A2.25 2.25 0 0018 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25"/></svg>
        </a>
      </div>
      <div class="csc-stats">
        <div class="csc-stat">
          <span class="csc-stat-num">ISO</span>
          <div class="csc-stat-label">9001:2015<br>Certified Operations</div>
        </div>
        <div class="csc-stat">
          <span class="csc-stat-num">BSP</span>
          <div class="csc-stat-label">Licensed Payment<br>Service Operator</div>
        </div>
        <div class="csc-stat">
          <span class="csc-stat-num">CSC</span>
          <div class="csc-stat-label">Cloud Signature<br>Consortium Member</div>
        </div>
        <div class="csc-stat">
          <span class="csc-stat-num">AES</span>
          <div class="csc-stat-label">Advanced Electronic<br>Signatures Compliant</div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- FEATURES -->
<section class="features-section" id="features">
  <div class="container">
    <div class="section-head">
      <div class="section-label reveal">Features</div>
      <h2 class="section-title reveal">Everything You Need to Manage<br>Documents with Confidence</h2>
      <p class="section-sub reveal">From legally binding signatures to audit-ready trails — one platform built for teams that cannot afford gaps in security or speed.</p>
    </div>
    <div class="features-grid">
      @foreach (\App\Support\MarketingFeatures::all() as $marketingFeature)
        <a
          href="{{ route('features.show', $marketingFeature['slug']) }}"
          class="feature-card reveal @if ($marketingFeature['featured']) featured @endif"
        >
          @if ($marketingFeature['badge'])
            <div class="feature-badge">{{ $marketingFeature['badge'] }}</div>
          @endif
          <div class="feature-icon">
            @include('features.partials.icon', ['icon' => $marketingFeature['icon']])
          </div>
          <h3>{{ $marketingFeature['title'] }}</h3>
          <p>{{ $marketingFeature['summary'] }}</p>
          <span class="feature-card-learn">{{ __('Learn more →') }}</span>
        </a>
      @endforeach
    </div>
  </div>
</section>

<!-- BLOCKCHAIN KPI -->
<section class="blockchain-section" id="blockchain">
  <div class="container">
    <div class="blockchain-inner">
      <div class="blockchain-grid">
        <div>
          <div class="section-label reveal">Security & Performance</div>
          <h2 class="section-title reveal">Bank-Grade Security,<br>Blockchain-Certified Trust</h2>
          <p class="section-sub reveal" style="margin-bottom:28px">DocuTrust applies the highest standards in digital security — backed by Surepay Technologies Inc., a BSP-licensed fintech with proven expertise in secure digital ecosystems.</p>
          <div class="blockchain-certs reveal">
            <div class="cert-item">
              <div class="cert-icon"><svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg></div>
              <span class="cert-text">ISO 9001:2015 Certified Quality Management</span>
            </div>
            <div class="cert-item">
              <div class="cert-icon"><svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg></div>
              <span class="cert-text">AES-256 Military-Grade Encryption at Rest & Transit</span>
            </div>
            <div class="cert-item">
              <div class="cert-icon"><svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.1-1.1m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/></svg></div>
              <span class="cert-text">CSC-Standard Cloud Signature API (Open Standard)</span>
            </div>
            <div class="cert-item">
              <div class="cert-icon"><svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064"/></svg></div>
              <span class="cert-text">BSP-Licensed Payment Service Operator (Surepay)</span>
            </div>
          </div>
        </div>
        <div class="kpi-grid reveal">
          <div class="kpi-card">
            <span class="kpi-num">256-bit</span>
            <div class="kpi-label">AES Encryption<br>Standard</div>
          </div>
          <div class="kpi-card highlight">
            <span class="kpi-num">100%</span>
            <div class="kpi-label">Tamper-Proof<br>Blockchain Records</div>
          </div>
          <div class="kpi-card">
            <span class="kpi-num">80%</span>
            <div class="kpi-label">Faster Document<br>Processing</div>
          </div>
          <div class="kpi-card">
            <span class="kpi-num">60%</span>
            <div class="kpi-label">Reduction in<br>Paper Usage</div>
          </div>
          <div class="kpi-card">
            <span class="kpi-num">40%</span>
            <div class="kpi-label">Cost Reduction<br>per Transaction</div>
          </div>
          <div class="kpi-card highlight">
            <span class="kpi-num">10K+</span>
            <div class="kpi-label">Teams Worldwide<br>Trust DocuTrust</div>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- AI SECTION -->
<section class="ai-section" id="ai">
  <div class="container">
    <div class="ai-inner">
      <div>
        <div class="section-label reveal">Agentic AI Engine</div>
        <h2 class="section-title reveal">Full Document Automation<br>Powered by AI</h2>
        <p class="section-sub reveal">DocuTrust's Agentic AI goes beyond simple signing — it understands, extracts, validates, and routes documents intelligently, reducing manual work to near-zero.</p>
        <div class="ai-feature-list reveal">
          <div class="ai-feature">
            <div class="ai-feature-icon"><svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg></div>
            <div>
              <div class="ai-feature-title">AI Document Extraction</div>
              <div class="ai-feature-desc">Automatically identifies key fields, clauses, dates, and signatories from any document format.</div>
            </div>
          </div>
          <div class="ai-feature">
            <div class="ai-feature-icon"><svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 9l3 3-3 3m5 0h3M5 20h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg></div>
            <div>
              <div class="ai-feature-title">Smart Workflow Routing</div>
              <div class="ai-feature-desc">AI decides who signs next, triggers reminders, and escalates overdue documents automatically.</div>
            </div>
          </div>
          <div class="ai-feature">
            <div class="ai-feature-icon"><svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg></div>
            <div>
              <div class="ai-feature-title">Contract Intelligence</div>
              <div class="ai-feature-desc">Detect risk clauses, missing fields, and compliance gaps before sending for signature.</div>
            </div>
          </div>
          <div class="ai-feature">
            <div class="ai-feature-icon"><svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z"/></svg></div>
            <div>
              <div class="ai-feature-title">Conversational Document Assistant</div>
              <div class="ai-feature-desc">Ask questions about your documents in plain language — the AI answers instantly from the content.</div>
            </div>
          </div>
        </div>
      </div>
      <div class="reveal">
        <div class="ai-visual">
          <div style="font-size:.7rem;letter-spacing:.1em;text-transform:uppercase;color:var(--text-dim);margin-bottom:16px;display:flex;align-items:center;gap:6px">
            <span style="width:6px;height:6px;border-radius:50%;background:var(--teal);animation:pulse 2s infinite"></span>
            DocuTrust AI Assistant — Live
          </div>
          <div class="ai-chat">
            <div class="ai-msg user">
              <div class="ai-msg-avatar ai-msg-user-avatar" style="font-size:.65rem">You</div>
              <div class="ai-msg-bubble">What's the contract expiry date in NDA_2025.pdf?</div>
            </div>
            <div class="ai-msg">
              <div class="ai-msg-avatar">AI</div>
              <div class="ai-msg-bubble">The NDA expires on <strong>December 31, 2025</strong>. There's also an auto-renewal clause on Page 4, Section 3.2 — would you like me to flag it for review?</div>
            </div>
            <div class="ai-msg user">
              <div class="ai-msg-avatar ai-msg-user-avatar" style="font-size:.65rem">You</div>
              <div class="ai-msg-bubble">Yes, and send a reminder to all signers 30 days before.</div>
            </div>
            <div class="ai-msg">
              <div class="ai-msg-avatar">AI</div>
              <div class="ai-msg-bubble"><strong>Done.</strong> Reminder scheduled for Dec 1, 2025. I've also detected 2 unsigned fields — routing now to pending signers automatically.</div>
            </div>
            <div class="ai-msg">
              <div class="ai-msg-avatar">AI</div>
              <div class="ai-msg-bubble" style="display:flex;align-items:center;gap:8px;padding:10px 14px">
                <div class="ai-typing">
                  <div class="ai-dot"></div>
                  <div class="ai-dot"></div>
                  <div class="ai-dot"></div>
                </div>
                <span style="font-size:.75rem">Processing blockchain record...</span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- INDUSTRIES -->
<section class="industries-section" id="industries">
  <div class="container">
    <div class="section-label reveal" style="text-align:center">Industries</div>
    <h2 class="section-title reveal" style="text-align:center">Built for Every Industry</h2>
    <p class="section-sub reveal" style="text-align:center;margin:0 auto">DocuTrust adapts to the unique compliance and workflow requirements of every sector.</p>
    <div class="industries-grid">
      <div class="industry-card reveal">
        <div class="industry-icon"><svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3"/></svg></div>
        <div>
          <div class="industry-name">Government & LGU</div>
          <div class="industry-desc">Compliant digital signing for public sector agreements and LIFT-integrated workflows.</div>
        </div>
      </div>
      <div class="industry-card reveal">
        <div class="industry-icon"><svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 14l9-5-9-5-9 5 9 5z"/><path stroke-linecap="round" stroke-linejoin="round" d="M12 14l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z"/></svg></div>
        <div>
          <div class="industry-name">Education</div>
          <div class="industry-desc">Enrollment agreements, faculty contracts, and accreditation documents — all digital.</div>
        </div>
      </div>
      <div class="industry-card reveal">
        <div class="industry-icon"><svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0"/></svg></div>
        <div>
          <div class="industry-name">Legal</div>
          <div class="industry-desc">Court-admissible audit trails, attorney collaboration, and matter-specific signing workflows.</div>
        </div>
      </div>
      <div class="industry-card reveal">
        <div class="industry-icon"><svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg></div>
        <div>
          <div class="industry-name">Real Estate</div>
          <div class="industry-desc">Deed of sale, lease agreements, and title transfers — signed securely in minutes.</div>
        </div>
      </div>
      <div class="industry-card reveal">
        <div class="industry-icon"><svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg></div>
        <div>
          <div class="industry-name">HR & Recruitment</div>
          <div class="industry-desc">Offer letters, NDAs, employment contracts — onboard faster with zero paper.</div>
        </div>
      </div>
      <div class="industry-card reveal">
        <div class="industry-icon"><svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg></div>
        <div>
          <div class="industry-name">Finance & Fintech</div>
          <div class="industry-desc">Loan agreements, KYC documents, and financial instruments with full regulatory compliance.</div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ABOUT -->
<section class="about-section" id="about">
  <div class="container">
    <div class="about-grid">
      <div>
        <div class="section-label reveal">About DocuTrust</div>
        <h2 class="section-title reveal">Powering Trust Through Innovation</h2>
        <div class="about-desc reveal">
          DocuTrust is a next-generation digital signing platform built to transform how organizations manage agreements, approvals, and document workflows. Designed for speed, security, and scalability, it empowers teams to move faster while maintaining complete trust in every transaction.
        </div>
        <div class="about-desc reveal">
          Backed by <strong class="text-on-body">Surepay Technologies Inc.</strong>, a <strong style="color:var(--teal-light)">BSP-licensed</strong> payment service operator and trusted Philippine fintech, DocuTrust is built on a foundation of bank-grade security and continuous innovation.
        </div>
        <div style="margin-top:24px" class="reveal">
          <div class="about-highlight">
            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
            <span class="about-highlight-text">Nationwide digital transformation solutions for modern organizations</span>
          </div>
          <div class="about-highlight">
            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
            <span class="about-highlight-text"><strong>ISO 9001:2015</strong> certified operations — quality management at every layer</span>
          </div>
          <div class="about-highlight">
            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
            <span class="about-highlight-text">Official <strong>Cloud Signature Consortium</strong> member — globally recognized signing standards</span>
          </div>
        </div>
      </div>
      <div class="reveal">
        <div class="about-media">
          <img src="{{ asset('images/about-us.jpg') }}" alt="Digital document workflow and signing interface">
          <div class="about-surepay">
            <img src="{{ asset('images/surepay.png') }}" alt="Surepay Technologies Inc.">
            <div class="about-surepay-label">Powered by Surepay Technologies Inc.</div>
          </div>
        </div>
        <div class="about-stats">
          <div class="about-stat">
            <span class="about-stat-num">10K+</span>
            <div class="about-stat-label">Teams Worldwide</div>
          </div>
          <div class="about-stat">
            <span class="about-stat-num">5M+</span>
            <div class="about-stat-label">Documents Signed</div>
          </div>
          <div class="about-stat">
            <span class="about-stat-num">99.9%</span>
            <div class="about-stat-label">Platform Uptime</div>
          </div>
          <div class="about-stat">
            <span class="about-stat-num">0</span>
            <div class="about-stat-label">Security Breaches</div>
          </div>
        </div>
        <div style="margin-top:20px;background:var(--surface);border:1px solid var(--border);border-radius:16px;padding:24px">
          <div style="font-size:.7rem;letter-spacing:.1em;text-transform:uppercase;color:var(--text-dim);margin-bottom:12px">Compliance & Certifications</div>
          <div style="display:flex;flex-wrap:wrap;gap:8px">
            <span style="background:rgba(46,196,182,0.08);border:1px solid rgba(46,196,182,0.2);padding:5px 12px;border-radius:8px;font-size:.75rem;color:var(--teal-light);font-weight:600">BSP Licensed</span>
            <span style="background:rgba(46,196,182,0.08);border:1px solid rgba(46,196,182,0.2);padding:5px 12px;border-radius:8px;font-size:.75rem;color:var(--teal-light);font-weight:600">ISO 9001:2015</span>
            <span style="background:rgba(46,196,182,0.08);border:1px solid rgba(46,196,182,0.2);padding:5px 12px;border-radius:8px;font-size:.75rem;color:var(--teal-light);font-weight:600">CSC Member</span>
            <span style="background:rgba(46,196,182,0.08);border:1px solid rgba(46,196,182,0.2);padding:5px 12px;border-radius:8px;font-size:.75rem;color:var(--teal-light);font-weight:600">PAdES Compliant</span>
            <span style="background:rgba(46,196,182,0.08);border:1px solid rgba(46,196,182,0.2);padding:5px 12px;border-radius:8px;font-size:.75rem;color:var(--teal-light);font-weight:600">XAdES Compliant</span>
            <span style="background:rgba(46,196,182,0.08);border:1px solid rgba(46,196,182,0.2);padding:5px 12px;border-radius:8px;font-size:.75rem;color:var(--teal-light);font-weight:600">AES-256 Encrypted</span>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ADVERTISEMENT -->
<section class="showcase-section" id="showcase" style="padding:72px 0;background:linear-gradient(180deg,rgba(46,196,182,0.12),rgba(6,13,16,0.94));border-top:2px solid rgba(46,196,182,0.35);border-bottom:2px solid rgba(46,196,182,0.35);">
  <div class="container">
    <div style="text-align:center;margin-bottom:18px">
      <div class="section-label" style="font-size:.9rem;letter-spacing:.2em;color:var(--gold)">ADVERTISEMENT</div>
      <h2 style="font-family:var(--font-display);font-size:clamp(2.2rem,4vw,3.2rem);color:#fff;margin:0">DocuTrust Advertisement</h2>
      <p style="margin:12px auto 0;max-width:900px;color:#d5e9e6;font-size:1.08rem;line-height:1.75">
        Watch our official product video and see how DocuTrust helps teams sign, verify, and automate document workflows with security and speed.
      </p>
    </div>
    <div style="max-width:1100px;margin:0 auto;border-radius:20px;overflow:hidden;border:1px solid rgba(46,196,182,0.35);box-shadow:0 20px 60px rgba(0,0,0,0.4);background:#000">
      <video class="showcase-video" controls preload="metadata" playsinline poster="{{ asset('images/about-us.jpg') }}">
        <source src="{{ asset('images/docutrust.mp4') }}" type="video/mp4">
        Your browser does not support the video tag.
      </video>
    </div>
    <div style="margin-top:20px;display:flex;justify-content:center;gap:12px;flex-wrap:wrap">
      <a href="{{ $primaryCtaUrl }}" class="btn-cta">{{ $primaryCtaLabel }}</a>
      <a href="{{ route('verify.index') }}" class="btn-secondary">Verify a document</a>
    </div>
  </div>
</section>

<!-- TESTIMONIALS -->
<section class="testimonials-section">
  <div class="container">
    <div class="section-label reveal" style="text-align:center">Testimonials</div>
    <h2 class="section-title reveal" style="text-align:center">What Our Users Say</h2>
    <div class="testimonials-grid">
      <div class="testimonial reveal">
        <div class="stars">
          <svg fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
          <svg fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
          <svg fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
          <svg fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
          <svg fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
        </div>
        <p class="testimonial-text">DocuTrust reduced our contract turnaround time dramatically. The blockchain verification gives our legal team the confidence they need — every signature is irrefutable.</p>
        <div class="testimonial-author">
          <div class="testimonial-avatar">OL</div>
          <div>
            <div class="testimonial-name">Operations Lead</div>
            <div class="testimonial-role">Civic Agency</div>
          </div>
        </div>
      </div>
      <div class="testimonial reveal">
        <div class="stars">
          <svg fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
          <svg fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
          <svg fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
          <svg fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
          <svg fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
        </div>
        <p class="testimonial-text">The audit trail and verification workflow are exactly what our legal team needed. The CSC compliance and blockchain anchoring mean we can stand behind every signed document in court.</p>
        <div class="testimonial-author">
          <div class="testimonial-avatar">PA</div>
          <div>
            <div class="testimonial-name">Partner</div>
            <div class="testimonial-role">Legal Advisory Group</div>
          </div>
        </div>
      </div>
      <div class="testimonial reveal">
        <div class="stars">
          <svg fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
          <svg fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
          <svg fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
          <svg fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
          <svg fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
        </div>
        <p class="testimonial-text">Secure, intuitive, and lightning-fast onboarding for every signer. The AI assistant saves our HR team hours every week — it handles reminders, routing, and extraction automatically.</p>
        <div class="testimonial-author">
          <div class="testimonial-avatar">HD</div>
          <div>
            <div class="testimonial-name">HR Director</div>
            <div class="testimonial-role">Enterprise Group</div>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- FAQ -->
<section class="faq-section" id="faq">
  <div class="container">
    <div class="section-label reveal" style="text-align:center">FAQ</div>
    <h2 class="section-title reveal" style="text-align:center">Frequently Asked Questions</h2>
    <div class="faq-list">
      <details class="faq-item reveal">
        <summary>What is DocuTrust?</summary>
        <div class="faq-body">DocuTrust is a secure, blockchain-powered digital signing platform that helps organizations send, sign, and manage documents online — with full document automation, AI-powered processing, and CSC-compliant cloud signatures.</div>
      </details>
      <details class="faq-item reveal">
        <summary>Are DocuTrust signatures legally binding?</summary>
        <div class="faq-body">Yes. DocuTrust supports legally recognized electronic signatures aligned with international e-signature laws. Every signed document is blockchain-anchored, timestamped, and audit-logged — making it court-admissible and tamper-evident.</div>
      </details>
      <details class="faq-item reveal">
        <summary>What is the Cloud Signature Consortium (CSC)?</summary>
        <div class="faq-body">The Cloud Signature Consortium (CSC) is the global standards body for cloud-based digital signatures, trusted by enterprises, governments, and regulators worldwide. As a CSC member, DocuTrust implements open CSC API standards ensuring legal validity, interoperability, and cross-border compliance. Visit <a href="https://cloudsignatureconsortium.org/" target="_blank" rel="noopener" style="color:var(--teal)">cloudsignatureconsortium.org</a> to learn more.</div>
      </details>
      <details class="faq-item reveal">
        <summary>How is DocuTrust secured with blockchain?</summary>
        <div class="faq-body">Every document signed through DocuTrust receives a unique cryptographic hash that is anchored on the blockchain. This makes it mathematically impossible to alter the document post-signing — any tampering instantly invalidates the record, providing irrefutable proof of integrity.</div>
      </details>
      <details class="faq-item reveal">
        <summary>What certifications does DocuTrust hold?</summary>
        <div class="faq-body">DocuTrust is backed by Surepay Technologies Inc., which holds ISO 9001:2015 certification for quality management and operates as a BSP-licensed payment service operator in the Philippines. DocuTrust is also a member of the Cloud Signature Consortium and implements PAdES and XAdES compliant signature standards.</div>
      </details>
      <details class="faq-item reveal">
        <summary>Can I use DocuTrust for my organization?</summary>
        <div class="faq-body">Yes. Teams of any size across government, legal, education, finance, real estate, and HR can deploy DocuTrust for approvals, contracts, onboarding, and internal workflows — all with enterprise-grade security and full audit trails.</div>
      </details>
    </div>
  </div>
</section>

<!-- CTA -->
<section class="cta-section">
  <div class="container">
    <div class="cta-inner reveal">
      <h2>Start Signing Smarter Today</h2>
      <p>Move from manual paperwork to secure, blockchain-verified, AI-powered digital workflows — trusted by 10,000+ teams worldwide.</p>
      <div class="cta-actions">
        <a href="{{ $primaryCtaUrl }}" class="btn-cta">
          <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 5.25a3 3 0 013 3m3 0a6 6 0 01-7.029 5.912c-.563-.097-1.159.026-1.563.43L10.5 17.25H8.25v2.25H6v2.25H2.25v-2.818c0-.597.237-1.17.659-1.591l6.499-6.499c.404-.404.527-1 .43-1.563A6 6 0 1121.75 8.25z"/></svg>
          {{ $authenticatedUser ? __('Open workspace') : __('Create Free Account') }}
        </a>
        <a href="mailto:{{ config('mail.from.address') }}?subject=Sales%20inquiry" class="btn-secondary cta-talk-sales">
          Talk to Sales
        </a>
      </div>
    </div>
  </div>
</section>

<!-- FOOTER -->
<footer>
  <div class="container">
    <div class="footer-grid">
      <div>
        <a href="{{ route('home') }}" class="footer-logo">
          <span class="logo-mark">
            @if ($docutrustLogoLight)
              <picture>
                <source media="(prefers-color-scheme: light)" srcset="{{ $docutrustLogoLight }}">
                <img
                  src="{{ $docutrustLogoDefault }}"
                  alt=""
                  class="logo-img"
                  width="36"
                  height="36"
                  loading="lazy"
                  decoding="async"
                >
              </picture>
            @else
              <img
                src="{{ $docutrustLogoDefault }}"
                alt=""
                class="logo-img"
                width="36"
                height="36"
                loading="lazy"
                decoding="async"
              >
            @endif
          </span>
          <span class="logo-text">{{ config('app.name') }}</span>
        </a>
        <p class="footer-desc">Secure, tamper-proof digital signatures powered by Agentic AI and blockchain-certified trust.</p>
        <a href="https://cloudsignatureconsortium.org/" target="_blank" rel="noopener" class="footer-csc">
          <svg width="10" height="10" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
          CSC Member
        </a>
      </div>
      <div class="footer-col">
        <h4>Product</h4>
        <div class="footer-links">
          <a href="#features">Features</a>
          <a href="#blockchain">Security</a>
          <a href="#ai">AI Engine</a>
          <a href="#industries">Integrations</a>
          <a href="{{ route('verify.index') }}">Verify document</a>
        </div>
      </div>
      <div class="footer-col">
        <h4>Company</h4>
        <div class="footer-links">
          <a href="#about">About Us</a>
          <a href="#">Blog</a>
          <a href="#">Careers</a>
          <a href="mailto:{{ config('mail.from.address') }}">Contact</a>
          <a href="https://cloudsignatureconsortium.org/" target="_blank" rel="noopener">CSC Membership</a>
        </div>
      </div>
      <div class="footer-col">
        <h4>Support</h4>
        <div class="footer-links">
          <a href="#faq">Help Center</a>
          <a href="#faq">Privacy Policy</a>
          <a href="#faq">Terms of Service</a>
          <a href="#blockchain">Trust &amp; Compliance</a>
          <a href="#">Status</a>
        </div>
      </div>
    </div>
    <div class="footer-bottom">
      <div class="footer-copy">© {{ date('Y') }} {{ config('app.name') }}. All rights reserved.</div>
      <div class="footer-powered">Powered by <a href="#">Surepay Technologies Inc.</a> · BSP-Licensed · ISO 9001:2015</div>
    </div>
  </div>
</footer>

<script>
// Mobile nav
const mobileNavToggle = document.getElementById('mobileNavToggle');
const mobileNavClose = document.getElementById('mobileNavClose');
const mobileNav = document.getElementById('mobileNav');

function openMobileNav() {
  if (!mobileNav) {
    return;
  }
  mobileNav.hidden = false;
  mobileNav.classList.add('open');
  document.body.classList.add('mobile-nav-open');
  mobileNavToggle?.setAttribute('aria-expanded', 'true');
  requestAnimationFrame(() => mobileNavClose?.focus());
}

function closeMobileNav() {
  if (!mobileNav) {
    return;
  }
  mobileNav.classList.remove('open');
  mobileNav.hidden = true;
  document.body.classList.remove('mobile-nav-open');
  mobileNavToggle?.setAttribute('aria-expanded', 'false');
  mobileNavToggle?.focus();
}

mobileNavToggle?.addEventListener('click', (e) => {
  e.preventDefault();
  e.stopPropagation();
  if (mobileNav?.classList.contains('open')) {
    closeMobileNav();
  } else {
    openMobileNav();
  }
});
mobileNavClose?.addEventListener('click', () => closeMobileNav());
mobileNav?.addEventListener('click', (e) => {
  if (e.target === mobileNav) {
    closeMobileNav();
  }
});
document.addEventListener('keydown', (e) => {
  if (e.key === 'Escape' && mobileNav?.classList.contains('open')) {
    closeMobileNav();
  }
});

// Scroll reveal
const revealEls = document.querySelectorAll('.reveal');
const observer = new IntersectionObserver((entries) => {
  entries.forEach((entry, i) => {
    if (entry.isIntersecting) {
      entry.target.style.transitionDelay = (i * 40) + 'ms';
      entry.target.classList.add('visible');
      observer.unobserve(entry.target);
    }
  });
}, { threshold: 0.1, rootMargin: '0px 0px -40px 0px' });
revealEls.forEach(el => observer.observe(el));
</script>

<x-marketing-chatbot />
</body>
</html>
