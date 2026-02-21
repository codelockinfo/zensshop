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

        return fetch(url, {
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
                
                // 1.5. Clean up TinyMCE BEFORE replacing content
                if (typeof tinymce !== 'undefined') {
                    try {
                        tinymce.remove();
                    } catch (e) {
                        console.warn("TinyMCE cleanup error:", e);
                    }
                }
                
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
            // Skip scripts already handled or part of the AJAX system
            if (script.hasAttribute('data-no-ajax')) return;
            
            const newScript = document.createElement('script');
            Array.from(script.attributes).forEach(attr => newScript.setAttribute(attr.name, attr.value));
            
            if (script.src) {
                newScript.src = script.src;
            } else {
                newScript.textContent = script.textContent;
            }
            
            script.parentNode.replaceChild(newScript, script);
        });

        // Trigger custom event for page-specific scripts AFTER they are re-executed
        document.dispatchEvent(new CustomEvent('adminPageLoaded'));
        
        // Re-dismiss alerts if any
        if (window.initAlertDismissal) window.initAlertDismissal();
    }

    function updateSidebarActiveState(url) {
        if (!sidebar) return;
        const links = sidebar.querySelectorAll('a');
        const allSubmenus = sidebar.querySelectorAll('.sidebar-submenu');
        
        // 1. Hide all submenus first to ensure only the active one is open
        allSubmenus.forEach(sub => {
            sub.classList.add('hidden');
            const trigger = sub.previousElementSibling;
            if (trigger) {
                const arrow = trigger.querySelector('.fa-chevron-up, .fa-chevron-down');
                if (arrow) {
                    arrow.classList.remove('fa-chevron-up');
                    arrow.classList.add('fa-chevron-down');
                }
            }
        });

        let path;
        try {
            path = new URL(url, window.location.origin).pathname;
        } catch(e) {
            return;
        }

        // Standardize path for comparison (remove trailing slashes, .php)
        const normalizePath = (p) => p.replace(/\.php$/, '').replace(/\/$/, '');
        const targetPath = normalizePath(path);

        links.forEach(link => {
            try {
                const href = link.getAttribute('href');
                if (!href || href === '#' || href.startsWith('javascript:')) return;
                
                const linkPath = normalizePath(new URL(href, window.location.origin).pathname);
                
                // Remove active classes
                link.classList.remove('bg-gray-700');
                
                // Check for match
                // 1. Exact match (ignoring extension/slash)
                // 2. Sub-path match for parent menu items (e.g. /admin/products matches parent Ecommerce)
                if (targetPath === linkPath || (linkPath !== '' && linkPath !== '/admin' && targetPath.startsWith(linkPath))) {
                    link.classList.add('bg-gray-700');
                    
                    // Also ensure parent submenus are expanded
                    let parentSubmenu = link.closest('.sidebar-submenu');
                    if (parentSubmenu) {
                        parentSubmenu.classList.remove('hidden');
                        const trigger = parentSubmenu.previousElementSibling;
                        if (trigger && trigger.classList.contains('sidebar-menu-item')) {
                            const arrow = trigger.querySelector('.fa-chevron-down, .fa-chevron-up');
                            if (arrow) {
                                arrow.classList.remove('fa-chevron-down');
                                arrow.classList.add('fa-chevron-up');
                            }
                        }
                    }
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

    // Intercept Form Submissions
    document.addEventListener('submit', function (e) {
        const form = e.target.closest('form');
        if (!form || form.hasAttribute('data-no-ajax')) return;

        if (typeof tinymce !== 'undefined') {
            tinymce.triggerSave();
        }
        
        e.preventDefault();
        
        const url = form.getAttribute('action') || window.location.href;
        const formData = new FormData(form);
        const submitBtn = form.querySelector('[type="submit"]');

        if (window.setBtnLoading && submitBtn) window.setBtnLoading(submitBtn, true);
        if (loader) loader.classList.remove('hidden');

        fetch(url, {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => {
            // Check if it's a redirect after POST
            if (response.redirected) {
                return loadPage(response.url);
            }
            return response.text();
        })
        .then(html => {
            if (!html) return;
            
            const parser = new DOMParser();
            const doc = parser.parseFromString(html, 'text/html');
            
            const newContent = doc.querySelector('#ajax-content-inner');
            if (newContent && contentInner) {
                contentInner.innerHTML = newContent.innerHTML;
                reinitializeScripts();
                window.scrollTo(0, 0);
            } else {
                window.location.reload();
            }
        })
        .catch(error => {
            console.error('Form Submit Error:', error);
            form.submit(); // Fallback
        })
        .finally(() => {
            if (window.setBtnLoading && submitBtn) window.setBtnLoading(submitBtn, false);
            if (loader) loader.classList.add('hidden');
        });
    });
});
