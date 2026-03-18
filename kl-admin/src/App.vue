<template>
  <div class="kl-shell">
    <AppNav :theme="theme" @toggle-theme="toggleTheme" />
    <main class="kl-main">
      <router-view />
    </main>
  </div>
</template>

<script setup>
import { ref, watch, onMounted } from 'vue'
import AppNav from './components/AppNav.vue'

const STORAGE_KEY = 'abilities-kl-theme'
const theme = ref(localStorage.getItem(STORAGE_KEY) || 'dark')

function toggleTheme() {
  theme.value = theme.value === 'dark' ? 'light' : 'dark'
}

function applyTheme(val) {
  const el = document.getElementById('abilities-kl-app')
  if (el) {
    el.classList.toggle('light-theme', val === 'light')
  }
  localStorage.setItem(STORAGE_KEY, val)
}

watch(theme, applyTheme)
onMounted(() => applyTheme(theme.value))
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

/* ── Light Theme Overrides ── */
#abilities-kl-app.light-theme {
  --bg-base:       #F2EFE9;
  --bg-surface:    #EAE6DF;
  --bg-raised:     #E2DDD5;
  --bg-overlay:    #D8D3C9;
  --bg-hover:      #D0CAC0;
  --border-subtle:  rgba(50,44,36,0.08);
  --border-default: rgba(50,44,36,0.14);
  --border-strong:  rgba(50,44,36,0.24);
  --text-primary:   #1C1915;
  --text-secondary: #2E2A24;
  --text-muted:     #7A7268;
  --accent:         #8B6914;
  --accent-dim:     rgba(139,105,20,0.10);
  --accent-glow:    rgba(139,105,20,0.06);
  --accent-text:    #7A5C10;
  --type-skill:#1A7A6E; --type-agent:#BF5A30; --type-knowledge:#1A6E9A; --type-course:#2E7D32;
  --type-config:#7B1FA2; --type-diagnostic:#9A7B00; --type-boot:#C62828; --type-essence:#AD1457;
  --type-template:#546E7A; --type-site-identity:#5E35B1; --type-site-state:#3949AB; --type-capabilities:#0277BD;
  --status-active:#2E7D32; --status-draft:#1565C0; --status-seed:#757575; --status-archived:#9E9E9E;
  --sev-info:#757575; --sev-attention:#E65100; --sev-action:#C62828;
  --obs-open:#1565C0; --obs-resolved:#2E7D32; --obs-wontfix:#757575; --obs-deferred:#E65100;
}

/* Light mode fixes */
#abilities-kl-app.light-theme .kl-filter,
#abilities-kl-app.light-theme .kl-field-select {
  background-image: url("data:image/svg+xml,%3Csvg width='10' height='6' viewBox='0 0 10 6' fill='none' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M1 1L5 5L9 1' stroke='rgba(50,44,36,0.45)' stroke-width='1.5' stroke-linecap='round'/%3E%3C/svg%3E");
}
#abilities-kl-app.light-theme .kl-btn-primary { color: #fff; }
#abilities-kl-app.light-theme .kl-publish-sidebar { box-shadow: -8px 0 32px rgba(0,0,0,.1); }

/* Theme transition */
#abilities-kl-app .kl-sidebar,
#abilities-kl-app .kl-page-header,
#abilities-kl-app .kl-stat-card,
#abilities-kl-app .kl-feed-item,
#abilities-kl-app .kl-content-preview,
#abilities-kl-app .kl-publish-sidebar {
  transition: background .25s cubic-bezier(0.22, 1, 0.36, 1),
              color .25s cubic-bezier(0.22, 1, 0.36, 1),
              border-color .25s cubic-bezier(0.22, 1, 0.36, 1);
}

