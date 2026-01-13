/**
 * Admin Support Messages System
 */

(function() {
    'use strict';

    const supportBell = document.getElementById('supportBell');
    const supportDropdown = document.getElementById('supportDropdown');
    const supportCount = document.getElementById('supportCount');
    const supportList = document.getElementById('supportList');

    if (!supportBell || !supportDropdown) return;

    let isOpen = false;

    // Toggle dropdown
    supportBell.addEventListener('click', function(e) {
        e.stopPropagation();
        isOpen = !isOpen;
        
        if (isOpen) {
            supportDropdown.classList.remove('hidden');
            loadMessages();
        } else {
            supportDropdown.classList.add('hidden');
        }
    });

    // Close dropdown when clicking outside
    document.addEventListener('click', function(e) {
        if (isOpen && !supportDropdown.contains(e.target) && e.target !== supportBell) {
            isOpen = false;
            supportDropdown.classList.add('hidden');
        }
    });

    // Load message count
    function loadMessageCount() {
        fetch(BASE_URL + '/api/support-messages.php?action=count')
            .then(response => response.json())
            .then(data => {
                const count = data.count || 0;
                if (count > 0) {
                    supportCount.textContent = count > 99 ? '99+' : count;
                    supportCount.classList.remove('hidden');
                } else {
                    supportCount.classList.add('hidden');
                }
            })
            .catch(error => console.error('Error loading support count:', error));
    }

    // Load messages
    function loadMessages() {
        supportList.innerHTML = '<div class="p-4 text-center text-gray-500"><i class="fas fa-spinner fa-spin"></i> Loading...</div>';

        fetch(BASE_URL + '/api/support-messages.php?action=list&limit=10')
            .then(response => response.json())
            .then(data => {
                if (data.messages && data.messages.length > 0) {
                    renderMessages(data.messages);
                } else {
                    supportList.innerHTML = '<div class="p-4 text-center text-gray-500">No messages</div>';
                }
            })
            .catch(error => {
                console.error('Error loading messages:', error);
                supportList.innerHTML = '<div class="p-4 text-center text-red-500">Error loading messages</div>';
            });
    }

    // Render messages
    function renderMessages(messages) {
        supportList.innerHTML = '';

        messages.forEach(message => {
            const item = document.createElement('div');
            item.className = `message-item ${message.status === 'open' ? 'unread' : ''}`;

            const statusColor = message.status === 'open' ? 'bg-red-100 text-red-800' : 
                               (message.status === 'replied' ? 'bg-blue-100 text-blue-800' : 'bg-green-100 text-green-800');
            
            const timeAgo = getTimeAgo(message.created_at);

            item.innerHTML = `
                <a href="${BASE_URL}/admin/support.php" class="block p-4 hover:bg-gray-50 border-b">
                    <div class="flex items-start justify-between mb-2">
                        <h4 class="text-sm font-semibold text-gray-900 flex-1">${message.subject}</h4>
                        <span class="px-2 py-0.5 text-xs rounded ${statusColor} ml-2">${message.status}</span>
                    </div>
                    <p class="text-sm text-gray-600 mb-1">
                        <i class="fas fa-user text-xs mr-1"></i> ${message.customer_name}
                    </p>
                    <p class="text-sm text-gray-500 line-clamp-2 mb-2">${message.message}</p>
                    <p class="text-xs text-gray-400">
                        <i class="far fa-clock mr-1"></i> ${timeAgo}
                    </p>
                </a>
            `;

            supportList.appendChild(item);
        });
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

    // Load count on page load
    loadMessageCount();

    // Refresh count every 30 seconds
    setInterval(loadMessageCount, 30000);

})();
