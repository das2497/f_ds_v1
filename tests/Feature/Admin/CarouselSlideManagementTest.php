<?php

namespace Tests\Feature\Admin;

use App\Models\AppSetting;
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
            'media_width' => 400,
            'media_height' => 250,
            'cta_link' => '/products?cat=kitchen',
        ]);

        $response->assertRedirect();

        $slide = CarouselSlide::firstWhere('cta_link', '/products?cat=kitchen');
        $this->assertNotNull($slide);
        $this->assertSame('#F5F0E6', $slide->background_color);
        $this->assertSame(400, $slide->media_width);
        $this->assertSame(250, $slide->media_height);
        Storage::disk('public')->assertExists($slide->media_path);
    }

    public function test_an_admin_can_set_a_global_carousel_width_and_height(): void
    {
        $response = $this->actingAs($this->admin())
            ->put(route('admin.carousel.settings.update'), ['carousel_width' => 240, 'carousel_height' => 260]);

        $response->assertRedirect();
        $this->assertSame(240, AppSetting::getInt('carousel.width'));
        $this->assertSame(260, AppSetting::getInt('carousel.height'));
    }

    public function test_clearing_the_size_fields_reverts_to_automatic_sizing(): void
    {
        AppSetting::set('carousel.width', 240);
        AppSetting::set('carousel.height', 260);

        $response = $this->actingAs($this->admin())
            ->put(route('admin.carousel.settings.update'), ['carousel_width' => '', 'carousel_height' => '']);

        $response->assertRedirect();
        $this->assertNull(AppSetting::getInt('carousel.width'));
        $this->assertNull(AppSetting::getInt('carousel.height'));
    }

    public function test_the_global_carousel_width_overrides_each_slides_own_width_on_the_homepage(): void
    {
        AppSetting::set('carousel.width', 240);
        CarouselSlide::factory()->create(['media_width' => 900, 'is_active' => true]);

        $response = $this->get(route('home'));

        $response->assertSee('width: 240px', false);
        $response->assertDontSee('width: 900px', false);
    }

    public function test_an_admin_can_apply_a_width_and_height_to_every_slide_at_once(): void
    {
        CarouselSlide::factory()->count(3)->create(['media_width' => 100, 'media_height' => 100]);

        $response = $this->actingAs($this->admin())->put(route('admin.carousel.bulk-dimensions.update'), [
            'bulk_media_width' => 350,
            'bulk_media_height' => 280,
        ]);

        $response->assertRedirect();
        $this->assertSame(0, CarouselSlide::where('media_width', '!=', 350)
            ->orWhere('media_height', '!=', 280)
            ->count());
    }
}
