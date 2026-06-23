<style>
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
.logo-img{
  width:100%;
  height:100%;
  max-width:100%;
  max-height:100%;
  object-fit:contain;
  display:block;
  border-radius:8px;
}
.logo-mark .logo-img{
  filter:brightness(1.12) contrast(1.05);
}
html.dark-scheme .logo-mark{
  background:#0a0a0a;
}
html.dark-scheme .logo-mark .logo-img{
  filter:none;
}
.logo-text{
  font-family:var(--font-display);
  font-weight:800;
  font-size:1.2rem;
  color:var(--teal-dark);
  background:none;
  -webkit-text-fill-color:currentColor;
  background-clip:border-box;
}
html.dark-scheme .logo-text{
  color:var(--text);
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
.header-actions{
  display:flex;
  align-items:center;
  gap:10px;
  flex-shrink:1;
  min-width:0;
  justify-content:flex-end;
}
.btn-ghost{
  font-size:1rem;
  font-weight:500;
  color:var(--text-muted);
  text-decoration:none;
  padding:10px 18px;
  border-radius:8px;
  transition:color .2s;
}
.btn-ghost:hover{color:var(--teal)}
.btn-primary{
  font-size:1rem;
  font-weight:600;
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
.theme-toggle{
  display:inline-flex;
  align-items:center;
  justify-content:center;
  gap:10px;
  flex-shrink:0;
  box-sizing:border-box;
  min-width:44px;
  height:44px;
  padding:8px 12px;
  margin:0;
  background:var(--chip-bg);
  border:1px solid var(--border);
  border-radius:12px;
  cursor:pointer;
  -webkit-tap-highlight-color:rgba(46,196,182,0.15);
  touch-action:manipulation;
  transition:background .15s,border-color .15s,transform .1s;
}
.theme-toggle:hover{
  background:rgba(46,196,182,0.08);
  border-color:rgba(46,196,182,0.35);
}
.theme-toggle:active{transform:scale(0.96)}
.theme-toggle:focus-visible{
  outline:2px solid var(--teal);
  outline-offset:2px;
}
.theme-toggle svg{
  width:20px;
  height:20px;
  flex-shrink:0;
}
.theme-toggle-label{
  font-size:.8rem;
  font-weight:600;
  color:var(--text);
  white-space:nowrap;
}
.theme-toggle-icon--moon{display:block}
.theme-toggle-icon--sun{display:none}
html.dark-scheme .theme-toggle-icon--moon{display:none}
html.dark-scheme .theme-toggle-icon--sun{display:block}
.theme-toggle-icon--moon{fill:#6d28d9}
.theme-toggle-icon--sun{fill:#eab308}
body.mobile-nav-open{overflow:hidden;overscroll-behavior:none}
.mobile-nav{
  display:none;
  position:fixed;
  inset:0;
  z-index:200;
  background:var(--mobile-nav-bg);
  backdrop-filter:blur(20px);
  flex-direction:column;
  align-items:center;
  justify-content:center;
  gap:24px;
  padding:max(24px,env(safe-area-inset-top)) max(24px,env(safe-area-inset-right)) max(24px,env(safe-area-inset-bottom)) max(24px,env(safe-area-inset-left));
  -webkit-overflow-scrolling:touch;
  overflow-y:auto;
  overscroll-behavior:contain;
}
.mobile-nav.open{display:flex}
.mobile-nav a{
  font-family:var(--font-display);
  font-size:1.5rem;
  font-weight:700;
  color:var(--text-muted);
  text-decoration:none;
  transition:color .2s;
}
.mobile-nav a:hover{color:var(--teal)}
.mobile-nav-close{
  position:absolute;
  top:max(24px,env(safe-area-inset-top));
  right:max(24px,env(safe-area-inset-right));
  display:inline-flex;
  align-items:center;
  justify-content:center;
  width:44px;
  height:44px;
  padding:0;
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
.mobile-nav-close:focus-visible{
  outline:2px solid var(--teal);
  outline-offset:2px;
}
@media (max-width: 720px){
  .theme-toggle{
    width:44px;
    padding:8px;
  }
  .theme-toggle-label{display:none}
}
@media(max-width:1180px){
  nav{display:none}
  .nav-mobile-toggle{display:inline-flex}
  .header-inner{padding:0 16px}
  .logo-text{font-size:1.05rem}
  .header-actions{gap:8px}
  .btn-ghost{display:none}
  .btn-primary{padding:9px 14px;font-size:.8125rem}
}
@media(max-width:560px){
  .btn-primary{display:none}
}
@media(max-width:420px){
  .logo-text{font-size:.95rem}
}
</style>
