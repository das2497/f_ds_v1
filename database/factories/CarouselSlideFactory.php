<?php

namespace Database\Factories;

use App\Models\CarouselSlide;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CarouselSlide>
 */
class CarouselSlideFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'media_type' => 'image',
            'background_color' => $this->faker->safeHexColor(),
            'media_width' => 300,
            'media_height' => 300,
            'sort_order' => $this->faker->unique()->numberBetween(0, 1000),
            'is_active' => true,
        ];
    }
}
