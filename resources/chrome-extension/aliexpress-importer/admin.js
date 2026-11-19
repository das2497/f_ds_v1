/**
 * Runs only on the store's own "Add Product Manually" admin page (see
 * manifest.json's second content_scripts entry). Powers the "Fetch from
 * AliExpress" button there: scrapes the pasted product link the same way
 * the AliExpress-side buttons do (via background.js, since that's the
 * only thing that can actually load and render an AliExpress page), has
 * the app's own backend download/re-host the images, and hands the
 * result to the page's own script — which does the actual form-filling,
 * since content scripts run in an isolated JS world and can't call
 * functions defined in the page's own <script> tags directly.
 */

(function () {
  const button = document.getElementById('aliexpressFetchBtn');
  const input = document.getElementById('aliexpressLink');

  if (!button || !input) {
    return; // not on a page with this button
  }

  function setStatus(message, kind) {
    const status = document.getElementById('aliexpressFetchStatus');
    if (!status) {
      return;
    }
    status.textContent = message;
    status.style.color = kind === 'error' ? '#b42318' : '#6b7280';
  }

  button.addEventListener('click', async () => {
    const url = input.value.trim();

    if (!/^https?:\/\/[^\s]*aliexpress\.(com|us)\/item\/\d+/.test(url)) {
      setStatus('Paste a valid AliExpress product link (…/item/12345.html) first.', 'error');
      return;
    }

    button.disabled = true;
    setStatus('Reading the AliExpress page…');

    try {
      const scraped = await scrapeProductPage(url);

      setStatus('Downloading images…');

      const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
      const response = await fetch(window.location.origin + '/admin/products/aliexpress-prefill', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          Accept: 'application/json',
          'X-CSRF-TOKEN': csrfToken,
        },
        credentials: 'same-origin',
        body: JSON.stringify(scraped),
      });

      const body = await response.json().catch(() => ({}));

      if (!response.ok) {
        throw new Error(body.message || body.error || 'HTTP ' + response.status);
      }

      window.dispatchEvent(new CustomEvent('shobley-aliexpress-prefill', { detail: body }));
      setStatus('Filled in title, description, ' + body.images.length + ' image(s) and ' + body.variants.length + ' variant(s) — review before publishing.');
    } catch (err) {
      setStatus('Could not fetch: ' + err.message, 'error');
    } finally {
      button.disabled = false;
    }
  });

  /**
   * Same mechanism the listing-card "+ Shobley" button uses — AliExpress
   * renders everything client-side, so this asks background.js to open
   * the link as a hidden tab and scrape the actually-rendered page.
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
})();