/* ── Toolbar ── */
.kl-toolbar {
  padding: 16px var(--content-pad);
  display: flex;
  align-items: center;
  gap: 10px;
  flex-wrap: wrap;
  border-bottom: 1px solid var(--border-subtle);
}
.kl-search {
  background: var(--bg-raised);
  border: 1px solid var(--border-default);
  color: var(--text-primary);
  padding: 7px 12px;
  border-radius: var(--radius-md);
  font-family: var(--font-mono);
  font-size: .8125rem;
  width: 240px;
  outline: none;
  transition: border-color .2s;
}
.kl-search::placeholder { color: var(--text-muted); }
.kl-search:focus { border-color: var(--accent); }

.kl-filter {
  background: var(--bg-raised);
  border: 1px solid var(--border-default);
  color: var(--text-secondary);
  padding: 7px 12px;
  border-radius: var(--radius-md);
  font-family: var(--font-display);
  font-size: .8125rem;
  font-weight: 600;
  cursor: pointer;
  appearance: none;
  -webkit-appearance: none;
  background-image: url("data:image/svg+xml,%3Csvg width='10' height='6' viewBox='0 0 10 6' fill='none' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M1 1L5 5L9 1' stroke='rgba(224,221,213,0.38)' stroke-width='1.5' stroke-linecap='round'/%3E%3C/svg%3E");
  background-repeat: no-repeat;
  background-position: right 10px center;
  padding-right: 28px;
  transition: border-color .2s;
}
.kl-filter:hover { border-color: var(--border-strong); }

/* ── Buttons ── */
.kl-btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 7px 14px;
  border-radius: var(--radius-md);
  font-family: var(--font-display);
  font-size: .8125rem;
  font-weight: 700;
  cursor: pointer;
  border: 1px solid transparent;
  transition: all .15s;
}
.kl-btn-primary {
  background: var(--accent);
  color: #111;
  border-color: var(--accent);
}
.kl-btn-primary:hover { opacity: .85; }
.kl-btn-secondary {
  background: var(--bg-raised);
  color: var(--text-secondary);
  border-color: var(--border-default);
}
.kl-btn-secondary:hover { border-color: var(--border-strong); color: var(--text-primary); }
.kl-btn-ghost {
  background: transparent;
  color: var(--text-secondary);
  border-color: transparent;
}
.kl-btn-ghost:hover { background: var(--bg-hover); color: var(--text-primary); }
.kl-btn-sm { padding: 4px 10px; font-size: .75rem; }
.kl-btn-danger {
  background: rgba(239,83,80,0.12);
  color: var(--sev-action);
  border-color: rgba(239,83,80,0.25);
}
.kl-btn-danger:hover { background: rgba(239,83,80,0.20); }
.kl-btn-success {
  background: rgba(102,187,106,0.12);
  color: var(--status-active);
  border-color: rgba(102,187,106,0.25);
}
.kl-btn-success:hover { background: rgba(102,187,106,0.20); }

/* ── Data Table ── */
.kl-table-wrap {
  padding: 0 var(--content-pad);
  flex: 1;
  overflow-x: auto;
}
table.kl-table {
  width: 100%;
  border-collapse: collapse;
}
table.kl-table thead th {
  font-family: var(--font-display);
  font-size: .6875rem;
  font-weight: 700;
  letter-spacing: .08em;
  text-transform: uppercase;
  color: var(--text-muted);
  padding: 10px 12px;
  text-align: left;
  border-bottom: 1px solid var(--border-default);
  position: sticky;
  top: 0;
  background: var(--bg-base);
  white-space: nowrap;
}
table.kl-table tbody tr {
  border-bottom: 1px solid var(--border-subtle);
  transition: background .1s;
  cursor: pointer;
}
table.kl-table tbody tr:hover { background: var(--bg-hover); }
table.kl-table tbody td {
  padding: 10px 12px;
  color: var(--text-secondary);
  font-size: .875rem;
  vertical-align: middle;
  line-height: 1.45;
}
table.kl-table .col-check { width: 36px; text-align: center; }
table.kl-table .col-check input[type="checkbox"] {
  accent-color: var(--accent);
  width: 14px; height: 14px;
  cursor: pointer;
}
.kl-cell-title { font-weight: 500; color: var(--text-primary); }
.kl-cell-mono { font-family: var(--font-mono); font-size: .8125rem; }
.kl-cell-muted { color: var(--text-muted); font-size: .8125rem; }

