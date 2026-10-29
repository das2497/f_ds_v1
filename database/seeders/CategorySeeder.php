<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CategorySeeder extends Seeder
{
    /**
     * Mirrors SUBCATEGORY_MAP in ui-prototype/assets/js/main.js — keep in sync.
     */
    private const CATEGORIES = [
        'Electronics' => [
            'icon' => 'bi-cpu',
            'subcategories' => ['Headphones & Audio', 'Smart Watches', 'Phone Accessories', 'Computer Accessories', 'Cameras'],
        ],
        'Home & Living' => [
            'icon' => 'bi-house-door',
            'subcategories' => ['Kitchen & Dining', 'Furniture', 'Bedding', 'Decor', 'Storage & Organization'],
        ],
        'Fashion' => [
            'icon' => 'bi-bag',
            'subcategories' => ["Men's Clothing", "Women's Clothing", 'Shoes', 'Bags & Accessories', 'Jewelry'],
        ],
        'Beauty & Health' => [
            'icon' => 'bi-flower1',
            'subcategories' => ['Skincare', 'Haircare', 'Personal Care', 'Wellness'],
        ],
        'Sports & Outdoors' => [
            'icon' => 'bi-bicycle',
            'subcategories' => ['Fitness Equipment', 'Outdoor Gear', 'Activewear', 'Cycling'],
        ],
        'Toys & Games' => [
            'icon' => 'bi-controller',
            'subcategories' => ['Action Figures', 'Board Games', 'Educational Toys', 'Outdoor Play'],
        ],
        'Automotive' => [
            'icon' => 'bi-car-front',
            'subcategories' => ['Car Accessories', 'Car Electronics', 'Car Care'],
        ],
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $sortOrder = 0;
        foreach (self::CATEGORIES as $name => $data) {
            $category = Category::updateOrCreate(
                ['slug' => Str::slug($name)],
                [
                    'name' => $name,
                    'icon' => $data['icon'],
                    'sort_order' => $sortOrder,
                    'is_active' => true,
                ]
            );

            foreach ($data['subcategories'] as $subSortOrder => $subName) {
                $category->subcategories()->updateOrCreate(
                    ['slug' => Str::slug($subName)],
                    [
                        'name' => $subName,
                        'sort_order' => $subSortOrder,
                        'is_active' => true,
                    ]
                );
            }

            $sortOrder++;
        }
    }
}
