<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;

class RobotsController extends Controller
{
    /**
     * Private and faceted URLs are kept out of the crawl budget, and the
     * sitemap line is built from the running app URL so staging and
     * production each advertise their own.
     */
    public function __invoke(): Response
    {
        $disallowed = [
            '/admin',
            '/cart',
            '/checkout',
            '/order-success',
            '/account',
            '/profile',
            '/wishlist',
            '/dashboard',
            '/login',
            '/register',
            '/forgot-password',
            '/reset-password',
            '/confirm-password',
            '/verify-email',
            '/theme-check',
            '/*?*sort=',
            '/*?*page=',
            '/*?*min_price=',
            '/*?*max_price=',
            '/*?*in_stock=',
            '/*?*on_sale=',
        ];

        $lines = ['User-agent: *'];

        // Keep every crawler off a non-production deployment entirely.
        if (! app()->isProduction()) {
            $lines[] = 'Disallow: /';

            return $this->plainText($lines);
        }

        $lines[] = 'Allow: /';
        $lines[] = '';

        foreach ($disallowed as $path) {
            $lines[] = "Disallow: {$path}";
        }

        $lines[] = '';
        $lines[] = 'Sitemap: '.route('sitemap');

        return $this->plainText($lines);
    }

    /**
     * @param  list<string>  $lines
     */
    private function plainText(array $lines): Response
    {
        return response(implode("\n", $lines)."\n")
            ->header('Content-Type', 'text/plain; charset=UTF-8');
    }
}
