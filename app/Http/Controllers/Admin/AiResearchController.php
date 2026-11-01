<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\VendorApiException;
use App\Http\Controllers\Controller;
use App\Models\AiResearchConversation;
use App\Services\AI\ProductResearchChatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AiResearchController extends Controller
{
    public function __construct(private readonly ProductResearchChatService $chat) {}

    public function index(): View
    {
        return view('admin.ai-research.index');
    }

    /**
     * Streams one tool-calling round trip through ProductResearchChatService
     * as Server-Sent Events, so the admin sees assistant text appear as it's
     * generated instead of waiting for the whole (possibly multi-tool-call)
     * turn to finish. Conversation history is still posted back in full on
     * every turn (held client-side, mirrored into ai_research_conversations
     * once this turn completes) rather than re-read from storage mid-turn.
     */
    public function chat(Request $request): JsonResponse|StreamedResponse
    {
        if (! $this->chat->configured()) {
            return response()->json(['error' => 'Ollama Cloud is not configured yet. Add an API key in Gateway Settings.'], 422);
        }

        $validated = $request->validate([
            'conversation_id' => ['nullable', 'integer', 'exists:ai_research_conversations,id'],
            'messages' => ['required', 'array', 'max:20'],
            'messages.*.role' => ['required', 'string', 'in:user,assistant'],
            'messages.*.content' => ['required', 'string', 'max:4000'],
        ]);

        $conversation = $this->ownedConversation($request, $validated['conversation_id'] ?? null);
        $userId = $request->user()->id;

        return response()->stream(function () use ($validated, $conversation, $userId) {
            $send = function (string $event, array $data): void {
                echo "event: {$event}\n";
                echo 'data: '.json_encode($data)."\n\n";

                if (ob_get_level() > 0) {
                    ob_flush();
                }
                flush();
            };

            try {
                $result = $this->chat->stream($validated['messages'], $send);
            } catch (VendorApiException $e) {
                $send('error', ['message' => $e->getMessage()]);

                return;
            } catch (\Throwable $e) {
                report($e);
                $send('error', ['message' => 'Something went wrong.']);

                return;
            }

            $saved = $this->persistConversation($userId, $conversation, $validated['messages'], $result);

            $send('done', [
                'reply' => $result['reply'],
                'cards' => $result['cards'],
                'sources' => $result['sources'],
                'conversation_id' => $saved->id,
                'title' => $saved->title,
            ]);
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'X-Accel-Buffering' => 'no',
            'Connection' => 'keep-alive',
        ]);
    }

    /**
     * Account-wide Ollama Cloud credit balance — fetched on demand (page
     * load / manual refresh), never after every chat message, since this
     * is a separate, account-shared endpoint with its own rate limit.
     */
    public function balance(): JsonResponse
    {
        if (! $this->chat->configured()) {
            return response()->json(['error' => 'Ollama Cloud is not configured yet.'], 422);
        }

        try {
            return response()->json($this->chat->balance());
        } catch (VendorApiException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        } catch (\Throwable) {
            return response()->json(['error' => 'Could not load Ollama usage right now.'], 422);
        }
    }

    /**
     * Sidebar list of this admin's own saved conversations, most recently
     * active first. Every admin's conversations are private to them — two
     * admins researching at once never see each other's chat history.
     */
    public function conversations(Request $request): JsonResponse
    {
        $conversations = AiResearchConversation::where('user_id', $request->user()->id)
            ->orderByDesc('updated_at')
            ->limit(50)
            ->get(['id', 'title', 'updated_at']);

        return response()->json(['conversations' => $conversations]);
    }

    public function showConversation(Request $request, AiResearchConversation $conversation): JsonResponse
    {
        abort_if($conversation->user_id !== $request->user()->id, 403);

        return response()->json([
            'id' => $conversation->id,
            'title' => $conversation->title,
            'messages' => $conversation->messages,
        ]);
    }

    public function destroyConversation(Request $request, AiResearchConversation $conversation): JsonResponse
    {
        abort_if($conversation->user_id !== $request->user()->id, 403);

        $conversation->delete();

        return response()->json(['status' => 'deleted']);
    }

    private function ownedConversation(Request $request, ?int $conversationId): ?AiResearchConversation
    {
        if (! $conversationId) {
            return null;
        }

        $conversation = AiResearchConversation::find($conversationId);
        abort_if(! $conversation || $conversation->user_id !== $request->user()->id, 403);

        return $conversation;
    }

    /**
     * @param  array<int, array{role: string, content: string}>  $sentMessages
     * @param  array{reply: string, cards: array<int, array<string, mixed>>, sources: array<int, array<string, mixed>>}  $result
     */
    private function persistConversation(int $userId, ?AiResearchConversation $conversation, array $sentMessages, array $result): AiResearchConversation
    {
        $fullTranscript = [
            ...$sentMessages,
            ['role' => 'assistant', 'content' => $result['reply'], 'cards' => $result['cards'], 'sources' => $result['sources']],
        ];

        if ($conversation) {
            $conversation->update(['messages' => $fullTranscript]);

            return $conversation;
        }

        $firstUserMessage = collect($sentMessages)->firstWhere('role', 'user')['content'] ?? 'New conversation';

        return AiResearchConversation::create([
            'user_id' => $userId,
            'title' => Str::limit($firstUserMessage, 60),
            'messages' => $fullTranscript,
        ]);
    }
}
