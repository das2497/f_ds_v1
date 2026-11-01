/* Vendor Research catalog grid (Admin — Printful/CJD) — adds AI-score
 * badges to cards that already have a cached score (see AiProductScore),
 * a "Score All Visible" batch action that reuses the same per-product
 * score endpoint the product detail page uses, and a client-side
 * sort/filter by that score — all without re-querying the vendor's own
 * catalog API, since the score lives in our own database, not theirs.
 */
document.addEventListener('DOMContentLoaded', function () {
    var grid = document.getElementById('research-grid');
    var toolbar = document.getElementById('research-score-toolbar');
    if (!grid || !toolbar) return;

    var cards = Array.prototype.slice.call(grid.querySelectorAll('.research-card-col'));
    if (!cards.length) return;

    var originalOrder = cards.slice();
    var csrfToken = document.querySelector('meta[name="csrf-token"]').content;
    var scoreAllBtn = document.getElementById('research-score-all');
    var progressEl = document.getElementById('research-score-progress');
    var sortSelect = document.getElementById('research-score-sort');
    var minScoreSelect = document.getElementById('research-min-score');

    function scoreTierClass(score) {
        if (score >= 8) return 'is-high';
        if (score >= 5) return 'is-mid';
        return 'is-low';
    }

    function relativeTime(iso) {
        try {
            var days = Math.round((Date.now() - new Date(iso).getTime()) / 86400000);
            if (days < 1) return 'today';
            if (days === 1) return '1 day ago';
            return days + ' days ago';
        } catch (e) {
            return '';
        }
    }

    function applyScore(card, score, verdict, scoredAt) {
        card.dataset.score = score;
        var badge = card.querySelector('[data-score-badge]');
        if (!badge) return;

        badge.textContent = score + '/10';
        badge.className = 'research-card__score-badge ' + scoreTierClass(score);
        badge.title = (verdict ? verdict + ' — ' : '') + (scoredAt ? 'Scored ' + relativeTime(scoredAt) : '');
    }

    function fetchExistingScores() {
        var params = new URLSearchParams();
        cards.forEach(function (card) { params.append('ids[]', card.dataset.externalId); });

        return fetch(toolbar.dataset.scoresUrl + '?' + params.toString(), { headers: { Accept: 'application/json' } })
            .then(function (res) { return res.ok ? res.json() : { scores: {} }; })
            .then(function (data) {
                var scores = data.scores || {};
                cards.forEach(function (card) {
                    var entry = scores[card.dataset.externalId];
                    if (entry) applyScore(card, entry.score, entry.verdict, entry.scored_at);
                });
            })
            .catch(function () { /* badges just stay hidden — not fatal */ });
    }

    function scoreOneCard(card) {
        return fetch(card.dataset.scoreUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                Accept: 'application/json',
            },
            body: JSON.stringify({ refresh: false }),
        })
            .then(function (res) { return res.json().then(function (data) { return { ok: res.ok, data: data }; }); })
            .then(function (result) {
                if (result.ok) applyScore(card, result.data.score, result.data.verdict, result.data.scored_at);
            })
            .catch(function () { /* one failed card shouldn't stop the batch */ });
    }

    function scoreAllVisible() {
        var unscored = cards.filter(function (card) {
            return !card.dataset.score && card.style.display !== 'none';
        });

        if (!unscored.length) return;

        if (!window.confirm(
            'This runs ' + unscored.length + ' AI scoring call' + (unscored.length === 1 ? '' : 's')
            + ' against your Ollama Cloud quota. Continue?'
        )) return;

        scoreAllBtn.disabled = true;
        progressEl.classList.remove('d-none');

        var done = 0;
        var total = unscored.length;

        function next() {
            if (done >= total) {
                progressEl.classList.add('d-none');
                scoreAllBtn.disabled = false;
                return;
            }

            progressEl.textContent = 'Scoring ' + (done + 1) + ' / ' + total + '…';

            scoreOneCard(unscored[done]).finally(function () {
                done++;
                next();
            });
        }

        next();
    }

    function applySort() {
        var mode = sortSelect.value;
        var ordered = mode === 'score_desc'
            ? cards.slice().sort(function (a, b) {
                var scoreA = a.dataset.score ? parseFloat(a.dataset.score) : -Infinity;
                var scoreB = b.dataset.score ? parseFloat(b.dataset.score) : -Infinity;
                return scoreB - scoreA;
            })
            : originalOrder;

        ordered.forEach(function (card) { grid.appendChild(card); });
    }

    function applyMinScoreFilter() {
        var threshold = minScoreSelect.value ? parseFloat(minScoreSelect.value) : null;

        cards.forEach(function (card) {
            var score = card.dataset.score ? parseFloat(card.dataset.score) : null;
            var hidden = threshold !== null && (score === null || score < threshold);
            card.style.display = hidden ? 'none' : '';
        });
    }

    scoreAllBtn.addEventListener('click', scoreAllVisible);
    sortSelect.addEventListener('change', applySort);
    minScoreSelect.addEventListener('change', applyMinScoreFilter);

    fetchExistingScores();
});
