(function() {
    const DESCRIBE_PLACEHOLDER = 'Describe your problem...\n\nTip: Use #tag for tags, ##Category for category';

    document.addEventListener('DOMContentLoaded', function() {
        const textarea = document.getElementById('description');
        if (!textarea) return;

        if (textarea.placeholder === 'Describe your problem in detail...') {
            textarea.placeholder = DESCRIBE_PLACEHOLDER;
        }

        const detectedSection = document.createElement('div');
        detectedSection.id = 'detected-items';
        detectedSection.className = 'detected-section';
        detectedSection.style.display = 'none';
        textarea.parentNode.appendChild(detectedSection);

        const inputHandler = debounce(function() {
            const text = textarea.value;
            const tags = detectTags(text);
            const cat = detectCategory(text);
            renderDetected(detectedSection, tags, cat, window.AVAILABLE_CATEGORIES || []);
        }, 100);

        textarea.addEventListener('input', inputHandler);
        textarea.addEventListener('change', inputHandler);
    });

    function detectTags(text) {
        const regex = /(?:^|\s)#(\w+)/g;
        const tags = [];
        let match;
        while ((match = regex.exec(text)) !== null) {
            const tag = match[1];
            if (tag.length >= 2 && !/^\d+$/.test(tag)) {
                tags.push(tag);
            }
        }
        return [...new Set(tags)];
    }

    function detectCategory(text) {
        const regex = /##([\w\s]+?)(?=\s*#|$)/;
        const match = text.match(regex);
        if (match && match[1].trim().length > 0) {
            return match[1].trim();
        }
        return null;
    }

    function renderDetected(container, tags, category, availableCategories) {
        if (tags.length === 0 && !category) {
            container.style.display = 'none';
            return;
        }

        container.style.display = 'block';
        let html = '';

        if (tags.length > 0) {
            html += '<div class="detected-row"><span class="detected-label"><i class="fas fa-tags"></i> Tags:</span>';
            tags.forEach(function(t) {
                html += '<span class="detected-badge detected-tag">#' + escapeHtml(t) + '</span>';
            });
            html += '</div>';
        }

        if (category) {
            const isValid = availableCategories.some(function(c) {
                return c.name.toLowerCase() === category.toLowerCase();
            });
            const catClass = isValid ? 'detected-category-valid' : 'detected-category-invalid';
            html += '<div class="detected-row"><span class="detected-label"><i class="fas fa-folder"></i> Category:</span>';
            html += '<span class="detected-badge ' + catClass + '">' + escapeHtml(category) + '</span>';
            if (!isValid) {
                const catNames = availableCategories.map(function(c) { return c.name; }).join(', ');
                html += '<span class="detected-error">Not found. Available: ' + escapeHtml(catNames) + '</span>';
            }
            html += '</div>';
        }

        container.innerHTML = html;
    }

    function escapeHtml(str) {
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(str));
        return div.innerHTML;
    }

    function debounce(fn, delay) {
        var timer = null;
        return function() {
            var context = this;
            var args = arguments;
            clearTimeout(timer);
            timer = setTimeout(function() {
                fn.apply(context, args);
            }, delay);
        };
    }
})();
