import * as bootstrap from 'bootstrap';

window.bootstrap = bootstrap;

/* ---------------- Theme (light / dark) ---------------- */
const THEME_KEY = 'gw-theme';

function preferredTheme() {
    try {
        const saved = localStorage.getItem(THEME_KEY);
        if (saved === 'light' || saved === 'dark') return saved;
    } catch (e) { /* storage unavailable */ }
    return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
}

function applyTheme(theme) {
    document.documentElement.setAttribute('data-bs-theme', theme);
    document.querySelectorAll('[data-theme-icon]').forEach((el) => {
        el.className = theme === 'dark' ? 'bi bi-sun' : 'bi bi-moon-stars';
    });
    document.dispatchEvent(new CustomEvent('theme:changed', { detail: theme }));
}

document.addEventListener('click', (e) => {
    const toggle = e.target.closest('[data-theme-toggle]');
    if (!toggle) return;
    const next = document.documentElement.getAttribute('data-bs-theme') === 'dark' ? 'light' : 'dark';
    try { localStorage.setItem(THEME_KEY, next); } catch (err) { /* ignore */ }
    applyTheme(next);
});

applyTheme(preferredTheme());

/* ---------------- Sidebar (mobile) ---------------- */
document.addEventListener('click', (e) => {
    if (e.target.closest('[data-sidebar-toggle]') || e.target.closest('.sidebar-backdrop')) {
        document.querySelector('.app-sidebar')?.classList.toggle('show');
        document.querySelector('.sidebar-backdrop')?.classList.toggle('show');
    }
});

/* ---------------- Forms: loading state + confirmations ---------------- */
document.addEventListener('submit', (e) => {
    const form = e.target;
    const message = form.getAttribute('data-confirm');
    if (message && !window.confirm(message)) {
        e.preventDefault();
        return;
    }
    const btn = form.querySelector('button[type="submit"][data-loading]');
    if (btn) {
        btn.classList.add('is-loading');
        btn.setAttribute('disabled', 'disabled');
    }
});

/* ---------------- Auto-submit controls (filters, mode switch) ---------------- */
document.addEventListener('change', (e) => {
    if (e.target.matches('[data-autosubmit]') && e.target.form) {
        e.target.form.requestSubmit();
    }
});

/* ---------------- Copy to clipboard ---------------- */
document.addEventListener('click', async (e) => {
    const btn = e.target.closest('[data-copy]');
    if (!btn) return;
    const target = document.querySelector(btn.getAttribute('data-copy'));
    const text = target ? (target.value ?? target.textContent).trim() : btn.getAttribute('data-copy-text');
    try {
        await navigator.clipboard.writeText(text);
        const original = btn.innerHTML;
        btn.innerHTML = '<i class="bi bi-check2"></i> Copied';
        setTimeout(() => { btn.innerHTML = original; }, 1500);
    } catch (err) { /* clipboard blocked */ }
});

/* ---------------- Tooltips ---------------- */
document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach((el) => new bootstrap.Tooltip(el));

/* ---------------- Payout form ---------------- */
const payoutConfig = document.getElementById('payout-config');
if (payoutConfig) {
    import('./payout-form.js').then(({ initPayoutForm }) => initPayoutForm(payoutConfig));
}

/* ---------------- Charts (lazy-loaded) ---------------- */
const chartEls = document.querySelectorAll('[data-chart]');
if (chartEls.length) {
    import('./charts.js').then(({ renderCharts }) => renderCharts(chartEls));
}
