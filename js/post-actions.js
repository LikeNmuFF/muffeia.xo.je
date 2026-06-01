function getCsrfToken() {
    const meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? meta.content : '';
}

function toggleBookmark(problemId) {
    const btn = document.querySelector(`.bookmark-btn[onclick*="toggleBookmark(${problemId})"]`) || 
                document.querySelector(`[onclick*="toggleBookmark(${problemId})"]`);
    const icon = btn ? btn.querySelector('i') : null;

    fetch('/api/bookmark_post.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `problem_id=${problemId}&csrf_token=${encodeURIComponent(getCsrfToken())}`
    })
    .then(r => r.json())
    .then(data => {
        if (data.success && icon) {
            icon.className = data.bookmarked ? 'fas fa-bookmark' : 'far fa-bookmark';
            btn.title = data.bookmarked ? 'Remove from saved' : 'Save post';
        }
    })
    .catch(err => console.error('Bookmark error:', err));
}

function deletePost(problemId) {
    if (!confirm('Are you sure you want to delete this post? This cannot be undone.')) {
        return;
    }

    fetch('/api/delete_post.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `problem_id=${problemId}&csrf_token=${encodeURIComponent(getCsrfToken())}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Remove the post from the page
            const postElement = document.querySelector(`[data-problem-id="${problemId}"]`);
            if (postElement) {
                postElement.remove();
            } else {
                // If we're on the problem detail page, redirect to home
                window.location.href = '/index.php';
            }
            // Show success message
            alert(data.message);
        } else {
            alert(data.message);
        }
    })
    .catch(error => {
        console.error('Delete post error:', error);
        if (error.response) {
            error.response.text().then(text => {
                console.error('Server response:', text);
                alert('Error deleting post. Check console for details.');
            });
        } else {
            alert('Error deleting post: ' + error);
        }
    });
}