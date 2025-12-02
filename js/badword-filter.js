// Client-side badword filter: fetches badwords and auto-sanitizes inputs as the user types.
// Supports masking (default) or deletion when element has data-badword-action="delete".

document.addEventListener('DOMContentLoaded', () => {
    const endpoint = window.location.origin + '/api/get_badwords.php';
    fetch(endpoint, {cache: 'no-store'})
        .then(res => res.json())
        .then(words => initFilter(words || []))
        .catch(err => {
            console.warn('Failed to load badwords list:', err);
        });

    function escapeRegexChar(c) {
        return c.replace(/[-/\\^$*+?.()|[\]{}]/g, '\\$&');
    }

    function createPatternFromWord(word) {
        // Build a fuzzy pattern that allows non-alphanumeric characters between letters
        // e.g. "bad" -> b\W*a\W*d
        const letters = Array.from(word.replace(/[^\p{L}\p{N}]/gu, ''));
        if (letters.length === 0) return null;
        const parts = letters.map(ch => escapeRegexChar(ch));
        const pattern = parts.join('\\W*');
        // Use ASCII word-boundary guards so we don't match substrings inside larger words
        // e.g. don't match 'ass' inside 'massive'. We use lookarounds to ensure the match is
        // not preceded or followed by an ASCII letter/digit. This avoids false positives while
        // still allowing obfuscation with punctuation (e.g. b.a.d)
        try {
            return new RegExp('(?<![A-Za-z0-9])' + pattern + '(?![A-Za-z0-9])', 'giu');
        } catch (err) {
            // If lookbehind isn't supported, fall back to naive pattern with word boundaries
            return new RegExp('\\b' + pattern + '\\b', 'giu');
        }
    }

    // Simple toast for showing messages when bad words are removed
    function showToast(msg) {
        let toast = document.getElementById('badword-toast');
        if (!toast) {
            toast = document.createElement('div');
            toast.id = 'badword-toast';
            Object.assign(toast.style, {
                position: 'fixed',
                right: '20px',
                bottom: '20px',
                padding: '10px 14px',
                background: 'rgba(0,0,0,0.85)',
                color: '#fff',
                borderRadius: '8px',
                zIndex: 99999,
                fontSize: '14px',
                opacity: '0',
                transition: 'opacity 260ms ease-in-out'
            });
            document.body.appendChild(toast);
        }
        toast.textContent = msg;
        toast.style.opacity = '1';
        clearTimeout(toast._hideTimer);
        toast._hideTimer = setTimeout(() => {
            toast.style.opacity = '0';
        }, 2000);
    }

    function initFilter(words) {
        if (!Array.isArray(words) || words.length === 0) return;
        // Build token-aware patterns: each pattern matches an entire token (from ^ to $)
        // so we don't match substrings inside larger words (e.g. 'ass' inside 'massive').
        const patterns = words
            .map(w => w.trim())
            .filter(Boolean)
            .map(w => {
                const letters = Array.from(w.replace(/[^\p{L}\p{N}]/gu, ''));
                if (letters.length === 0) return null;
                const parts = letters.map(ch => escapeRegexChar(ch));
                const tokenPattern = parts.join('\\W*');
                // Anchored pattern to match an entire token only
                let re = null;
                try {
                    re = new RegExp('^' + tokenPattern + '$', 'iu');
                } catch (err) {
                    // fallback to simple word boundary pattern
                    re = new RegExp('\\b' + tokenPattern + '\\b', 'iu');
                }
                // also store a squished form for direct comparison
                const squish = w.replace(/[^A-Za-z0-9]+/g, '').toLowerCase();
                return { word: w, reToken: re, squish };
            })
            .filter(o => o !== null);

        // Target textareas and single-line text inputs
        const inputs = Array.from(document.querySelectorAll('textarea, input[type="text"], input[type="search"]'));
        if (inputs.length === 0) return;

        function debounce(fn, wait) {
            let t = null;
            return function(...args) {
                clearTimeout(t);
                t = setTimeout(() => fn.apply(this, args), wait);
            };
        }

        inputs.forEach(el => {
            const action = (el.getAttribute('data-badword-action') || 'mask').toLowerCase(); // 'mask' or 'delete'

            const handler = debounce(function() {
                const original = el.value;
                let modified = original;
                let changed = false;

                // Tokenize input preserving whitespace/separators so we can operate per-token
                const tokens = modified.split(/(\s+)/);
                for (let i = 0; i < tokens.length; i++) {
                    const tok = tokens[i];
                    if (!tok || /^\s+$/.test(tok)) continue; // skip whitespace

                    // Compute squished token (remove non-alnum)
                    const t_squish = tok.replace(/[^A-Za-z0-9]+/g, '').toLowerCase();
                    let isBad = false;
                    for (const p of patterns) {
                        if (!p) continue;
                        // direct squish equality (handles spaced letters like f u c k -> fuck)
                        if (t_squish && p.squish && t_squish === p.squish) { isBad = true; break; }

                        // token-level obfuscation match (anchored)
                        try {
                            if (p.reToken.test(tok)) { isBad = true; break; }
                        } catch (err) {
                            // ignore regex errors for a single pattern
                        }
                    }

                    if (isBad) {
                        changed = true;
                        tokens[i] = (action === 'delete') ? '' : '[removed]';
                    }
                }
                modified = tokens.join('');

                if (action !== 'delete') {
                    // Collapse multiple [removed] into single and trim excessive spaces
                    modified = modified.replace(/(\s*\[removed\]\s*)+/gi, ' [removed] ');
                }
                // Reduce repeated whitespace
                modified = modified.replace(/\s{2,}/g, ' ');

                if (changed && modified !== original) {
                    const pos = el.selectionStart || 0;
                    el.value = modified;
                    try { el.selectionStart = el.selectionEnd = Math.min(pos, el.value.length); } catch (err) {}
                    showToast('Bad words removed');
                }
            }, 200);

            el.addEventListener('input', handler);
            el.addEventListener('paste', () => setTimeout(handler, 50));
        });

        // Expose for debugging
        window.__muffeia_moderation_patterns = patterns.map(p => p.word);
    }
});
