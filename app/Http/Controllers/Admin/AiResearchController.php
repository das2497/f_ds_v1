<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\VendorApiException;
use App\Http\Controllers\Controller;
use App\Services\AI\ProductResearchChatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AiResearchController extends Controller
{
    public function __construct(private readonly ProductResearchChatService $chat) {}

    public function index(): View
    {
        return view('admin.ai-research.index');
    }

    /**
     * Conversation history is held entirely client-side (never persisted) —
     * the client posts its running transcript back on every turn and this
     * just proxies one tool-calling round through ProductResearchChatService.
     */
    public function chat(Request $request): JsonResponse
    {
        if (! $this->chat->configured()) {
            return response()->json(['error' => 'Ollama Cloud is not configured yet. Add an API key in Gateway Settings.'], 422);
        }

        $validated = $request->validate([
            'messages' => ['required', 'array', 'max:20'],
            'messages.*.role' => ['required', 'string', 'in:user,assistant'],
            'messages.*.content' => ['required', 'string', 'max:4000'],
        ]);

        try {
            $result = $this->chat->respond($validated['messages']);
        } catch (VendorApiException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json($result);
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
}
