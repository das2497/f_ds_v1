@php
    $storeName = config('store.name');
    $supportEmail = config('store.contact.email');
    $freeShippingThreshold = config('store.free_shipping_threshold');
    $returnWindow = config('store.return_window_days');
@endphp

<x-policy-page
    heading="Shipping & Refund Policy"
    description="How {{ $storeName }} ships orders across the USA, our delivery estimates, the 2-hour free cancellation window, and how returns and refunds work."
    updated="2026-10-27"
    :sections="[
        'overview' => 'Shipping Overview',
        'rates' => 'Rates & Delivery Estimates',
        'tracking' => 'Order Tracking',
        'cancellation' => '2-Hour Cancellation Window',
        'returns' => 'Returns & Refunds',
        'damaged' => 'Damaged or Defective Items',
        'non-returnable' => 'Non-Returnable Items',
        'contact' => 'Contact Support',
    ]">

<h2 id="overview">1. Shipping Overview</h2>
<p>
  We ship to addresses across the United States. Orders are fulfilled through our trusted fulfillment
  partners, and items in the same order may occasionally ship separately depending on where they are
  produced or warehoused. You are not charged extra when an order ships in more than one package.
</p>

<h2 id="rates">2. Rates &amp; Delivery Estimates</h2>
<p>
  Shipping is free on orders over ${{ $freeShippingThreshold }}. Below that threshold, shipping is
  calculated at checkout based on your delivery address and the items in your cart, and the exact
  amount is shown before you pay.
</p>
<ul>
  <li><strong>Standard Shipping</strong> — typically 5–7 business days after dispatch.</li>
  <li><strong>Express Shipping</strong> — typically 2–3 business days after dispatch.</li>
</ul>
<p>
  Estimated delivery windows appear at checkout and in your order confirmation email. Business days
  exclude weekends and US public holidays, and delivery estimates do not include the order processing
  time described below.
</p>

<h2 id="tracking">3. Order Tracking</h2>
<p>
  Once your order ships you will receive a tracking link by email. You can also follow an order's
  progress at any time from <a href="{{ route('account.orders') }}" class="text-accent">your order
  history</a>. If an order ships in multiple packages, each one gets its own tracking information.
</p>

<h2 id="cancellation">4. 2-Hour Cancellation Window</h2>
<div class="cancellation-notice mb-3">
  <i class="bi bi-clock-history me-2" style="color:#B45309" aria-hidden="true"></i>
  <strong style="color:#92400E">You can cancel any order free of charge within 2 hours of payment confirmation.</strong>
</div>
<p>
  After your payment is verified, your order enters a 2-hour hold period before it is sent to our
  fulfillment partners. During this window you can cancel the order yourself from your account and
  receive a full refund, with no questions asked and no restocking fee.
</p>
<p>
  Once the hold period ends, your order is submitted for fulfillment and can no longer be cancelled —
  the returns process in section 5 applies instead.
</p>

<h2 id="returns">5. Returns &amp; Refunds</h2>
<p>
  If you are not satisfied with your order, contact our support team within {{ $returnWindow }} days
  of delivery and we will send you return instructions. Please keep the original packaging where
  possible.
</p>
<p>
  Approved returns are refunded to your original payment method within 5–10 business days of us
  receiving the returned item. Refunds are issued through Stripe, so the time it takes to appear on
  your statement depends on your bank or card issuer. Partial refunds may apply where an item is
  returned used, damaged, or with missing parts for reasons not caused by us.
</p>

<h2 id="damaged">6. Damaged or Defective Items</h2>
<p>
  If an item arrives damaged or defective, contact us within 7 days of delivery and include photos of
  the item and its packaging. We will arrange a replacement or a full refund at no additional cost to
  you, and you will not be asked to pay return shipping.
</p>

<h2 id="non-returnable">7. Non-Returnable Items</h2>
<p>
  Some items cannot be returned unless they arrive damaged or defective. This includes personalized
  or made-to-order products, and items sold as final sale. Where an item is non-returnable, that is
  stated on its product page before you buy.
</p>

<h2 id="contact">8. Contact Support</h2>
<p>
  For any shipping, cancellation or refund question, email us at
  <a href="mailto:{{ $supportEmail }}" class="text-accent">{{ $supportEmail }}</a>. Include your order
  number so we can help you faster.
</p>

</x-policy-page>
