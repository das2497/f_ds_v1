<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use App\Models\CarouselSlide;
use App\Models\HomepageSection;
use App\Models\Product;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function index(): View
    {
        $slides = CarouselSlide::where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        $carouselWidth = AppSetting::getInt('carousel.width');
        $carouselHeight = AppSetting::getInt('carousel.height');

        $featuredProducts = Product::with('variants')
            ->where('is_active', true)
            ->latest()
            ->limit(7)
            ->get();

        $homepageSections = HomepageSection::where('is_active', true)
            ->whereHas('items', fn ($q) => $q->where('is_active', true))
            ->with(['items' => fn ($q) => $q->where('is_active', true)->orderBy('sort_order')])
            ->orderBy('sort_order')
            ->get();

        return view('store.home', compact('slides', 'carouselWidth', 'carouselHeight', 'featuredProducts', 'homepageSections'));
    }
}
