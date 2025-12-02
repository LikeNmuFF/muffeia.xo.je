document.addEventListener('DOMContentLoaded', () => {
    const themeToggle = document.getElementById('theme-toggle');
    const body = document.body;

    // 1. Check for saved theme in localStorage
    const savedTheme = localStorage.getItem('theme');
    
    if (savedTheme === 'dark') {
        body.classList.add('dark-mode');
        themeToggle.checked = true;
    } else {
        // Default to light mode
        body.classList.remove('dark-mode');
        themeToggle.checked = false;
    }

    // 2. Add event listener for the toggle
    themeToggle.addEventListener('change', () => {
        if (themeToggle.checked) {
            // Switch to Dark Mode
            body.classList.add('dark-mode');
            localStorage.setItem('theme', 'dark');
        } else {
            // Switch to Light Mode
            body.classList.remove('dark-mode');
            localStorage.setItem('theme', 'light');
        }
    });
});