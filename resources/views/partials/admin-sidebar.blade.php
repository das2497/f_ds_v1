<aside class="admin-sidebar">
  <div class="brand d-flex align-items-center gap-2">
    <img src="{{ asset('images/logo.jpeg') }}" alt="Shobley" style="height:28px">
    <span class="text-white fw-semibold small">Admin Panel</span>
  </div>

  <div class="nav-section-label">Dashboard &amp; Analytics</div>
  <nav class="nav flex-column">
    <a class="nav-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}" href="{{ route('admin.dashboard') }}"><i class="bi bi-speedometer2"></i> Main Dashboard</a>
    <a class="nav-link {{ request()->routeIs('admin.analytics-utm') ? 'active' : '' }}" href="{{ route('admin.analytics-utm') }}"><i class="bi bi-graph-up"></i> UTM Attribution</a>
    <a class="nav-link {{ request()->routeIs('admin.analytics-demand') ? 'active' : '' }}" href="{{ route('admin.analytics-demand') }}"><i class="bi bi-bar-chart"></i> Demand Forecasting</a>
  </nav>

  <div class="nav-section-label">Content</div>
  <nav class="nav flex-column">
    <a class="nav-link {{ request()->routeIs('admin.carousel.*') ? 'active' : '' }}" href="{{ route('admin.carousel.index') }}"><i class="bi bi-images"></i> Homepage Settings</a>
  </nav>

  <div class="nav-section-label">Product &amp; Vendor Catalog</div>
  <nav class="nav flex-column">
    <a class="nav-link {{ request()->routeIs('admin.products') ? 'active' : '' }}" href="{{ route('admin.products') }}"><i class="bi bi-box-seam"></i> Products</a>
    <a class="nav-link {{ request()->routeIs('admin.categories.*') ? 'active' : '' }}" href="{{ route('admin.categories.index') }}"><i class="bi bi-tags"></i> Categories</a>
    <a class="nav-link {{ request()->routeIs('admin.products-research-printful') ? 'active' : '' }}" href="{{ route('admin.products-research-printful') }}"><i class="bi bi-search"></i> Printful Research</a>
    <a class="nav-link {{ request()->routeIs('admin.products-research-cjd') ? 'active' : '' }}" href="{{ route('admin.products-research-cjd') }}"><i class="bi bi-search"></i> CJD Research</a>
    <a class="nav-link {{ request()->routeIs('admin.ai-research') ? 'active' : '' }}" href="{{ route('admin.ai-research') }}"><i class="bi bi-stars"></i> AI Research Assistant</a>
    <a class="nav-link {{ request()->routeIs('admin.products-selected') ? 'active' : '' }}" href="{{ route('admin.products-selected') }}"><i class="bi bi-bookmark-check"></i> Selected Products</a>
  </nav>

  <div class="nav-section-label">Order &amp; Fulfillment</div>
  <nav class="nav flex-column">
    <a class="nav-link {{ request()->routeIs('admin.orders') ? 'active' : '' }}" href="{{ route('admin.orders') }}"><i class="bi bi-receipt"></i> Order Queue</a>
    <a class="nav-link {{ request()->routeIs('admin.order-retry') ? 'active' : '' }}" href="{{ route('admin.order-retry') }}"><i class="bi bi-arrow-repeat"></i> Retry Console</a>
  </nav>

  <div class="nav-section-label">Financials &amp; Taxes</div>
  <nav class="nav flex-column">
    <a class="nav-link {{ request()->routeIs('admin.tax-rates') ? 'active' : '' }}" href="{{ route('admin.tax-rates') }}"><i class="bi bi-percent"></i> Tax Rates</a>
    <a class="nav-link {{ request()->routeIs('admin.refunds') ? 'active' : '' }}" href="{{ route('admin.refunds') }}"><i class="bi bi-cash-coin"></i> Refunds</a>
  </nav>

  @role('super-admin')
  <div class="nav-section-label">Super Admin Only</div>
  <nav class="nav flex-column">
    <a class="nav-link {{ request()->routeIs('admin.staff') ? 'active' : '' }}" href="{{ route('admin.staff') }}"><i class="bi bi-people"></i> Staff Management <span class="super-admin-tag">SA</span></a>
    <a class="nav-link {{ request()->routeIs('admin.settings-gateways') ? 'active' : '' }}" href="{{ route('admin.settings-gateways') }}"><i class="bi bi-key"></i> Gateway Settings <span class="super-admin-tag">SA</span></a>
    <a class="nav-link {{ request()->routeIs('admin.audit-logs') ? 'active' : '' }}" href="{{ route('admin.audit-logs') }}"><i class="bi bi-journal-text"></i> Audit Logs <span class="super-admin-tag">SA</span></a>
    <a class="nav-link {{ request()->routeIs('admin.maintenance') ? 'active' : '' }}" href="{{ route('admin.maintenance') }}"><i class="bi bi-hdd-stack"></i> Maintenance <span class="super-admin-tag">SA</span></a>
  </nav>
  @endrole
</aside>
