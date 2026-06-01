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

    function injectAnimStyles() {
        if (document.getElementById('badword-anim-css')) return;
        const style = document.createElement('style');
        style.id = 'badword-anim-css';
        style.textContent = `
            @keyframes badwordFlash {
                0% { background-color: rgba(255, 60, 60, 0.15); }
                30% { background-color: rgba(255, 60, 60, 0.25); }
                100% { background-color: transparent; }
            }
            .badword-masking {
                animation: badwordFlash 0.6s ease-out;
            }
        `;
        document.head.appendChild(style);
    }

    function maskWord(word) {
        const len = word.length;
        if (len <= 1) return '*';
        if (len === 2) return word[0] + '*';
        return word[0] + '*'.repeat(len - 2) + word[len - 1];
    }

    function initFilter(words) {
        if (!Array.isArray(words) || words.length === 0) return;

        injectAnimStyles();

        const patternEntries = [];
        for (const w of words) {
            const trimmed = w.trim();
            if (!trimmed) continue;
            const letters = Array.from(trimmed.replace(/[^\p{L}\p{N}]/gu, ''));
            if (letters.length < 3) continue;
            const parts = letters.map(ch => escapeRegexChar(ch));
            const patStr = parts.join('[\\W]*');
            try {
                const re = new RegExp(patStr, 'giu');
                const squish = trimmed.replace(/[^A-Za-z0-9]+/g, '').toLowerCase();
                patternEntries.push({ word: trimmed, re, squish });
            } catch (e) {}
        }

        patternEntries.sort((a, b) => b.squish.length - a.squish.length);
        if (patternEntries.length === 0) return;

        const inputs = Array.from(document.querySelectorAll('textarea, input[type="text"], input[type="search"]'));
        if (inputs.length === 0) return;

        function debounce(fn, wait) {
            let t = null;
            return function(...args) {
                clearTimeout(t);
                t = setTimeout(() => fn.apply(this, args), wait);
            };
        }

        const animating = new WeakSet();

        function triggerAnim(el) {
            if (animating.has(el)) return;
            animating.add(el);
            el.classList.add('badword-masking');
            setTimeout(() => {
                el.classList.remove('badword-masking');
                animating.delete(el);
            }, 500);
        }

        inputs.forEach(el => {
            const action = (el.getAttribute('data-badword-action') || 'mask').toLowerCase();

            const handler = debounce(function() {
                const original = el.value;
                let modified = original;
                let changed = false;

                for (const entry of patternEntries) {
                    modified = modified.replace(entry.re, (match) => {
                        changed = true;
                        return maskWord(match);
                    });
                }

                modified = modified.replace(/\s{2,}/g, ' ');

                if (changed && modified !== original) {
                    const pos = el.selectionStart || 0;
                    el.value = modified;
                    try { el.selectionStart = el.selectionEnd = Math.min(pos, el.value.length); } catch (err) {}
                    triggerAnim(el);
                    showToast('Bad words masked');
                }
            }, 200);

            el.addEventListener('input', handler);
            el.addEventListener('paste', () => setTimeout(handler, 50));
        });

        window.__muffeia_moderation_patterns = patternEntries.map(p => p.word);
    }
});
