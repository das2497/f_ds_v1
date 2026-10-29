<div class="account-nav mb-3">
  <a href="{{ route('account.dashboard') }}" class="{{ request()->routeIs('account.dashboard') ? 'active' : '' }}"><i class="bi bi-grid"></i> Dashboard</a>
  <a href="{{ route('account.orders') }}" class="{{ request()->routeIs('account.orders*') ? 'active' : '' }}"><i class="bi bi-receipt"></i> Order History</a>
  <a href="{{ route('wishlist.index') }}" class="{{ request()->routeIs('wishlist.*') ? 'active' : '' }}"><i class="bi bi-heart"></i> Wishlist</a>
  <a href="{{ route('profile.edit') }}" class="{{ request()->routeIs('profile.edit') ? 'active' : '' }}"><i class="bi bi-person"></i> Profile Settings</a>
  <form action="{{ route('logout') }}" method="POST" class="mb-0">
    @csrf
    <button type="submit" class="text-danger" style="display:flex;align-items:center;gap:0.6rem;padding:0.75rem 1.1rem;width:100%;text-align:left;border:0;border-left:3px solid transparent;background:transparent;font-size:0.92rem;"><i class="bi bi-box-arrow-right"></i> Sign Out</button>
  </form>
</div>
