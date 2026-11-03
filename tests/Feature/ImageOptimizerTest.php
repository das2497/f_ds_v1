<?php

namespace Tests\Feature;

use App\Services\Images\ImageOptimizer;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;
use Tests\TestCase;

class ImageOptimizerTest extends TestCase
{
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
}
