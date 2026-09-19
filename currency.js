// Shared currency helper: normalize codes, convert between currencies (base USD), and optional session sync.
(function () {
  const RATES = {
    USD: 1,
    BDT: 122.30,
    EUR: 0.92,
    GBP: 0.79,
    INR: 83,
    JPY: 149,
    CNY: 7.24,
    AUD: 1.52,
    CAD: 1.36,
  };

  const SYMBOLS = {
    USD: '$',
    EUR: '€',
    BDT: '৳',
    INR: '₹',
    GBP: '£',
    JPY: '¥',
    CNY: '¥',
    AUD: '$',
    CAD: '$',
  };

  const DEFAULT = 'BDT';
  let lastSynced = null;

  function normalize(code) {
    const upper = String(code || '').toUpperCase();
    return RATES[upper] ? upper : DEFAULT;
  }

  function getCurrency() {
    return normalize(localStorage.getItem('currency') || DEFAULT);
  }

  function setCurrency(code, options) {
    const norm = normalize(code);
    const storageKey = options && options.key ? String(options.key) : 'currency';
    localStorage.setItem(storageKey, norm);
    if (!options || options.sync !== false) {
      syncToSession(norm);
    }
    return norm;
  }

  function convert(amount, fromCode, toCode) {
    const from = normalize(fromCode);
    const to = normalize(toCode || getCurrency());
    const fromRate = RATES[from] || 1;
    const toRate = RATES[to] || 1;
    const baseUsd = Number(amount || 0) / fromRate;
    return baseUsd * toRate;
  }

  function convertFromUSD(amountUsd, toCode) {
    return convert(amountUsd, 'USD', toCode);
  }

  function formatAmount(amount, fromCode, toCode, opts) {
    const target = normalize(toCode || getCurrency());
    const symbol = SYMBOLS[target] || '';
    const value = convert(amount, fromCode, target);
    const absVal = Math.abs(value);
    const options = Object.assign(
      { minimumFractionDigits: 2, maximumFractionDigits: 2 },
      (opts && typeof opts === 'object') ? opts : {}
    );
    return symbol + absVal.toLocaleString(undefined, options);
  }

  function format(amountUsd, toCode, opts) {
    // Backward compatibility: treat input as USD amount
    return formatAmount(amountUsd, 'USD', toCode, opts);
  }

  function syncToSession(code) {
    const norm = normalize(code);
    if (lastSynced === norm) return;
    lastSynced = norm;

    try {
      const form = new URLSearchParams();
      form.set('currency', norm);

      fetch('set_currency.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: form.toString(),
        credentials: 'same-origin'
      }).catch(() => {});
    } catch (_) {
      // ignore
    }
  }

  syncToSession(getCurrency());

  window.Currency = {
    RATES,
    SYMBOLS,
    DEFAULT,
    normalize,
    getCurrency,
    setCurrency,
    convert,
    convertFromUSD,
    format,
    formatAmount,
  };
})();
