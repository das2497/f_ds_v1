<?php

namespace App\Models;

use Database\Factories\CarouselSlideFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'media_type', 'media_path', 'background_color', 'media_width', 'media_height', 'eyebrow_text', 'headline',
    'subtext', 'cta_label', 'cta_link', 'sort_order', 'is_active',
])]
class CarouselSlide extends Model
{
    /** @use HasFactory<CarouselSlideFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
}
