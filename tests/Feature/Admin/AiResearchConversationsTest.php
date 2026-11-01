<?php

namespace Tests\Feature\Admin;

use App\Models\AiResearchConversation;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiResearchConversationsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->seed(RoleSeeder::class);

        $user = User::factory()->create();
        $user->assignRole('admin');

        return $user;
    }

    public function test_an_admin_can_list_only_their_own_conversations(): void
    {
        $admin = $this->admin();
        $otherAdmin = User::factory()->create();
        $otherAdmin->assignRole('admin');

        AiResearchConversation::create(['user_id' => $admin->id, 'title' => 'Mine', 'messages' => []]);
        AiResearchConversation::create(['user_id' => $otherAdmin->id, 'title' => 'Not mine', 'messages' => []]);

        $response = $this->actingAs($admin)->getJson(route('admin.ai-research.conversations'));

        $response->assertOk();
        $titles = collect($response->json('conversations'))->pluck('title');

        $this->assertTrue($titles->contains('Mine'));
        $this->assertFalse($titles->contains('Not mine'));
    }

    public function test_an_admin_can_load_their_own_conversation(): void
    {
        $admin = $this->admin();
        $conversation = AiResearchConversation::create([
            'user_id' => $admin->id,
            'title' => 'Phone stands',
            'messages' => [
                ['role' => 'user', 'content' => 'cheap phone stands'],
                ['role' => 'assistant', 'content' => 'Here are a few.', 'cards' => [], 'sources' => []],
            ],
        ]);

        $response = $this->actingAs($admin)->getJson(route('admin.ai-research.conversations.show', $conversation));

        $response->assertOk()
            ->assertJsonPath('title', 'Phone stands')
            ->assertJsonPath('messages.0.content', 'cheap phone stands');
    }

    public function test_an_admin_cannot_load_another_admins_conversation(): void
    {
        $owner = $this->admin();
        $conversation = AiResearchConversation::create(['user_id' => $owner->id, 'title' => 'Private', 'messages' => []]);

        $otherAdmin = User::factory()->create();
        $otherAdmin->assignRole('admin');

        $this->actingAs($otherAdmin)
            ->getJson(route('admin.ai-research.conversations.show', $conversation))
            ->assertForbidden();
    }

    public function test_an_admin_can_delete_their_own_conversation(): void
    {
        $admin = $this->admin();
        $conversation = AiResearchConversation::create(['user_id' => $admin->id, 'title' => 'To delete', 'messages' => []]);

        $this->actingAs($admin)
            ->deleteJson(route('admin.ai-research.conversations.destroy', $conversation))
            ->assertOk();

        $this->assertDatabaseMissing('ai_research_conversations', ['id' => $conversation->id]);
    }

    public function test_an_admin_cannot_delete_another_admins_conversation(): void
    {
        $owner = $this->admin();
        $conversation = AiResearchConversation::create(['user_id' => $owner->id, 'title' => 'Private', 'messages' => []]);

        $otherAdmin = User::factory()->create();
        $otherAdmin->assignRole('admin');

        $this->actingAs($otherAdmin)
            ->deleteJson(route('admin.ai-research.conversations.destroy', $conversation))
            ->assertForbidden();

        $this->assertDatabaseHas('ai_research_conversations', ['id' => $conversation->id]);
    }

    public function test_a_non_admin_cannot_access_conversations(): void
    {
        $this->seed(RoleSeeder::class);
        $user = User::factory()->create();
        $conversation = AiResearchConversation::create(['user_id' => $user->id, 'title' => 'Mine', 'messages' => []]);

        $this->actingAs($user)->getJson(route('admin.ai-research.conversations'))->assertForbidden();
        $this->actingAs($user)->getJson(route('admin.ai-research.conversations.show', $conversation))->assertForbidden();
    }

    public function test_a_guest_is_redirected_away_from_conversations(): void
    {
        $this->get(route('admin.ai-research.conversations'))->assertRedirect(route('login'));
    }
}