/* ── Bulk Action Bar ── */
.kl-bulk-bar {
  display: none;
  padding: 10px var(--content-pad);
  background: var(--accent-dim);
  border-bottom: 1px solid rgba(255,238,88,0.15);
  align-items: center;
  gap: 10px;
  font-family: var(--font-display);
  font-size: .8125rem;
  font-weight: 600;
  color: var(--accent);
}
.kl-bulk-bar.visible { display: flex; }
.kl-bulk-count {
  font-family: var(--font-mono);
  font-size: .75rem;
  color: var(--text-secondary);
}

/* ── Tag Add Button ── */
.kl-tag-add {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 22px; height: 22px;
  border-radius: 3px;
  border: 1px dashed var(--border-default);
  color: var(--text-muted);
  font-size: .8125rem;
  cursor: pointer;
  transition: all .15s;
}
.kl-tag-add:hover { border-color: var(--accent); color: var(--accent); }
.kl-tags-row { display: flex; flex-wrap: wrap; gap: 5px; align-items: center; }
.kl-tag:hover { border-color: var(--border-strong); }
.kl-tag .tag-remove {
  margin-left: 4px;
  cursor: pointer;
  opacity: .5;
  font-size: .625rem;
}
.kl-tag .tag-remove:hover { opacity: 1; color: var(--sev-action); }

/* ── Detail Header ── */
.kl-detail-header {
  padding: var(--content-pad);
  border-bottom: 1px solid var(--border-subtle);
}
.kl-back {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-family: var(--font-display);
  font-size: .8125rem;
  font-weight: 600;
  color: var(--text-muted);
  margin-bottom: 16px;
  transition: color .15s;
  cursor: pointer;
}
.kl-back:hover { color: var(--text-primary); opacity: 1; }
.kl-detail-title-row {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-bottom: 6px;
}
.kl-detail-title {
  font-family: var(--font-display);
  font-size: 1.5rem;
  font-weight: 800;
  color: var(--text-primary);
  letter-spacing: -0.02em;
}
.kl-detail-meta {
  font-family: var(--font-mono);
  font-size: .75rem;
  color: var(--text-muted);
  margin-bottom: 12px;
}
.kl-detail-meta span { margin-right: 16px; }

/* ── Lock Banner ── */
.kl-lock-banner {
  display: flex;
  align-items: center;
  gap: 12px;
  background: rgba(255,202,40,0.08);
  border: 1px solid rgba(255,202,40,0.20);
  border-radius: var(--radius-md);
  padding: 12px 16px;
  margin-bottom: 20px;
  font-size: .875rem;
  color: var(--type-diagnostic);
}
.kl-lock-banner .lock-icon { font-size: 1.1rem; }

/* ── Tabs ── */
.kl-tabs {
  display: flex;
  border-bottom: 1px solid var(--border-default);
  padding: 0 var(--content-pad);
  gap: 0;
}
.kl-tab {
  font-family: var(--font-display);
  font-size: .8125rem;
  font-weight: 600;
  color: var(--text-muted);
  padding: 12px 16px;
  border-bottom: 2px solid transparent;
  cursor: pointer;
  transition: color .15s, border-color .15s;
  white-space: nowrap;
  background: none;
  border-top: none;
  border-left: none;
  border-right: none;
}
.kl-tab:hover { color: var(--text-secondary); }
.kl-tab.active { color: var(--text-primary); border-bottom-color: var(--accent); }

