/**
 * Admin AJAX Navigation (PJAX-style)
 */
document.addEventListener('DOMContentLoaded', function () {
    const contentInner = document.querySelector('#ajax-content-inner');
    const loader = document.querySelector('#ajax-loader');
    const sidebar = document.querySelector('#sidebar');

    function showLoader() { if (loader) loader.classList.remove('hidden'); }
    function hideLoader() { if (loader) loader.classList.add('hidden'); }

    async function loadPage(url, push = true, clickedLink = null) {
        showLoader();
        
        let originalLinkHtml = '';
        if (clickedLink) {
            originalLinkHtml = clickedLink.innerHTML;
            clickedLink.innerHTML = '<i class="fas fa-spinner fa-spin"></i> ' + originalLinkHtml;
            clickedLink.classList.add('pointer-events-none', 'opacity-70');
        }

        try {
            const response = await fetch(url, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });

            if (!response.ok) throw new Error('Network response was not ok');
            const html = await response.text();

            const parser = new DOMParser();
            const doc = parser.parseFromString(html, 'text/html');
            
            // Update Title
            document.title = doc.title;
            
            // Clean up TinyMCE BEFORE replacing content
            if (typeof tinymce !== 'undefined') {
                try { tinymce.remove(); } catch (e) { }
            }
            
            // Extract and Update Main Content
            const newContent = doc.querySelector('#ajax-content-inner');
            if (newContent && contentInner) {
                contentInner.innerHTML = newContent.innerHTML;
            } else {
                window.location.href = url;
                return;
            }

            // Update URL
            if (push) {
                history.pushState({ url: url }, doc.title, url);
            }

            // Update Sidebar Active State
            updateSidebarActiveState(url);

            // Re-initialize Scripts
            await reinitializeScripts(url);

            // Scroll to top
            window.scrollTo(0, 0);
            
            // Hide sidebar on mobile if it was open
            if (window.innerWidth < 1024 && sidebar && !sidebar.classList.contains('collapsed')) {
                const toggle = document.getElementById('sidebarToggle');
                if (toggle) toggle.click();
            }
        } catch (error) {
            window.location.href = url; 
        } finally {
            hideLoader();
            if (clickedLink) {
                clickedLink.innerHTML = originalLinkHtml;
                clickedLink.classList.remove('pointer-events-none', 'opacity-70');
            }
        }
    }

    async function reinitializeScripts(url) {
        // Re-init
        // High-priority Admin UI Re-init
        if (window.initAdminUI) {
            try { window.initAdminUI(); } catch(e) { }
        }

        // Close all existing modals/dropdowns
        document.querySelectorAll('.notification-dropdown-menu').forEach(m => m.classList.add('hidden'));
        
        if (!contentInner) return;
        
        // Find ALL scripts in the response container
        const scripts = Array.from(contentInner.querySelectorAll('script'));
        
        
        for (const script of scripts) {
            if (script.hasAttribute('data-no-ajax')) continue;
            
            try {
                const newScript = document.createElement('script');
                
                // Copy all attributes
                Array.from(script.attributes).forEach(attr => {
                    newScript.setAttribute(attr.name, attr.value);
                });
                
                if (script.src) {
                    // External Script
                    await new Promise((resolve) => {
                        newScript.onload = () => {
                            resolve();
                        };
                        newScript.onerror = () => { 
                            resolve(); 
                        };
                        document.body.appendChild(newScript);
                    });
                } else {
                    // Inline Script
                    newScript.textContent = script.textContent;
                    document.body.appendChild(newScript);
                    // Inline scripts run synchronously
                }
                
                // Clean up the dormant script tag from the DOM
                if (script.parentNode) script.parentNode.removeChild(script);
            } catch (err) {
            }
        }

        // Delay slightly for script execution and definition propagation
        await new Promise(r => setTimeout(r, 100));

        // Call common init functions
        if (window.initTinyMCE) {
            window.initTinyMCE();
        }
        if (window.initAlertDismissal) window.initAlertDismissal();
        
        // Consolidated Auto-Init Logic based on URL
        // We handle both /admin/slug and /admin/slug.php
        let slug = 'dashboard';
        try {
            const urlObj = new URL(url, window.location.origin);
            let path = urlObj.pathname.replace(/\/+$/, '');
            let segments = path.split('/');
            let lastSegment = segments.pop() || 'dashboard';
            slug = lastSegment.replace('.php', '');
        } catch(e) {
            let pathParts = url.split('/');
            let lastPart = pathParts.pop() || pathParts.pop() || 'dashboard';
            slug = lastPart.split('?')[0].replace('.php', '');
        }
        
        // Mapping common slugs to init function names
        const slugMap = {
            'settings': 'initSettingsJS',
            'banner': 'initBannerJS',
            'category': 'initCategoryJS',
            'offers': 'initOffersJS',
            'features': 'initFeaturesJS',
            'footer_features': 'initFooterFeaturesJS',
            'special_offers_settings': 'initOffersJS',
            'system-settings': 'initSettingsJS',
            'banner_settings': 'initBannerJS',
            'homepage_categories_settings': 'initCategoryJS',
            'footer': 'initFooterJS',
            'footer_info': 'initFooterJS'
        };

        let autoInitName = slugMap[slug] || ('init' + slug.split(/[-_]/).map(word => word.charAt(0).toUpperCase() + word.slice(1)).join('') + 'JS');
        

        if (window[autoInitName]) {
            try { 
                window[autoInitName](); 
            } catch(e) { 
            }
        } else {
            if (slug === 'banner' && window.initBannerSettings) window.initBannerSettings();
        }

        // Reset button states
        document.querySelectorAll('.btn-loading').forEach(btn => {
            if (window.setBtnLoading && btn.disabled) window.setBtnLoading(btn, false);
        });

        document.dispatchEvent(new CustomEvent('adminPageLoaded', { detail: { url: url, slug: slug } }));
    }

    function updateSidebarActiveState(url) {
        if (!sidebar) return;
        const links = sidebar.querySelectorAll('a');
        const allSubmenus = sidebar.querySelectorAll('.sidebar-submenu');
        
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
        } catch(e) { return; }

        const normalizePath = (p) => p.replace(/\.php$/, '').replace(/\/$/, '');
        const targetPath = normalizePath(path);

        links.forEach(link => {
            try {
                const href = link.getAttribute('href');
                if (!href || href === '#' || href.startsWith('javascript:')) return;
                
                const linkPath = normalizePath(new URL(href, window.location.origin).pathname);
                link.classList.remove('bg-gray-700');
                
                if (targetPath === linkPath || (linkPath !== '' && linkPath !== '/admin' && targetPath.startsWith(linkPath))) {
                    link.classList.add('bg-gray-700');
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
        if (e.defaultPrevented) return;

        const link = e.target.closest('a');
        if (!link) return;

        const url = link.getAttribute('href');
        if (!url || url.startsWith('#') || url.startsWith('javascript:') || link.getAttribute('target') === '_blank') return;

        if (link.hasAttribute('data-no-ajax') || url.includes('logout') || url.includes('api/auth')) return;

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

    function closeAllModals() {
        const modals = document.querySelectorAll('.fixed, .absolute'); // Generic modal close
        modals.forEach(m => {
            if (m.id && (m.id.includes('Modal') || m.id.includes('modal'))) {
                m.classList.add('hidden');
            }
        });
        document.body.style.overflow = '';
    }

    // Intercept Form Submissions
    document.addEventListener('submit', function (e) {
        if (e.defaultPrevented) return;

        const form = e.target.closest('form');
        if (!form || form.hasAttribute('data-no-ajax')) return;
        if (form.id === 'adminSearchForm') return;

        if (typeof tinymce !== 'undefined') {
            tinymce.triggerSave();
        }
        
        e.preventDefault();
        
        const url = form.getAttribute('action') || window.location.href;
        const formData = new FormData(form);
        const submitBtn = form.querySelector('[type="submit"]');

        showLoader();

        fetch(url, {
            method: 'POST',
            body: formData,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(async response => {
            const finalUrl = response.url;
            const html = await response.text();
            
            if (!html) {
                window.location.href = finalUrl;
                return;
            }

            const parser = new DOMParser();
            const doc = parser.parseFromString(html, 'text/html');
            const newContent = doc.querySelector('#ajax-content-inner');

            if (newContent && contentInner) {
                contentInner.innerHTML = newContent.innerHTML;
                document.title = doc.title;
                if (window.location.href !== finalUrl) {
                    history.pushState({ url: finalUrl }, doc.title, finalUrl);
                }
                closeAllModals();
                await reinitializeScripts(finalUrl);
                updateSidebarActiveState(finalUrl);
                window.scrollTo(0, 0);
            } else {
                window.location.href = finalUrl;
            }
        })
        .catch(error => {
            form.submit();
        })
        .finally(() => {
            if (window.setBtnLoading && submitBtn) window.setBtnLoading(submitBtn, false);
            hideLoader();
        });
    });
});
