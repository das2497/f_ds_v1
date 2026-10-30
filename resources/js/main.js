/* Shobley UI Prototype — shared interactions (no backend, mock data only) */

document.addEventListener("DOMContentLoaded", function () {
  // Admin sidebar toggle (mobile)
  var sidebarToggle = document.querySelector("[data-toggle='admin-sidebar']");
  var adminSidebar = document.querySelector(".admin-sidebar");
  if (sidebarToggle && adminSidebar) {
    sidebarToggle.addEventListener("click", function () {
      adminSidebar.classList.toggle("show");
    });
  }

  // Admin dark/light theme toggle (persisted in localStorage; see the
  // anti-FOUC inline script in layouts/admin.blade.php that applies it
  // before first paint)
  var themeToggle = document.getElementById("theme-toggle");
  if (themeToggle) {
    var themeIcon = themeToggle.querySelector("i");
    var syncThemeIcon = function () {
      var isDark = document.documentElement.getAttribute("data-theme") === "dark";
      themeIcon.className = isDark ? "bi bi-sun" : "bi bi-moon-stars";
    };
    syncThemeIcon();
    themeToggle.addEventListener("click", function () {
      var next = document.documentElement.getAttribute("data-theme") === "dark" ? "light" : "dark";
      try { localStorage.setItem("admin-theme", next); } catch (e) {}
      document.documentElement.setAttribute("data-theme", next);
      location.reload();
    });
  }

  // Homepage category carousel — prev/next arrows scroll the card track,
  // and hide themselves once there's nothing left to scroll toward.
  document.querySelectorAll("[data-carousel]").forEach(function (carousel) {
    var track = carousel.querySelector("[data-carousel-track]");
    var prev = carousel.querySelector("[data-carousel-prev]");
    var next = carousel.querySelector("[data-carousel-next]");
    if (!track || !prev || !next) return;

    var scrollByCard = function (direction) {
      var card = track.querySelector(".category-card");
      var step = card ? card.getBoundingClientRect().width + 16 : track.clientWidth * 0.8;
      track.scrollBy({ left: direction * step, behavior: "smooth" });
    };
    prev.addEventListener("click", function () { scrollByCard(-1); });
    next.addEventListener("click", function () { scrollByCard(1); });

    // Left/right arrow keys move one card at a time once the track has focus
    // (e.g. after tabbing to a card), matching standard carousel behavior.
    track.addEventListener("keydown", function (e) {
      if (e.key === "ArrowRight") { e.preventDefault(); scrollByCard(1); }
      if (e.key === "ArrowLeft") { e.preventDefault(); scrollByCard(-1); }
    });

    var syncArrows = function () {
      var maxScroll = track.scrollWidth - track.clientWidth;
      var atStart = track.scrollLeft <= 1;
      var atEnd = track.scrollLeft >= maxScroll - 1;
      carousel.classList.toggle("has-overflow", maxScroll > 1);
      carousel.classList.toggle("can-scroll-left", !atStart);
      carousel.classList.toggle("can-scroll-right", !atEnd && maxScroll > 1);
      prev.disabled = atStart;
      next.disabled = atEnd;
    };
    syncArrows();
    track.addEventListener("scroll", syncArrows, { passive: true });
    window.addEventListener("resize", syncArrows);
  });

  // Back-to-top button
  var backToTop = document.querySelector("[data-action='back-to-top']");
  if (backToTop) {
    backToTop.addEventListener("click", function () {
      window.scrollTo({ top: 0, behavior: "smooth" });
    });
  }

  // Mock "Add to Cart" — bumps the header cart badge, no real cart logic yet
  document.querySelectorAll("[data-action='mock-add-to-cart']").forEach(function (btn) {
    btn.addEventListener("click", function (e) {
      e.preventDefault();
      var badge = document.querySelector(".cart-badge");
      if (badge) {
        var count = parseInt(badge.textContent || "0", 10) + 1;
        badge.textContent = count;
      }
      var original = btn.innerHTML;
      btn.innerHTML = "Added ✓";
      btn.disabled = true;
      setTimeout(function () {
        btn.innerHTML = original;
        btn.disabled = false;
      }, 900);
    });
  });

  // Variant swatches (color/size) — toggles .active within the same group
  document.querySelectorAll("[data-swatch-group]").forEach(function (group) {
    group.querySelectorAll(".swatch-option").forEach(function (opt) {
      opt.addEventListener("click", function () {
        group.querySelectorAll(".swatch-option").forEach(function (o) {
          o.classList.remove("active");
          if (o.hasAttribute("aria-pressed")) o.setAttribute("aria-pressed", "false");
        });
        opt.classList.add("active");
        if (opt.hasAttribute("aria-pressed")) opt.setAttribute("aria-pressed", "true");
      });
    });
  });

  // Quantity stepper ([data-qty-input] with [data-action="qty-decrease"/"qty-increase"] buttons)
  document.querySelectorAll("[data-qty-wrap]").forEach(function (wrap) {
    var input = wrap.querySelector("[data-qty-input]");
    var dec = wrap.querySelector("[data-action='qty-decrease']");
    var inc = wrap.querySelector("[data-action='qty-increase']");
    if (dec) dec.addEventListener("click", function () {
      input.value = Math.max(1, parseInt(input.value || "1", 10) - 1);
    });
    if (inc) inc.addEventListener("click", function () {
      input.value = parseInt(input.value || "1", 10) + 1;
    });
  });

  // PDP image gallery — thumbnail click swaps main image. Thumbs are
  // <button> elements wrapping an <img> so they are keyboard reachable, so
  // the source comes from data-image rather than the element's own src.
  document.querySelectorAll("[data-gallery-thumb]").forEach(function (thumb) {
    thumb.addEventListener("click", function () {
      var main = document.querySelector("[data-gallery-main]");
      var source = thumb.dataset.image || (thumb.querySelector("img") || {}).src || thumb.src;
      if (main && main.tagName === "IMG" && source) main.src = source;
      document.querySelectorAll("[data-gallery-thumb]").forEach(function (t) {
        t.classList.remove("active");
        if (t.hasAttribute("aria-pressed")) t.setAttribute("aria-pressed", "false");
      });
      thumb.classList.add("active");
      if (thumb.hasAttribute("aria-pressed")) thumb.setAttribute("aria-pressed", "true");
    });
  });

  // Remove cart item row (visual only — no total recalculation in this prototype)
  document.querySelectorAll("[data-action='remove-cart-item']").forEach(function (btn) {
    btn.addEventListener("click", function (e) {
      e.preventDefault();
      var row = btn.closest("[data-cart-item]");
      if (row) {
        row.style.transition = "opacity 0.2s ease";
        row.style.opacity = "0";
        setTimeout(function () { row.remove(); }, 200);
      }
    });
  });

  // Research page — "Add to Selection" toggle (visual only, no real staging list in this prototype)
  document.querySelectorAll("[data-action='toggle-select-product']").forEach(function (btn) {
    btn.addEventListener("click", function () {
      var selected = btn.classList.toggle("btn-accent");
      btn.classList.toggle("btn-outline-secondary");
      btn.innerHTML = selected
        ? '<i class="bi bi-check-lg"></i> Added to Selection'
        : '<i class="bi bi-plus-lg"></i> Add to Selection';
    });
  });

  // Category → Subcategory dependent dropdown (admin product customize page)
  var SUBCATEGORY_MAP = {
    "Electronics": ["Headphones & Audio", "Smart Watches", "Phone Accessories", "Computer Accessories", "Cameras"],
    "Home & Living": ["Kitchen & Dining", "Furniture", "Bedding", "Decor", "Storage & Organization"],
    "Fashion": ["Men's Clothing", "Women's Clothing", "Shoes", "Bags & Accessories", "Jewelry"],
    "Beauty & Health": ["Skincare", "Haircare", "Personal Care", "Wellness"],
    "Sports & Outdoors": ["Fitness Equipment", "Outdoor Gear", "Activewear", "Cycling"],
    "Toys & Games": ["Action Figures", "Board Games", "Educational Toys", "Outdoor Play"],
    "Automotive": ["Car Accessories", "Car Electronics", "Car Care"]
  };
  document.querySelectorAll("[data-category-select]").forEach(function (catSelect) {
    var subSelect = document.querySelector(catSelect.getAttribute("data-category-select"));
    if (!subSelect) return;
    var preselect = subSelect.getAttribute("data-preselect");
    function populate() {
      var subs = SUBCATEGORY_MAP[catSelect.value] || [];
      subSelect.innerHTML = subs.map(function (s) {
        var selected = (s === preselect) ? " selected" : "";
        return '<option' + selected + '>' + s + '</option>';
      }).join("");
    }
    catSelect.addEventListener("change", populate);
    populate();
  });

  // Minimal rich-text editor (bold/italic/underline/lists/headings), backed
  // by a hidden textarea so the surrounding form keeps posting plain HTML
  // under the same field name — no change needed on the server side.
  document.querySelectorAll("[data-rich-text-editor]").forEach(function (wrap) {
    var surface = wrap.querySelector("[data-rte-surface]");
    var source = wrap.querySelector("[data-rte-source]");
    if (!surface || !source) return;

    surface.innerHTML = source.value;

    function sync() {
      source.value = surface.innerHTML;
      source.dispatchEvent(new Event("input", { bubbles: true }));
    }

    surface.addEventListener("input", sync);
    surface.addEventListener("blur", sync);

    wrap.querySelectorAll("[data-rte-command]").forEach(function (btn) {
      btn.addEventListener("click", function () {
        surface.focus();
        document.execCommand(btn.getAttribute("data-rte-command"), false, btn.getAttribute("data-rte-value") || undefined);
        sync();
      });
    });
  });

  // Simple countdown for deal timers ([data-countdown="HH:MM:SS"])
  document.querySelectorAll("[data-countdown]").forEach(function (el) {
    var parts = el.getAttribute("data-countdown").split(":").map(Number);
    var remaining = parts[0] * 3600 + parts[1] * 60 + parts[2];
    setInterval(function () {
      if (remaining <= 0) return;
      remaining -= 1;
      var h = String(Math.floor(remaining / 3600)).padStart(2, "0");
      var m = String(Math.floor((remaining % 3600) / 60)).padStart(2, "0");
      var s = String(remaining % 60).padStart(2, "0");
      el.textContent = h + ":" + m + ":" + s;
    }, 1000);
  });
});
