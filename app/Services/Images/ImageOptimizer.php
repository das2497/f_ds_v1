<?php

namespace App\Services\Images;

use App\Http\Controllers\Admin\ImageTemplateSettingsController;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Encoders\WebpEncoder;
use Intervention\Image\ImageManager;
use Intervention\Image\Interfaces\ImageInterface;

/**
 * Re-encodes admin-uploaded storefront photos as WebP and caps their longest
 * side, so a 5MB phone photo doesn't ship to every shopper untouched.
 */
class ImageOptimizer
{
    private const MAX_DIMENSION = 1920;

    private const QUALITY = 82;

    /**
     * Store an uploaded image, resized and WebP-encoded, and return its
     * disk-relative path — the same shape UploadedFile::store() returns.
     *
     * $applyTemplate stamps the admin-configured logo/border overlay (see
     * ImageTemplateSettingsController) onto the result — only pass true for
     * a product/variant photo the admin square-cropped in the browser (see
     * admin-image-crop.js); it stays false for every other caller
     * (homepage sections, carousel slides, …), whose images aren't
     * square-cropped and would just get the overlay stretched over them.
     */
    public function store(UploadedFile $file, string $directory, string $disk = 'public', bool $applyTemplate = false): string
    {
        $image = (new ImageManager(new Driver))
            ->decodePath($file->getRealPath())
            // Rotates pixels to match the EXIF orientation tag *before* that
            // tag (and the rest of the file's metadata) disappears on
            // re-encode below — otherwise a phone photo shot in portrait
            // would re-save sideways once its orientation hint is gone.
            ->orient()
            ->scaleDown(self::MAX_DIMENSION, self::MAX_DIMENSION);

        if ($applyTemplate) {
            $this->stampTemplate($image);
        }

        $path = trim($directory, '/').'/'.Str::random(40).'.webp';

        Storage::disk($disk)->put(
            $path,
            (string) $image->encode(new WebpEncoder(quality: self::QUALITY))
        );

        return $path;
    }

    /**
     * Stamps the admin-configured border/logo overlay (see
     * ImageTemplateSettingsController) onto $image in place, stretched to
     * its exact size. A no-op until an admin has actually uploaded one.
     */
    private function stampTemplate(ImageInterface $image): void
    {
        $templatePath = ImageTemplateSettingsController::path();

        if (blank($templatePath) || ! Storage::disk('public')->exists($templatePath)) {
            return;
        }

        $template = (new ImageManager(new Driver))
            ->decodePath(Storage::disk('public')->path($templatePath))
            ->resize($image->width(), $image->height());

        $image->insert($template, 0, 0);
    }

    /**
     * Downloads a vendor-hosted image (AliExpress CDN, etc.) and re-encodes
     * it the same way as an upload, so the product no longer depends on
     * that external host staying up. Returns null on any failure (missing
     * image, unreachable host, not actually an image) instead of throwing
     * — one bad vendor photo shouldn't fail the whole import.
     */
    public function storeFromUrl(?string $url, string $directory, string $disk = 'public'): ?string
    {
        if (blank($url)) {
            return null;
        }

        try {
            $response = Http::timeout(15)->get($url);

            if (! $response->successful()) {
                return null;
            }

            $image = (new ImageManager(new Driver))
                ->decodeBinary($response->body())
                ->scaleDown(self::MAX_DIMENSION, self::MAX_DIMENSION);

            $path = trim($directory, '/').'/'.Str::random(40).'.webp';

            Storage::disk($disk)->put(
                $path,
                (string) $image->encode(new WebpEncoder(quality: self::QUALITY))
            );

            return $path;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Same as storeFromUrl(), but returns the public URL to the stored
     * copy (or null) instead of the disk-relative path — the shape every
     * caller that downloads a vendor image actually wants.
     */
    public function storeFromUrlAsPublicUrl(?string $url, string $directory, string $disk = 'public'): ?string
    {
        $path = $this->storeFromUrl($url, $directory, $disk);

        return $path ? Storage::disk($disk)->url($path) : null;
    }
}
