/**
 * Shared by popup.js (injected into the tab the user has open, via
 * chrome.scripting.executeScript) and background.js (injected into a
 * hidden tab it opens for a card-click import) — must stay a single,
 * fully self-contained function with no outside references, since
 * executeScript serializes it and runs it in the target page's context.
 *
 * AliExpress renders everything client-side (a plain fetch of the page's
 * HTML returns an empty shell), and ships no stable public API for this,
 * so this only works against an actually-loaded page and reads whatever
 * the page's own React/Vue render happens to expose. Class names here
 * are CSS-module-generated hashes (e.g. "sku-item--wrap--t9Qszzx") that
 * WILL change across AliExpress deploys — matched by prefix via
 * `[class*="..."]` to survive the hash suffix changing, but the prefix
 * itself can still rot and need re-checking against a live product page.
 */
async function scrapeAliExpressProduct() {
  const text = (el) => (el && el.textContent ? el.textContent.trim() : '');
  const sleep = (ms) => new Promise((resolve) => setTimeout(resolve, ms));

  // AliExpress shows a crossed-out "original" price next to almost every
  // "New shopper exclusive" / "Black Friday" / "Limited deal" promo price —
  // that struck-through figure is the product's real, standing price; the
  // large bold one beside it is a one-time promo that doesn't reflect what
  // this product actually costs to source. Prefer the original price, and
  // only fall back to "current" when there's no discount at all (nothing
  // struck through — so "current" already IS the real price).
  // Re-run after selecting each SKU option too, since AliExpress re-renders
  // this same block with that option's own price.
  const readPrice = () => {
    const priceText = text(
      document.querySelector('[class*="price-default--original"]')
        || document.querySelector('[class*="price--original"]')
        || document.querySelector('[class*="price-default--current"]')
        || document.querySelector('[class*="price--current"]')
        || document.querySelector('[class*="uniform-banner-box-price"]')
        || document.querySelector('[class*="product-price-value"]')
    ) || (document.body.innerText.match(/(?:US\s?\$|Rs\.|₹|£|€|\$)\s?[\d,]+\.?\d*/) || [''])[0];

    return parseFloat((priceText.match(/[\d,]+\.?\d*/) || ['0'])[0].replace(/,/g, '')) || 0;
  };

  const title = text(
    document.querySelector('h1[data-pl="product-title"]')
      || document.querySelector('.product-title-text')
      || document.querySelector('h1')
  ) || document.title.replace(/\s*[-|].*$/, '').trim();

  const price = readPrice();

  const description = text(
    document.querySelector('[class*="product-description"]')
      || document.querySelector('meta[name="description"]')
  ) || (document.querySelector('meta[name="description"]')?.content || '');

  const imageSet = new Set();
  document.querySelectorAll('[class*="images-view-item"] img, [class*="image-view"] img, [class*="slider--img"] img')
    .forEach((img) => {
      const src = img.getAttribute('src') || img.getAttribute('data-src') || '';
      // Real photo thumbnails use "..._WxHqQ.ext" (underscore before the
      // size); small decorative icons (e.g. the video-play badge on a
      // gallery thumbnail) use "...path/WxH.ext" instead — requiring the
      // underscore filters those out, and stripping everything from it
      // onward recovers the original, full-resolution photo.
      if (/^https?:\/\//.test(src) && /_\d+x\d+/.test(src)) {
        imageSet.add(src.replace(/_\d+x\d+.*$/, ''));
      }
    });
  if (imageSet.size === 0) {
    const og = document.querySelector('meta[property="og:image"]');
    if (og && og.content) {
      imageSet.add(og.content);
    }
  }
  const images = Array.from(imageSet).slice(0, 10);

  // Real AliExpress variant pricing/stock is combinatorial (Color x Size,
  // ...) and the full matrix is only available by simulating every click
  // across every property group, which this doesn't attempt. Instead this
  // builds one variant per option of the FIRST property group only (almost
  // always "Color", which is what the product photos key off), skipping
  // options already marked sold out. Each option IS clicked, though, so its
  // own price — which can differ from the base/default option, e.g. a
  // "Plus size" color variant that costs more — gets read fresh rather than
  // every variant sharing the page's initial price. Any pricing that still
  // varies by a second dimension (e.g. Size) gets corrected afterwards in
  // the admin panel or synced Sheet.
  const variants = [];
  const firstPropertyWrap = document.querySelector('[class*="sku-item--wrap"]');
  if (firstPropertyWrap) {
    const skusContainer = firstPropertyWrap.querySelector('[class*="sku-item--skus"]');
    const items = skusContainer ? Array.from(skusContainer.children) : [];
    for (const item of items) {
      if (/soldOut/.test(item.className)) {
        continue;
      }
      const img = item.querySelector('img');
      const label = (img && img.getAttribute('alt')) || text(item) || item.getAttribute('title') || 'Option';
      const rawImage = img ? (img.getAttribute('src') || img.getAttribute('data-src') || null) : null;

      let variantPrice = price;
      try {
        item.click();
        await sleep(400);
        const updated = readPrice();
        if (updated > 0) {
          variantPrice = updated;
        }
      } catch (e) {
        // Fall back to the base price if clicking/reading this option fails.
      }

      variants.push({
        option_label: label,
        color: label,
        size: null,
        vendor_sku: null,
        weight_lb: null,
        image: rawImage ? rawImage.replace(/_\d+x\d+.*$/, '') : null,
        cost_price: variantPrice,
      });
    }
  }
  if (variants.length === 0) {
    variants.push({
      option_label: 'Default',
      color: null,
      size: null,
      vendor_sku: null,
      weight_lb: null,
      image: null,
      cost_price: price,
    });
  }

  const vendorExternalId = (location.pathname.match(/\/item\/(\d+)\.html/) || [])[1]
    || (location.pathname.match(/(\d{6,})/) || [])[1]
    || String(Date.now());

  // location.href carries AliExpress's own tracking query string (algo_pvid,
  // pdp_npi, utparam-url, ...), which is routinely 500-1000+ characters and
  // irrelevant to actually viewing the product — the canonical /item/{id}.html
  // link works identically and stays short enough to store and display sanely.
  const sourceUrl = location.origin + '/item/' + vendorExternalId + '.html';

  return {
    vendor_external_id: vendorExternalId,
    title,
    description,
    preview_image_url: images[0] || null,
    images,
    variants,
    source_url: sourceUrl,
  };
}
