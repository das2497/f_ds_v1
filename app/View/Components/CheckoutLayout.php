<?php

namespace App\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

class CheckoutLayout extends Component
{
    /**
     * @param  ?string  $heading  Renders the page's <h1> for pages whose design
     *                            carries no visible page title. Pages that already
     *                            have their own <h1> leave this null.
     */
    public function __construct(
        public ?string $title = null,
        public string $step = 'shipping',
        public ?string $description = null,
        public ?string $heading = null,
    ) {}

    /**
     * Get the view / contents that represents the component.
     */
    public function render(): View
    {
        return view('layouts.checkout');
    }
}
