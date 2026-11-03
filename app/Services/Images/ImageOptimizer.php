<?php

namespace App\Services\Images;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Encoders\WebpEncoder;
use Intervention\Image\ImageManager;

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
     */
    public function store(UploadedFile $file, string $directory, string $disk = 'public'): string
    {
        $image = (new ImageManager(new Driver))
            ->decodePath($file->getRealPath())
            ->scaleDown(self::MAX_DIMENSION, self::MAX_DIMENSION);

        $path = trim($directory, '/').'/'.Str::random(40).'.webp';

        Storage::disk($disk)->put(
            $path,
            (string) $image->encode(new WebpEncoder(quality: self::QUALITY))
        );

        return $path;
    }
}
