@include('layouts.partials.marketing-theme-variables')
@include('layouts.partials.marketing-header-styles')
<style>
html{
  --color-background-primary:var(--bg);
  --color-background-secondary:var(--surface);
  --color-background-tertiary:var(--surface2);
  --color-background-info:rgba(13,148,136,.1);
  --color-background-success:rgba(22,101,52,.12);
  --color-background-warning:rgba(180,83,9,.12);
  --color-background-danger:rgba(220,38,38,.12);
  --color-border-primary:var(--border);
  --color-border-secondary:var(--border);
  --color-border-tertiary:var(--border2);
  --color-border-info:rgba(13,148,136,.24);
  --color-border-success:rgba(22,101,52,.25);
  --color-border-warning:rgba(180,83,9,.28);
  --color-border-danger:rgba(220,38,38,.25);
  --color-text-primary:var(--text);
  --color-text-secondary:var(--text-muted);
  --color-text-tertiary:var(--text-dim);
  --color-text-info:var(--teal);
  --color-text-success:var(--green-mid);
  --color-text-warning:var(--gold);
  --color-text-danger:rgb(220,38,38);
  --feature-illustration-wrapper-bg:radial-gradient(circle at 18% 12%,rgba(13,148,136,.18),transparent 34%),radial-gradient(circle at 82% 78%,rgba(22,101,52,.16),transparent 34%),rgba(255,255,255,.42);
  --feature-illustration-wrapper-border:rgba(13,148,136,.16);
  --feature-illustration-wrapper-shadow:0 30px 90px rgba(15,23,42,.12),inset 0 1px 0 rgba(255,255,255,.55);
  --feature-illustration-panel-start:rgba(255,255,255,.98);
  --feature-illustration-panel-end:rgba(232,243,240,.94);
  --feature-illustration-panel-border:rgba(13,148,136,.22);
  --feature-illustration-inner-border:rgba(13,148,136,.12);
  --feature-illustration-line:rgba(13,148,136,.24);
  --feature-illustration-shadow-info:rgba(13,148,136,.34);
  --feature-illustration-shadow-success:rgba(22,101,52,.3);
  --feature-illustration-glow-info:rgba(13,148,136,.32);
  --feature-illustration-glow-success:rgba(22,101,52,.28);
}
html.dark-scheme{
  --color-background-secondary:rgba(24,34,48,.98);
  --color-background-tertiary:rgba(14,23,38,.96);
  --color-background-info:rgba(94,162,255,.2);
  --color-background-success:rgba(45,122,53,.26);
  --color-background-warning:rgba(255,209,102,.2);
  --color-background-danger:rgba(248,113,113,.14);
  --color-border-info:rgba(94,162,255,.48);
  --color-border-success:rgba(74,222,128,.38);
  --color-border-warning:rgba(255,209,102,.42);
  --color-border-danger:rgba(248,113,113,.28);
  --color-text-danger:rgb(248,113,113);
  --feature-illustration-wrapper-bg:radial-gradient(circle at 18% 12%,rgba(94,162,255,.34),transparent 36%),radial-gradient(circle at 82% 78%,rgba(45,122,53,.3),transparent 34%),rgba(8,14,24,.58);
  --feature-illustration-wrapper-border:rgba(159,176,195,.28);
  --feature-illustration-wrapper-shadow:0 40px 120px rgba(0,0,0,.62),0 0 80px rgba(94,162,255,.1),inset 0 1px 0 rgba(255,255,255,.1);
  --feature-illustration-panel-start:rgba(27,38,54,.99);
  --feature-illustration-panel-end:rgba(9,17,30,.97);
  --feature-illustration-panel-border:rgba(94,162,255,.38);
  --feature-illustration-inner-border:rgba(159,176,195,.22);
  --feature-illustration-line:rgba(94,162,255,.42);
  --feature-illustration-shadow-info:rgba(94,162,255,.55);
  --feature-illustration-shadow-success:rgba(74,222,128,.42);
  --feature-illustration-glow-info:rgba(94,162,255,.5);
  --feature-illustration-glow-success:rgba(74,222,128,.38);
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
html{color-scheme:light dark;font-size:18px}
@media (prefers-reduced-motion:no-preference){
  html.docutrust-smooth-scroll{scroll-behavior:smooth}
}
body{
  font-family:var(--font-body);
  background:var(--bg);
  color:var(--text);
  line-height:1.72;
  overflow-x:hidden;
}
.container{max-width:1280px;margin:0 auto;padding:0 24px}
.orb{
  position:fixed;
  border-radius:50%;
  pointer-events:none;
  z-index:0;
  filter:blur(100px);
  opacity:.12;
}
html.dark-scheme .orb{opacity:.18}
.orb1{width:420px;height:420px;background:var(--teal);top:-80px;right:-80px}
.orb2{width:320px;height:320px;background:var(--green);bottom:-80px;left:-80px}
.btn-secondary{
  display:inline-flex;
  align-items:center;
  gap:8px;
  background:transparent;
  color:var(--text);
  font-size:1rem;
  font-weight:500;
  padding:14px 26px;
  border-radius:11px;
  border:1px solid var(--border);
  text-decoration:none;
  transition:all .25s;
}
.btn-secondary:hover{border-color:var(--teal);color:var(--teal)}
.btn-cta{
  display:inline-flex;
  align-items:center;
  gap:8px;
  background:linear-gradient(135deg,var(--teal),var(--green-mid));
  color:#fff;
  font-size:1rem;
  font-weight:600;
  padding:14px 26px;
  border-radius:11px;
  text-decoration:none;
  box-shadow:0 0 32px rgba(46,196,182,0.4);
  transition:all .25s;
}
.btn-cta:hover{
  transform:translateY(-2px);
  box-shadow:0 0 48px rgba(46,196,182,0.6);
}
main{position:relative;z-index:1;padding:48px 0 80px}
.feature-detail-hero{padding:24px 0 32px}
.feature-detail-label{
  font-size:.7rem;
  letter-spacing:.15em;
  text-transform:uppercase;
  color:var(--teal);
  font-weight:700;
  margin-bottom:12px;
}
.feature-detail-title{
  font-family:var(--font-display);
  font-weight:800;
  font-size:clamp(2rem,3.5vw,2.75rem);
  color:var(--headline);
  line-height:1.15;
  margin-bottom:16px;
}
.feature-detail-summary{
  font-size:1.1rem;
  color:var(--text-muted);
  max-width:720px;
  line-height:1.75;
}
.feature-detail-grid{
  display:grid;
  grid-template-columns:1.2fr 1fr;
  gap:40px;
  margin-top:40px;
}
.feature-detail-card{
  background:var(--surface);
  border:1px solid var(--border2);
  border-radius:18px;
  padding:28px;
}
.feature-detail-card h2{
  font-family:var(--font-display);
  font-size:1.15rem;
  color:var(--headline);
  margin-bottom:16px;
}
.feature-detail-card p{
  color:var(--text-muted);
  line-height:1.75;
  margin-bottom:20px;
}
.feature-detail-list{
  list-style:none;
  display:flex;
  flex-direction:column;
  gap:12px;
}
.feature-detail-list li{
  display:flex;
  gap:10px;
  color:var(--text-muted);
  font-size:.95rem;
  line-height:1.6;
}
.feature-detail-list li svg{
  width:18px;
  height:18px;
  color:var(--teal);
  flex-shrink:0;
  margin-top:2px;
}
.feature-detail-icon{
  width:56px;
  height:56px;
  background:rgba(46,196,182,0.1);
  border:1px solid rgba(46,196,182,0.2);
  border-radius:14px;
  display:flex;
  align-items:center;
  justify-content:center;
  margin-bottom:20px;
}
html.dark-scheme .feature-detail-icon{
  background:rgba(94,162,255,0.12);
  border-color:rgba(94,162,255,0.24);
}
.feature-detail-icon svg{width:26px;height:26px;color:var(--teal)}
.feature-detail-actions{
  display:flex;
  flex-wrap:wrap;
  gap:12px;
  margin-top:32px;
}
.feature-hero-illustration{
  width:100%;
  max-width:760px;
  margin:2.5rem auto 0;
  padding:10px;
  border:1px solid var(--feature-illustration-wrapper-border);
  border-radius:34px;
  background:var(--feature-illustration-wrapper-bg);
  box-shadow:var(--feature-illustration-wrapper-shadow);
  isolation:isolate;
}
.feature-hero-illustration svg{
  display:block;
  width:100%;
  height:auto;
  color-scheme:light dark;
}
.feature-related{margin-top:56px}
.feature-related h2{
  font-family:var(--font-display);
  font-size:1.35rem;
  color:var(--headline);
  margin-bottom:20px;
}
.feature-related-grid{
  display:grid;
  grid-template-columns:repeat(3,1fr);
  gap:16px;
}
.feature-related-card{
  background:var(--surface);
  border:1px solid var(--border2);
  border-radius:14px;
  padding:20px;
  text-decoration:none;
  color:inherit;
  transition:all .25s;
}
.feature-related-card:hover{
  border-color:rgba(94,162,255,0.35);
  transform:translateY(-3px);
}
.feature-related-card h3{
  font-family:var(--font-display);
  font-size:1rem;
  color:var(--headline);
  margin-bottom:8px;
}
.feature-related-card p{
  font-size:.85rem;
  color:var(--text-muted);
  line-height:1.55;
}
.marketing-footer{
  position:relative;
  z-index:1;
  border-top:1px solid var(--border2);
  padding:32px 0;
  background:var(--footer-bg);
}
.marketing-footer-inner{
  display:flex;
  justify-content:space-between;
  align-items:center;
  flex-wrap:wrap;
  gap:16px;
}
.marketing-back{
  color:var(--teal);
  font-weight:600;
  text-decoration:none;
}
.marketing-back:hover{color:var(--teal-light)}
@media(max-width:900px){
  .feature-detail-grid{grid-template-columns:1fr}
  .feature-related-grid{grid-template-columns:1fr 1fr}
}
@media(max-width:600px){
  .feature-related-grid{grid-template-columns:1fr}
}
</style>