/* ── Content Preview ── */
.kl-content-preview {
  background: var(--bg-raised);
  border: 1px solid var(--border-default);
  border-radius: var(--radius-md);
  padding: 24px;
  font-family: var(--font-body);
  font-size: .9375rem;
  line-height: 1.7;
  color: var(--text-secondary);
  max-height: 500px;
  overflow-y: auto;
}
.kl-content-preview h1 { font-family: var(--font-display); font-size: 1.375rem; font-weight: 800; color: var(--text-primary); margin-bottom: 12px; }
.kl-content-preview h2 { font-family: var(--font-display); font-size: 1.125rem; font-weight: 700; color: var(--text-primary); margin-top: 24px; margin-bottom: 8px; }
.kl-content-preview h3 { font-family: var(--font-display); font-size: 1rem; font-weight: 600; color: var(--text-primary); margin-top: 16px; margin-bottom: 6px; }
.kl-content-preview p { margin-bottom: 12px; }
.kl-content-preview code { font-family: var(--font-mono); font-size: .85em; background: var(--bg-overlay); padding: 2px 6px; border-radius: 3px; color: var(--accent); }
.kl-content-preview ul, .kl-content-preview ol { padding-left: 24px; margin-bottom: 12px; }
.kl-content-preview li { margin-bottom: 4px; }

/* ── Metadata Key-Value ── */
.kl-meta-grid {
  display: grid;
  grid-template-columns: 180px 1fr;
  gap: 0;
  border: 1px solid var(--border-default);
  border-radius: var(--radius-md);
  overflow: hidden;
}
.kl-meta-key, .kl-meta-val {
  padding: 10px 14px;
  border-bottom: 1px solid var(--border-subtle);
  font-size: .875rem;
}
.kl-meta-key {
  background: var(--bg-raised);
  font-family: var(--font-mono);
  font-size: .8125rem;
  color: var(--text-muted);
  border-right: 1px solid var(--border-subtle);
}
.kl-meta-val {
  color: var(--text-secondary);
  font-family: var(--font-mono);
  font-size: .8125rem;
  word-break: break-word;
}

/* ── Revision List ── */
.kl-revision-item {
  display: flex;
  align-items: center;
  gap: 14px;
  padding: 12px 0;
  border-bottom: 1px solid var(--border-subtle);
}
.kl-revision-ver {
  font-family: var(--font-mono);
  font-size: .875rem;
  font-weight: 500;
  color: var(--accent);
  width: 48px;
  flex-shrink: 0;
}
.kl-revision-info { flex: 1; }
.kl-revision-summary {
  font-size: .875rem;
  color: var(--text-primary);
  margin-bottom: 2px;
}
.kl-revision-meta {
  font-family: var(--font-mono);
  font-size: .75rem;
  color: var(--text-muted);
}

/* ── Feed / Activity List ── */
.kl-feed {
  display: flex;
  flex-direction: column;
  gap: 1px;
  background: var(--border-subtle);
  border: 1px solid var(--border-default);
  border-radius: var(--radius-lg);
  overflow: hidden;
}
.kl-feed-item {
  background: var(--bg-surface);
  padding: 14px 18px;
  display: flex;
  gap: 12px;
  align-items: flex-start;
  transition: background .1s;
}
.kl-feed-item:hover { background: var(--bg-raised); }
.kl-feed-title {
  font-weight: 500;
  color: var(--text-primary);
  font-size: .875rem;
  margin-bottom: 3px;
}
.kl-feed-sub {
  font-family: var(--font-mono);
  font-size: .75rem;
  color: var(--text-muted);
}
.kl-feed-body { flex: 1; min-width: 0; }
.kl-feed-right {
  flex-shrink: 0;
  text-align: right;
}

/* Severity badges */
.badge-info       { background: rgba(158,158,158,0.12); color: var(--sev-info);      border-color: rgba(158,158,158,0.25); }
.badge-attention  { background: rgba(255,183,77,0.12); color: var(--sev-attention); border-color: rgba(255,183,77,0.25); }
.badge-action     { background: rgba(239,83,80,0.12);  color: var(--sev-action);    border-color: rgba(239,83,80,0.25); }

