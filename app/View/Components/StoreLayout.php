<?php

namespace App\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

class StoreLayout extends Component
{
    /**
     * @param  ?string  $description  Meta description; falls back to the store description.
     * @param  ?string  $canonical  Canonical URL; falls back to the current URL without query noise.
     * @param  ?string  $robots  Robots directive, e.g. "noindex, follow" for private pages.
     * @param  ?string  $image  Absolute URL for the Open Graph / Twitter card image.
     * @param  list<array<string, mixed>>  $schema  JSON-LD graphs to emit for this page.
     */
    public function __construct(
        public ?string $title = null,
        public ?string $description = null,
        public ?string $canonical = null,
        public ?string $robots = null,
        public ?string $image = null,
        public string $ogType = 'website',
        public array $schema = [],
    ) {}

    /**
     * Get the view / contents that represents the component.
     */
    public function render(): View
    {
        return view('layouts.store');
    }
}
