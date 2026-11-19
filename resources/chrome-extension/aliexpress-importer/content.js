/**
 * Injects a "+ Shobley" button onto every product card on any AliExpress
 * listing page (search results, category pages, the homepage grid, etc.),
 * AND a floating "+ Add to Shobley" button on a product's own page.
 *
 * A card's own page hasn't been loaded yet, so clicking its button asks
 * background.js to open that product's real page in a hidden tab and
 * scrape it there (AliExpress renders everything client-side, so a plain
 * fetch of the page returns nothing useful) — see scrapeProductPage()
 * below. The floating button on a product's own page is already looking
 * at the real, rendered page, so it scrapes straight from `document`
 * instead (scraper.js, loaded before this file). Either way it then
 * imports the product straight away as Inactive — no review step, just a
 * toast, the same way "add to cart" works.
 *
 * AliExpress's markup/class names are undocumented and change over time,
 * so card detection here is deliberately loose (any link to `/item/...`)
 * rather than tied to specific class names that will eventually rot.
 */

(function () {
  const PROCESSED_ATTR = 'data-shobley-processed';

  function injectStyles() {
    if (document.getElementById('shobley-importer-styles')) {
      return;
    }

    const style = document.createElement('style');
    style.id = 'shobley-importer-styles';
    style.textContent = `
      .shobley-add-btn {
        position: absolute;
        top: 6px;
        left: 6px;
        z-index: 2147483000;
        background: #1a7f37;
        color: #fff;
        border: none;
        border-radius: 999px;
        font-size: 11px;
        font-weight: 600;
        padding: 4px 10px;
        cursor: pointer;
        box-shadow: 0 1px 4px rgba(0,0,0,.3);
        font-family: system-ui, sans-serif;
        line-height: 1.4;
      }
      .shobley-add-btn:hover { background: #166534; }
      .shobley-add-btn[data-state="loading"] { background: #6b7280; cursor: wait; }
      .shobley-add-btn[data-state="done"] { background: #1a7f37; }
      .shobley-add-btn[data-state="error"] { background: #b42318; }
      #shobley-product-page-btn {
        position: fixed;
        bottom: 24px;
        left: 24px;
        z-index: 2147483000;
        background: #1a7f37;
        color: #fff;
        border: none;
        border-radius: 999px;
        font-size: 14px;
        font-weight: 600;
        padding: 12px 20px;
        cursor: pointer;
        box-shadow: 0 2px 10px rgba(0,0,0,.35);
        font-family: system-ui, sans-serif;
      }
      #shobley-product-page-btn:hover { background: #166534; }
      #shobley-product-page-btn[data-state="loading"] { background: #6b7280; cursor: wait; }
      #shobley-product-page-btn[data-state="error"] { background: #b42318; }
      #shobley-toast {
        position: fixed;
        bottom: 20px;
        right: 20px;
        background: #1f2937;
        color: #fff;
        padding: 10px 16px;
        border-radius: 8px;
        font: 13px/1.4 system-ui, sans-serif;
        z-index: 2147483647;
        box-shadow: 0 4px 12px rgba(0,0,0,.3);
        max-width: 320px;
        opacity: 0;
        transition: opacity .2s ease;
      }
      #shobley-toast.shobley-visible { opacity: 1; }
    `;
    document.head.appendChild(style);
  }

  function showToast(message, kind) {
    let toast = document.getElementById('shobley-toast');
    if (!toast) {
      toast = document.createElement('div');
      toast.id = 'shobley-toast';
      document.body.appendChild(toast);
    }

    toast.textContent = message;
    toast.style.background = kind === 'error' ? '#b42318' : '#1f2937';
    toast.classList.add('shobley-visible');

    clearTimeout(toast._hideTimer);
    toast._hideTimer = setTimeout(() => toast.classList.remove('shobley-visible'), 3000);
  }

  function findCardAnchors() {
    return Array.from(document.querySelectorAll('a[href*="/item/"]')).filter(
      (a) => !a.hasAttribute(PROCESSED_ATTR) && a.querySelector('img')
    );
  }

  function scan() {
    findCardAnchors().forEach((anchor) => {
      anchor.setAttribute(PROCESSED_ATTR, '1');

      if (getComputedStyle(anchor).position === 'static') {
        anchor.style.position = 'relative';
      }

      const button = document.createElement('button');
      button.type = 'button';
      button.className = 'shobley-add-btn';
      button.textContent = '+ Shobley';
      button.title = 'Add to Shobley (as Inactive)';

      button.addEventListener('click', (event) => {
        event.preventDefault();
        event.stopPropagation();
        handleAdd(button, () => scrapeProductPage(anchor.href), '+ Shobley');
      });

      anchor.appendChild(button);
    });
  }

  function isProductPage() {
    return /\/item\/\d+\.html/.test(location.pathname);
  }

  function injectProductPageButton() {
    if (document.getElementById('shobley-product-page-btn')) {
      return;
    }

    const button = document.createElement('button');
    button.type = 'button';
    button.id = 'shobley-product-page-btn';
    button.textContent = '+ Add to Shobley';
    button.title = 'Add this product to Shobley (as Inactive)';

    // Already on the real, rendered page — no need to ask background.js
    // to open a hidden tab, just scrape `document` directly.
    button.addEventListener('click', () => {
      handleAdd(button, () => Promise.resolve(scrapeAliExpressProduct()), '+ Add to Shobley');
    });

    document.body.appendChild(button);
  }

  async function handleAdd(button, scrape, idleLabel) {
    const { storeUrl, token } = await chrome.storage.local.get(['storeUrl', 'token']);

    if (!storeUrl || !token) {
      showToast('Set the store URL and token in the extension popup\'s "Store settings" first.', 'error');
      return;
    }

    button.dataset.state = 'loading';
    button.textContent = 'Adding…';

    try {
      const payload = await scrape();

      const response = await fetch(storeUrl.replace(/\/$/, '') + '/api/aliexpress/products', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Authorization: 'Bearer ' + token },
        body: JSON.stringify(payload),
      });

      const body = await response.json().catch(() => ({}));

      if (!response.ok) {
        throw new Error(body.error || body.message || 'HTTP ' + response.status);
      }

      button.dataset.state = 'done';
      button.textContent = body.status === 'exists' ? 'Already added' : 'Added ✓';
      showToast(
        body.status === 'exists'
          ? 'Already imported earlier (product #' + body.product_id + ').'
          : 'Added as Inactive (product #' + body.product_id + '). Review it in the admin panel or Sheet.'
      );
    } catch (err) {
      button.dataset.state = 'error';
      button.textContent = idleLabel;
      showToast('Could not add: ' + err.message, 'error');
    }
  }

  /**
   * A plain fetch() of the product page only returns an empty shell
   * (AliExpress renders everything client-side), so this asks the
   * background worker to open it as a real, hidden tab and scrape the
   * actually-rendered page instead — see background.js.
   */
  function scrapeProductPage(productUrl) {
    return new Promise((resolve, reject) => {
      chrome.runtime.sendMessage({ type: 'shobley-scrape-product', url: productUrl }, (response) => {
        if (chrome.runtime.lastError) {
          reject(new Error(chrome.runtime.lastError.message));

          return;
        }
        if (!response || !response.ok) {
          reject(new Error(response?.error || 'Could not read that product page.'));

          return;
        }
        resolve(response.data);
      });
    });
  }

  injectStyles();
  scan();
  if (isProductPage()) {
    injectProductPageButton();
  }

  // Listing pages lazy-load cards as the user scrolls, and AliExpress's
  // category/search pages are largely a SPA — re-scan whenever the DOM
  // changes instead of only once on load. The product-page button only
  // ever needs adding once (injectProductPageButton() is itself a no-op
  // if it's already there), but the SPA can still swap the whole page
  // (e.g. following a "related product" link) without a real navigation,
  // so re-check that too on every DOM change.
  let scheduled = false;
  const observer = new MutationObserver(() => {
    if (scheduled) {
      return;
    }
    scheduled = true;
    setTimeout(() => {
      scheduled = false;
      scan();
      if (isProductPage()) {
        injectProductPageButton();
      }
    }, 500);
  });
  observer.observe(document.body, { childList: true, subtree: true });
})();
