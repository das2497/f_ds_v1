<x-store-layout title="Profile Settings" robots="noindex, nofollow">

<main class="container py-4">
  <nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb small mb-0">
      <li class="breadcrumb-item"><a href="{{ route('home') }}" class="text-muted-slate text-decoration-none">Home</a></li>
      <li class="breadcrumb-item"><a href="{{ route('account.dashboard') }}" class="text-muted-slate text-decoration-none">My Account</a></li>
      <li class="breadcrumb-item active">Profile Settings</li>
    </ol>
  </nav>

  @if (session('status') === 'profile-updated')
    <div class="alert alert-success">Profile updated.</div>
  @endif
  @if (session('status') === 'password-updated')
    <div class="alert alert-success">Password updated.</div>
  @endif

  <div class="row g-4">
    <div class="col-lg-3">
      @include('partials.account-nav')
    </div>

    <div class="col-lg-9">
      <h1 class="h4 mb-4">Profile Settings</h1>

      <div class="card-surface p-4 mb-4">
        <h6 class="mb-3"><i class="bi bi-person me-2 text-accent"></i>Personal Information</h6>
        <form method="POST" action="{{ route('profile.update') }}">
          @csrf
          @method('PATCH')
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label small">Full Name</label>
              <input type="text" name="name" class="form-control" value="{{ old('name', $user->name) }}" required>
              @error('name') <div class="small text-danger mt-1">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-6">
              <label class="form-label small">Email Address</label>
              <input type="email" name="email" class="form-control" value="{{ old('email', $user->email) }}" required>
              @error('email') <div class="small text-danger mt-1">{{ $message }}</div> @enderror
            </div>
          </div>
          <button type="submit" class="btn btn-accent mt-4">Save Changes</button>
        </form>
      </div>

      <div class="card-surface p-4">
        <h6 class="mb-3"><i class="bi bi-lock me-2 text-accent"></i>Change Password</h6>
        <form method="POST" action="{{ route('password.update') }}">
          @csrf
          @method('PUT')
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label small">Current Password</label>
              <input type="password" name="current_password" class="form-control" placeholder="Current password">
              @error('current_password', 'updatePassword') <div class="small text-danger mt-1">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-6"></div>
            <div class="col-md-6">
              <label class="form-label small">New Password</label>
              <input type="password" name="password" class="form-control" placeholder="At least 8 characters">
              @error('password', 'updatePassword') <div class="small text-danger mt-1">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-6">
              <label class="form-label small">Confirm New Password</label>
              <input type="password" name="password_confirmation" class="form-control" placeholder="Re-enter new password">
            </div>
          </div>
          <button type="submit" class="btn btn-accent mt-4">Update Password</button>
        </form>
      </div>
    </div>
  </div>
</main>

</x-store-layout>
