<?php

namespace Tests\Feature\Admin;

use App\Models\CarouselSlide;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CarouselSlideManagementTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->seed(RoleSeeder::class);

        $user = User::factory()->create();
        $user->assignRole('admin');

        return $user;
    }

    public function test_an_admin_can_create_a_slide_with_a_background_color_and_image(): void
    {
        Storage::fake('public');

        $response = $this->actingAs($this->admin())->post(route('admin.carousel.store'), [
            'media_type' => 'image',
            'media' => UploadedFile::fake()->image('product.png'),
            'background_color' => '#F5F0E6',
            'headline' => 'Kitchen Essentials',
        ]);

        $response->assertRedirect();

        $slide = CarouselSlide::firstWhere('headline', 'Kitchen Essentials');
        $this->assertNotNull($slide);
        $this->assertSame('#F5F0E6', $slide->background_color);
        Storage::disk('public')->assertExists($slide->media_path);
    }
}
