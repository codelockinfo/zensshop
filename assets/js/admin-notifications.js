/**
 * Admin Notifications System
 */

(function() {
    'use strict';

    const notificationBell = document.getElementById('notificationBell');
    const notificationDropdown = document.getElementById('notificationDropdown');
    const notificationCount = document.getElementById('notificationCount');
    const notificationList = document.getElementById('notificationList');
    const markAllReadBtn = document.getElementById('markAllRead');

    if (!notificationBell || !notificationDropdown) return;

    let isOpen = false;

    // Toggle dropdown
    notificationBell.addEventListener('click', function(e) {
        e.stopPropagation();
        isOpen = !isOpen;
        
        if (isOpen) {
            notificationDropdown.classList.remove('hidden');
            loadNotifications();
        } else {
            notificationDropdown.classList.add('hidden');
        }
    });

    // Close dropdown when clicking outside
    document.addEventListener('click', function(e) {
        if (isOpen && !notificationDropdown.contains(e.target) && e.target !== notificationBell) {
            isOpen = false;
            notificationDropdown.classList.add('hidden');
        }
    });

    // Load notification count
    function loadNotificationCount() {
        fetch(BASE_URL + '/api/notifications.php?action=count')
            .then(response => response.json())
            .then(data => {
                const count = data.count || 0;
                if (count > 0) {
                    notificationCount.textContent = count > 99 ? '99+' : count;
                    notificationCount.classList.remove('hidden');
                } else {
                    notificationCount.classList.add('hidden');
                }
            })
            .catch(error => console.error('Error loading notification count:', error));
    }

    // Load notifications
    function loadNotifications() {
        notificationList.innerHTML = '<div class="p-4 text-center text-gray-500"><i class="fas fa-spinner fa-spin"></i> Loading...</div>';

        fetch(BASE_URL + '/api/notifications.php?action=list&limit=10')
            .then(response => response.json())
            .then(data => {
                if (data.notifications && data.notifications.length > 0) {
                    renderNotifications(data.notifications);
                } else {
                    notificationList.innerHTML = '<div class="p-4 text-center text-gray-500">No notifications</div>';
                }
            })
            .catch(error => {
                console.error('Error loading notifications:', error);
                notificationList.innerHTML = '<div class="p-4 text-center text-red-500">Error loading notifications</div>';
            });
    }

    // Render notifications
    function renderNotifications(notifications) {
        notificationList.innerHTML = '';

        notifications.forEach(notification => {
            const item = document.createElement('div');
            item.className = `notification-item ${notification.is_read == 0 ? 'unread' : ''}`;
            item.dataset.id = notification.id;

            const icon = getNotificationIcon(notification.type);
            const timeAgo = getTimeAgo(notification.created_at);

            item.innerHTML = `
                <div class="flex items-start p-4 hover:bg-gray-50 cursor-pointer border-b" onclick="handleNotificationClick(${notification.id}, '${notification.link || ''}')">
                    <div class="flex-shrink-0 mr-3">
                        <div class="w-10 h-10 rounded-full ${getNotificationColor(notification.type)} flex items-center justify-center">
                            <i class="${icon} text-white"></i>
                        </div>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium text-gray-900">${notification.title}</p>
                        <p class="text-sm text-gray-600 mt-1">${notification.message}</p>
                        <p class="text-xs text-gray-400 mt-1">${timeAgo}</p>
                    </div>
                    ${notification.is_read == 0 ? '<div class="flex-shrink-0 ml-2"><div class="w-2 h-2 bg-blue-500 rounded-full"></div></div>' : ''}
                </div>
            `;

            notificationList.appendChild(item);
        });
    }

    // Get notification icon based on type
    function getNotificationIcon(type) {
        const icons = {
            'order': 'fas fa-shopping-cart',
            'customer': 'fas fa-user-plus',
            'subscriber': 'fas fa-envelope',
            'default': 'fas fa-bell'
        };
        return icons[type] || icons['default'];
    }

    // Get notification color based on type
    function getNotificationColor(type) {
        const colors = {
            'order': 'bg-green-500',
            'customer': 'bg-blue-500',
            'subscriber': 'bg-purple-500',
            'default': 'bg-gray-500'
        };
        return colors[type] || colors['default'];
    }

    // Get time ago
    function getTimeAgo(dateString) {
        const date = new Date(dateString);
        const now = new Date();
        const seconds = Math.floor((now - date) / 1000);

        if (seconds < 60) return 'Just now';
        if (seconds < 3600) return Math.floor(seconds / 60) + ' minutes ago';
        if (seconds < 86400) return Math.floor(seconds / 3600) + ' hours ago';
        if (seconds < 604800) return Math.floor(seconds / 86400) + ' days ago';
        return date.toLocaleDateString();
    }

    // Handle notification click
    window.handleNotificationClick = function(id, link) {
        // Mark as read
        fetch(BASE_URL + '/api/notifications.php?action=mark_read', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'id=' + id
        })
        .then(() => {
            loadNotificationCount();
            if (link) {
                window.location.href = BASE_URL + link;
            }
        })
        .catch(error => console.error('Error marking notification as read:', error));
    };

    // Mark all as read
    if (markAllReadBtn) {
        markAllReadBtn.addEventListener('click', function() {
            fetch(BASE_URL + '/api/notifications.php?action=mark_all_read', {
                method: 'POST'
            })
            .then(() => {
                loadNotificationCount();
                loadNotifications();
            })
            .catch(error => console.error('Error marking all as read:', error));
        });
    }

    // Load count on page load
    loadNotificationCount();

    // Refresh count every 30 seconds
    setInterval(loadNotificationCount, 30000);

})();
