/* AI Research Assistant (Admin) — vanilla JS chat client.
 *
 * Conversations are persisted server-side (ai_research_conversations, one
 * row per saved chat, scoped to the logged-in admin) via the endpoints in
 * AiResearchController — this client only keeps a sessionStorage pointer to
 * *which* conversation is currently open, so a reload resumes it without a
 * refetch-everything flash.
 *
 * Each turn is streamed from the server as Server-Sent Events (see
 * ProductResearchChatService::stream()): 'delta' frames grow the assistant
 * bubble live, 'tool_start'/'tool_result' frames show what the assistant is
 * doing (and double as the "sources" shown once the turn finishes), and a
 * final 'done' frame carries the canonical reply/cards/sources plus the
 * conversation id to persist against next turn.
 */
document.addEventListener('DOMContentLoaded', function () {
    var app = document.getElementById('ai-research-app');
    if (!app) return;

    var messagesEl = document.getElementById('ai-chat-messages');
    var emptyEl = document.getElementById('ai-chat-empty');
    var errorEl = document.getElementById('ai-chat-error');
    var form = document.getElementById('ai-chat-form');
    var input = document.getElementById('ai-chat-input');
    var sendBtn = document.getElementById('ai-chat-send');
    var resetBtn = document.getElementById('ai-chat-reset');
    var rateLimitEl = document.getElementById('ai-chat-rate-limit');
    var conversationsEl = document.getElementById('ai-chat-conversations');
    var conversationsEmptyEl = document.getElementById('ai-chat-conversations-empty');
    var usageEl = document.getElementById('ai-chat-usage');
    var usageLabelEl = document.getElementById('ai-chat-usage-label');
    var usagePercentEl = document.getElementById('ai-chat-usage-percent');
    var usageBarFillEl = document.getElementById('ai-chat-usage-bar-fill');
    var usageRefillEl = document.getElementById('ai-chat-usage-refill');
    var usageRefreshBtn = document.getElementById('ai-chat-usage-refresh');
    var csrfToken = document.querySelector('meta[name="csrf-token"]').content;

    var CURRENT_CONVERSATION_KEY = 'ai-research-current-conversation-id';
    var transcript = [];
    var typingRow = null;
    var currentConversationId = null;

    try {
        var storedId = sessionStorage.getItem(CURRENT_CONVERSATION_KEY);
        currentConversationId = storedId ? parseInt(storedId, 10) : null;
    } catch (e) {
        currentConversationId = null;
    }

    var VENDOR_LABEL = { cj: 'CJ Dropshipping', printful: 'Printful' };
    var VENDOR_STYLE = { cj: 'background:#DBEAFE;color:#1E40AF', printful: 'background:#EDE9FE;color:#6D28D9' };

    var vendorUrls = {
        cj: { select: app.dataset.cjSelectUrl, show: app.dataset.cjShowUrl },
        printful: { select: app.dataset.printfulSelectUrl, show: app.dataset.printfulShowUrl },
    };

    // Pure string replace — avoids creating/discarding a throwaway DOM node
    // (and the forced style/serialization work that comes with reading
    // .innerHTML back) on every call, which otherwise happens once per
    // message and once per product card.
    var ESCAPE_MAP = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' };
    function escapeHtml(text) {
        return String(text).replace(/[&<>"']/g, function (ch) { return ESCAPE_MAP[ch]; });
    }

    // Minimal, safe markdown: bold (**text**) and "- " bullet lines only.
    // Content is always HTML-escaped first, so this can only add the tags
    // below — never raw HTML from the model.
    function renderMarkdownLite(text) {
        var escaped = escapeHtml(text);
        var lines = escaped.split('\n');
        var html = '';
        var inList = false;

        lines.forEach(function (line) {
            var isBullet = /^\s*-\s+/.test(line);
            if (isBullet && !inList) { html += '<ul class="mb-2 ps-3">'; inList = true; }
            if (!isBullet && inList) { html += '</ul>'; inList = false; }

            var withBold = line.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');

            html += isBullet
                ? '<li>' + withBold.replace(/^\s*-\s+/, '') + '</li>'
                : (line.trim() === '' ? '<br>' : '<p class="mb-1">' + withBold + '</p>');
        });

        if (inList) html += '</ul>';

        return html;
    }

    // Multiple renders in the same tick (e.g. a bubble followed by its
    // cards) only need one scrollHeight read + scrollTop write, not one
    // per call — coalesce into a single rAF so a batch of DOM writes gets
    // one layout pass instead of forcing a synchronous reflow each time.
    var scrollScheduled = false;
    function scrollToBottom() {
        if (scrollScheduled) return;
        scrollScheduled = true;
        requestAnimationFrame(function () {
            messagesEl.scrollTop = messagesEl.scrollHeight;
            scrollScheduled = false;
        });
    }

    function buildAvatar(role) {
        var avatar = document.createElement('div');
        avatar.className = 'ai-chat-avatar ai-chat-avatar--' + role;
        avatar.innerHTML = role === 'user' ? '<i class="bi bi-person-fill"></i>' : '<i class="bi bi-stars"></i>';
        return avatar;
    }

    function copyToClipboard(text) {
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).catch(function () { fallbackCopy(text); });
        } else {
            fallbackCopy(text);
        }
    }

    // navigator.clipboard needs a secure context (https or localhost) and
    // isn't available in every browser — this covers the rest via the
    // old hidden-textarea + execCommand trick.
    function fallbackCopy(text) {
        var textarea = document.createElement('textarea');
        textarea.value = text;
        textarea.style.position = 'fixed';
        textarea.style.left = '-9999px';
        document.body.appendChild(textarea);
        textarea.focus();
        textarea.select();
        try { document.execCommand('copy'); } catch (e) { /* no-op — nothing more to try */ }
        document.body.removeChild(textarea);
    }

    // `getText` is a function, not a string, so a copy button attached to
    // the still-streaming assistant bubble always copies whatever text is
    // current at click time rather than a snapshot taken when the button
    // was created.
    function buildCopyButton(getText) {
        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'ai-chat-copy-btn';
        btn.title = 'Copy message';
        btn.setAttribute('aria-label', 'Copy message');
        btn.innerHTML = '<i class="bi bi-clipboard"></i>';

        btn.addEventListener('click', function (e) {
            e.stopPropagation();
            copyToClipboard(getText());
            btn.innerHTML = '<i class="bi bi-check2"></i>';
            btn.classList.add('is-copied');
            setTimeout(function () {
                btn.innerHTML = '<i class="bi bi-clipboard"></i>';
                btn.classList.remove('is-copied');
            }, 1200);
        });

        return btn;
    }

    function buildBubbleRow(role, content) {
        var row = document.createElement('div');
        row.className = 'ai-chat-row ai-chat-row--' + (role === 'user' ? 'user' : 'assistant');

        var bubble = document.createElement('div');
        bubble.className = 'ai-chat-bubble ai-chat-bubble--' + (role === 'user' ? 'user' : 'assistant');

        if (role === 'user') {
            bubble.textContent = content;
        } else {
            bubble.innerHTML = renderMarkdownLite(content);
        }

        var wrap = document.createElement('div');
        wrap.className = 'ai-chat-bubble-wrap';
        wrap.appendChild(bubble);
        wrap.appendChild(buildCopyButton(function () { return content; }));

        row.appendChild(buildAvatar(role));
        row.appendChild(wrap);
        return row;
    }

    function renderBubble(role, content) {
        emptyEl.classList.add('d-none');
        messagesEl.appendChild(buildBubbleRow(role, content));
        scrollToBottom();
    }

    function showTyping() {
        typingRow = document.createElement('div');
        typingRow.className = 'ai-chat-row ai-chat-row--assistant';
        typingRow.innerHTML =
            '<div class="ai-chat-typing"><span></span><span></span><span></span></div>';
        typingRow.insertBefore(buildAvatar('assistant'), typingRow.firstChild);
        messagesEl.appendChild(typingRow);
        scrollToBottom();
    }

    function hideTyping() {
        if (typingRow) {
            typingRow.remove();
            typingRow = null;
        }
    }

    function buildCardsBlock(cards) {
        var frag = document.createDocumentFragment();

        var header = document.createElement('div');
        header.className = 'small text-muted-slate ai-chat-cards-header';
        header.textContent = cards.length + (cards.length === 1 ? ' product found' : ' products found');
        frag.appendChild(header);

        var wrap = document.createElement('div');
        wrap.className = 'ai-chat-cards';
        cards.forEach(function (card) { wrap.appendChild(buildCard(card)); });
        frag.appendChild(wrap);

        return frag;
    }

    function renderCards(cards) {
        messagesEl.appendChild(buildCardsBlock(cards));
        scrollToBottom();
    }

    // A small chip per tool call the assistant made this turn ("4 products
    // found", "12 views ... in the last 30 days") — lets the admin see what
    // grounded a claim without reading raw tool-call JSON.
    function buildSourcesBlock(sources) {
        var wrap = document.createElement('div');
        wrap.className = 'ai-chat-sources';
        sources.forEach(function (source) {
            var chip = document.createElement('span');
            chip.className = 'ai-chat-source-chip';
            chip.title = source.label;
            chip.textContent = source.summary;
            wrap.appendChild(chip);
        });
        return wrap;
    }

    function renderSources(sources) {
        messagesEl.appendChild(buildSourcesBlock(sources));
        scrollToBottom();
    }

    // CJ's suggested-sell-price field is sometimes a plain number and
    // sometimes a "min-max" range string straight from their API (e.g.
    // "0.97-4.08") — format the former as currency and pass the latter
    // through as-is rather than rendering "$NaN".
    function formatMoney(value) {
        if (value === null || value === undefined || value === '') return '—';
        var num = Number(value);
        return isNaN(num) ? String(value) : '$' + num.toFixed(2);
    }

    // Side-by-side comparison table for specific products the assistant
    // looked up individually (get_product / score_product) — as opposed to
    // a bulk search_catalog hit list, which stays a card grid. Only shown
    // when 2+ such "detail" cards exist in the same turn.
    function buildCompareTable(cards) {
        var wrap = document.createElement('div');
        wrap.className = 'ai-chat-compare-wrap';

        var hasScore = cards.some(function (c) { return c.score !== null && c.score !== undefined; });
        var headers = ['', 'Product', 'Vendor', 'Base Cost', 'Suggested Price', 'Variants', 'Weight (lb)', 'Free Ship'];
        if (hasScore) headers.push('Score');

        var table = document.createElement('table');
        table.className = 'ai-chat-compare';

        var thead = document.createElement('thead');
        var headRow = document.createElement('tr');
        headers.forEach(function (label) {
            var th = document.createElement('th');
            th.textContent = label;
            headRow.appendChild(th);
        });
        thead.appendChild(headRow);
        table.appendChild(thead);

        var tbody = document.createElement('tbody');
        cards.forEach(function (card) {
            var tr = document.createElement('tr');
            var img = card.preview_image_url || 'https://placehold.co/80x80/F8FAFC/64748B?text=No+Image';

            var imgCell = document.createElement('td');
            imgCell.innerHTML = '<img src="' + img + '" alt="">';
            tr.appendChild(imgCell);

            var values = [
                card.title,
                VENDOR_LABEL[card.vendor] || card.vendor,
                formatMoney(card.base_cost),
                formatMoney(card.suggested_sell_price),
                card.variant_count || '—',
                card.product_weight_lb != null ? card.product_weight_lb : '—',
                card.free_shipping === true ? 'Yes' : (card.free_shipping === false ? 'No' : '—'),
            ];
            if (hasScore) {
                values.push(card.score != null ? card.score + '/10' + (card.verdict ? ' (' + card.verdict + ')' : '') : '—');
            }

            values.forEach(function (value) {
                var td = document.createElement('td');
                td.textContent = value;
                tr.appendChild(td);
            });

            tbody.appendChild(tr);
        });
        table.appendChild(tbody);

        wrap.appendChild(table);
        return wrap;
    }

    function renderCompareTable(cards) {
        messagesEl.appendChild(buildCompareTable(cards));
        scrollToBottom();
    }

    // Replays a loaded conversation's messages in one batch: everything is
    // built into an off-DOM fragment first, so a long conversation costs
    // one reflow (on the single final appendChild) instead of one per
    // message/card block the way sequential render*() calls would.
    function renderInitialTranscript() {
        if (!transcript.length) return;

        emptyEl.classList.add('d-none');

        var frag = document.createDocumentFragment();
        transcript.forEach(function (entry) {
            frag.appendChild(buildBubbleRow(entry.role, entry.content));

            if (entry.sources && entry.sources.length) {
                frag.appendChild(buildSourcesBlock(entry.sources));
            }

            if (entry.cards && entry.cards.length) {
                var detailCards = entry.cards.filter(function (c) { return c.source === 'detail'; });
                if (detailCards.length >= 2) frag.appendChild(buildCompareTable(detailCards));
                frag.appendChild(buildCardsBlock(entry.cards));
            }
        });

        messagesEl.appendChild(frag);
        scrollToBottom();
    }

    function buildCard(card) {
        var urls = vendorUrls[card.vendor] || vendorUrls.cj;
        var showUrl = (urls.show || '').replace('__ID__', encodeURIComponent(card.vendor_external_id));
        var selectUrl = (urls.select || '').replace('__ID__', encodeURIComponent(card.vendor_external_id));
        var vendorLabel = VENDOR_LABEL[card.vendor] || card.vendor;
        var vendorStyle = VENDOR_STYLE[card.vendor] || '';

        var col = document.createElement('div');
        col.className = 'research-card';

        var img = card.preview_image_url || 'https://placehold.co/300x300/F8FAFC/64748B?text=No+Image';

        col.innerHTML =
            '<a href="' + showUrl + '" class="text-decoration-none text-dark d-block">' +
                '<div class="research-card__image"><img src="' + img + '" alt="" loading="lazy"></div>' +
            '</a>' +
            '<div class="research-card__body">' +
                '<span class="badge rounded-pill mb-2" style="' + vendorStyle + '">' + vendorLabel + '</span>' +
                '<a href="' + showUrl + '" class="text-decoration-none text-dark">' +
                    '<div class="research-card__title">' + escapeHtml(card.title) + '</div>' +
                '</a>' +
                (card.variant_count
                    ? '<span class="badge rounded-pill bg-light text-muted-slate border mb-2">' + card.variant_count + ' variant' + (card.variant_count === 1 ? '' : 's') + '</span>'
                    : '') +
                (card.score != null
                    ? '<span class="badge rounded-pill bg-light text-muted-slate border mb-2 ms-1">Score ' + card.score + '/10</span>'
                    : '') +
                '<div class="mt-auto">' +
                    (card.base_cost != null
                        ? '<div class="research-card__cost mb-2">$' + Number(card.base_cost).toFixed(2) + ' <span class="small text-muted-slate fw-normal">base cost</span></div>'
                        : '') +
                    '<button type="button" class="btn btn-sm w-100 btn-outline-secondary ai-card-select">' +
                        '<i class="bi bi-plus-lg"></i> Add to Selection' +
                    '</button>' +
                '</div>' +
            '</div>';

        var selectBtn = col.querySelector('.ai-card-select');
        selectBtn.addEventListener('click', function () {
            selectBtn.disabled = true;
            fetch(selectUrl, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
            }).then(function (res) {
                if (res.ok || res.redirected) {
                    selectBtn.innerHTML = '<i class="bi bi-check-lg"></i> Added to Selection';
                    selectBtn.classList.remove('btn-outline-secondary');
                    selectBtn.classList.add('btn-accent');
                } else {
                    selectBtn.disabled = false;
                }
            }).catch(function () {
                selectBtn.disabled = false;
            });
        });

        return col;
    }

    function setLoading(isLoading) {
        sendBtn.disabled = isLoading;
        input.disabled = isLoading;
    }

    function autoResize() {
        input.style.height = 'auto';
        input.style.height = Math.min(input.scrollHeight, 140) + 'px';
    }

    // Laravel's `throttle:20,1` middleware on the chat route adds
    // X-RateLimit-* headers to every response (even a streamed one, since
    // they're attached before the body starts sending) — surface them so
    // the admin sees a quota running low instead of hitting a bare 429.
    function updateRateLimit(headers) {
        if (!rateLimitEl) return;

        var remaining = headers.get('X-RateLimit-Remaining');
        var limit = headers.get('X-RateLimit-Limit');

        if (remaining === null || limit === null) {
            rateLimitEl.textContent = '';
            return;
        }

        rateLimitEl.textContent = remaining + ' / ' + limit + ' requests left this minute';
        rateLimitEl.classList.toggle('text-danger', Number(remaining) <= 2);
    }

    function parseSseFrame(frame, onEvent) {
        if (!frame.trim()) return;

        var eventName = 'message';
        var dataLines = [];

        frame.split('\n').forEach(function (line) {
            if (line.indexOf('event:') === 0) {
                eventName = line.slice(6).trim();
            } else if (line.indexOf('data:') === 0) {
                dataLines.push(line.slice(5).trim());
            }
        });

        if (!dataLines.length) return;

        var data;
        try {
            data = JSON.parse(dataLines.join('\n'));
        } catch (e) {
            return;
        }

        onEvent(eventName, data);
    }

    function sendMessage(text) {
        text = text.trim();
        if (!text) return;

        errorEl.classList.add('d-none');
        renderBubble('user', text);
        transcript.push({ role: 'user', content: text });
        input.value = '';
        autoResize();
        setLoading(true);
        showTyping();

        // Per-turn streaming state — a fresh closure per sendMessage() call,
        // since the Send button is disabled while a turn is in flight so
        // only one of these is ever live at a time.
        var statusRow = null;
        var statusWrap = null;
        var lastStatusLine = null;
        var bubbleEl = null;
        var liveText = '';

        function ensureStatusRow() {
            if (!statusRow) {
                hideTyping();
                statusRow = document.createElement('div');
                statusRow.className = 'ai-chat-row ai-chat-row--assistant';
                statusWrap = document.createElement('div');
                statusWrap.className = 'd-flex flex-column gap-1';
                statusRow.appendChild(buildAvatar('assistant'));
                statusRow.appendChild(statusWrap);
                messagesEl.appendChild(statusRow);
            }
            return statusWrap;
        }

        function ensureBubble() {
            if (!bubbleEl) {
                hideTyping();
                emptyEl.classList.add('d-none');
                var bubbleRow = document.createElement('div');
                bubbleRow.className = 'ai-chat-row ai-chat-row--assistant';
                bubbleEl = document.createElement('div');
                bubbleEl.className = 'ai-chat-bubble ai-chat-bubble--assistant';

                var wrap = document.createElement('div');
                wrap.className = 'ai-chat-bubble-wrap';
                wrap.appendChild(bubbleEl);
                wrap.appendChild(buildCopyButton(function () { return liveText; }));

                bubbleRow.appendChild(buildAvatar('assistant'));
                bubbleRow.appendChild(wrap);
                messagesEl.appendChild(bubbleRow);
            }
        }

        function handleEvent(eventName, data) {
            if (eventName === 'delta') {
                ensureBubble();
                liveText += data.text;
                bubbleEl.innerHTML = renderMarkdownLite(liveText);
                scrollToBottom();
            } else if (eventName === 'tool_start') {
                var wrap = ensureStatusRow();
                lastStatusLine = document.createElement('div');
                lastStatusLine.className = 'ai-chat-tool-status';
                lastStatusLine.innerHTML =
                    '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span><span>' +
                    escapeHtml(data.label) + '</span>';
                wrap.appendChild(lastStatusLine);
                scrollToBottom();
            } else if (eventName === 'tool_result') {
                if (lastStatusLine) {
                    lastStatusLine.classList.add('is-done');
                    lastStatusLine.innerHTML = '<i class="bi bi-check-circle"></i><span>' + escapeHtml(data.summary) + '</span>';
                }
                scrollToBottom();
            } else if (eventName === 'error') {
                hideTyping();
                if (statusRow) { statusRow.remove(); statusRow = null; }
                errorEl.textContent = data.message;
                errorEl.classList.remove('d-none');
            } else if (eventName === 'done') {
                hideTyping();
                if (statusRow) { statusRow.remove(); statusRow = null; }

                if (!bubbleEl) {
                    renderBubble('assistant', data.reply);
                } else {
                    liveText = data.reply;
                    bubbleEl.innerHTML = renderMarkdownLite(liveText);
                }

                if (data.sources && data.sources.length) renderSources(data.sources);

                if (data.cards && data.cards.length) {
                    var detailCards = data.cards.filter(function (c) { return c.source === 'detail'; });
                    if (detailCards.length >= 2) renderCompareTable(detailCards);
                    renderCards(data.cards);
                }

                transcript.push({ role: 'assistant', content: data.reply, cards: data.cards || [], sources: data.sources || [] });

                currentConversationId = data.conversation_id || currentConversationId;
                try { sessionStorage.setItem(CURRENT_CONVERSATION_KEY, String(currentConversationId)); } catch (e) {}
                refreshConversationsList();
            }
        }

        fetch(app.dataset.chatUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'text/event-stream',
            },
            body: JSON.stringify({
                conversation_id: currentConversationId,
                messages: transcript
                    .filter(function (m) { return m.role === 'user' || m.role === 'assistant'; })
                    .map(function (m) { return { role: m.role, content: m.content }; }),
            }),
        })
            .then(function (res) {
                updateRateLimit(res.headers);

                if (!res.ok || !res.body) {
                    return res.json().then(function (data) {
                        throw new Error(data.error || 'Something went wrong.');
                    });
                }

                var reader = res.body.getReader();
                var decoder = new TextDecoder();
                var buffer = '';

                function pump() {
                    return reader.read().then(function (result) {
                        if (result.done) return;

                        buffer += decoder.decode(result.value, { stream: true });
                        var frames = buffer.split('\n\n');
                        buffer = frames.pop();

                        frames.forEach(function (frame) { parseSseFrame(frame, handleEvent); });

                        return pump();
                    });
                }

                return pump();
            })
            .catch(function (err) {
                hideTyping();
                if (statusRow) { statusRow.remove(); statusRow = null; }
                errorEl.textContent = err.message;
                errorEl.classList.remove('d-none');
            })
            .finally(function () {
                setLoading(false);
                input.focus();
            });
    }

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        sendMessage(input.value);
    });

    input.addEventListener('input', autoResize);

    input.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            form.requestSubmit();
        }
    });

    document.querySelectorAll('.ai-chat-suggestion').forEach(function (chip) {
        chip.addEventListener('click', function () {
            sendMessage(chip.textContent);
        });
    });

    function startNewConversation() {
        currentConversationId = null;
        try { sessionStorage.removeItem(CURRENT_CONVERSATION_KEY); } catch (e) {}
        transcript = [];
        messagesEl.innerHTML = '';
        messagesEl.appendChild(emptyEl);
        emptyEl.classList.remove('d-none');
        errorEl.classList.add('d-none');
        refreshConversationsList();
    }

    resetBtn.addEventListener('click', function () {
        if (transcript.length && !window.confirm('Start a new conversation? Your current chat stays saved in the sidebar.')) {
            return;
        }

        startNewConversation();
    });

    function relativeTime(iso) {
        try {
            var diffMs = Date.now() - new Date(iso).getTime();
            var mins = Math.round(diffMs / 60000);
            if (mins < 1) return 'just now';
            if (mins < 60) return mins + 'm ago';
            var hours = Math.round(mins / 60);
            if (hours < 24) return hours + 'h ago';
            return Math.round(hours / 24) + 'd ago';
        } catch (e) {
            return '';
        }
    }

    function loadConversation(id) {
        if (!app.dataset.conversationShowUrl) return;

        var url = app.dataset.conversationShowUrl.replace('__ID__', id);

        fetch(url, { headers: { 'Accept': 'application/json' } })
            .then(function (res) {
                if (!res.ok) throw new Error('Could not load that conversation.');
                return res.json();
            })
            .then(function (data) {
                currentConversationId = data.id;
                try { sessionStorage.setItem(CURRENT_CONVERSATION_KEY, String(currentConversationId)); } catch (e) {}

                transcript = data.messages || [];
                messagesEl.innerHTML = '';
                messagesEl.appendChild(emptyEl);
                emptyEl.classList.remove('d-none');
                renderInitialTranscript();
                errorEl.classList.add('d-none');
                refreshConversationsList();
            })
            .catch(function (err) {
                errorEl.textContent = err.message;
                errorEl.classList.remove('d-none');
            });
    }

    function renderConversationsList(list) {
        conversationsEl.querySelectorAll('.ai-chat-conversation').forEach(function (el) { el.remove(); });
        conversationsEmptyEl.classList.toggle('d-none', list.length > 0);

        list.forEach(function (conv) {
            var item = document.createElement('div');
            item.className = 'ai-chat-conversation' + (conv.id === currentConversationId ? ' is-active' : '');
            item.innerHTML =
                '<span class="ai-chat-conversation__title">' + escapeHtml(conv.title || 'Untitled conversation') + '</span>' +
                '<button type="button" class="ai-chat-conversation__delete" title="Delete conversation" aria-label="Delete conversation"><i class="bi bi-trash3"></i></button>';
            item.title = relativeTime(conv.updated_at);

            item.addEventListener('click', function (e) {
                if (e.target.closest('.ai-chat-conversation__delete')) return;
                loadConversation(conv.id);
            });

            item.querySelector('.ai-chat-conversation__delete').addEventListener('click', function (e) {
                e.stopPropagation();
                if (!window.confirm('Delete "' + (conv.title || 'this conversation') + '"? This cannot be undone.')) return;

                var url = (app.dataset.conversationDestroyUrl || '').replace('__ID__', conv.id);
                fetch(url, { method: 'DELETE', headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' } })
                    .then(function () {
                        if (conv.id === currentConversationId) startNewConversation();
                        refreshConversationsList();
                    });
            });

            conversationsEl.appendChild(item);
        });
    }

    function refreshConversationsList() {
        if (!app.dataset.conversationsUrl) return;

        fetch(app.dataset.conversationsUrl, { headers: { 'Accept': 'application/json' } })
            .then(function (res) { return res.json(); })
            .then(function (data) { renderConversationsList(data.conversations || []); })
            .catch(function () {});
    }

    // Matches the phrasing on Ollama's own Usage dashboard ("Refills in 4
    // weeks") rather than an absolute date — a relative countdown reads
    // faster at a glance for something that resets on a rolling period.
    function relativeRefill(iso) {
        try {
            var diffMs = new Date(iso).getTime() - Date.now();
            if (diffMs <= 0) return 'soon';

            var days = Math.round(diffMs / 86400000);
            if (days < 1) return 'today';
            if (days < 14) return 'in ' + days + ' day' + (days === 1 ? '' : 's');

            var weeks = Math.round(days / 7);
            return 'in ' + weeks + ' week' + (weeks === 1 ? '' : 's');
        } catch (e) {
            return null;
        }
    }

    // @return {label, percent, percentText, refillText, purchased}|null
    function computeUsage(data) {
        var included = data.included || {};
        var purchased = data.purchased && data.purchased.balance_usd > 0 ? data.purchased.balance_usd : 0;

        if (typeof included.balance_usd === 'number' && included.allowance_usd) {
            var used = included.allowance_usd - included.balance_usd;
            var percent = Math.max(0, Math.min(100, (used / included.allowance_usd) * 100));

            return {
                label: 'Free usage',
                percent: percent,
                percentText: (Math.round(percent * 10) / 10) + '% used',
                refillText: included.period && included.period.until ? 'Refills ' + relativeRefill(included.period.until) : '',
                purchased: purchased,
            };
        }

        var window_ = included.session || included.weekly;
        if (window_) {
            var usedPercent = 100 - window_.remaining_percent;

            return {
                label: included.session ? 'Session usage' : 'Weekly usage',
                percent: usedPercent,
                percentText: (Math.round(usedPercent * 10) / 10) + '% used',
                refillText: window_.resets_at ? 'Refills ' + relativeRefill(window_.resets_at) : '',
                purchased: purchased,
            };
        }

        return null;
    }

    function loadUsage() {
        if (!app.dataset.balanceUrl) return;

        usageRefreshBtn.disabled = true;

        fetch(app.dataset.balanceUrl, { headers: { 'Accept': 'application/json' } })
            .then(function (res) { return res.json().then(function (data) { return { ok: res.ok, data: data }; }); })
            .then(function (result) {
                var usage = result.ok ? computeUsage(result.data) : null;

                if (!usage) {
                    usageEl.classList.add('d-none');
                    return;
                }

                usageLabelEl.textContent = usage.label;
                usagePercentEl.textContent = usage.percentText;
                usageBarFillEl.style.width = usage.percent.toFixed(1) + '%';
                usageRefillEl.textContent = usage.refillText + (usage.purchased ? ' · $' + usage.purchased.toFixed(2) + ' purchased credits' : '');
                usageEl.classList.remove('d-none');
            })
            .catch(function () {
                usageEl.classList.add('d-none');
            })
            .finally(function () {
                usageRefreshBtn.disabled = false;
            });
    }

    usageRefreshBtn.addEventListener('click', loadUsage);

    refreshConversationsList();
    if (currentConversationId) loadConversation(currentConversationId);
    loadUsage();
});
