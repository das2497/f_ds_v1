<x-admin-layout title="Pricing Settings">
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <div class="card-surface p-3 mb-4 d-flex flex-row align-items-start gap-2" style="background:#EFF6FF;border-color:#BFDBFE">
        <i class="bi bi-info-circle text-primary"></i>
        <div class="small" style="color:#1E40AF">This margin is used to suggest a retail price whenever a product or variant is sourced from a vendor or added manually — on top of the cost price. Admins can always type over the suggestion before publishing; existing products already published are not changed.</div>
    </div>

    <form action="{{ route('admin.pricing-settings.update') }}" method="POST">
        @csrf
        @method('PUT')

        <div class="table-admin p-4 mb-3">
            <h6 class="mb-3"><i class="bi bi-graph-up-arrow me-2 text-accent"></i>Profit Margin</h6>
            <div class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label class="form-label small">Markup over cost price (%)</label>
                    <div class="input-group">
                        <input type="number" step="0.1" min="0" max="1000" name="profit_margin_percent" id="profitMarginPercent" class="form-control" value="{{ old('profit_margin_percent', $profitMarginPercent) }}" required>
                        <span class="input-group-text">%</span>
                    </div>
                    @error('profit_margin_percent')
                        <div class="text-danger small mt-1">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-8">
                    <div class="small text-muted-slate">Example: a <span id="exampleCost">$10.00</span> cost price becomes a suggested price of <strong id="examplePrice">—</strong>.</div>
                </div>
            </div>
        </div>

        <button type="submit" class="btn btn-accent">Save Changes</button>
    </form>

    <script>
        (function () {
            var input = document.getElementById('profitMarginPercent');
            var exampleCost = 10;
            var out = document.getElementById('examplePrice');

            function recalc() {
                var margin = parseFloat(input.value);
                if (isNaN(margin) || margin < 0) {
                    out.textContent = '—';
                    return;
                }
                out.textContent = '$' + (exampleCost * (1 + margin / 100)).toFixed(2);
            }

            input.addEventListener('input', recalc);
            recalc();
        })();
    </script>
</x-admin-layout>
