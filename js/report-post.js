function reportPost(problemId, csrfToken) {
    const overlay = document.createElement('div');
    overlay.className = 'modal-overlay';
    overlay.innerHTML = `
        <div class="modal report-modal">
            <div class="modal-header">
                <h3><i class="fas fa-flag"></i> Report Post</h3>
                <button class="modal-close" onclick="this.closest('.modal-overlay').remove()">&times;</button>
            </div>
            <div class="modal-body">
                <p style="margin-bottom: 16px; color: var(--clr-text-secondary);">Why are you reporting this post?</p>
                <select id="report-reason" class="form-select" style="width: 100%; padding: 10px; border-radius: 8px; border: 1px solid var(--clr-border-theme); background: var(--clr-bg-theme); color: var(--clr-text-primary); margin-bottom: 12px;">
                    <option value="spam">Spam</option>
                    <option value="harassment">Harassment</option>
                    <option value="inappropriate">Inappropriate content</option>
                    <option value="off_topic">Off-topic</option>
                    <option value="other">Other</option>
                </select>
                <textarea id="report-details" class="form-textarea" placeholder="Additional details (optional)" style="width: 100%; padding: 10px; border-radius: 8px; border: 1px solid var(--clr-border-theme); background: var(--clr-bg-theme); color: var(--clr-text-primary); resize: vertical; min-height: 80px; margin-bottom: 16px;"></textarea>
                <div style="display: flex; gap: 8px; justify-content: flex-end;">
                    <button class="btn btn-secondary" onclick="this.closest('.modal-overlay').remove()">Cancel</button>
                    <button class="btn btn-primary" id="submit-report-btn" onclick="submitReport(${problemId}, '${csrfToken}')">
                        <i class="fas fa-paper-plane"></i> Submit Report
                    </button>
                </div>
                <div id="report-feedback" style="margin-top: 12px; display: none;"></div>
            </div>
        </div>
    `;
    document.body.appendChild(overlay);
    requestAnimationFrame(() => overlay.classList.add('active'));
}

function submitReport(problemId, csrfToken) {
    const reason = document.getElementById('report-reason').value;
    const details = document.getElementById('report-details').value;
    const btn = document.getElementById('submit-report-btn');
    const feedback = document.getElementById('report-feedback');

    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Submitting...';

    fetch('/api/report_post.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `problem_id=${problemId}&reason=${encodeURIComponent(reason)}&details=${encodeURIComponent(details)}&csrf_token=${encodeURIComponent(csrfToken)}`
    })
    .then(r => r.json())
    .then(data => {
        feedback.style.display = 'block';
        if (data.success) {
            feedback.className = 'success-message';
            feedback.innerHTML = '<i class="fas fa-check-circle"></i> ' + data.message;
            if (data.csrf_token) {
                const meta = document.querySelector('meta[name="csrf-token"]');
                if (meta) meta.content = data.csrf_token;
            }
            setTimeout(() => btn.closest('.modal-overlay').remove(), 1500);
        } else {
            feedback.className = 'error-message';
            feedback.innerHTML = '<i class="fas fa-exclamation-circle"></i> ' + data.message;
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-paper-plane"></i> Submit Report';
        }
    })
    .catch(err => {
        feedback.style.display = 'block';
        feedback.className = 'error-message';
        feedback.innerHTML = '<i class="fas fa-exclamation-circle"></i> Network error. Please try again.';
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-paper-plane"></i> Submit Report';
    });
}
