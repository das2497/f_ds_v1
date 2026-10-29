@php
    $storeName = config('store.name');
    $supportEmail = config('store.contact.email');
@endphp

<x-policy-page
    heading="Privacy Policy"
    description="What personal information {{ $storeName }} collects, how we use it, the cookies and analytics we set, who we share data with, and the choices and rights you have."
    updated="2026-10-27"
    :sections="[
        'intro' => 'Introduction',
        'collect' => 'Information We Collect',
        'use' => 'How We Use Your Information',
        'payments' => 'Payments',
        'cookies' => 'Cookies & Analytics',
        'sharing' => 'Who We Share Data With',
        'retention' => 'How Long We Keep Data',
        'rights' => 'Your Rights & Choices',
        'security' => 'Security',
        'children' => 'Children\'s Privacy',
        'changes' => 'Changes to This Policy',
        'contact' => 'Contact Us',
    ]">

<h2 id="intro">1. Introduction</h2>
<p>
  This policy explains what personal information {{ $storeName }} collects when you browse or buy from
  this store, why we collect it, and what control you have over it. It applies to this website and to
  the order and support emails we send you.
</p>

<h2 id="collect">2. Information We Collect</h2>
<p>We collect only what we need to run the store:</p>
<ul>
  <li><strong>Account information</strong> — your name, email address and password. If you sign in with Google, we receive your name, email address and Google account identifier instead of a password.</li>
  <li><strong>Order information</strong> — the items you buy, your shipping and billing address, your phone number where you provide it, and your order history.</li>
  <li><strong>Payment information</strong> — handled by Stripe, not by us. See section 4.</li>
  <li><strong>Usage information</strong> — pages and products you view, searches you run, items you add to your cart, and the referral or campaign link that brought you to the store.</li>
  <li><strong>Technical information</strong> — your IP address, browser and device type, and the cookies described in section 5.</li>
</ul>

<h2 id="use">3. How We Use Your Information</h2>
<ul>
  <li>To process, fulfill, ship and support your orders.</li>
  <li>To create and secure your account, and to sign you in.</li>
  <li>To send transactional email such as order confirmations, shipping updates, cancellations and refunds.</li>
  <li>To decide which products to stock and how to improve the store, based on aggregated browsing and search activity.</li>
  <li>To measure how well our advertising performs, where you have allowed analytics cookies.</li>
  <li>To detect and prevent fraud, abuse and payment disputes.</li>
</ul>
<p>We do not sell your personal information.</p>

<h2 id="payments">4. Payments</h2>
<p>
  Card payments are processed by <a href="https://stripe.com/privacy" class="text-accent" target="_blank" rel="noopener noreferrer">Stripe</a>.
  Your full card number, expiry date and security code are entered on Stripe's own secure payment
  page and are never sent to or stored on our servers. We receive only the result of the payment and
  a limited reference such as the card brand and last four digits, so we can match a payment to your
  order and process refunds.
</p>

<h2 id="cookies">5. Cookies &amp; Analytics</h2>
<p>We use two kinds of cookies:</p>
<ul>
  <li><strong>Essential cookies</strong> always run. They keep you signed in, remember your cart between visits, and protect forms against cross-site request forgery. The store cannot work without them.</li>
  <li><strong>Analytics and advertising cookies</strong> only run after you press <em>Accept</em> on our cookie banner. These may include Google Analytics, the Meta pixel and the TikTok pixel, which help us understand which products and campaigns work.</li>
</ul>
<p>
  If you press <em>Decline</em>, no analytics or advertising scripts are loaded at all. You can change
  your mind at any time by clearing this site's cookies in your browser, which brings the banner back.
</p>

<h2 id="sharing">6. Who We Share Data With</h2>
<p>We share personal information only with the service providers we need to complete your order:</p>
<ul>
  <li><strong>Payment processing</strong> — Stripe.</li>
  <li><strong>Fulfillment and shipping</strong> — our print and dropship partners and their carriers, who receive your name, shipping address and the items to ship.</li>
  <li><strong>Email delivery</strong> — our transactional email provider.</li>
  <li><strong>Analytics and advertising</strong> — only where you have consented, as described in section 5.</li>
</ul>
<p>
  We may also disclose information where we are legally required to, or to protect our rights in a
  dispute. We do not share your personal information for any other purpose.
</p>

<h2 id="retention">7. How Long We Keep Data</h2>
<p>
  We keep order records for as long as we are required to for tax, accounting and dispute-resolution
  purposes. Account data is kept while your account is open. Analytics data is kept in aggregated form
  and is not tied back to your name or email address.
</p>

<h2 id="rights">8. Your Rights &amp; Choices</h2>
<p>You can, at any time:</p>
<ul>
  <li>View and update your name, email address and password from <a href="{{ route('profile.edit') }}" class="text-accent">your profile settings</a>.</li>
  <li>Review your orders from <a href="{{ route('account.orders') }}" class="text-accent">your order history</a>.</li>
  <li>Ask us for a copy of the personal information we hold about you.</li>
  <li>Ask us to delete your account and personal information, subject to records we must keep by law.</li>
  <li>Withdraw your analytics and advertising consent, as described in section 5.</li>
</ul>
<p>
  To make any of these requests, email <a href="mailto:{{ $supportEmail }}" class="text-accent">{{ $supportEmail }}</a>.
  Depending on where you live, you may have additional rights under laws such as the CCPA or GDPR, and
  we will honour those where they apply to you.
</p>

<h2 id="security">9. Security</h2>
<p>
  Traffic to this store is encrypted in transit. Passwords are stored as salted hashes and are never
  readable by us or by our staff. Payment credentials never reach our systems. Administrative access
  is limited to named staff accounts and significant administrative actions are logged.
</p>

<h2 id="children">10. Children's Privacy</h2>
<p>
  This store is not directed at children under 13, and we do not knowingly collect personal
  information from them. If you believe a child has given us personal information, contact us and we
  will delete it.
</p>

<h2 id="changes">11. Changes to This Policy</h2>
<p>
  If we change this policy we will update the "last updated" date at the top of this page. Where the
  change is significant, we will tell you by email or with a notice in the store.
</p>

<h2 id="contact">12. Contact Us</h2>
<p>
  For any privacy question or request, email
  <a href="mailto:{{ $supportEmail }}" class="text-accent">{{ $supportEmail }}</a>.
</p>

</x-policy-page>
