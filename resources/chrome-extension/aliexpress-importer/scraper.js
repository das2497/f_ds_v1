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
  // ...), so every property group (not just the first) has to be walked and
  // every combination across ALL groups actually clicked through — a 4-color
  // x 6-size product is 24 variants, not 4. Each group's sold-out items are
  // skipped, and because selecting one group's option can grey out/disable
  // options in another group (e.g. a color that's out of stock in size L),
  // every group's item list is re-read from the live DOM right before it's
  // iterated, rather than captured once up front.
  const isUnselectable = (item) => /soldOut|disabled/i.test(item.className)
    || item.getAttribute('aria-disabled') === 'true';

  // Title text for a property group ("Color", "Size(US)", ...) is read by
  // removing the actual options from a clone of the wrap and reading what's
  // left, rather than matching a class name for the title element itself —
  // that text node's own class is just as likely to rot across AliExpress
  // deploys as the ones already called out above, and this needs no class
  // match to survive that.
  const propertyTitle = (wrap, skusContainer) => {
    const clone = wrap.cloneNode(true);
    if (skusContainer) {
      const skusClone = clone.querySelector('[class*="sku-item--skus"]');
      if (skusClone) {
        skusClone.remove();
      }
    }
    return clone.textContent.trim();
  };

  const MAX_VARIANTS = 100;
  const variants = [];

  const assignSelection = (variant, extras, propertyName, index, label) => {
    const lower = propertyName.toLowerCase();
    if (variant.color === null && (/colou?r/.test(lower) || (!lower && index === 0))) {
      variant.color = label;
    } else if (variant.size === null && (/size|length|width/.test(lower) || (!lower && index === 1))) {
      variant.size = label;
    } else {
      extras.push(propertyName ? `${propertyName}: ${label}` : label);
    }
  };

  const propertyWraps = Array.from(document.querySelectorAll('[class*="sku-item--wrap"]'));

  if (propertyWraps.length > 0) {
    const recurse = async (wrapIndex, selections) => {
      if (variants.length >= MAX_VARIANTS) {
        return;
      }
      const currentWraps = Array.from(document.querySelectorAll('[class*="sku-item--wrap"]'));
      const wrap = currentWraps[wrapIndex];
      if (!wrap) {
        return;
      }
      const skusContainer = wrap.querySelector('[class*="sku-item--skus"]');
      const items = skusContainer ? Array.from(skusContainer.children) : [];
      const propertyName = propertyTitle(wrap, skusContainer);

      for (const item of items) {
        if (variants.length >= MAX_VARIANTS) {
          return;
        }
        if (isUnselectable(item)) {
          continue;
        }
        const img = item.querySelector('img');
        const label = (img && img.getAttribute('alt')) || text(item) || item.getAttribute('title') || 'Option';
        const rawImage = img ? (img.getAttribute('src') || img.getAttribute('data-src') || null) : null;

        try {
          item.click();
          await sleep(400);
        } catch (e) {
          continue;
        }

        const nextSelections = selections.concat([{ wrapIndex, propertyName, label, image: rawImage }]);

        if (wrapIndex + 1 < propertyWraps.length) {
          await recurse(wrapIndex + 1, nextSelections);
        } else {
          const variant = { color: null, size: null, vendor_sku: null, weight_lb: null, image: null, cost_price: price };
          const extras = [];
          for (const [i, selection] of nextSelections.entries()) {
            assignSelection(variant, extras, selection.propertyName, i, selection.label);
            if (!variant.image && selection.image) {
              variant.image = selection.image;
            }
          }

          const updated = readPrice();
          if (updated > 0) {
            variant.cost_price = updated;
          }
          variant.image = variant.image ? variant.image.replace(/_\d+x\d+.*$/, '') : null;
          variant.option_label = extras.length > 0
            ? nextSelections.map((s) => s.label).join(' / ') + ' (' + extras.join(', ') + ')'
            : nextSelections.map((s) => s.label).join(' / ');

          variants.push(variant);
        }
      }
    };

    await recurse(0, []);
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
