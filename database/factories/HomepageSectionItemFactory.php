<?php

namespace Database\Factories;

use App\Models\HomepageSection;
use App\Models\HomepageSectionItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HomepageSectionItem>
 */
class HomepageSectionItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'homepage_section_id' => HomepageSection::factory(),
            'label' => $this->faker->words(2, true),
            'link_url' => '/products',
            'sort_order' => $this->faker->unique()->numberBetween(0, 1000),
            'is_active' => true,
        ];
    }
}
