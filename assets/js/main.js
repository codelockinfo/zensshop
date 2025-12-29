/**
 * Main JavaScript Utilities
 */

// Mobile Menu Toggle
document.addEventListener('DOMContentLoaded', function() {
    const mobileMenuBtn = document.getElementById('mobileMenuBtn');
    const mobileMenu = document.getElementById('mobileMenu');
    
    if (mobileMenuBtn && mobileMenu) {
        mobileMenuBtn.addEventListener('click', function() {
            mobileMenu.classList.toggle('hidden');
        });
    }
    
    // Search Overlay
    const searchBtn = document.getElementById('searchBtn');
    const searchOverlay = document.getElementById('searchOverlay');
    const closeSearch = document.getElementById('closeSearch');
    
    if (searchBtn && searchOverlay) {
        searchBtn.addEventListener('click', function() {
            searchOverlay.classList.remove('hidden');
        });
    }
    
    if (closeSearch && searchOverlay) {
        closeSearch.addEventListener('click', function() {
            searchOverlay.classList.add('hidden');
        });
    }
    
    // Close search on overlay click
    if (searchOverlay) {
        searchOverlay.addEventListener('click', function(e) {
            if (e.target === searchOverlay) {
                searchOverlay.classList.add('hidden');
            }
        });
    }
});

// Smooth Scroll
function smoothScrollTo(element) {
    element.scrollIntoView({
        behavior: 'smooth',
        block: 'start'
    });
}

// Format Currency
function formatCurrency(amount) {
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'USD'
    }).format(amount);
}

// Debounce Function
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

// Check if element is in viewport
function isInViewport(element) {
    const rect = element.getBoundingClientRect();
    return (
        rect.top >= 0 &&
        rect.left >= 0 &&
        rect.bottom <= (window.innerHeight || document.documentElement.clientHeight) &&
        rect.right <= (window.innerWidth || document.documentElement.clientWidth)
    );
}

// Intersection Observer for animations
const observerOptions = {
    threshold: 0.1,
    rootMargin: '0px 0px -50px 0px'
};

const observer = new IntersectionObserver(function(entries) {
    entries.forEach(entry => {
        if (entry.isIntersecting) {
            entry.target.classList.add('fade-in');
            observer.unobserve(entry.target);
        }
    });
}, observerOptions);

// Observe all sections for fade-in animation
document.addEventListener('DOMContentLoaded', function() {
    const sections = document.querySelectorAll('section[id$="-section"]');
    sections.forEach(section => {
        observer.observe(section);
    });
    
    // Mega menu hover handling for better mobile/tablet support
    const megaMenuParents = document.querySelectorAll('.mega-menu-parent');
    megaMenuParents.forEach(parent => {
        const megaMenu = parent.querySelector('.mega-menu');
        if (megaMenu) {
            // Keep menu open when hovering over it
            parent.addEventListener('mouseenter', function() {
                megaMenu.style.opacity = '1';
                megaMenu.style.visibility = 'visible';
                megaMenu.style.transform = 'translateY(0)';
            });
            
            parent.addEventListener('mouseleave', function() {
                megaMenu.style.opacity = '0';
                megaMenu.style.visibility = 'hidden';
                megaMenu.style.transform = 'translateY(-10px)';
            });
        }
    });
    
    // Pages dropdown menu hover handling
    const pagesMenuParents = document.querySelectorAll('.pages-menu-parent');
    
    // Shop menu hover handling
    const shopMenuParents = document.querySelectorAll('.shop-menu-parent');
    shopMenuParents.forEach(parent => {
        const shopDropdown = parent.querySelector('.shop-dropdown');
        if (shopDropdown) {
            parent.addEventListener('mouseenter', function() {
                shopDropdown.classList.remove('hidden');
            });
            parent.addEventListener('mouseleave', function() {
                shopDropdown.classList.add('hidden');
            });
        }
    });
    pagesMenuParents.forEach(parent => {
        const pagesDropdown = parent.querySelector('.pages-dropdown');
        if (pagesDropdown) {
            // Keep menu open when hovering over it
            parent.addEventListener('mouseenter', function() {
                pagesDropdown.style.opacity = '1';
                pagesDropdown.style.visibility = 'visible';
                pagesDropdown.style.transform = 'translateY(0)';
            });
            
            parent.addEventListener('mouseleave', function() {
                pagesDropdown.style.opacity = '0';
                pagesDropdown.style.visibility = 'hidden';
                pagesDropdown.style.transform = 'translateY(-10px)';
            });
        }
    });
});

