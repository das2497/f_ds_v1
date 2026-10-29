<?php

namespace Database\Seeders;

use App\Models\CarouselSlide;
use Illuminate\Database\Seeder;

class CarouselSlideSeeder extends Seeder
{
    /**
     * Mirrors the default slides shown in ui-prototype/index.html + admin/carousel.html.
     */
    public function run(): void
    {
        $slides = [
            [
                'media_type' => 'image',
                'eyebrow_text' => 'SHOP SMART • LIVE BETTER',
                'headline' => 'Quality Products for a Smarter You.',
                'subtext' => 'Top brands. Great value. Fast & reliable delivery across USA.',
                'cta_label' => 'Shop Now',
                'cta_link' => '/products',
                'sort_order' => 0,
                'is_active' => true,
            ],
            [
                'media_type' => 'video',
                'eyebrow_text' => 'NEW ARRIVALS',
                'headline' => 'Discover More. Shop Smarter.',
                'subtext' => 'Quality products, great value & a seamless online shopping experience.',
                'cta_label' => 'Shop Now',
                'cta_link' => '/products',
                'sort_order' => 1,
                'is_active' => true,
            ],
            [
                'media_type' => 'image',
                'eyebrow_text' => 'LIMITED TIME',
                'headline' => 'Holiday Deals Are Here',
                'subtext' => null,
                'cta_label' => 'Explore Deals',
                'cta_link' => '/products?deals=1',
                'sort_order' => 2,
                'is_active' => false,
            ],
        ];

        foreach ($slides as $slide) {
            CarouselSlide::updateOrCreate(
                ['headline' => $slide['headline']],
                $slide
            );
        }
    }
}
