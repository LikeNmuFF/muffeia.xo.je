document.addEventListener('DOMContentLoaded', function() {
    if (document.querySelector('.notification-link')) {
        checkNewNotifications();
        // Check every 30 seconds
        setInterval(checkNewNotifications, 30000);
    }
});

function checkNewNotifications() {
    fetch('check_notifications.php')
        .then(response => response.json())
        .then(data => {
            const notificationCount = document.querySelector('.notification-count');
            if (data.count > 0) {
                if (notificationCount) {
                    notificationCount.textContent = data.count;
                    notificationCount.style.display = 'inline';
                }
            } else {
                if (notificationCount) {
                    notificationCount.style.display = 'none';
                }
            }
        });
}