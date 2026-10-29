<div class="admin-topbar d-flex align-items-center justify-content-between">
  <div class="d-flex align-items-center gap-3">
    <button class="btn btn-sm btn-outline-secondary d-lg-none" data-toggle="admin-sidebar">
      <i class="bi bi-list"></i>
    </button>
    <h5 class="mb-0">{{ $title ?? 'Dashboard' }}</h5>
  </div>
  <div class="d-flex align-items-center gap-3">
    <button type="button" id="theme-toggle" class="btn btn-sm btn-outline-secondary" title="Toggle dark mode">
      <i class="bi bi-moon-stars"></i>
    </button>
    <i class="bi bi-bell fs-5 text-muted-slate"></i>
    <div class="dropdown">
      <button class="btn btn-sm btn-outline-secondary dropdown-toggle d-flex align-items-center gap-2" data-bs-toggle="dropdown">
        <i class="bi bi-person-circle fs-5"></i>
        <span class="small">{{ auth()->user()->name }}</span>
      </button>
      <ul class="dropdown-menu dropdown-menu-end">
        <li><a class="dropdown-item" href="{{ route('profile.edit') }}">Profile</a></li>
        <li><hr class="dropdown-divider"></li>
        <li>
          <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="dropdown-item">Logout</button>
          </form>
        </li>
      </ul>
    </div>
  </div>
</div>
