<x-auth-layout title="Sign In">
    <h1 class="h4 text-center mb-1">Welcome Back</h1>
    <p class="text-muted-slate text-center small mb-4">Sign in to your Shobley account</p>

    @if (session('status'))
        <div class="alert alert-success small">{{ session('status') }}</div>
    @endif
    @if (session('error'))
        <div class="alert alert-danger small">{{ session('error') }}</div>
    @endif

    <form method="POST" action="{{ route('login') }}">
        @csrf
        <div class="mb-3">
            <label for="email" class="form-label small">Email Address</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" class="form-control @error('email') is-invalid @enderror" placeholder="you@example.com" required autofocus autocomplete="username">
            @error('email')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
        <div class="mb-2">
            <label for="password" class="form-label small">Password</label>
            <input id="password" type="password" name="password" class="form-control @error('password') is-invalid @enderror" placeholder="Enter your password" required autocomplete="current-password">
            @error('password')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div class="form-check">
                <input class="form-check-input" type="checkbox" name="remember" id="rememberMe">
                <label class="form-check-label small" for="rememberMe">Remember me</label>
            </div>
            @if (Route::has('password.request'))
                <a href="{{ route('password.request') }}" class="small text-accent text-decoration-none">Forgot password?</a>
            @endif
        </div>
        <button type="submit" class="btn btn-accent w-100 btn-lg">Sign In</button>
    </form>

    <div class="d-flex align-items-center gap-2 my-4">
        <hr class="flex-grow-1"><span class="small text-muted-slate">or</span><hr class="flex-grow-1">
    </div>

    <a href="{{ route('auth.google') }}" class="btn btn-outline-secondary w-100 btn-lg d-flex align-items-center justify-content-center gap-2">
        <svg width="18" height="18" viewBox="0 0 18 18" xmlns="http://www.w3.org/2000/svg"><path fill="#4285F4" d="M17.64 9.2c0-.64-.06-1.25-.16-1.84H9v3.48h4.84a4.14 4.14 0 0 1-1.8 2.72v2.26h2.92c1.7-1.57 2.68-3.88 2.68-6.62z"/><path fill="#34A853" d="M9 18c2.43 0 4.47-.81 5.96-2.18l-2.92-2.26c-.81.54-1.85.86-3.04.86-2.34 0-4.32-1.58-5.03-3.71H.96v2.33A9 9 0 0 0 9 18z"/><path fill="#FBBC05" d="M3.97 10.71A5.4 5.4 0 0 1 3.68 9c0-.59.1-1.17.29-1.71V4.96H.96A9 9 0 0 0 0 9c0 1.45.35 2.83.96 4.04l3.01-2.33z"/><path fill="#EA4335" d="M9 3.58c1.32 0 2.51.46 3.44 1.35l2.59-2.59C13.46.89 11.43 0 9 0A9 9 0 0 0 .96 4.96l3.01 2.33C4.68 5.16 6.66 3.58 9 3.58z"/></svg>
        Continue with Google
    </a>

    <div class="text-center small text-muted-slate mt-4">
        New to Shobley? <a href="{{ route('register') }}" class="text-accent text-decoration-none fw-semibold">Create an account</a>
    </div>
</x-auth-layout>
