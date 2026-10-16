/**
 * Payout request form: destination types, currencies and networks depend on the
 * selected provider (e.g. Binance Pay -> Binance ID / email, Cryptomus -> wallet address).
 */
export function initPayoutForm(configEl) {
    const config = JSON.parse(configEl.textContent);
    const providerSel = document.querySelector('[data-payout-provider]');
    const typeSel = document.querySelector('[data-payout-destination-type]');
    const currencySel = document.querySelector('[data-payout-currency]');
    const networkSel = document.querySelector('[data-payout-network]');
    if (!providerSel) return;

    const option = (value, label, selected) => {
        const o = document.createElement('option');
        o.value = value;
        o.textContent = label;
        if (selected) o.selected = true;
        return o;
    };

    function provider() {
        return config.providers.find((p) => p.code === providerSel.value) || config.providers[0];
    }

    function fillNetworks() {
        const p = provider();
        const rows = p.currencies.filter((c) => c.currency === currencySel.value);
        const networks = [...new Set(rows.map((r) => r.network).filter((n) => n))];
        networkSel.replaceChildren();
        if (networks.length === 0) {
            networkSel.append(option('', 'Default', true));
            networkSel.disabled = true;
        } else {
            networkSel.disabled = false;
            networks.forEach((n) => networkSel.append(option(n, n.toUpperCase(), n === config.old.network)));
        }
    }

    function fillProvider() {
        const p = provider();
        typeSel.replaceChildren(...p.destinations.map((d) => option(d.value, d.label, d.value === config.old.destination_type)));
        const currencies = [...new Set(p.currencies.map((c) => c.currency))];
        currencySel.replaceChildren(...currencies.map((c) => option(c, c, c === config.old.currency)));
        if (currencies.length === 0) currencySel.append(option('', 'No currencies enabled', true));
        fillNetworks();
    }

    providerSel.addEventListener('change', fillProvider);
    currencySel.addEventListener('change', fillNetworks);
    fillProvider();
}
