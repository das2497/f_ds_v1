@php
    $mask = fn (?string $value) => $value ? '•••••••••••• '.substr($value, -4) : 'Not configured';
@endphp
<x-admin-layout title="Gateway Settings">
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <div class="card-surface p-3 mb-4 d-flex flex-row align-items-start gap-2" style="background:#FEF2F2;border-color:#FECACA">
        <i class="bi bi-shield-exclamation text-danger"></i>
        <div class="small" style="color:#991B1B">These credentials control live payments and vendor order submission. Changes take effect immediately — double-check before saving. Leave a field blank to keep its current value.</div>
    </div>

    <form action="{{ route('admin.settings-gateways.update') }}" method="POST">
        @csrf
        @method('PUT')

        <div class="table-admin p-4 mb-3">
            <h6 class="mb-3"><i class="bi bi-credit-card-2-front me-2 text-accent"></i>Stripe</h6>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label small">Publishable Key</label>
                    <input type="text" name="stripe_key" class="form-control" placeholder="{{ $mask($settings['stripe.key']) }}">
                </div>
                <div class="col-md-6">
                    <label class="form-label small">Secret Key</label>
                    <input type="password" name="stripe_secret" class="form-control" placeholder="{{ $mask($settings['stripe.secret']) }}">
                </div>
                <div class="col-md-6">
                    <label class="form-label small">Webhook Signing Secret</label>
                    <input type="password" name="stripe_webhook_secret" class="form-control" placeholder="{{ $mask($settings['stripe.webhook_secret']) }}">
                </div>
            </div>
        </div>

        <div class="table-admin p-4 mb-3">
            <h6 class="mb-3"><i class="bi bi-box-seam me-2 text-accent"></i>Printful</h6>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label small">API Key</label>
                    <input type="password" name="printful_api_key" class="form-control" placeholder="{{ $mask($settings['printful.api_key']) }}">
                </div>
                <div class="col-md-6">
                    <label class="form-label small">Store ID</label>
                    <input type="text" name="printful_store_id" class="form-control" placeholder="{{ $mask($settings['printful.store_id']) }}">
                </div>
            </div>
        </div>

        <div class="table-admin p-4 mb-3">
            <h6 class="mb-3"><i class="bi bi-truck me-2 text-accent"></i>CJDropshipping</h6>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label small">API Key</label>
                    <input type="password" name="cj_api_key" class="form-control" placeholder="{{ $mask($settings['cj.api_key']) }}">
                    <div class="form-text">From your CJ dashboard: install the "API" app under Apps, then Settings &gt; API &gt; Add API &gt; copy the generated key (looks like <code>CJUserNum@api@...</code>).</div>
                </div>
            </div>
            <div class="small text-muted-slate mt-2"><i class="bi bi-arrow-repeat me-1"></i>Access/refresh token rotation handled automatically by the credential service.</div>
        </div>

        <div class="table-admin p-4 mb-3">
            <h6 class="mb-3"><i class="bi bi-stars me-2 text-accent"></i>Ollama Cloud (AI Product Scoring &amp; Research Chat)</h6>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label small">API Key</label>
                    <input type="password" name="ollama_api_key" class="form-control" placeholder="{{ $mask($settings['ollama.api_key']) }}">
                    <div class="form-text">From <code>ollama.com/settings/keys</code>. Powers the "AI Score" button and the AI Research Assistant chat.</div>
                </div>
                <div class="col-md-6">
                    <label class="form-label small">Model</label>
                    <input type="text" name="ollama_model" class="form-control" placeholder="{{ $settings['ollama.model'] ?: 'gemma4:31b (default)' }}">
                    <div class="form-text">The AI Research Assistant needs a model with tool/function-calling support — not every Ollama Cloud model has it. AI Score works with any chat model.</div>
                </div>
            </div>
        </div>

        <div class="table-admin p-4 mb-3">
            <h6 class="mb-3"><i class="bi bi-google me-2 text-accent"></i>Google Sign-In (OAuth)</h6>
            <div class="small text-muted-slate mb-3">Powers the "Continue with Google" button on sign in/register. Create an OAuth client under <a href="https://console.cloud.google.com/apis/credentials" target="_blank" rel="noopener">Google Cloud Console &gt; APIs &amp; Services &gt; Credentials</a>, then add the authorized redirect URI below.</div>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label small">Client ID</label>
                    <input type="text" name="google_client_id" class="form-control" placeholder="{{ $settings['google.client_id'] ? $mask($settings['google.client_id']) : 'Not configured' }}">
                </div>
                <div class="col-md-6">
                    <label class="form-label small">Client Secret</label>
                    <input type="password" name="google_client_secret" class="form-control" placeholder="{{ $mask($settings['google.client_secret']) }}">
                </div>
                <div class="col-12">
                    <label class="form-label small">Authorized redirect URI (copy into Google Cloud Console)</label>
                    <input type="text" class="form-control" value="{{ url('/auth/google/callback') }}" readonly onclick="this.select()">
                </div>
            </div>
        </div>

        <div class="table-admin p-4 mb-3">
            <h6 class="mb-3"><i class="bi bi-graph-up-arrow me-2 text-accent"></i>Marketing Attribution (server-side conversion events)</h6>
            <div class="small text-muted-slate mb-3">Optional — every service below no-ops safely when unconfigured. These send purchase/refund events server-side, alongside (not instead of) the browser-side pixels.</div>

            <div class="mb-3">
                <div class="fw-semibold small mb-2">Google Analytics 4 (Measurement Protocol)</div>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label small">Measurement ID</label>
                        <input type="text" name="ga4_measurement_id" class="form-control" placeholder="{{ $settings['ga4.measurement_id'] ?: 'G-XXXXXXXXXX' }}">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small">API Secret</label>
                        <input type="password" name="ga4_api_secret" class="form-control" placeholder="{{ $mask($settings['ga4.api_secret']) }}">
                        <div class="form-text">GA4 Admin &gt; Data Streams &gt; your stream &gt; Measurement Protocol API secrets.</div>
                    </div>
                </div>
            </div>

            <div class="mb-3">
                <div class="fw-semibold small mb-2">Meta Conversions API</div>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label small">Pixel ID</label>
                        <input type="text" name="meta_pixel_id" class="form-control" placeholder="{{ $settings['meta.pixel_id'] ?: 'e.g. 123456789012345' }}">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small">Access Token</label>
                        <input type="password" name="meta_access_token" class="form-control" placeholder="{{ $mask($settings['meta.access_token']) }}">
                        <div class="form-text">Events Manager &gt; your pixel &gt; Settings &gt; Conversions API &gt; Generate access token.</div>
                    </div>
                </div>
            </div>

            <div>
                <div class="fw-semibold small mb-2">TikTok Events API</div>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label small">Pixel Code</label>
                        <input type="text" name="tiktok_pixel_code" class="form-control" placeholder="{{ $settings['tiktok.pixel_code'] ?: 'e.g. CXXXXXXXXXXXXXXXXXXX' }}">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small">Access Token</label>
                        <input type="password" name="tiktok_access_token" class="form-control" placeholder="{{ $mask($settings['tiktok.access_token']) }}">
                        <div class="form-text">TikTok Events Manager &gt; your pixel &gt; Settings &gt; Generate Access Token.</div>
                    </div>
                </div>
            </div>
        </div>

        <button type="submit" class="btn btn-accent">Save Changes</button>
    </form>
</x-admin-layout>
