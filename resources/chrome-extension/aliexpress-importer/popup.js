/**
 * Runs inside the AliExpress product page (injected via
 * chrome.scripting.executeScript) and must be fully self-contained — no
 * references to anything outside this function body.
 *
 * AliExpress ships no stable public API for this, so this reads whatever
 * the page's own rendered DOM happens to expose. Their markup/class names
 * change over time and differ between item-page layouts, so this tries
 * several fallbacks per field and is expected to need occasional updates —
 * that's also why the popup shows every scraped field as an editable
 * form instead of sending it straight through.
 */
function scrapeAliExpressProduct() {
  const text = (el) => (el && el.textContent ? el.textContent.trim() : '');

  const title = text(
    document.querySelector('h1[data-pl="product-title"]')
      || document.querySelector('.product-title-text')
      || document.querySelector('h1')
  ) || document.title.replace(/\s*[-|].*$/, '').trim();

  const priceText = text(
    document.querySelector('[class*="price--current"]')
      || document.querySelector('[class*="uniform-banner-box-price"]')
      || document.querySelector('[class*="product-price-value"]')
      || document.querySelector('[class*="Price_"]')
  ) || (document.body.innerText.match(/US\s?\$\s?[\d,.]+/) || [''])[0];
  const price = parseFloat((priceText.match(/[\d,]+\.?\d*/) || ['0'])[0].replace(/,/g, '')) || 0;

  const description = text(
    document.querySelector('[class*="product-description"]')
      || document.querySelector('meta[name="description"]')
  ) || (document.querySelector('meta[name="description"]')?.content || '');

  const imageSet = new Set();
  document.querySelectorAll('[class*="images-view-item"] img, [class*="image-view"] img, [class*="slider--img"] img')
    .forEach((img) => {
      const src = img.getAttribute('src') || img.getAttribute('data-src') || '';
      if (src.startsWith('http')) {
        imageSet.add(src.replace(/_\d+x\d+(\.\w+)?$/, '$1'));
      }
    });
  if (imageSet.size === 0) {
    const og = document.querySelector('meta[property="og:image"]');
    if (og && og.content) {
      imageSet.add(og.content);
    }
  }
  const images = Array.from(imageSet).slice(0, 10);

  // AliExpress shows each SKU property (Color, Size, ...) as its own
  // swatch list. True variant pricing/stock is combinatorial and only
  // available by simulating every click, which this doesn't attempt —
  // instead it builds one variant per option of the FIRST property list
  // only (almost always "Color", which is what the product photos key
  // off), all sharing the scraped base price. The admin panel / Sheet is
  // where real per-variant cost/price gets corrected afterwards.
  const variants = [];
  const firstPropertyList = document.querySelector('[class*="sku-property-list"]');
  if (firstPropertyList) {
    firstPropertyList.querySelectorAll('[class*="sku-property-item"]').forEach((item) => {
      const label = item.getAttribute('title') || text(item) || 'Option';
      const img = item.querySelector('img');
      variants.push({
        option_label: label,
        color: label,
        image: img ? (img.getAttribute('src') || img.getAttribute('data-src') || null) : null,
        cost_price: price,
      });
    });
  }
  if (variants.length === 0) {
    variants.push({ option_label: 'Default', cost_price: price });
  }

  const vendorExternalId = (location.pathname.match(/\/item\/(\d+)\.html/) || [])[1]
    || (location.pathname.match(/(\d{6,})/) || [])[1]
    || String(Date.now());

  return {
    vendor_external_id: vendorExternalId,
    title,
    description,
    preview_image_url: images[0] || null,
    images,
    variants,
    source_url: location.href,
  };
}

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
