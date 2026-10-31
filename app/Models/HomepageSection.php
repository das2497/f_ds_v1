<?php

namespace App\Models;

use Database\Factories\HomepageSectionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['title', 'link_url', 'sort_order', 'is_active'])]
class HomepageSection extends Model
{
    /** @use HasFactory<HomepageSectionFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<HomepageSectionItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(HomepageSectionItem::class)->orderBy('sort_order');
    }
}
