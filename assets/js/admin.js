/**
 * Admin Dashboard JavaScript
 */

document.addEventListener('DOMContentLoaded', function() {
    // Sidebar toggle
    const sidebarToggle = document.getElementById('sidebarToggle');
    const sidebar = document.getElementById('sidebar');
    const contentWrapper = document.querySelector('.admin-content-wrapper');
    
    if (sidebarToggle && sidebar) {
        sidebarToggle.addEventListener('click', function() {
            sidebar.classList.toggle('collapsed');
            if (contentWrapper) {
                if (sidebar.classList.contains('collapsed')) {
                    contentWrapper.style.marginLeft = '80px';
                } else {
                    contentWrapper.style.marginLeft = '260px';
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
    
    // Category menu toggle
    const categoryMenuParent = document.querySelector('.category-menu-parent');
    if (categoryMenuParent) {
        const categoryLink = categoryMenuParent.querySelector('a');
        const submenu = categoryMenuParent.querySelector('.sidebar-submenu');
        const arrow = categoryMenuParent.querySelector('.category-arrow');
        
        if (categoryLink && submenu) {
            categoryLink.addEventListener('click', function(e) {
                if (submenu.classList.contains('hidden')) {
                    e.preventDefault();
                    submenu.classList.remove('hidden');
                    if (arrow) {
                        arrow.classList.remove('fa-chevron-up');
                        arrow.classList.add('fa-chevron-down');
                    }
                }
            });
            
            // Keep submenu open if on category page
            if (window.location.pathname.includes('categories')) {
                submenu.classList.remove('hidden');
                if (arrow) {
                    arrow.classList.remove('fa-chevron-up');
                    arrow.classList.add('fa-chevron-down');
                }
            }
        }
    }
});

