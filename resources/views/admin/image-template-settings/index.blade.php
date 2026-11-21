<x-admin-layout title="Image Template">
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <div class="card-surface p-3 mb-4 d-flex flex-row align-items-start gap-2" style="background:#EFF6FF;border-color:#BFDBFE">
        <i class="bi bi-info-circle text-primary"></i>
        <div class="small" style="color:#1E40AF">Upload a square, transparent PNG with your logo/border baked in. It's stamped on top of every product and variant photo as soon as an admin crops and uploads it — the crop screen previews it live, and it's merged into the final saved image.</div>
    </div>

    <div class="table-admin p-4 mb-3">
        <h6 class="mb-3"><i class="bi bi-image me-2 text-accent"></i>Current Template</h6>
        @if ($templateUrl)
            <div class="mb-3" style="width:220px;height:220px;background:repeating-conic-gradient(#e2e8f0 0% 25%, #fff 0% 50%) 50% / 20px 20px;border-radius:8px;overflow:hidden">
                <img src="{{ $templateUrl }}" style="width:100%;height:100%;object-fit:contain">
            </div>
            <form action="{{ route('admin.image-template-settings.destroy') }}" method="POST" onsubmit="return confirm('Remove the image template? New uploads will no longer get a logo/border overlay.');">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-sm btn-outline-danger mb-3"><i class="bi bi-trash"></i> Remove Template</button>
            </form>
        @else
            <p class="small text-muted-slate">No template configured yet — photos are saved as uploaded, with no overlay.</p>
        @endif
    </div>

    <div class="table-admin p-4 mb-3">
        <h6 class="mb-3">Replace Template</h6>
        <form action="{{ route('admin.image-template-settings.update') }}" method="POST" enctype="multipart/form-data">
            @csrf
            @method('PUT')
            <label class="form-label small">Transparent PNG</label>
            <input type="file" name="template" accept="image/png" class="form-control form-control-sm" required>
            <div class="form-text">PNG only, 5MB max. Design it square — it's stretched to exactly match each cropped photo.</div>
            @error('template')
                <div class="text-danger small mt-1">{{ $message }}</div>
            @enderror
            <button type="submit" class="btn btn-accent mt-3">Save Template</button>
        </form>
    </div>
</x-admin-layout>
