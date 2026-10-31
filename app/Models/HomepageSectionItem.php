<?php

namespace App\Models;

use Database\Factories\HomepageSectionItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['homepage_section_id', 'label', 'image_path', 'link_url', 'sort_order', 'is_active'])]
class HomepageSectionItem extends Model
{
    /** @use HasFactory<HomepageSectionItemFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<HomepageSection, $this>
     */
    public function section(): BelongsTo
    {
        return $this->belongsTo(HomepageSection::class, 'homepage_section_id');
    }
}
