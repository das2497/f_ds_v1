<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Tests\TestCase;

class GoogleAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // The controller now requires Google credentials to be configured
        // (admin gateway settings, falling back to .env) before it will
        // proceed — these tests exercise the already-configured path.
        config([
            'services.google.client_id' => 'test-client-id',
            'services.google.client_secret' => 'test-client-secret',
        ]);
    }

    public function test_redirect_sends_the_user_to_google(): void
    {
        $response = $this->get(route('auth.google'));

        $response->assertStatus(302);
        $response->assertRedirect();
        $this->assertNotSame(route('login'), $response->headers->get('Location'));
    }

    public function test_redirect_bounces_back_to_login_when_google_is_not_configured(): void
    {
        config(['services.google.client_id' => null, 'services.google.client_secret' => null]);

        $response = $this->get(route('auth.google'));

        $response->assertRedirect(route('login'));
        $this->assertSame('Google sign-in is not configured yet.', session('error'));
    }

    public function test_new_google_user_is_registered_and_logged_in(): void
    {
        $this->seed(RoleSeeder::class);

        Socialite::fake('google', SocialiteUser::fake([
            'id' => 'google-123',
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'avatar' => 'https://example.com/avatar.jpg',
        ]));

        $response = $this->get(route('auth.google.callback'));

        $this->assertAuthenticated();
        $response->assertRedirect(route('account.dashboard', absolute: false));

        $user = User::firstWhere('email', 'jane@example.com');
        $this->assertNotNull($user);
        $this->assertSame('google-123', $user->google_id);
        $this->assertTrue($user->hasRole('customer'));
    }

    public function test_existing_email_is_linked_to_the_google_account_instead_of_duplicated(): void
    {
        $this->seed(RoleSeeder::class);

        $existing = User::create([
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'password' => bcrypt('password'),
        ]);
        $existing->assignRole('customer');

        Socialite::fake('google', SocialiteUser::fake([
            'id' => 'google-123',
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
        ]));

        $this->get(route('auth.google.callback'));

        $this->assertAuthenticatedAs($existing->fresh());
        $this->assertSame(1, User::where('email', 'jane@example.com')->count());
        $this->assertSame('google-123', $existing->fresh()->google_id);
    }

    public function test_returning_google_user_is_logged_into_their_existing_account(): void
    {
        $this->seed(RoleSeeder::class);

        $existing = User::create([
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'password' => bcrypt('password'),
            'google_id' => 'google-123',
        ]);
        $existing->assignRole('customer');

        Socialite::fake('google', SocialiteUser::fake([
            'id' => 'google-123',
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
        ]));

        $this->get(route('auth.google.callback'));

        $this->assertAuthenticatedAs($existing->fresh());
    }
}
