<x-admin-layout title="Category Management">
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <div class="d-flex justify-content-between align-items-center mb-3">
        <div class="small text-muted-slate">These categories power the Shop Category/Subcategory fields on the Customize &amp; List page and the storefront's category navigation.</div>
        <button class="btn btn-accent btn-sm" data-bs-toggle="modal" data-bs-target="#categoryModal"><i class="bi bi-plus-lg"></i> Add Category</button>
    </div>

    <div class="accordion" id="categoryAccordion">
        @foreach ($categories as $category)
            <div class="accordion-item table-admin mb-3" style="border-radius:var(--radius-card);overflow:hidden">
                <h2 class="accordion-header d-flex align-items-center justify-content-between px-3 py-2">
                    <button class="btn btn-link text-decoration-none text-dark fw-semibold p-0 flex-grow-1 text-start" type="button" data-bs-toggle="collapse" data-bs-target="#cat{{ $category->id }}">
                        <i class="bi {{ $category->icon }} text-accent me-2"></i>{{ $category->name }}
                        <span class="small text-muted-slate fw-normal">&middot; {{ $category->subcategories_count }} subcategories</span>
                    </button>
                    <div class="d-flex gap-2">
                        <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="modal" data-bs-target="#editCategoryModal{{ $category->id }}">Edit</button>
                        <form action="{{ route('admin.categories.destroy', $category) }}" method="POST" onsubmit="return confirm('Delete this category and all its subcategories?');">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                        </form>
                    </div>
                </h2>
                <div id="cat{{ $category->id }}" class="accordion-collapse collapse" data-bs-parent="#categoryAccordion">
                    <div class="accordion-body pt-0">
                        <table class="table table-admin align-middle mb-2">
                            <thead><tr><th>Subcategory</th><th></th></tr></thead>
                            <tbody>
                                @forelse ($category->subcategories as $subcategory)
                                    <tr>
                                        <td>{{ $subcategory->name }}</td>
                                        <td>
                                            <a href="#" class="small text-accent text-decoration-none me-2" data-bs-toggle="modal" data-bs-target="#editSubcategoryModal{{ $subcategory->id }}">Edit</a>
                                            <form action="{{ route('admin.subcategories.destroy', $subcategory) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete this subcategory?');">
                                                @csrf @method('DELETE')
                                                <button type="submit" class="small text-danger text-decoration-none border-0 bg-transparent p-0">Delete</button>
                                            </form>
                                        </td>
                                    </tr>

                                    <div class="modal fade" id="editSubcategoryModal{{ $subcategory->id }}" tabindex="-1">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <form action="{{ route('admin.subcategories.update', $subcategory) }}" method="POST">
                                                    @csrf @method('PUT')
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">Edit Subcategory</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <label class="form-label small">Subcategory Name</label>
                                                        <input type="text" name="name" class="form-control" value="{{ $subcategory->name }}" required>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                                                        <button type="submit" class="btn btn-accent">Save Subcategory</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                @empty
                                    <tr><td colspan="2" class="text-muted-slate small">No subcategories yet.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                        <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="modal" data-bs-target="#subcategoryModal{{ $category->id }}"><i class="bi bi-plus-lg"></i> Add Subcategory</button>
                    </div>
                </div>
            </div>

            <div class="modal fade" id="editCategoryModal{{ $category->id }}" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <form action="{{ route('admin.categories.update', $category) }}" method="POST">
                            @csrf @method('PUT')
                            <div class="modal-header">
                                <h5 class="modal-title">Edit Category</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <label class="form-label small">Category Name</label>
                                <input type="text" name="name" class="form-control mb-3" value="{{ $category->name }}" required>
                                <label class="form-label small">Icon</label>
                                <select name="icon" class="form-select">
                                    @foreach (['bi-box-seam', 'bi-cpu', 'bi-house-door', 'bi-bag', 'bi-flower1', 'bi-bicycle', 'bi-controller', 'bi-car-front'] as $icon)
                                        <option value="{{ $icon }}" @selected($category->icon === $icon)>{{ $icon }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" class="btn btn-accent">Save Category</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="modal fade" id="subcategoryModal{{ $category->id }}" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <form action="{{ route('admin.subcategories.store') }}" method="POST">
                            @csrf
                            <input type="hidden" name="category_id" value="{{ $category->id }}">
                            <div class="modal-header">
                                <h5 class="modal-title">Add Subcategory to {{ $category->name }}</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <label class="form-label small">Subcategory Name</label>
                                <input type="text" name="name" class="form-control" placeholder="e.g. Smart Home Devices" required>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" class="btn btn-accent">Save Subcategory</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <!-- Add Category Modal -->
    <div class="modal fade" id="categoryModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form action="{{ route('admin.categories.store') }}" method="POST">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title">Add Category</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <label class="form-label small">Category Name</label>
                        <input type="text" name="name" class="form-control mb-3" placeholder="e.g. Pet Supplies" required>
                        <label class="form-label small">Icon</label>
                        <select name="icon" class="form-select">
                            <option value="bi-box-seam">bi-box-seam (default)</option>
                            <option value="bi-cpu">bi-cpu</option>
                            <option value="bi-house-door">bi-house-door</option>
                            <option value="bi-bag">bi-bag</option>
                            <option value="bi-flower1">bi-flower1</option>
                            <option value="bi-bicycle">bi-bicycle</option>
                            <option value="bi-controller">bi-controller</option>
                            <option value="bi-car-front">bi-car-front</option>
                        </select>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-accent">Save Category</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-admin-layout>
