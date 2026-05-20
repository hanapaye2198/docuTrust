<style>
:root {
  --font-body: 'Source Sans 3', system-ui, sans-serif;
  --font-display: 'Outfit', system-ui, sans-serif;
  --teal: #2EC4B6;
  --teal-dark: #1a9e92;
  --teal-light: #7ce8dc;
  --gold: #FFD166;
  --bg: #060d10;
  --surface: #0d1a1f;
  --border: rgba(46,196,182,0.15);
  --border2: rgba(46,196,182,0.08);
  --text: #e8f4f2;
  --text-muted: #7a9e9b;
  --text-dim: #4a706d;
  --headline: #ffffff;
  --header-bg: rgba(6,13,16,0.88);
}
@media (prefers-color-scheme: light) {
  :root {
    --teal: #0d9488;
    --teal-dark: #0f766e;
    --teal-light: #0f766e;
    --gold: #b45309;
    --bg: #f0f7f5;
    --surface: #ffffff;
    --border: rgba(13, 148, 136, 0.2);
    --border2: rgba(13, 148, 136, 0.1);
    --text: #0f172a;
    --text-muted: #475569;
    --text-dim: #64748b;
    --headline: #0a1917;
    --header-bg: rgba(255, 255, 255, 0.92);
  }
}
html.light-scheme{
  --teal: #0d9488;
  --teal-dark: #0f766e;
  --teal-light: #0f766e;
  --gold: #b45309;
  --bg: #f0f7f5;
  --surface: #ffffff;
  --border: rgba(13, 148, 136, 0.2);
  --border2: rgba(13, 148, 136, 0.1);
  --text: #0f172a;
  --text-muted: #475569;
  --text-dim: #64748b;
  --headline: #0a1917;
  --header-bg: rgba(255, 255, 255, 0.92);
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
html{color-scheme:dark light;font-size:18px}
body{font-family:var(--font-body);background:var(--bg);color:var(--text);line-height:1.72;overflow-x:hidden}
.container{max-width:1280px;margin:0 auto;padding:0 24px}
.orb{position:fixed;border-radius:50%;pointer-events:none;z-index:0;filter:blur(100px);opacity:.15}
.orb1{width:420px;height:420px;background:var(--teal);top:-80px;right:-80px}
.orb2{width:320px;height:320px;background:#1B5E20;bottom:-80px;left:-80px}
header{position:sticky;top:0;z-index:100;border-bottom:1px solid var(--border);background:var(--header-bg);backdrop-filter:blur(20px)}
.header-inner{max-width:1280px;margin:0 auto;padding:0 24px;height:72px;display:flex;align-items:center;justify-content:space-between;gap:16px}
.logo{display:flex;align-items:center;gap:10px;text-decoration:none}
.logo-mark{display:grid;background:#0a0a0a;border-radius:10px;padding:6px;width:52px;height:52px;box-shadow:0 0 20px rgba(46,196,182,0.2)}
html.light-scheme .logo-mark{background:#fff;box-shadow:0 2px 10px rgba(0,0,0,0.06),0 0 0 1px rgba(0,0,0,0.08)}
.logo-img{width:100%;height:100%;object-fit:contain;border-radius:8px}
.logo-text{font-family:var(--font-display);font-weight:800;font-size:1.2rem;color:var(--text)}
nav{display:flex;align-items:center;gap:24px}
nav a{font-size:1rem;font-weight:500;color:var(--text-muted);text-decoration:none}
nav a:hover{color:var(--teal)}
.header-actions{display:flex;align-items:center;gap:10px}
.btn-ghost{font-size:1rem;font-weight:500;color:var(--text-muted);text-decoration:none;padding:10px 18px;border-radius:8px}
.btn-ghost:hover{color:var(--teal)}
.btn-primary{font-size:1rem;font-weight:600;background:linear-gradient(135deg,var(--teal),#2d7a35);color:#fff;text-decoration:none;padding:12px 22px;border-radius:10px;white-space:nowrap}
.btn-secondary{display:inline-flex;align-items:center;gap:8px;background:transparent;color:var(--text);font-size:1rem;font-weight:500;padding:14px 26px;border-radius:11px;border:1px solid var(--border);text-decoration:none}
.btn-secondary:hover{border-color:var(--teal);color:var(--teal)}
.btn-cta{display:inline-flex;align-items:center;gap:8px;background:linear-gradient(135deg,var(--teal),#2d7a35);color:#fff;font-size:1rem;font-weight:600;padding:14px 26px;border-radius:11px;text-decoration:none}
main{position:relative;z-index:1;padding:48px 0 80px}
.feature-detail-hero{padding:24px 0 32px}
.feature-detail-label{font-size:.7rem;letter-spacing:.15em;text-transform:uppercase;color:var(--teal);font-weight:700;margin-bottom:12px}
.feature-detail-title{font-family:var(--font-display);font-weight:800;font-size:clamp(2rem,3.5vw,2.75rem);color:var(--headline);line-height:1.15;margin-bottom:16px}
.feature-detail-summary{font-size:1.1rem;color:var(--text-muted);max-width:720px;line-height:1.75}
.feature-detail-grid{display:grid;grid-template-columns:1.2fr 1fr;gap:40px;margin-top:40px}
.feature-detail-card{background:var(--surface);border:1px solid var(--border2);border-radius:18px;padding:28px}
.feature-detail-card h2{font-family:var(--font-display);font-size:1.15rem;color:var(--headline);margin-bottom:16px}
.feature-detail-card p{color:var(--text-muted);line-height:1.75;margin-bottom:20px}
.feature-detail-list{list-style:none;display:flex;flex-direction:column;gap:12px}
.feature-detail-list li{display:flex;gap:10px;color:var(--text-muted);font-size:.95rem;line-height:1.6}
.feature-detail-list li svg{width:18px;height:18px;color:var(--teal);flex-shrink:0;margin-top:2px}
.feature-detail-icon{width:56px;height:56px;background:rgba(46,196,182,0.1);border:1px solid rgba(46,196,182,0.2);border-radius:14px;display:flex;align-items:center;justify-content:center;margin-bottom:20px}
.feature-detail-icon svg{width:26px;height:26px;color:var(--teal)}
.feature-detail-actions{display:flex;flex-wrap:wrap;gap:12px;margin-top:32px}
.feature-related{margin-top:56px}
.feature-related h2{font-family:var(--font-display);font-size:1.35rem;color:var(--headline);margin-bottom:20px}
.feature-related-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:16px}
.feature-related-card{background:var(--surface);border:1px solid var(--border2);border-radius:14px;padding:20px;text-decoration:none;color:inherit;transition:all .25s}
.feature-related-card:hover{border-color:rgba(46,196,182,0.35);transform:translateY(-3px)}
.feature-related-card h3{font-family:var(--font-display);font-size:1rem;color:var(--headline);margin-bottom:8px}
.feature-related-card p{font-size:.85rem;color:var(--text-muted);line-height:1.55}
.marketing-footer{border-top:1px solid var(--border2);padding:32px 0;background:rgba(6,13,16,0.9)}
.marketing-footer-inner{display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:16px}
.marketing-back{color:var(--teal);font-weight:600;text-decoration:none}
.marketing-back:hover{color:var(--teal-light)}
@media(max-width:900px){
  nav{display:none}
  .feature-detail-grid{grid-template-columns:1fr}
  .feature-related-grid{grid-template-columns:1fr 1fr}
}
@media(max-width:600px){
  .feature-related-grid{grid-template-columns:1fr}
}
</style>
