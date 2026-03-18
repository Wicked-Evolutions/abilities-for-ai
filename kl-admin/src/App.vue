<template>
  <div class="kl-shell">
    <AppNav />
    <main class="kl-main">
      <router-view />
    </main>
  </div>
</template>

<script setup>
import AppNav from './components/AppNav.vue'
</script>

<style>
@import url('https://fonts.googleapis.com/css2?family=Syne:wght@500;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap');

/* ── Scope all KL styles under #abilities-kl-app to avoid WP admin conflicts ── */
#abilities-kl-app {
  /* Tokens */
  --bg-base:       #111111;
  --bg-surface:    #161618;
  --bg-raised:     #1c1c20;
  --bg-overlay:    #242428;
  --bg-hover:      #2a2a30;

  --border-subtle:  rgba(255,255,255,0.06);
  --border-default: rgba(255,255,255,0.10);
  --border-strong:  rgba(255,255,255,0.18);

  --text-primary:   rgba(224,221,213,0.87);
  --text-secondary: rgba(224,221,213,0.65);
  --text-muted:     rgba(224,221,213,0.38);

  --accent:         #FFEE58;
  --accent-dim:     rgba(255,238,88,0.10);
  --accent-glow:    rgba(255,238,88,0.05);
  --accent-text:    #FFEE58;

  /* Doc type badge colours */
  --type-skill:       #26A69A;
  --type-agent:       #FF8A65;
  --type-knowledge:   #42A5F5;
  --type-course:      #66BB6A;
  --type-config:      #AB47BC;
  --type-diagnostic:  #FFCA28;
  --type-boot:        #EF5350;
  --type-essence:     #EC407A;
  --type-template:    #78909C;
  --type-site-identity:#7E57C2;
  --type-site-state:  #5C6BC0;
  --type-capabilities:#29B6F6;

  /* Status colours */
  --status-active:    #66BB6A;
  --status-draft:     #42A5F5;
  --status-seed:      #9E9E9E;
  --status-archived:  #616161;

  /* Severity colours */
  --sev-info:         #9E9E9E;
  --sev-attention:    #FFB74D;
  --sev-action:       #EF5350;

  /* Observation status */
  --obs-open:         #42A5F5;
  --obs-resolved:     #66BB6A;
  --obs-wontfix:      #9E9E9E;
  --obs-deferred:     #FFB74D;

  /* Fonts */
  --font-display: 'Syne', sans-serif;
  --font-mono:    'JetBrains Mono', monospace;
  --font-body:    -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;

  /* Layout */
  --sidebar-width:   220px;
  --topbar-height:   52px;
  --content-pad:     28px;

  --ease: cubic-bezier(0.22, 1, 0.36, 1);
  --radius-sm:  4px;
  --radius-md:  6px;
  --radius-lg:  10px;

  /* Reset within our app scope */
  background: var(--bg-base);
  color: var(--text-primary);
  font-family: var(--font-body);
  font-size: 14px;
  line-height: 1.5;
  -webkit-font-smoothing: antialiased;
  min-height: 100vh;
  margin: 0;
  padding: 0;
}

#abilities-kl-app *, #abilities-kl-app *::before, #abilities-kl-app *::after {
  box-sizing: border-box;
}

#abilities-kl-app a { color: var(--accent-text); text-decoration: none; transition: opacity .15s; }
#abilities-kl-app a:hover { opacity: .8; }

/* ── Shell Layout ── */
.kl-shell {
  display: flex;
  min-height: 100vh;
}

/* ── Left Sidebar ── */
.kl-sidebar {
  width: var(--sidebar-width);
  flex-shrink: 0;
  background: var(--bg-surface);
  border-right: 1px solid var(--border-subtle);
  position: fixed;
  top: 32px; bottom: 0; left: 160px; /* WP admin bar (32px) + admin menu (160px) */
  z-index: 50;
  display: flex;
  flex-direction: column;
  overflow-y: auto;
}
.kl-sidebar::-webkit-scrollbar { width: 3px; }
.kl-sidebar::-webkit-scrollbar-track { background: transparent; }
.kl-sidebar::-webkit-scrollbar-thumb { background: var(--border-default); border-radius: 2px; }

.kl-sidebar-brand {
  padding: 16px 16px 12px;
  border-bottom: 1px solid var(--border-subtle);
}
.kl-sidebar-brand h2 {
  font-family: var(--font-display);
  font-size: .75rem;
  font-weight: 700;
  letter-spacing: .06em;
  text-transform: uppercase;
  color: var(--text-muted);
  margin-bottom: 2px;
}
.kl-sidebar-brand h1 {
  font-family: var(--font-display);
  font-size: 1rem;
  font-weight: 800;
  color: var(--text-primary);
  letter-spacing: -0.01em;
}
.kl-sidebar-brand .dot {
  display: inline-block;
  width: 6px; height: 6px;
  border-radius: 50%;
  background: var(--accent);
  margin-right: 6px;
  position: relative;
  top: -1px;
}

.kl-nav {
  padding: 12px 8px;
  flex: 1;
}
.kl-nav-item {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 9px 12px;
  border-radius: var(--radius-md);
  font-family: var(--font-display);
  font-size: .8125rem;
  font-weight: 600;
  color: var(--text-secondary);
  transition: color .15s, background .15s;
  text-decoration: none;
  margin-bottom: 2px;
}
.kl-nav-item:hover {
  color: var(--text-primary);
  background: var(--bg-hover);
  opacity: 1;
}
.kl-nav-item.active {
  color: var(--accent);
  background: var(--accent-dim);
  opacity: 1;
}
.kl-nav-icon {
  font-size: 1rem;
  width: 20px;
  text-align: center;
  flex-shrink: 0;
  opacity: .7;
}
.kl-nav-item.active .kl-nav-icon { opacity: 1; }

