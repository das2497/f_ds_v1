<?php

namespace App\Http\Controllers;

use App\Models\CarouselSlide;
use App\Models\Product;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function index(): View
    {
        $slides = CarouselSlide::where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        $featuredProducts = Product::with('variants')
            ->where('is_active', true)
            ->latest()
            ->limit(8)
            ->get();

        return view('store.home', compact('slides', 'featuredProducts'));
    }
}
