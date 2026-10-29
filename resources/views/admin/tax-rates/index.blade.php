<x-admin-layout title="Tax Rates">
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div class="small text-muted-slate"><i class="bi bi-info-circle me-1"></i>CJ items use these manually-entered base rates. Printful items use live API tax at checkout — not listed here.</div>
    </div>

    <div class="table-admin p-3">
        <table class="table table-admin align-middle mb-0">
            <thead>
                <tr><th>State</th><th>Code</th><th>Base Rate</th><th>Last Updated</th><th></th></tr>
            </thead>
            <tbody>
                @foreach ($taxRates as $taxRate)
                    <tr>
                        <td>{{ $taxRate->state_name }}</td>
                        <td>{{ $taxRate->state_code }}</td>
                        <td>
                            <form action="{{ route('admin.tax-rates.update', $taxRate) }}" method="POST" class="d-flex align-items-center gap-2">
                                @csrf
                                @method('PUT')
                                <input type="text" name="rate_percent" class="form-control form-control-sm" value="{{ number_format($taxRate->rate * 100, 2) }}" style="width:90px">
                                <button type="submit" class="btn btn-sm btn-link text-accent text-decoration-none p-0">Save</button>
                            </form>
                        </td>
                        <td class="small text-muted-slate">{{ $taxRate->updated_at->format('M j, Y') }}</td>
                        <td></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</x-admin-layout>
