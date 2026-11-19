/**
 * Service worker — handles the "+ Shobley" button content.js injects
 * onto AliExpress listing cards. A content script can't open or script
 * other tabs itself (no chrome.tabs/chrome.scripting access), so it
 * messages this background worker instead.
 *
 * A plain fetch() of a product's page only returns an empty shell —
 * AliExpress renders everything client-side — so getting real data means
 * actually loading the page. This opens it as a background (non-active)
 * tab the user never sees focus, runs scrapeAliExpressProduct() in it
 * once it's done rendering, then closes it.
 */
importScripts('scraper.js');

chrome.runtime.onMessage.addListener((message, sender, sendResponse) => {
  if (message?.type !== 'shobley-scrape-product') {
    return false;
  }

  scrapeInHiddenTab(message.url)
    .then((data) => sendResponse({ ok: true, data }))
    .catch((err) => sendResponse({ ok: false, error: err.message }));

  return true; // keep the message channel open for the async response
});

async function scrapeInHiddenTab(url) {
  const tab = await chrome.tabs.create({ url, active: false });

  try {
    await waitForTabToRender(tab.id);

    const [{ result }] = await chrome.scripting.executeScript({
      target: { tabId: tab.id },
      func: scrapeAliExpressProduct,
    });

    return result;
  } finally {
    chrome.tabs.remove(tab.id).catch(() => {});
  }
}

/**
 * Resolves once the tab finishes its initial load AND a short grace
 * period has passed — AliExpress is a client-rendered SPA, so the
 * browser's "complete" status fires before the page's own JS has
 * actually populated price/variant data.
 */
function waitForTabToRender(tabId, timeoutMs = 20000, settleMs = 1800) {
  return new Promise((resolve, reject) => {
    const timer = setTimeout(() => {
      chrome.tabs.onUpdated.removeListener(listener);
      reject(new Error('Timed out waiting for the AliExpress product page to load.'));
    }, timeoutMs);

    function listener(updatedTabId, changeInfo) {
      if (updatedTabId === tabId && changeInfo.status === 'complete') {
        clearTimeout(timer);
        chrome.tabs.onUpdated.removeListener(listener);
        setTimeout(resolve, settleMs);
      }
    }

    chrome.tabs.onUpdated.addListener(listener);
  });
}
