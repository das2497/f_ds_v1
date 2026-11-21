<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Services\Images\ImageOptimizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Encoders\PngEncoder;
use Intervention\Image\ImageManager;
use Tests\TestCase;

class ImageOptimizerTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_encodes_the_stored_file_as_webp(): void
    {
        Storage::fake('public');

        $path = (new ImageOptimizer)->store(
            UploadedFile::fake()->image('photo.jpg', 800, 600),
            'products',
        );

        $this->assertStringEndsWith('.webp', $path);
        Storage::disk('public')->assertExists($path);

        $decoded = (new ImageManager(new Driver))->decode(Storage::disk('public')->get($path));
        $this->assertSame(800, $decoded->width());
        $this->assertSame(600, $decoded->height());
    }

    public function test_it_scales_down_oversized_images_without_upscaling_small_ones(): void
    {
        Storage::fake('public');
        $optimizer = new ImageOptimizer;

        $largePath = $optimizer->store(UploadedFile::fake()->image('large.jpg', 4000, 2000), 'products');
        $smallPath = $optimizer->store(UploadedFile::fake()->image('small.jpg', 400, 300), 'products');

        $large = (new ImageManager(new Driver))->decode(Storage::disk('public')->get($largePath));
        $small = (new ImageManager(new Driver))->decode(Storage::disk('public')->get($smallPath));

        $this->assertSame(1920, $large->width());
        $this->assertSame(960, $large->height());
        $this->assertSame(400, $small->width());
        $this->assertSame(300, $small->height());
    }

    public function test_it_leaves_photos_untouched_when_no_template_is_configured(): void
    {
        Storage::fake('public');

        $path = (new ImageOptimizer)->store(UploadedFile::fake()->image('photo.jpg', 400, 400), 'products', applyTemplate: true);

        Storage::disk('public')->assertExists($path);
    }

    public function test_it_does_not_apply_the_template_unless_asked_to(): void
    {
        // Homepage sections / carousel slides call store() without
        // applyTemplate — they're not square-cropped, so stamping the
        // overlay on them would just stretch it over the wrong shape.
        Storage::fake('public');

        $template = (new ImageManager(new Driver))->createImage(400, 400)->fill('#ff0000');
        Storage::disk('public')->put('branding/template.png', (string) $template->encode(new PngEncoder));
        AppSetting::set('branding.image_template_path', 'branding/template.png');

        $path = (new ImageOptimizer)->store(UploadedFile::fake()->image('photo.jpg', 400, 400), 'products');

        $decoded = (new ImageManager(new Driver))->decode(Storage::disk('public')->get($path));

        $pixel = sscanf($decoded->colorAt(200, 200)->toHex(true), '#%02x%02x%02x');
        $this->assertLessThan(200, $pixel[0], 'Red channel should not be saturated — the all-red template must not have been applied.');
    }

    public function test_it_stamps_the_configured_template_onto_every_stored_photo(): void
    {
        Storage::fake('public');

        $template = (new ImageManager(new Driver))->createImage(400, 400)->fill('#ff0000');
        Storage::disk('public')->put('branding/template.png', (string) $template->encode(new PngEncoder));
        AppSetting::set('branding.image_template_path', 'branding/template.png');

        $path = (new ImageOptimizer)->store(UploadedFile::fake()->image('photo.jpg', 400, 400), 'products', applyTemplate: true);

        $decoded = (new ImageManager(new Driver))->decode(Storage::disk('public')->get($path));

        // Within a few units, not exact — the final WebP re-encode is lossy.
        $this->assertHexColorNear('#ff0000', $decoded->colorAt(200, 200)->toHex(true));
    }

    public function test_it_stretches_the_template_to_match_the_photos_own_size(): void
    {
        Storage::fake('public');

        // A deliberately non-square, differently-sized template — if
        // insert() ever stopped resizing to match, this would either throw
        // or only cover part of the photo instead of the whole thing.
        $template = (new ImageManager(new Driver))->createImage(100, 50)->fill('#00ff00');
        Storage::disk('public')->put('branding/template.png', (string) $template->encode(new PngEncoder));
        AppSetting::set('branding.image_template_path', 'branding/template.png');

        $path = (new ImageOptimizer)->store(UploadedFile::fake()->image('photo.jpg', 600, 300), 'products', applyTemplate: true);

        $decoded = (new ImageManager(new Driver))->decode(Storage::disk('public')->get($path));

        $this->assertHexColorNear('#00ff00', $decoded->colorAt(10, 10)->toHex(true));
        $this->assertHexColorNear('#00ff00', $decoded->colorAt(590, 290)->toHex(true));
    }

    /**
     * The photo is re-encoded as lossy WebP after the template is stamped
     * on, so a composited pixel's channels can drift by a few units from
     * the template's exact color — this allows that drift without letting
     * through a real compositing bug (wrong color, wrong position, or no
     * overlay at all), which would be off by much more than a few units.
     */
    private function assertHexColorNear(string $expectedHex, string $actualHex, int $delta = 10): void
    {
        $expected = sscanf($expectedHex, '#%02x%02x%02x');
        $actual = sscanf($actualHex, '#%02x%02x%02x');

        foreach ([0, 1, 2] as $channel) {
            $this->assertEqualsWithDelta($expected[$channel], $actual[$channel], $delta, "Channel {$channel} of {$actualHex} too far from {$expectedHex}");
        }
    }
}