/* Observation status badges */
.badge-open       { background: rgba(66,165,245,0.12); color: var(--obs-open);      border-color: rgba(66,165,245,0.25); }
.badge-resolved   { background: rgba(102,187,106,0.12); color: var(--obs-resolved);  border-color: rgba(102,187,106,0.25); }
.badge-wontfix    { background: rgba(158,158,158,0.12); color: var(--obs-wontfix);   border-color: rgba(158,158,158,0.25); }
.badge-deferred   { background: rgba(255,183,77,0.12); color: var(--obs-deferred);  border-color: rgba(255,183,77,0.25); }

/* Agent badges */
.badge-agent-type { background: rgba(255,138,101,0.12); color: var(--type-agent); border-color: rgba(255,138,101,0.25); }

/* ── Publish Sidebar ── */
.kl-publish-sidebar {
  position: fixed;
  top: 0; right: 0; bottom: 0;
  width: 400px;
  background: var(--bg-surface);
  border-left: 1px solid var(--border-default);
  z-index: 60;
  display: flex;
  flex-direction: column;
  box-shadow: -8px 0 32px rgba(0,0,0,.3);
}
.kl-publish-header {
  padding: 18px 20px;
  border-bottom: 1px solid var(--border-subtle);
  display: flex;
  align-items: center;
  justify-content: space-between;
}
.kl-publish-header h3 {
  font-family: var(--font-display);
  font-size: 1rem;
  font-weight: 700;
  color: var(--text-primary);
}
.kl-publish-body {
  flex: 1;
  overflow-y: auto;
  padding: 20px;
  display: flex;
  flex-direction: column;
  gap: 18px;
}
.kl-publish-footer {
  padding: 16px 20px;
  border-top: 1px solid var(--border-subtle);
  display: flex;
  gap: 10px;
  justify-content: flex-end;
}
.kl-field-group { display: flex; flex-direction: column; gap: 6px; }
.kl-field-label {
  font-family: var(--font-display);
  font-size: .6875rem;
  font-weight: 700;
  letter-spacing: .08em;
  text-transform: uppercase;
  color: var(--text-muted);
}
.kl-field-input {
  background: var(--bg-raised);
  border: 1px solid var(--border-default);
  color: var(--text-primary);
  padding: 9px 12px;
  border-radius: var(--radius-md);
  font-size: .875rem;
  outline: none;
  transition: border-color .2s;
}
.kl-field-input:focus { border-color: var(--accent); }
.kl-field-textarea {
  background: var(--bg-raised);
  border: 1px solid var(--border-default);
  color: var(--text-primary);
  padding: 9px 12px;
  border-radius: var(--radius-md);
  font-size: .875rem;
  outline: none;
  resize: vertical;
  min-height: 80px;
  font-family: var(--font-body);
  line-height: 1.5;
  transition: border-color .2s;
}
.kl-field-textarea:focus { border-color: var(--accent); }

/* ── Theme toggle in sidebar ── */
.kl-theme-toggle {
  padding: 8px 16px;
  display: flex;
  align-items: center;
  gap: 8px;
}
.kl-theme-btn {
  flex: 1;
  padding: 5px 0;
  border-radius: var(--radius-sm);
  border: 1px solid var(--border-default);
  background: transparent;
  color: var(--text-muted);
  font-family: var(--font-mono);
  font-size: .6875rem;
  cursor: pointer;
  transition: all .15s;
  text-align: center;
}
.kl-theme-btn:hover { border-color: var(--border-strong); color: var(--text-primary); }
.kl-theme-btn.active {
  background: var(--accent-dim);
  border-color: var(--accent);
  color: var(--accent);
}

/* ── Responsive ── */
@media (max-width: 768px) {
  .kl-sidebar { display: none; }
  .kl-main { margin-left: 0; }
  .kl-publish-sidebar { width: 100%; }
}
</style>
