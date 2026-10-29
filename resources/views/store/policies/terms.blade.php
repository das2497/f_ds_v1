@php
    $storeName = config('store.name');
    $supportEmail = config('store.contact.email');
    $freeShippingThreshold = config('store.free_shipping_threshold');
@endphp

<x-policy-page
    heading="Terms of Service"
    description="The terms that apply when you use {{ $storeName }} — accounts, orders and pricing, payment, shipping, cancellations, acceptable use and liability."
    updated="2026-10-27"
    :sections="[
        'agreement' => 'Agreement to These Terms',
        'eligibility' => 'Eligibility',
        'accounts' => 'Your Account',
        'products' => 'Products & Availability',
        'pricing' => 'Pricing & Taxes',
        'orders' => 'Orders & Acceptance',
        'payment' => 'Payment',
        'shipping' => 'Shipping & Cancellations',
        'conduct' => 'Acceptable Use',
        'ip' => 'Intellectual Property',
        'liability' => 'Disclaimers & Liability',
        'termination' => 'Suspension & Termination',
        'law' => 'Governing Law',
        'contact' => 'Contact Us',
    ]">

<h2 id="agreement">1. Agreement to These Terms</h2>
<p>
  By browsing or buying from {{ $storeName }} you agree to these terms. If you do not agree with them,
  please do not use the store. These terms work together with our
  <a href="{{ route('policies.privacy') }}" class="text-accent">Privacy Policy</a> and
  <a href="{{ route('policies.shipping-refund') }}" class="text-accent">Shipping &amp; Refund Policy</a>,
  which form part of this agreement.
</p>

<h2 id="eligibility">2. Eligibility</h2>
<p>
  You must be at least 18 years old, or have the consent of a parent or guardian, to place an order.
  We currently ship to addresses within the United States only.
</p>

<h2 id="accounts">3. Your Account</h2>
<p>
  You can browse and check out as a guest, but an account lets you track orders, cancel within the
  cancellation window and keep a wishlist. You are responsible for keeping your password confidential
  and for activity under your account. Tell us immediately if you believe your account has been used
  without your permission.
</p>
<p>
  The information you give us — particularly your shipping address — must be accurate and complete.
  We are not responsible for a delivery that fails because of an address error you made.
</p>

<h2 id="products">4. Products &amp; Availability</h2>
<p>
  We work to describe and photograph products accurately, but colours shown on your screen can differ
  from the physical item, and product images are illustrative. All products are subject to
  availability; a listed item can sell out between you adding it to your cart and placing your order.
</p>

<h2 id="pricing">5. Pricing &amp; Taxes</h2>
<p>
  Prices are shown in US dollars and may change at any time before you place an order. Sales tax is
  calculated at checkout based on your delivery address, and shipping is free on orders over
  ${{ $freeShippingThreshold }}. The total shown on the checkout page before you pay is the total you
  will be charged.
</p>
<p>
  If a product is listed at an obviously incorrect price because of a technical or human error, we may
  cancel the affected order and refund you in full rather than fulfill it. We will contact you before
  doing so.
</p>

<h2 id="orders">6. Orders &amp; Acceptance</h2>
<p>
  Placing an order is an offer to buy. Your order is accepted when we confirm payment and send your
  order confirmation email. We may decline or cancel an order — for example where the item is out of
  stock, where we suspect fraud, or where we cannot ship to the address given — and will refund any
  payment in full if we do.
</p>

<h2 id="payment">7. Payment</h2>
<p>
  Payments are processed securely by Stripe. We accept the card types shown at checkout. Your card
  details are entered on Stripe's payment page and are never stored on our servers. You confirm that
  you are authorised to use the payment method you provide.
</p>

<h2 id="shipping">8. Shipping &amp; Cancellations</h2>
<p>
  Delivery estimates, tracking, the 2-hour free cancellation window, returns and refunds are all
  covered in our <a href="{{ route('policies.shipping-refund') }}" class="text-accent">Shipping &amp;
  Refund Policy</a>, which applies to every order.
</p>

<h2 id="conduct">9. Acceptable Use</h2>
<p>When using this store you agree not to:</p>
<ul>
  <li>Scrape, crawl or harvest content or prices by automated means beyond ordinary search engine indexing.</li>
  <li>Interfere with the store's operation, security or availability, or attempt to access accounts or data that are not yours.</li>
  <li>Place fraudulent orders, or use a payment method you are not authorised to use.</li>
  <li>Resell products in breach of a restriction stated on the product page.</li>
</ul>

<h2 id="ip">10. Intellectual Property</h2>
<p>
  The {{ $storeName }} name, logo, site design and original copy belong to us and may not be copied or
  reused without our written permission. Product images and descriptions supplied by our vendors
  remain the property of their respective owners.
</p>

<h2 id="liability">11. Disclaimers &amp; Liability</h2>
<p>
  The store is provided on an "as is" and "as available" basis. We do not warrant that it will be
  uninterrupted or error-free. Nothing in these terms excludes any liability that cannot be excluded
  under applicable law, including for death, personal injury or fraud caused by us.
</p>
<p>
  Subject to that, our total liability to you for any claim relating to an order is limited to the
  amount you paid for that order. We are not liable for indirect or consequential loss, such as lost
  profits or lost opportunity.
</p>

<h2 id="termination">12. Suspension &amp; Termination</h2>
<p>
  We may suspend or close an account that breaches these terms. You can ask us to close your account
  at any time by emailing <a href="mailto:{{ $supportEmail }}" class="text-accent">{{ $supportEmail }}</a>;
  orders already placed will still be fulfilled or refunded under the policies above.
</p>

<h2 id="law">13. Governing Law</h2>
<p>
  These terms are governed by the laws of the United States and of the state in which
  {{ $storeName }} is established, without regard to conflict-of-law rules. If any provision is found
  unenforceable, the rest remains in force.
</p>

<h2 id="contact">14. Contact Us</h2>
<p>
  Questions about these terms? Email
  <a href="mailto:{{ $supportEmail }}" class="text-accent">{{ $supportEmail }}</a>.
</p>

</x-policy-page>
