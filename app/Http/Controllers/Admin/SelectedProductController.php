<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\VendorProductCandidate;
use Illuminate\View\View;

class SelectedProductController extends Controller
{
    public function index(): View
    {
        $candidates = VendorProductCandidate::whereIn('status', ['selected', 'listed'])
            ->latest('selected_at')
            ->get();

        return view('admin.products-selected', compact('candidates'));
    }
}
