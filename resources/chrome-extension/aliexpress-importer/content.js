/**
 * Injects a "+ Shobley" button onto every product card on any AliExpress
 * listing page (search results, category pages, the homepage grid, etc.)
 * — not just a single product's own page. Clicking it asks background.js
 * to open that product's real page in a hidden tab, scrape it there
 * (AliExpress renders everything client-side, so a plain fetch of the
 * page returns nothing useful), then imports it straight away as an
 * Inactive product — no review step, just a toast, the same way "add to
 * cart" works.
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
        handleAdd(button, anchor.href);
      });

      anchor.appendChild(button);
    });
  }

  async function handleAdd(button, productUrl) {
    const { storeUrl, token } = await chrome.storage.local.get(['storeUrl', 'token']);

    if (!storeUrl || !token) {
      showToast('Set the store URL and token in the extension popup\'s "Store settings" first.', 'error');
      return;
    }

    button.dataset.state = 'loading';
    button.textContent = 'Adding…';

    try {
      const payload = await scrapeProductPage(productUrl);

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
      button.textContent = '+ Shobley';
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

  // Listing pages lazy-load cards as the user scrolls, and AliExpress's
  // category/search pages are largely a SPA — re-scan whenever the DOM
  // changes instead of only once on load.
  let scheduled = false;
  const observer = new MutationObserver(() => {
    if (scheduled) {
      return;
    }
    scheduled = true;
    setTimeout(() => {
      scheduled = false;
      scan();
    }, 500);
  });
  observer.observe(document.body, { childList: true, subtree: true });
})();
