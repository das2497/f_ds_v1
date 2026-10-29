<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Google OAuth credentials come from the admin gateway settings page
 * (encrypted app_settings rows) first, falling back to .env — same pattern
 * already used for CJ and Printful.
 */
class GoogleAuthCredentialsTest extends TestCase
{
    use RefreshDatabase;

    public function test_visiting_the_google_redirect_without_credentials_configured_bounces_back_to_login_with_an_error(): void
    {
        $response = $this->get(route('auth.google'));

        $response->assertRedirect(route('login'));
        $this->assertSame('Google sign-in is not configured yet.', session('error'));
    }

    public function test_visiting_the_google_redirect_with_admin_saved_credentials_builds_the_auth_url_with_them(): void
    {
        AppSetting::set('google.client_id', 'admin-saved-client-id');
        AppSetting::set('google.client_secret', 'admin-saved-client-secret');

        $response = $this->get(route('auth.google'));

        $response->assertRedirect();
        $this->assertStringContainsString('client_id=admin-saved-client-id', $response->headers->get('Location'));
    }
}
