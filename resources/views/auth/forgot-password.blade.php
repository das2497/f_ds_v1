<x-auth-layout title="Reset Password">
    <div class="text-center mb-3">
        <i class="bi bi-key fs-1 text-accent"></i>
    </div>
    <h1 class="h4 text-center mb-1">Reset Your Password</h1>
    <p class="text-muted-slate text-center small mb-4">Enter your email and we'll send you a link to reset your password.</p>

    @if (session('status'))
        <div class="alert alert-success small">{{ session('status') }}</div>
    @endif

    <form method="POST" action="{{ route('password.email') }}">
        @csrf
        <div class="mb-4">
            <label for="email" class="form-label small">Email Address</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" class="form-control @error('email') is-invalid @enderror" placeholder="you@example.com" required autofocus>
            @error('email')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
        <button type="submit" class="btn btn-accent w-100 btn-lg">Send Reset Link</button>
    </form>

    <div class="text-center small text-muted-slate mt-4">
        <a href="{{ route('login') }}" class="text-accent text-decoration-none"><i class="bi bi-arrow-left"></i> Back to Sign In</a>
    </div>
</x-auth-layout>
