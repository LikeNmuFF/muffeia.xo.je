// Initialize theme on page load
document.addEventListener('DOMContentLoaded', function() {
    const savedTheme = localStorage.getItem('theme') || 'purple';
    document.body.setAttribute('data-theme', savedTheme);
    
    // Load the theme switcher if it exists
    if (typeof initThemeSwitcher !== 'undefined') {
        initThemeSwitcher();
    }
});

// Function to initialize theme switcher (will be called from mode.js)
function initThemeSwitcher() {
    const toggleContainer = document.getElementById('toggle-mode');
    if (toggleContainer && window.themeSwitcher) {
        // Theme switcher already initialized
        return;
    }
}

