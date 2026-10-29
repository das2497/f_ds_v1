/* AI Research Assistant (Admin) — vanilla JS chat client.
 * Conversation history lives only in this page's memory (mirrored to
 * sessionStorage as a best-effort convenience) and is posted back in full
 * on every turn; nothing is persisted server-side. See
 * app/Services/AI/ProductResearchChatService.php for the tool-calling loop
 * this talks to.
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
    var usageEl = document.getElementById('ai-chat-usage');
    var usageLabelEl = document.getElementById('ai-chat-usage-label');
    var usagePercentEl = document.getElementById('ai-chat-usage-percent');
    var usageBarFillEl = document.getElementById('ai-chat-usage-bar-fill');
    var usageRefillEl = document.getElementById('ai-chat-usage-refill');
    var usageRefreshBtn = document.getElementById('ai-chat-usage-refresh');
    var csrfToken = document.querySelector('meta[name="csrf-token"]').content;

    var STORAGE_KEY = 'ai-research-chat-transcript';
    var transcript = loadTranscript();
    var typingRow = null;

    var VENDOR_LABEL = { cj: 'CJ Dropshipping', printful: 'Printful' };
    var VENDOR_STYLE = { cj: 'background:#DBEAFE;color:#1E40AF', printful: 'background:#EDE9FE;color:#6D28D9' };

    var vendorUrls = {
        cj: { select: app.dataset.cjSelectUrl, show: app.dataset.cjShowUrl },
        printful: { select: app.dataset.printfulSelectUrl, show: app.dataset.printfulShowUrl },
    };

    function loadTranscript() {
        try {
            var raw = sessionStorage.getItem(STORAGE_KEY);
            return raw ? JSON.parse(raw) : [];
        } catch (e) {
            return [];
        }
    }

    function saveTranscript() {
        try {
            sessionStorage.setItem(STORAGE_KEY, JSON.stringify(transcript));
        } catch (e) {
            // best-effort only — chat still works without it
        }
    }

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

        row.appendChild(buildAvatar(role));
        row.appendChild(bubble);
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

    // Replays sessionStorage history in one batch: everything is built into
    // an off-DOM fragment first, so a long conversation costs one reflow
    // (on the single final appendChild) instead of one per message/card
    // block the way sequential renderBubble()/renderCards() calls would.
    function renderInitialTranscript() {
        if (!transcript.length) return;

        emptyEl.classList.add('d-none');

        var frag = document.createDocumentFragment();
        transcript.forEach(function (entry) {
            frag.appendChild(buildBubbleRow(entry.role, entry.content));
            if (entry.cards && entry.cards.length) frag.appendChild(buildCardsBlock(entry.cards));
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

    function sendMessage(text) {
        text = text.trim();
        if (!text) return;

        errorEl.classList.add('d-none');
        renderBubble('user', text);
        transcript.push({ role: 'user', content: text });
        saveTranscript();
        input.value = '';
        autoResize();
        setLoading(true);
        showTyping();

        fetch(app.dataset.chatUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json',
            },
            body: JSON.stringify({
                messages: transcript.map(function (m) { return { role: m.role, content: m.content }; }),
            }),
        })
            .then(function (res) {
                return res.json().then(function (data) { return { ok: res.ok, data: data }; });
            })
            .then(function (result) {
                hideTyping();

                if (!result.ok) {
                    throw new Error(result.data.error || 'Something went wrong.');
                }

                renderBubble('assistant', result.data.reply);
                if (result.data.cards && result.data.cards.length) renderCards(result.data.cards);

                transcript.push({ role: 'assistant', content: result.data.reply, cards: result.data.cards || [] });
                saveTranscript();
            })
            .catch(function (err) {
                hideTyping();
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

    resetBtn.addEventListener('click', function () {
        if (transcript.length && !window.confirm('Start a new conversation? This clears the current chat.')) {
            return;
        }

        transcript = [];
        saveTranscript();
        messagesEl.innerHTML = '';
        messagesEl.appendChild(emptyEl);
        emptyEl.classList.remove('d-none');
        errorEl.classList.add('d-none');
    });

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

    renderInitialTranscript();
    loadUsage();
});
