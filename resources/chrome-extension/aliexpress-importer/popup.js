// scrapeAliExpressProduct() is defined in scraper.js (loaded before this
// file in popup.html) — shared with background.js so the card-click
// import path and this popup's own "open on a product page" path can't
// drift out of sync with each other.

const els = {
  notice: document.getElementById('notice'),
  form: document.getElementById('form'),
  title: document.getElementById('title'),
  price: document.getElementById('price'),
  description: document.getElementById('description'),
  thumbs: document.getElementById('thumbs'),
  variantsInfo: document.getElementById('variantsInfo'),
  importBtn: document.getElementById('importBtn'),
  status: document.getElementById('status'),
  storeUrl: document.getElementById('storeUrl'),
  token: document.getElementById('token'),
  saveSettingsBtn: document.getElementById('saveSettingsBtn'),
};

let scraped = null;

function setStatus(message, kind) {
  els.status.textContent = message;
  els.status.className = kind || '';
}

async function loadSettings() {
  const { storeUrl, token } = await chrome.storage.local.get(['storeUrl', 'token']);
  els.storeUrl.value = storeUrl || '';
  els.token.value = token || '';
}

els.saveSettingsBtn.addEventListener('click', async () => {
  await chrome.storage.local.set({ storeUrl: els.storeUrl.value.trim(), token: els.token.value.trim() });
  setStatus('Settings saved.', 'ok');
});

els.importBtn.addEventListener('click', async () => {
  const { storeUrl, token } = await chrome.storage.local.get(['storeUrl', 'token']);

  if (!storeUrl || !token) {
    setStatus('Set the store URL and token under "Store settings" first.', 'error');
    return;
  }

  if (!scraped) {
    setStatus('Nothing scraped yet — reopen this popup on a product page.', 'error');
    return;
  }

  els.importBtn.disabled = true;
  setStatus('Sending…');

  const payload = {
    ...scraped,
    title: els.title.value.trim(),
    description: els.description.value.trim(),
    variants: scraped.variants.map((variant) => ({ ...variant, cost_price: parseFloat(els.price.value) || variant.cost_price })),
  };

  try {
    const response = await fetch(storeUrl.replace(/\/$/, '') + '/api/aliexpress/products', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        Authorization: 'Bearer ' + token,
      },
      body: JSON.stringify(payload),
    });

    const body = await response.json().catch(() => ({}));

    if (!response.ok) {
      setStatus('Error: ' + (body.error || body.message || ('HTTP ' + response.status)), 'error');
      els.importBtn.disabled = false;
      return;
    }

    setStatus(
      body.status === 'exists'
        ? 'Already imported earlier (product #' + body.product_id + ').'
        : 'Imported as Inactive (product #' + body.product_id + ').',
      'ok'
    );
  } catch (err) {
    setStatus('Network error: ' + err.message, 'error');
    els.importBtn.disabled = false;
  }
});

(async function init() {
  await loadSettings();

  const [tab] = await chrome.tabs.query({ active: true, currentWindow: true });

  if (!tab || !/aliexpress\.(com|us)/.test(tab.url || '')) {
    els.notice.textContent = 'Open an AliExpress product page first.';
    return;
  }

  try {
    const [{ result }] = await chrome.scripting.executeScript({
      target: { tabId: tab.id },
      func: scrapeAliExpressProduct,
    });

    scraped = result;
    els.notice.textContent = 'Review the details below, then import.';
    els.title.value = scraped.title || '';
    els.price.value = scraped.variants[0]?.cost_price || '';
    els.description.value = scraped.description || '';
    (scraped.images || []).forEach((src) => {
      const img = document.createElement('img');
      img.src = src;
      els.thumbs.appendChild(img);
    });
    els.variantsInfo.textContent = scraped.variants.length + ' variant(s) detected.';
    els.form.style.display = 'block';
  } catch (err) {
    els.notice.textContent = 'Could not read this page: ' + err.message;
  }
})();
