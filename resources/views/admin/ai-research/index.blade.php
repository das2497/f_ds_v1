<x-admin-layout title="AI Research Assistant">
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
        <div>
            <h5 class="mb-0"><i class="bi bi-stars text-accent"></i> AI Research Assistant</h5>
            <div class="small text-muted-slate">Searches live Printful &amp; CJ Dropshipping catalogs and your store's own demand data — never guesses at trends.</div>
        </div>
        <button type="button" id="ai-chat-reset" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-arrow-counterclockwise"></i> New Conversation
        </button>
    </div>

    <div id="ai-research-app"
         class="table-admin p-0 d-flex flex-column"
         style="height:68vh"
         data-chat-url="{{ route('admin.ai-research.chat') }}"
         data-balance-url="{{ route('admin.ai-research.balance') }}"
         data-cj-select-url="{{ route('admin.products-research-cjd.select', ['externalId' => '__ID__']) }}"
         data-cj-show-url="{{ route('admin.products-research-cjd.show', ['externalId' => '__ID__']) }}"
         data-printful-select-url="{{ route('admin.products-research-printful.select', ['externalId' => '__ID__']) }}"
         data-printful-show-url="{{ route('admin.products-research-printful.show', ['externalId' => '__ID__']) }}">

        <div id="ai-chat-messages" class="flex-grow-1 overflow-auto p-3 d-flex flex-column gap-3" aria-live="polite">
            <div id="ai-chat-empty" class="text-center py-5 px-3">
                <i class="bi bi-stars fs-1 d-block mb-3 text-accent" style="opacity:.45"></i>
                <div class="fw-semibold mb-1">What are you sourcing today?</div>
                <div class="small text-muted-slate mb-3 mx-auto" style="max-width:420px">
                    Ask about a category, a price range, or a specific item. I'll search live catalogs and real customer demand before giving you an answer.
                </div>
                <div class="d-flex flex-wrap justify-content-center gap-2">
                    <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill ai-chat-suggestion">Cheap phone stands under $10</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill ai-chat-suggestion">Trending CJ products this week</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill ai-chat-suggestion">Is there demand for yoga mats?</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill ai-chat-suggestion">Lightweight items under $5 base cost</button>
                </div>
            </div>
        </div>

        <div id="ai-chat-error" class="d-none alert alert-warning m-3 mb-0 small"></div>

        <div class="p-3 border-top">
            <form id="ai-chat-form" class="d-flex gap-2 align-items-end">
                @csrf
                <textarea id="ai-chat-input" class="form-control" rows="1" placeholder="Ask about products to source..." aria-label="Message the AI Research Assistant" required></textarea>
                <button type="submit" id="ai-chat-send" class="btn btn-accent px-4" aria-label="Send message">
                    <i class="bi bi-send"></i>
                </button>
            </form>
            <div class="small text-muted-slate mt-1">Press Enter to send &middot; Shift+Enter for a new line</div>
        </div>
    </div>

    <div id="ai-chat-usage" class="table-admin p-3 mt-2 small d-none">
        <div class="d-flex justify-content-between align-items-center">
            <span id="ai-chat-usage-label" class="fw-semibold"></span>
            <span>
                <span id="ai-chat-usage-percent" class="text-muted-slate"></span>
                <button type="button" id="ai-chat-usage-refresh" class="btn btn-link btn-sm p-0 ms-1 align-baseline" title="Refresh usage">
                    <i class="bi bi-arrow-clockwise"></i>
                </button>
            </span>
        </div>
        <div class="ai-chat-usage-bar mt-2"><div id="ai-chat-usage-bar-fill" class="ai-chat-usage-bar__fill"></div></div>
        <div id="ai-chat-usage-refill" class="text-muted-slate mt-1"></div>
    </div>

    @vite(['resources/js/ai-research-chat.js'])
</x-admin-layout>
