@php
    $vendorCount = $cart->items->map(fn ($item) => $item->variant->product->source_vendor)->unique()->count();
    $hasUnavailableItems = $cart->items->contains(
        fn ($item) => $item->variant->stock_status === 'out_of_stock' || ! $item->variant->is_active
    );
@endphp
<x-store-layout title="Shopping Cart"
    description="Review the items in your cart and continue to secure checkout."
    robots="noindex, follow">

<main class="container py-4">

  <nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb small mb-0">
      <li class="breadcrumb-item"><a href="{{ route('home') }}" class="text-muted-slate text-decoration-none">Home</a></li>
      <li class="breadcrumb-item active">Shopping Cart</li>
    </ol>
  </nav>

  @if (session('status'))
    <div class="alert alert-success">{{ session('status') }}</div>
  @endif
  @if (session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
  @endif

  <h1 class="h3 mb-4">Shopping Cart <span id="cartItemCountLabel" class="text-muted-slate fs-5 fw-normal">({{ $cart->items->sum('quantity') }} items)</span></h1>

  <div id="cartHasItems" class="{{ $cart->items->isEmpty() ? 'd-none' : '' }}">
    <div class="row g-4">
      <div class="col-lg-8">

        <div id="shippingSplitBanner" class="alert-shipping-split mb-4" style="{{ $vendorCount > 1 ? '' : 'display:none;' }}">
          <i class="bi bi-box-seam me-1"></i> Your order will be shipped in <strong><span id="shippingSplitCount">{{ $vendorCount }}</span> separate packages</strong> — you'll get tracking for each once they ship.
        </div>

        <div class="card-surface p-4">
          @foreach ($cart->items as $item)
            @php
                $isUnavailable = $item->variant->stock_status === 'out_of_stock' || ! $item->variant->is_active;
                $isLowStock = ! $isUnavailable && $item->variant->stock_status === 'low_stock';
            @endphp
            <div class="cart-item{{ $isUnavailable ? ' cart-item--unavailable' : '' }}"
                 data-cart-item-id="{{ $item->id }}"
                 data-vendor="{{ $item->variant->product->source_vendor }}"
                 data-update-url="{{ route('cart.update', $item) }}"
                 data-destroy-url="{{ route('cart.destroy', $item) }}">
              <div class="cart-item__image">
                @if (! empty($item->variant->product->images[0]))
                  <img src="{{ $item->variant->product->images[0] }}" style="max-width:100%;max-height:100%;object-fit:contain">
                @else
                  <div class="ph-icon-wrap" role="img" aria-label="{{ $item->variant->product->title }}"><i class="bi bi-box-seam"></i></div>
                @endif
              </div>
              <div class="flex-grow-1">
                <div class="d-flex justify-content-between">
                  <div>
                    <a href="{{ route('products.show', $item->variant->product) }}" class="text-decoration-none text-dark"><div class="fw-semibold">{{ $item->variant->product->title }}</div></a>
                    @if ($item->variant->option_label)
                      <div class="small text-muted-slate">{{ $item->variant->option_label }}</div>
                    @endif
                    @if ($isUnavailable)
                      <div class="small text-danger fw-semibold mt-1"><i class="bi bi-exclamation-triangle-fill"></i> No longer available — please remove to check out</div>
                    @elseif ($isLowStock)
                      <div class="small text-danger mt-1"><i class="bi bi-exclamation-circle-fill"></i> Only a few left</div>
                    @endif
                  </div>
                  <div class="text-end flex-shrink-0 ms-2">
                    <div class="fw-bold text-accent cart-item__line-total">${{ number_format($item->lineTotal(), 2) }}</div>
                    <div class="small text-muted-slate cart-item__unit-price" style="{{ $item->quantity > 1 ? '' : 'display:none;' }}">${{ number_format($item->unitPrice(), 2) }} each</div>
                  </div>
                </div>
                <div class="d-flex justify-content-between align-items-center mt-3">
                  @if ($isUnavailable)
                    <span></span>
                  @else
                    <form action="{{ route('cart.update', $item) }}" method="POST" class="d-flex align-items-center gap-2 cart-item__update-form">
                      @csrf @method('PUT')
                      <div class="qty-stepper">
                        <button type="button" class="qty-stepper__btn" data-step="-1" aria-label="Decrease quantity">&minus;</button>
                        <input type="number" name="quantity" value="{{ $item->quantity }}" min="1" max="20" aria-label="Quantity">
                        <button type="button" class="qty-stepper__btn" data-step="1" aria-label="Increase quantity">+</button>
                      </div>
                      <noscript><button type="submit" class="btn btn-sm btn-outline-secondary">Update</button></noscript>
                    </form>
                  @endif
                  <form action="{{ route('cart.destroy', $item) }}" method="POST" class="cart-item__remove-form" onsubmit="return confirm('Remove this item?');">
                    @csrf @method('DELETE')
                    <button type="submit" class="small text-danger text-decoration-none border-0 bg-transparent"><i class="bi bi-trash3 me-1"></i>Remove</button>
                  </form>
                </div>
              </div>
            </div>
          @endforeach
        </div>

        <a href="{{ route('products.index') }}" class="btn btn-outline-secondary btn-pill mt-4"><i class="bi bi-arrow-left"></i> Continue Shopping</a>
      </div>

      <div class="col-lg-4">
        <div class="cart-summary">
          <h5 class="mb-3">Order Summary</h5>

          <div class="row-line"><span class="text-muted-slate">Subtotal</span><span id="cartSubtotal">${{ number_format($subtotal, 2) }}</span></div>
          <div class="small text-muted-slate mb-3">Shipping &amp; tax calculated at checkout.</div>

          <div id="checkoutWarning" class="alert-cart-warning mb-3" style="{{ $hasUnavailableItems ? '' : 'display:none;' }}">
            <i class="bi bi-exclamation-triangle-fill me-1"></i> Remove unavailable items before checking out.
          </div>
          <button type="button" id="checkoutDisabledBtn" class="btn btn-accent w-100" style="{{ $hasUnavailableItems ? '' : 'display:none;' }}" disabled>Proceed to Checkout <i class="bi bi-arrow-right"></i></button>
          <a href="{{ route('checkout.show') }}" id="checkoutLink" class="btn btn-accent w-100" style="{{ $hasUnavailableItems ? 'display:none;' : '' }}">Proceed to Checkout <i class="bi bi-arrow-right"></i></a>

          <div class="text-center small text-muted-slate mt-3">
            <i class="bi bi-shield-check me-1"></i> Secure checkout via Stripe
          </div>
        </div>
      </div>
    </div>
  </div>

  <div id="cartEmptyState" class="card-surface p-5 text-center {{ $cart->items->isEmpty() ? '' : 'd-none' }}">
    <i class="bi bi-cart-x fs-1 text-muted-slate mb-2 d-block"></i>
    <p class="text-muted-slate">Your cart is empty.</p>
    <a href="{{ route('products.index') }}" class="btn btn-accent">Start Shopping</a>
  </div>
</main>

<script>
  (function () {
    var csrfToken = document.querySelector('meta[name="csrf-token"]').content;

    function updateCartBadge(count) {
      var badge = document.querySelector('a[title="Cart"] .cart-badge');
      if (badge) badge.textContent = count;
    }

    function updateItemCountLabel(count) {
      var el = document.getElementById('cartItemCountLabel');
      if (el) el.textContent = '(' + count + ' item' + (count === 1 ? '' : 's') + ')';
    }

    function toggleCheckoutState(hasUnavailable) {
      var warning = document.getElementById('checkoutWarning');
      var disabledBtn = document.getElementById('checkoutDisabledBtn');
      var link = document.getElementById('checkoutLink');
      if (! warning || ! disabledBtn || ! link) return;
      warning.style.display = hasUnavailable ? '' : 'none';
      disabledBtn.style.display = hasUnavailable ? '' : 'none';
      link.style.display = hasUnavailable ? 'none' : '';
    }

    function recomputeShippingSplit() {
      var banner = document.getElementById('shippingSplitBanner');
      if (! banner) return;
      var vendors = {};
      document.querySelectorAll('.cart-item[data-vendor]').forEach(function (el) {
        vendors[el.dataset.vendor] = true;
      });
      var count = Object.keys(vendors).length;
      var countEl = document.getElementById('shippingSplitCount');
      if (countEl) countEl.textContent = count;
      banner.style.display = count > 1 ? '' : 'none';
    }

    function showEmptyStateIfNeeded(cartCount) {
      if (cartCount > 0) return;
      var hasItems = document.getElementById('cartHasItems');
      var empty = document.getElementById('cartEmptyState');
      if (hasItems) hasItems.classList.add('d-none');
      if (empty) empty.classList.remove('d-none');
    }

    function applySummary(data) {
      var subtotalEl = document.getElementById('cartSubtotal');
      if (subtotalEl) subtotalEl.textContent = '$' + data.subtotal;
      updateCartBadge(data.cart_count);
      updateItemCountLabel(data.cart_count);
      toggleCheckoutState(data.has_unavailable_items);
      showEmptyStateIfNeeded(data.cart_count);
    }

    function showError(message) {
      window.alert(message || 'Something went wrong. Please try again.');
    }

    document.querySelectorAll('.cart-item').forEach(function (row) {
      var updateUrl = row.dataset.updateUrl;
      var destroyUrl = row.dataset.destroyUrl;
      var lineTotalEl = row.querySelector('.cart-item__line-total');
      var unitPriceEl = row.querySelector('.cart-item__unit-price');
      var stepper = row.querySelector('.qty-stepper');

      function submitQuantity(input) {
        var min = parseInt(input.min || '1', 10);
        var max = parseInt(input.max || '20', 10);
        var value = Math.min(max, Math.max(min, parseInt(input.value || '1', 10)));
        input.value = value;

        fetch(updateUrl, {
          method: 'PUT',
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': csrfToken,
          },
          body: JSON.stringify({quantity: value}),
        })
          .then(function (res) {
            if (! res.ok) throw new Error('Request failed');

            return res.json();
          })
          .then(function (data) {
            if (lineTotalEl) lineTotalEl.textContent = '$' + data.item.line_total;
            if (unitPriceEl) {
              unitPriceEl.textContent = '$' + data.item.unit_price + ' each';
              unitPriceEl.style.display = data.item.quantity > 1 ? '' : 'none';
            }
            applySummary(data);
          })
          .catch(function () {
            showError('Could not update quantity. Please try again.');
          });
      }

      if (stepper) {
        var input = stepper.querySelector('input[name="quantity"]');
        if (input) {
          stepper.querySelectorAll('button[data-step]').forEach(function (btn) {
            btn.addEventListener('click', function () {
              var min = parseInt(input.min || '1', 10);
              var max = parseInt(input.max || '20', 10);
              var next = parseInt(input.value || '1', 10) + parseInt(btn.dataset.step, 10);
              input.value = Math.min(max, Math.max(min, next));
              submitQuantity(input);
            });
          });

          input.addEventListener('change', function () {
            submitQuantity(input);
          });
        }
      }

      var removeForm = row.querySelector('.cart-item__remove-form');
      if (removeForm) {
        removeForm.addEventListener('submit', function (e) {
          e.preventDefault();
          if (! window.confirm('Remove this item?')) return;

          fetch(destroyUrl, {
            method: 'DELETE',
            headers: {
              'Accept': 'application/json',
              'X-CSRF-TOKEN': csrfToken,
            },
          })
            .then(function (res) {
              if (! res.ok) throw new Error('Request failed');

              return res.json();
            })
            .then(function (data) {
              row.remove();
              applySummary(data);
              recomputeShippingSplit();
            })
            .catch(function () {
              showError('Could not remove item. Please try again.');
            });
        });
      }
    });
  })();
</script>

</x-store-layout>
