/**
 * Admin AJAX Navigation (PJAX-style)
 */
document.addEventListener('DOMContentLoaded', function () {
    const contentInner = document.querySelector('#ajax-content-inner');
    const loader = document.querySelector('#ajax-loader');
    const sidebar = document.querySelector('#sidebar');

    function loadPage(url, push = true, clickedLink = null) {
        if (loader) loader.classList.remove('hidden');
        
        let originalLinkHtml = '';
        if (clickedLink) {
            originalLinkHtml = clickedLink.innerHTML;
            clickedLink.innerHTML = '<i class="fas fa-spinner fa-spin"></i> ' + originalLinkHtml;
            clickedLink.classList.add('pointer-events-none', 'opacity-70');
        }

        fetch(url, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
            .then(response => {
                if (!response.ok) throw new Error('Network response was not ok');
                return response.text();
            })
            .then(html => {
                const parser = new DOMParser();
                const doc = parser.parseFromString(html, 'text/html');
                
                // 1. Update Title
                document.title = doc.title;
                
                // 2. Extract and Update Main Content
                const newContent = doc.querySelector('#ajax-content-inner');
                if (newContent && contentInner) {
                    contentInner.innerHTML = newContent.innerHTML;
                } else {
                    window.location.href = url;
                    return;
                }

                // 3. Update URL
                if (push) {
                    history.pushState({ url: url }, doc.title, url);
                }

                // 4. Update Sidebar Active State
                updateSidebarActiveState(url);

                // 5. Re-initialize Scripts
                reinitializeScripts();

                // 6. Scroll to top
                window.scrollTo(0, 0);
                
                // 7. Hide sidebar on mobile if it was open
                if (window.innerWidth < 1024 && sidebar && !sidebar.classList.contains('collapsed')) {
                    const toggle = document.getElementById('sidebarToggle');
                    if (toggle) toggle.click();
                }
            })
            .catch(error => {
                console.error('Fetch error:', error);
                window.location.href = url; 
            })
            .finally(() => {
                if (loader) loader.classList.add('hidden');
                if (clickedLink) {
                    clickedLink.innerHTML = originalLinkHtml;
                    clickedLink.classList.remove('pointer-events-none', 'opacity-70');
                }
            });
    }

    function reinitializeScripts() {
        // TinyMCE Re-init
        if (window.initTinyMCE) window.initTinyMCE();
        
        // Admin UI Re-init (Sidebar, toggles, etc.)
        if (window.initAdminUI) window.initAdminUI();

        // Close dropdowns
        document.querySelectorAll('.notification-dropdown-menu').forEach(m => m.classList.add('hidden'));
        
        // Handle scripts in content
        const scripts = contentInner.querySelectorAll('script');
        scripts.forEach(script => {
            const newScript = document.createElement('script');
            Array.from(script.attributes).forEach(attr => newScript.setAttribute(attr.name, attr.value));
            
            if (script.src) {
                newScript.src = script.src;
            } else {
                newScript.textContent = script.textContent;
            }
            
            script.parentNode.replaceChild(newScript, script);
        });
        
        // Re-dismiss alerts if any
        if (window.initAlertDismissal) window.initAlertDismissal();
    }

    function updateSidebarActiveState(url) {
        if (!sidebar) return;
        const links = sidebar.querySelectorAll('a');
        
        let targetUrl;
        try {
            targetUrl = new URL(url, window.location.origin);
        } catch(e) {
            return;
        }

        links.forEach(link => {
            try {
                const linkUrl = new URL(link.getAttribute('href'), window.location.origin);
                
                // Remove active classes
                link.classList.remove('bg-gray-700');
                
                // Exact match first
                if (targetUrl.pathname === linkUrl.pathname) {
                    link.classList.add('bg-gray-700');
                }
            } catch(e) {}
        });
    }

    // Intercept clicks
    document.addEventListener('click', function (e) {
        const link = e.target.closest('a');
        if (!link) return;

        const url = link.getAttribute('href');
        if (!url || url.startsWith('#') || url.startsWith('javascript:') || link.getAttribute('target') === '_blank') return;

        // Skip specific links
        if (link.hasAttribute('data-no-ajax') || url.includes('logout') || url.includes('api/auth')) return;

        // Only AJAX-load admin internal pages
        try {
            const currentOrigin = window.location.origin;
            const targetUrl = new URL(url, currentOrigin);
            
            if (targetUrl.origin === currentOrigin && targetUrl.pathname.includes('/admin/')) {
                e.preventDefault();
                loadPage(url, true, link);
            }
        } catch(e) {}
    });

    // Handle back/forward
    window.addEventListener('popstate', function (e) {
        if (e.state && e.state.url) {
            loadPage(e.state.url, false);
        } else {
            loadPage(window.location.href, false);
        }
    });
});
