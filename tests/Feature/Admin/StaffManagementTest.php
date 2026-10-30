<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class StaffManagementTest extends TestCase
{
    use RefreshDatabase;

    private function superAdmin(): User
    {
        $this->seed(RoleSeeder::class);

        $user = User::factory()->create();
        $user->assignRole('super-admin');

        return $user;
    }

    public function test_the_page_lists_admin_and_super_admin_staff(): void
    {
        $actor = $this->superAdmin();

        $admin = User::factory()->create(['name' => 'Alice Admin']);
        $admin->assignRole('admin');

        $customer = User::factory()->create(['name' => 'Cindy Customer']);
        $customer->assignRole('customer');

        $response = $this->actingAs($actor)->get(route('admin.staff'));

        $response->assertOk();
        $response->assertSee('Alice Admin');
        $response->assertSee($actor->name);
        $response->assertDontSee('Cindy Customer');
    }

    public function test_a_super_admin_can_add_a_staff_member(): void
    {
        $actor = $this->superAdmin();

        $response = $this->actingAs($actor)->post(route('admin.staff.store'), [
            'name' => 'New Admin',
            'email' => 'new-admin@example.com',
            'role' => 'admin',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('status');

        $user = User::where('email', 'new-admin@example.com')->first();

        $this->assertNotNull($user);
        $this->assertTrue($user->hasRole('admin'));
        $this->assertFalse($user->hasRole('super-admin'));

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'Staff Added',
        ]);
    }

    public function test_adding_a_staff_member_requires_a_unique_email_and_valid_role(): void
    {
        $actor = $this->superAdmin();

        User::factory()->create(['email' => 'taken@example.com']);

        $this->actingAs($actor)->post(route('admin.staff.store'), [
            'name' => 'Dup Person',
            'email' => 'taken@example.com',
            'role' => 'admin',
        ])->assertSessionHasErrors('email');

        $this->actingAs($actor)->post(route('admin.staff.store'), [
            'name' => 'Bad Role Person',
            'email' => 'bad-role@example.com',
            'role' => 'customer',
        ])->assertSessionHasErrors('role');
    }

    public function test_a_super_admin_can_revoke_another_staff_members_access(): void
    {
        $actor = $this->superAdmin();

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $response = $this->actingAs($actor)->delete(route('admin.staff.destroy', $admin));

        $response->assertRedirect();
        $response->assertSessionHas('status');

        $this->assertTrue($admin->fresh()->hasRole('customer'));
        $this->assertFalse($admin->fresh()->hasRole('admin'));

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'Staff Removed',
        ]);
    }

    public function test_a_staff_member_cannot_remove_their_own_access(): void
    {
        $actor = $this->superAdmin();

        $response = $this->actingAs($actor)->delete(route('admin.staff.destroy', $actor));

        $response->assertStatus(422);
        $this->assertTrue($actor->fresh()->hasRole('super-admin'));
    }

    public function test_a_super_admin_can_edit_a_staff_members_name_email_and_role(): void
    {
        $actor = $this->superAdmin();

        $admin = User::factory()->create(['name' => 'Old Name', 'email' => 'old@example.com']);
        $admin->assignRole('admin');

        $response = $this->actingAs($actor)->put(route('admin.staff.update', $admin), [
            'name' => 'New Name',
            'email' => 'new@example.com',
            'role' => 'super-admin',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('status');

        $admin->refresh();
        $this->assertSame('New Name', $admin->name);
        $this->assertSame('new@example.com', $admin->email);
        $this->assertTrue($admin->hasRole('super-admin'));
        $this->assertFalse($admin->hasRole('admin'));

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'Staff Updated',
        ]);
    }

    public function test_a_super_admin_can_change_a_staff_members_password(): void
    {
        $actor = $this->superAdmin();

        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $originalHash = $admin->password;

        $this->actingAs($actor)->put(route('admin.staff.update', $admin), [
            'name' => $admin->name,
            'email' => $admin->email,
            'role' => 'admin',
            'password' => 'new-strong-password',
            'password_confirmation' => 'new-strong-password',
        ])->assertRedirect();

        $admin->refresh();
        $this->assertNotSame($originalHash, $admin->password);
        $this->assertTrue(Hash::check('new-strong-password', $admin->password));
    }

    public function test_editing_a_staff_member_requires_a_unique_email_and_matching_password_confirmation(): void
    {
        $actor = $this->superAdmin();

        $other = User::factory()->create(['email' => 'taken@example.com']);
        $other->assignRole('admin');

        $admin = User::factory()->create(['email' => 'mine@example.com']);
        $admin->assignRole('admin');

        $this->actingAs($actor)->put(route('admin.staff.update', $admin), [
            'name' => $admin->name,
            'email' => 'taken@example.com',
            'role' => 'admin',
        ])->assertSessionHasErrors('email');

        $this->actingAs($actor)->put(route('admin.staff.update', $admin), [
            'name' => $admin->name,
            'email' => $admin->email,
            'role' => 'admin',
            'password' => 'mismatched-one',
            'password_confirmation' => 'mismatched-two',
        ])->assertSessionHasErrors('password');

        // Keeping the same email on the same record is allowed.
        $this->actingAs($actor)->put(route('admin.staff.update', $admin), [
            'name' => 'Still Mine',
            'email' => 'mine@example.com',
            'role' => 'admin',
        ])->assertSessionDoesntHaveErrors();
    }

    public function test_a_super_admin_cannot_demote_their_own_role_via_edit(): void
    {
        $actor = $this->superAdmin();

        $response = $this->actingAs($actor)->put(route('admin.staff.update', $actor), [
            'name' => $actor->name,
            'email' => $actor->email,
            'role' => 'admin',
        ]);

        $response->assertStatus(422);
        $this->assertTrue($actor->fresh()->hasRole('super-admin'));
    }

    public function test_a_plain_admin_cannot_access_staff_management(): void
    {
        $this->seed(RoleSeeder::class);

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->actingAs($admin)->get(route('admin.staff'))->assertForbidden();
        $this->actingAs($admin)->post(route('admin.staff.store'), [
            'name' => 'Nope',
            'email' => 'nope@example.com',
            'role' => 'admin',
        ])->assertForbidden();
        $this->actingAs($admin)->put(route('admin.staff.update', $admin), [
            'name' => 'Nope',
            'email' => $admin->email,
            'role' => 'admin',
        ])->assertForbidden();
    }

    public function test_a_guest_is_redirected_away_from_staff_management(): void
    {
        $this->get(route('admin.staff'))->assertRedirect(route('login'));
    }
}
