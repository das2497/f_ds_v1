<?php

namespace Tests\Feature\Admin;

use App\Models\HomepageSection;
use App\Models\HomepageSectionItem;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class HomepageSectionManagementTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->seed(RoleSeeder::class);

        $user = User::factory()->create();
        $user->assignRole('admin');

        return $user;
    }

    public function test_an_admin_can_create_a_homepage_section(): void
    {
        $response = $this->actingAs($this->admin())->post(route('admin.homepage-sections.store'), [
            'title' => 'Plug in with our electronics',
            'link_url' => '/products?cat=electronics',
            'is_active' => '1',
        ]);

        $response->assertRedirect();

        $section = HomepageSection::firstWhere('title', 'Plug in with our electronics');
        $this->assertNotNull($section);
        $this->assertSame('/products?cat=electronics', $section->link_url);
        $this->assertTrue($section->is_active);
    }

    public function test_an_admin_can_add_an_image_card_to_a_section(): void
    {
        Storage::fake('public');
        $section = HomepageSection::factory()->create();

        $response = $this->actingAs($this->admin())->post(route('admin.homepage-sections.items.store', $section), [
            'label' => 'Headphones',
            'image' => UploadedFile::fake()->image('headphones.png'),
            'link_url' => '/products?cat=headphones',
        ]);

        $response->assertRedirect();

        $item = HomepageSectionItem::firstWhere('label', 'Headphones');
        $this->assertNotNull($item);
        $this->assertSame($section->id, $item->homepage_section_id);
        Storage::disk('public')->assertExists($item->image_path);
    }

    public function test_deleting_a_section_removes_its_cards_and_images(): void
    {
        Storage::fake('public');
        $section = HomepageSection::factory()->create();
        $item = HomepageSectionItem::factory()->for($section, 'section')->create([
            'image_path' => 'homepage-sections/example.png',
        ]);
        Storage::disk('public')->put($item->image_path, 'fake');

        $response = $this->actingAs($this->admin())->delete(route('admin.homepage-sections.destroy', $section));

        $response->assertRedirect();
        $this->assertDatabaseMissing('homepage_sections', ['id' => $section->id]);
        $this->assertDatabaseMissing('homepage_section_items', ['id' => $item->id]);
        Storage::disk('public')->assertMissing($item->image_path);
    }

    public function test_the_homepage_shows_active_sections_with_their_active_cards(): void
    {
        $section = HomepageSection::factory()->create(['title' => 'Plug in with our electronics', 'is_active' => true]);
        HomepageSectionItem::factory()->for($section, 'section')->create(['label' => 'Headphones', 'is_active' => true]);
        HomepageSectionItem::factory()->for($section, 'section')->create(['label' => 'Hidden Tracker', 'is_active' => false]);

        $hiddenSection = HomepageSection::factory()->create(['title' => 'Hidden Section', 'is_active' => false]);
        HomepageSectionItem::factory()->for($hiddenSection, 'section')->create();

        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertSee('Plug in with our electronics');
        $response->assertSee('Headphones');
        $response->assertDontSee('Hidden Tracker');
        $response->assertDontSee('Hidden Section');
    }

    public function test_a_section_with_no_active_cards_is_hidden_from_the_homepage(): void
    {
        $section = HomepageSection::factory()->create(['title' => 'Empty Section', 'is_active' => true]);
        HomepageSectionItem::factory()->for($section, 'section')->create(['is_active' => false]);

        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertDontSee('Empty Section');
    }
}
