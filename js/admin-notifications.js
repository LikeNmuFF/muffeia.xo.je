(function() {
    const STORAGE_KEY = 'admin_notif_pos';
    const CHECK_INTERVAL = 30000;

    let btn, badge, isDragging = false, dragOffsetX, dragOffsetY;

    function createButton() {
        if (document.getElementById('admin-notif-btn')) return;

        const style = document.createElement('style');
        style.textContent = `
            #admin-notif-btn {
                position: fixed;
                z-index: 99999;
                width: 56px;
                height: 56px;
                border-radius: 50%;
                background: #dc2626;
                color: #fff;
                border: none;
                box-shadow: 0 4px 16px rgba(220,38,38,0.4);
                cursor: grab;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 22px;
                transition: transform 0.2s, box-shadow 0.2s;
                user-select: none;
                -webkit-user-select: none;
                touch-action: none;
            }
            #admin-notif-btn:hover {
                transform: scale(1.08);
                box-shadow: 0 6px 24px rgba(220,38,38,0.55);
            }
            #admin-notif-btn:active { cursor: grabbing; }
            #admin-notif-badge {
                position: absolute;
                top: -6px;
                right: -6px;
                min-width: 22px;
                height: 22px;
                border-radius: 11px;
                background: #fbbf24;
                color: #000;
                font-size: 12px;
                font-weight: 700;
                line-height: 22px;
                text-align: center;
                padding: 0 5px;
                display: none;
                box-shadow: 0 2px 6px rgba(0,0,0,0.3);
            }
            #admin-notif-badge.bounce {
                animation: adminNotifBounce 0.6s ease infinite alternate;
            }
            @keyframes adminNotifBounce {
                0% { transform: scale(1); }
                100% { transform: scale(1.25); }
            }
        `;
        document.head.appendChild(style);

        btn = document.createElement('button');
        btn.id = 'admin-notif-btn';
        btn.innerHTML = '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1z"/><line x1="4" y1="22" x2="4" y2="15"/></svg>';

        badge = document.createElement('span');
        badge.id = 'admin-notif-badge';
        badge.textContent = '0';
        btn.appendChild(badge);

        document.body.appendChild(btn);
        restorePosition();

        btn.addEventListener('mousedown', startDrag);
        btn.addEventListener('touchstart', startDragTouch, { passive: false });
        document.addEventListener('mousemove', onDrag);
        document.addEventListener('mouseup', stopDrag);
        document.addEventListener('touchmove', onDragTouch, { passive: false });
        document.addEventListener('touchend', stopDrag);

        btn.addEventListener('click', function(e) {
            if (isDragging) return;
            window.location.href = '/pages/admin_dashboard.php';
        });

        fetchCount();
        setInterval(fetchCount, CHECK_INTERVAL);
    }

    function restorePosition() {
        try {
            const saved = localStorage.getItem(STORAGE_KEY);
            if (saved) {
                const pos = JSON.parse(saved);
                btn.style.left = pos.left || 'auto';
                btn.style.top = pos.top || 'auto';
                btn.style.right = pos.right || '20px';
                btn.style.bottom = pos.bottom || '20px';
                return;
            }
        } catch(e) {}
        btn.style.bottom = '20px';
        btn.style.right = '20px';
    }

    function savePosition() {
        try {
            const rect = btn.getBoundingClientRect();
            localStorage.setItem(STORAGE_KEY, JSON.stringify({
                bottom: (window.innerHeight - rect.bottom) + 'px',
                right: (window.innerWidth - rect.right) + 'px',
                top: rect.top + 'px',
                left: rect.left + 'px'
            }));
        } catch(e) {}
    }

    function startDrag(e) {
        isDragging = false;
        const rect = btn.getBoundingClientRect();
        dragOffsetX = e.clientX - rect.left;
        dragOffsetY = e.clientY - rect.top;
        btn.style.cursor = 'grabbing';
        btn.style.transition = 'none';
    }

    function startDragTouch(e) {
        const touch = e.touches[0];
        isDragging = false;
        const rect = btn.getBoundingClientRect();
        dragOffsetX = touch.clientX - rect.left;
        dragOffsetY = touch.clientY - rect.top;
        btn.style.transition = 'none';
    }

    function onDrag(e) {
        if (!btn) return;
        const rect = btn.getBoundingClientRect();
        const dx = e.clientX - dragOffsetX - rect.left;
        const dy = e.clientY - dragOffsetY - rect.top;
        if (Math.abs(dx) > 3 || Math.abs(dy) > 3) isDragging = true;
        if (!isDragging) return;
        btn.style.left = (e.clientX - dragOffsetX) + 'px';
        btn.style.top = (e.clientY - dragOffsetY) + 'px';
        btn.style.right = 'auto';
        btn.style.bottom = 'auto';
    }

    function onDragTouch(e) {
        if (!btn) return;
        const touch = e.touches[0];
        const rect = btn.getBoundingClientRect();
        const dx = touch.clientX - dragOffsetX - rect.left;
        const dy = touch.clientY - dragOffsetY - rect.top;
        if (Math.abs(dx) > 3 || Math.abs(dy) > 3) isDragging = true;
        if (!isDragging) return;
        btn.style.left = (touch.clientX - dragOffsetX) + 'px';
        btn.style.top = (touch.clientY - dragOffsetY) + 'px';
        btn.style.right = 'auto';
        btn.style.bottom = 'auto';
    }

    function stopDrag() {
        if (!btn) return;
        btn.style.cursor = 'grab';
        btn.style.transition = 'transform 0.2s, box-shadow 0.2s';
        if (isDragging) savePosition();
    }

    function fetchCount() {
        fetch('/api/get_pending_reports_count.php')
            .then(r => r.json())
            .then(data => {
                const count = parseInt(data.count) || 0;
                badge.textContent = count;
                if (count > 0) {
                    badge.style.display = 'block';
                    badge.classList.add('bounce');
                    btn.style.boxShadow = '0 0 20px rgba(220,38,38,0.6)';
                } else {
                    badge.style.display = 'none';
                    badge.classList.remove('bounce');
                    btn.style.boxShadow = '';
                }
            })
            .catch(() => {});
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', createButton);
    } else {
        createButton();
    }
})();