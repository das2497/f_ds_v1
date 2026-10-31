<?php

namespace Database\Factories;

use App\Models\HomepageSection;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HomepageSection>
 */
class HomepageSectionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => $this->faker->words(3, true),
            'link_url' => '/products',
            'sort_order' => $this->faker->unique()->numberBetween(0, 1000),
            'is_active' => true,
        ];
    }
}
