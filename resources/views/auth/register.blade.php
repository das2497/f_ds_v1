<x-auth-layout title="Create Account">
    <h1 class="h4 text-center mb-1">Create Your Account</h1>
    <p class="text-muted-slate text-center small mb-4">Join Shobley for faster checkout &amp; order tracking</p>

    <form method="POST" action="{{ route('register') }}">
        @csrf
        <div class="mb-3">
            <label for="name" class="form-label small">Full Name</label>
            <input id="name" type="text" name="name" value="{{ old('name') }}" class="form-control @error('name') is-invalid @enderror" placeholder="John Carter" required autofocus autocomplete="name">
            @error('name')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
        <div class="mb-3">
            <label for="email" class="form-label small">Email Address</label>
            <input id="email" type="email" name="email" value="{{ old('email', request('email')) }}" class="form-control @error('email') is-invalid @enderror" placeholder="you@example.com" required autocomplete="username">
            @error('email')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
        <div class="mb-3">
            <label for="password" class="form-label small">Password</label>
            <input id="password" type="password" name="password" class="form-control @error('password') is-invalid @enderror" placeholder="At least 8 characters" required autocomplete="new-password">
            @error('password')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
        <div class="mb-3">
            <label for="password_confirmation" class="form-label small">Confirm Password</label>
            <input id="password_confirmation" type="password" name="password_confirmation" class="form-control @error('password_confirmation') is-invalid @enderror" placeholder="Re-enter your password" required autocomplete="new-password">
            @error('password_confirmation')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
        <div class="form-check mb-4">
            <input class="form-check-input" type="checkbox" id="agreeTerms" required>
            <label class="form-check-label small" for="agreeTerms">
                I agree to the <a href="{{ route('policies.terms') }}" class="text-accent">Terms of Service</a> and <a href="{{ route('policies.privacy') }}" class="text-accent">Privacy Policy</a>
            </label>
        </div>
        <button type="submit" class="btn btn-accent w-100 btn-lg">Create Account</button>
    </form>

    <div class="d-flex align-items-center gap-2 my-4">
        <hr class="flex-grow-1"><span class="small text-muted-slate">or</span><hr class="flex-grow-1">
    </div>

    <a href="{{ route('auth.google') }}" class="btn btn-outline-secondary w-100 btn-lg d-flex align-items-center justify-content-center gap-2">
        <svg width="18" height="18" viewBox="0 0 18 18" xmlns="http://www.w3.org/2000/svg"><path fill="#4285F4" d="M17.64 9.2c0-.64-.06-1.25-.16-1.84H9v3.48h4.84a4.14 4.14 0 0 1-1.8 2.72v2.26h2.92c1.7-1.57 2.68-3.88 2.68-6.62z"/><path fill="#34A853" d="M9 18c2.43 0 4.47-.81 5.96-2.18l-2.92-2.26c-.81.54-1.85.86-3.04.86-2.34 0-4.32-1.58-5.03-3.71H.96v2.33A9 9 0 0 0 9 18z"/><path fill="#FBBC05" d="M3.97 10.71A5.4 5.4 0 0 1 3.68 9c0-.59.1-1.17.29-1.71V4.96H.96A9 9 0 0 0 0 9c0 1.45.35 2.83.96 4.04l3.01-2.33z"/><path fill="#EA4335" d="M9 3.58c1.32 0 2.51.46 3.44 1.35l2.59-2.59C13.46.89 11.43 0 9 0A9 9 0 0 0 .96 4.96l3.01 2.33C4.68 5.16 6.66 3.58 9 3.58z"/></svg>
        Continue with Google
    </a>

    <div class="text-center small text-muted-slate mt-4">
        Already have an account? <a href="{{ route('login') }}" class="text-accent text-decoration-none fw-semibold">Sign In</a>
    </div>
</x-auth-layout>
