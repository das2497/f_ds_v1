<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Storefront identity
    |--------------------------------------------------------------------------
    |
    | Used for page titles, meta descriptions, Open Graph tags and the
    | Organization / WebSite structured data emitted by the store layout.
    |
    */

    'name' => env('STORE_NAME', 'Shobley'),

    'tagline' => env('STORE_TAGLINE', 'Quality Products for a Smarter You'),

    'description' => env('STORE_DESCRIPTION', 'Shop quality home, electronics and everyday essentials at Shobley. Secure Stripe checkout, free shipping over $50 and fast, reliable delivery across the USA.'),

    'locale' => env('STORE_LOCALE', 'en_US'),

    'currency' => env('STORE_CURRENCY', 'USD'),

    'country' => env('STORE_COUNTRY', 'US'),

    'free_shipping_threshold' => (int) env('STORE_FREE_SHIPPING_THRESHOLD', 50),

    'return_window_days' => (int) env('STORE_RETURN_WINDOW_DAYS', 30),

    /*
    |--------------------------------------------------------------------------
    | Contact details
    |--------------------------------------------------------------------------
    */

    'contact' => [
        'email' => env('STORE_CONTACT_EMAIL', 'support@shobley.com'),
        'phone' => env('STORE_CONTACT_PHONE'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Social profiles
    |--------------------------------------------------------------------------
    |
    | Only profiles with a configured URL are rendered in the footer and
    | listed as Organization `sameAs` entries, so unset networks never
    | become dead "#" links.
    |
    */

    'social' => array_filter([
        'facebook' => env('STORE_SOCIAL_FACEBOOK'),
        'instagram' => env('STORE_SOCIAL_INSTAGRAM'),
        'twitter' => env('STORE_SOCIAL_TWITTER'),
    ]),

    'twitter_handle' => env('STORE_TWITTER_HANDLE'),

];
