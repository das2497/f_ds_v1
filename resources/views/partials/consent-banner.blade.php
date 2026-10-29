@unless (request()->cookie('analytics_consent'))
<div class="consent-banner" style="position:fixed;bottom:0;left:0;right:0;z-index:1050;background:#111827;color:#fff;padding:1rem 1.25rem;">
  <div class="container d-flex flex-wrap align-items-center justify-content-between gap-3">
    <div class="small" style="max-width:640px">
      We use cookies for analytics and to measure how well our ads perform. See our
      <a href="{{ route('policies.privacy') }}" class="text-white text-decoration-underline">Privacy Policy</a>.
    </div>
    <div class="d-flex gap-2 flex-shrink-0">
      <form action="{{ route('consent.store') }}" method="POST">
        @csrf
        <input type="hidden" name="choice" value="denied">
        <button type="submit" class="btn btn-sm btn-outline-light">Decline</button>
      </form>
      <form action="{{ route('consent.store') }}" method="POST">
        @csrf
        <input type="hidden" name="choice" value="granted">
        <button type="submit" class="btn btn-sm btn-accent">Accept</button>
      </form>
    </div>
  </div>
</div>
@endunless