.kl-sidebar-footer {
  padding: 12px 16px;
  border-top: 1px solid var(--border-subtle);
  font-family: var(--font-mono);
  font-size: .6875rem;
  color: var(--text-muted);
}

/* ── Main Area ── */
.kl-main {
  margin-left: var(--sidebar-width);
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
}

/* ── Page Header Bar ── */
.kl-page-header {
  padding: 20px var(--content-pad);
  border-bottom: 1px solid var(--border-subtle);
  display: flex;
  align-items: center;
  gap: 16px;
  background: var(--bg-surface);
  position: sticky;
  top: 32px; /* below WP admin bar */
  z-index: 40;
}
.kl-page-title {
  font-family: var(--font-display);
  font-size: 1.25rem;
  font-weight: 800;
  color: var(--text-primary);
  letter-spacing: -0.02em;
}

/* ── View Body ── */
.kl-view-body {
  padding: var(--content-pad);
  flex: 1;
}

/* ── Badges ── */
.kl-badge {
  display: inline-flex;
  align-items: center;
  font-family: var(--font-display);
  font-size: .625rem;
  font-weight: 700;
  letter-spacing: .08em;
  text-transform: uppercase;
  padding: 3px 8px;
  border-radius: var(--radius-sm);
  border: 1px solid transparent;
  white-space: nowrap;
}

/* Doc type badges */
.badge-skill       { background: rgba(38,166,154,0.12); color: var(--type-skill);      border-color: rgba(38,166,154,0.25); }
.badge-agent       { background: rgba(255,138,101,0.12); color: var(--type-agent);      border-color: rgba(255,138,101,0.25); }
.badge-knowledge   { background: rgba(66,165,245,0.12); color: var(--type-knowledge);  border-color: rgba(66,165,245,0.25); }
.badge-course      { background: rgba(102,187,106,0.12); color: var(--type-course);     border-color: rgba(102,187,106,0.25); }
.badge-config      { background: rgba(171,71,188,0.12); color: var(--type-config);     border-color: rgba(171,71,188,0.25); }
.badge-diagnostic  { background: rgba(255,202,40,0.12); color: var(--type-diagnostic); border-color: rgba(255,202,40,0.25); }
.badge-boot        { background: rgba(239,83,80,0.12);  color: var(--type-boot);       border-color: rgba(239,83,80,0.25); }
.badge-essence     { background: rgba(236,64,122,0.12); color: var(--type-essence);    border-color: rgba(236,64,122,0.25); }
.badge-template    { background: rgba(120,144,156,0.12); color: var(--type-template);   border-color: rgba(120,144,156,0.25); }
.badge-site-identity { background: rgba(126,87,194,0.12); color: var(--type-site-identity); border-color: rgba(126,87,194,0.25); }
.badge-site-state  { background: rgba(92,107,192,0.12); color: var(--type-site-state); border-color: rgba(92,107,192,0.25); }
.badge-capabilities{ background: rgba(41,182,246,0.12); color: var(--type-capabilities); border-color: rgba(41,182,246,0.25); }

/* Status badges */
.badge-active      { background: rgba(102,187,106,0.12); color: var(--status-active);   border-color: rgba(102,187,106,0.25); }
.badge-draft       { background: rgba(66,165,245,0.12); color: var(--status-draft);    border-color: rgba(66,165,245,0.25); }
.badge-seed        { background: rgba(158,158,158,0.12); color: var(--status-seed);     border-color: rgba(158,158,158,0.25); }
.badge-archived    { background: rgba(97,97,97,0.12);   color: var(--status-archived); border-color: rgba(97,97,97,0.25); }

/* Tag Chips */
.kl-tag {
  display: inline-flex;
  align-items: center;
  font-family: var(--font-mono);
  font-size: .6875rem;
  padding: 2px 8px;
  border-radius: 3px;
  border: 1px solid var(--border-default);
  background: var(--bg-overlay);
  color: var(--text-secondary);
  white-space: nowrap;
}
.kl-tag .tag-dot {
  width: 6px; height: 6px;
  border-radius: 50%;
  margin-right: 5px;
  flex-shrink: 0;
}

/* Pagination */
.kl-pagination {
  padding: 14px var(--content-pad);
  display: flex;
  align-items: center;
  justify-content: space-between;
  border-top: 1px solid var(--border-subtle);
  font-size: .8125rem;
  color: var(--text-muted);
}
.kl-pagination-info { font-family: var(--font-mono); font-size: .75rem; }
.kl-pagination-pages { display: flex; gap: 4px; }
.kl-page-btn {
  width: 30px; height: 30px;
  display: flex; align-items: center; justify-content: center;
  border-radius: var(--radius-sm);
  border: 1px solid var(--border-default);
  background: var(--bg-raised);
  color: var(--text-secondary);
  font-family: var(--font-mono);
  font-size: .75rem;
  cursor: pointer;
  transition: all .15s;
}
.kl-page-btn:hover { border-color: var(--border-strong); color: var(--text-primary); }
.kl-page-btn.active { background: var(--accent-dim); border-color: var(--accent); color: var(--accent); }

/* ── Responsive ── */
@media (max-width: 768px) {
  .kl-sidebar { display: none; }
  .kl-main { margin-left: 0; }
}
</style>
