/**
 * Admin Dashboard JavaScript
 */

document.addEventListener('DOMContentLoaded', function() {
    // Sidebar toggle
    const sidebarToggle = document.getElementById('sidebarToggle');
    const sidebar = document.getElementById('sidebar');
    const contentWrapper = document.querySelector('.admin-content-wrapper');
    
    // Function to check screen size and adjust sidebar
    // function checkScreenSize(){
    //     const isDesktop = window.innerWidth > 1024;

    //     if (isDesktop){
    //         sidebar.classList.add('expanded');
    //         sidebar.classList.remove('collapsed');

    //         if(contentWrapper){
    //             contentWrapper.style.marginLeft = '250px';
    //         }
    //     }else{
    //         sidebar.classLis.add('collapsed');
    //         sidebar.classList.remove('expanded');
    //         if(contentWrapper){
    //             contentWrapper.style.marginLeft = '80px';
    //         }
    //     }
    // }
    
    // window.addEventListener('load', checkScreenSize);
    // window.addEventListener('resize', checkScreenSize);
    
    // Check on window resize
    window.addEventListener('resize', checkScreenSize);
    
    // Create overlay backdrop for sidebar (works on all screen sizes)
    let sidebarOverlay = document.querySelector('.admin-sidebar-overlay');
    if (!sidebarOverlay) {
        sidebarOverlay = document.createElement('div');
        sidebarOverlay.className = 'admin-sidebar-overlay';
        document.body.appendChild(sidebarOverlay);
        
        // Close sidebar when clicking overlay
        sidebarOverlay.addEventListener('click', function() {
            sidebar.classList.add('collapsed');
            sidebar.classList.remove('expanded');
            sidebarOverlay.classList.remove('active');
            if (contentWrapper) {
                contentWrapper.style.marginLeft = '80px';
            }
        });
    }
    
    if (sidebarToggle && sidebar) {
        sidebarToggle.addEventListener('click', function() {
            // On all screen sizes, toggle between collapsed and expanded
            sidebar.classList.toggle('collapsed');
            sidebar.classList.toggle('expanded');
            
            // If collapsing, hide all submenus
            if (sidebar.classList.contains('collapsed')) {
                const submenus = document.querySelectorAll('.sidebar-submenu');
                submenus.forEach(sub => {
                    sub.classList.add('hidden');
                    // Reset arrows if needed
                    const parent = sub.closest('.sidebar-menu-item');
                    if (parent) {
                        const arrow = parent.querySelector('.fa-chevron-down, .fa-chevron-up');
                        if (arrow) {
                            arrow.classList.add('fa-chevron-up');
                            arrow.classList.remove('fa-chevron-down');
                        }
                    }
                });
            }
            
            // Adjust content margin based on sidebar state
            if (sidebar.classList.contains('expanded')) {
                if (contentWrapper) {
                    contentWrapper.style.marginLeft = '220px';
                }
            } else {
                if (contentWrapper) {
                    contentWrapper.style.marginLeft = '80px';
                }
            }
        });
    }
    
    // User profile dropdown hover handling
    const userProfileDropdown = document.querySelector('.user-profile-dropdown');
    if (userProfileDropdown) {
        const dropdownMenu = userProfileDropdown.querySelector('.user-dropdown-menu');
        const trigger = userProfileDropdown.querySelector('.user-profile-trigger');
        
        if (dropdownMenu && trigger) {
            userProfileDropdown.addEventListener('mouseenter', function() {
                dropdownMenu.style.opacity = '1';
                dropdownMenu.style.visibility = 'visible';
                dropdownMenu.style.transform = 'translateY(0)';
            });
            
            userProfileDropdown.addEventListener('mouseleave', function() {
                dropdownMenu.style.opacity = '0';
                dropdownMenu.style.visibility = 'hidden';
                dropdownMenu.style.transform = 'translateY(-10px)';
            });
        }
    }
    
    // Expand sidebar when clicking menu items (if collapsed)
    function expandSidebarIfCollapsed() {
        if (sidebar.classList.contains('collapsed') && !sidebar.classList.contains('expanded')) {
            sidebar.classList.remove('collapsed');
            sidebar.classList.add('expanded');
            if (contentWrapper) {
                contentWrapper.style.marginLeft = '220px';
            }
        }
    }
    
    // Generalized menu toggle for all menus with submenus
    const menuParents = document.querySelectorAll('.sidebar-menu-item');
    menuParents.forEach(function(menuItem) {
        const submenu = menuItem.nextElementSibling;
        const link = menuItem.tagName === 'A' ? menuItem : menuItem.querySelector('a');
        
        if (link) {
            if (submenu && submenu.classList.contains('sidebar-submenu')) {
                // Menu with submenu - toggle behavior
                const arrow = menuItem.querySelector('.fa-chevron-down, .fa-chevron-up');
                
                link.addEventListener('click', function(e) {
                    e.preventDefault(); // Always prevent navigation
                    
                    // Toggle submenu
                    if (submenu.classList.contains('hidden')) {
                        submenu.classList.remove('hidden');
                        if (arrow) {
                            arrow.classList.remove('fa-chevron-up');
                            arrow.classList.add('fa-chevron-down');
                        }
                    } else {
                        submenu.classList.add('hidden');
                        if (arrow) {
                            arrow.classList.add('fa-chevron-up');
                            arrow.classList.remove('fa-chevron-down');
                        }
                    }
                });
                
                // Keep submenu open if on relevant page
                const href = link.getAttribute('href');
                if (href) {
                    const pathParts = href.split('/');
                    const menuName = pathParts[pathParts.length - 2]; // e.g., 'products' from /admin/products/list.php
                    if (window.location.pathname.includes(menuName)) {
                        submenu.classList.remove('hidden');
                        if (arrow) {
                            arrow.classList.remove('fa-chevron-up');
                            arrow.classList.add('fa-chevron-down');
                        }
                    }
                }
            } else {
                // Menu without submenu - navigate only when sidebar is expanded
                link.addEventListener('click', function(e) {
                    if (sidebar.classList.contains('collapsed')) {
                        e.preventDefault();
                    }
                    // If expanded, allow navigation
                });
            }
        }
    });
    
    // Submenu items just navigate
    // No need to expand sidebar
});

