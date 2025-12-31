/**
 * Main JavaScript Utilities
 */

// Top Bar Slider
function initTopBarSlider() {
    const slider = document.getElementById('topBarSlider');
    if (!slider) return;
    
    const slides = slider.querySelectorAll('.top-bar-slide');
    if (slides.length === 0) return;
    
    const prevBtn = document.getElementById('topBarPrev');
    const nextBtn = document.getElementById('topBarNext');
    
    let currentSlide = 0;
    const totalSlides = slides.length;
    let autoSlideInterval;
    
    function updateSlider() {
        const translateX = -currentSlide * 100;
        slider.style.transform = `translateX(${translateX}%)`;
    }
    
    function nextSlide() {
        currentSlide = (currentSlide + 1) % totalSlides;
        updateSlider();
        resetAutoSlide();
    }
    
    function prevSlide() {
        currentSlide = (currentSlide - 1 + totalSlides) % totalSlides;
        updateSlider();
        resetAutoSlide();
    }
    
    function resetAutoSlide() {
        clearInterval(autoSlideInterval);
        autoSlideInterval = setInterval(nextSlide, 4000);
    }
    
    // Arrow button event listeners
    if (nextBtn) {
        nextBtn.addEventListener('click', nextSlide);
    }
    
    if (prevBtn) {
        prevBtn.addEventListener('click', prevSlide);
    }
    
    // Auto-rotate every 4 seconds
    autoSlideInterval = setInterval(nextSlide, 4000);
    
    // Initialize first slide
    updateSlider();
}

// Mobile Menu Toggle
document.addEventListener('DOMContentLoaded', function() {
    // Initialize top bar slider
    initTopBarSlider();
    
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
    
    // Currency Selector Dropdown
    const currencySelector = document.getElementById('currencySelector');
    const currencyDropdown = document.getElementById('currencyDropdown');
    
    if (currencySelector && currencyDropdown) {
        const selectedFlagImg = document.getElementById('selectedFlagImg');
        const countryCode = document.getElementById('countryCode');
        const selectedCurrency = document.getElementById('selectedCurrency');
        
        // Toggle dropdown on button click
        currencySelector.addEventListener('click', function(e) {
            e.stopPropagation();
            e.preventDefault();
            currencyDropdown.classList.toggle('hidden');
        });
        
        // Close dropdown when clicking outside
        document.addEventListener('click', function(e) {
            if (currencySelector && currencyDropdown) {
                if (!currencySelector.contains(e.target) && !currencyDropdown.contains(e.target)) {
                    currencyDropdown.classList.add('hidden');
                }
            }
        });
        
        // Handle currency selection
        const currencyOptions = currencyDropdown.querySelectorAll('.currency-option');
        currencyOptions.forEach(option => {
            option.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                const flag = this.getAttribute('data-flag');
                const code = this.getAttribute('data-code');
                const currency = this.getAttribute('data-currency');
                
                if (flag && code && currency) {
                    if (selectedFlagImg) {
                        selectedFlagImg.src = flag;
                        selectedFlagImg.alt = currency.split(' (')[0];
                    }
                    if (countryCode) {
                        countryCode.textContent = code;
                    }
                    if (selectedCurrency) {
                        selectedCurrency.textContent = currency;
                    }
                }
                
                currencyDropdown.classList.add('hidden');
            });
        });
        
        // Close dropdown on Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && currencyDropdown && !currencyDropdown.classList.contains('hidden')) {
                currencyDropdown.classList.add('hidden');
            }
        });
    }
    
    // Footer Currency Selector Dropdown
    const footerCurrencySelector = document.getElementById('footerCurrencySelector');
    const footerCurrencyDropdown = document.getElementById('footerCurrencyDropdown');
    const footerSelectedFlagImg = document.getElementById('footerSelectedFlagImg');
    const footerCountryCode = document.getElementById('footerCountryCode');
    const footerSelectedCurrency = document.getElementById('footerSelectedCurrency');
    
    if (footerCurrencySelector && footerCurrencyDropdown && footerSelectedFlagImg && footerCountryCode && footerSelectedCurrency) {
        // Toggle dropdown on button click
        footerCurrencySelector.addEventListener('click', function(e) {
            e.stopPropagation();
            e.preventDefault();
            footerCurrencyDropdown.classList.toggle('hidden');
        });
        
        // Close dropdown when clicking outside
        document.addEventListener('click', function(e) {
            if (footerCurrencySelector && footerCurrencyDropdown) {
                if (!footerCurrencySelector.contains(e.target) && !footerCurrencyDropdown.contains(e.target)) {
                    footerCurrencyDropdown.classList.add('hidden');
                }
            }
        });
        
        // Handle currency selection
        const footerCurrencyOptions = footerCurrencyDropdown.querySelectorAll('.footer-currency-option');
        footerCurrencyOptions.forEach(option => {
            option.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                const flag = this.getAttribute('data-flag');
                const code = this.getAttribute('data-code');
                const currency = this.getAttribute('data-currency');
                
                if (flag && code && currency) {
                    footerSelectedFlagImg.src = flag;
                    footerSelectedFlagImg.alt = currency.split(' (')[0];
                    footerCountryCode.textContent = code;
                    footerSelectedCurrency.textContent = currency;
                }
                
                footerCurrencyDropdown.classList.add('hidden');
            });
        });
        
        // Close dropdown on Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && footerCurrencyDropdown && !footerCurrencyDropdown.classList.contains('hidden')) {
                footerCurrencyDropdown.classList.add('hidden');
            }
        });
    }
    
    // Back to Top Button
    const backToTopBtn = document.getElementById('backToTop');
    if (backToTopBtn) {
        // Show/hide button based on scroll position
        window.addEventListener('scroll', function() {
            if (window.pageYOffset > 300) {
                backToTopBtn.classList.remove('hidden');
            } else {
                backToTopBtn.classList.add('hidden');
            }
        });
        
        // Scroll to top when clicked
        backToTopBtn.addEventListener('click', function() {
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
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

