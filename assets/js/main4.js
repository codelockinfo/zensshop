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
document.addEventListener('DOMContentLoaded', function () {
    // Initialize top bar slider
    initTopBarSlider();

    const mobileMenuBtn = document.getElementById('mobileMenuBtn');
    const mobileMenuDrawer = document.getElementById('mobileMenuDrawer');
    const mobileMenuOverlay = document.getElementById('mobileMenuOverlay');

    if (mobileMenuBtn && mobileMenuDrawer) {
        mobileMenuBtn.addEventListener('click', function () {
            openMobileMenu();
        });
    }

    if (mobileMenuOverlay) {
        mobileMenuOverlay.addEventListener('click', function () {
            closeMobileMenu();
        });
    }

    // Close mobile menu on Escape key
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && mobileMenuDrawer && !mobileMenuDrawer.classList.contains('-translate-x-full')) {
            closeMobileMenu();
        }
    });

    // Search Overlay
    const searchBtn = document.getElementById('searchBtn');
    const searchOverlay = document.getElementById('searchOverlay');
    const closeSearch = document.getElementById('closeSearch');

    if (searchBtn && searchOverlay) {
        searchBtn.addEventListener('click', function () {
            searchOverlay.classList.remove('hidden');
        });
    }

    if (closeSearch && searchOverlay) {
        closeSearch.addEventListener('click', function () {
            searchOverlay.classList.add('hidden');
        });
    }

    // Close search on overlay click
    if (searchOverlay) {
        searchOverlay.addEventListener('click', function (e) {
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
        currencySelector.addEventListener('click', function (e) {
            e.stopPropagation();
            e.preventDefault();
            currencyDropdown.classList.toggle('hidden');
        });

        // Close dropdown when clicking outside
        document.addEventListener('click', function (e) {
            if (currencySelector && currencyDropdown) {
                if (!currencySelector.contains(e.target) && !currencyDropdown.contains(e.target)) {
                    currencyDropdown.classList.add('hidden');
                }
            }
        });

        // Handle currency selection
        const currencyOptions = currencyDropdown.querySelectorAll('.currency-option');
        currencyOptions.forEach(option => {
            option.addEventListener('click', function (e) {
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
        document.addEventListener('keydown', function (e) {
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
        footerCurrencySelector.addEventListener('click', function (e) {
            e.stopPropagation();
            e.preventDefault();
            footerCurrencyDropdown.classList.toggle('hidden');
        });

        // Close dropdown when clicking outside
        document.addEventListener('click', function (e) {
            if (footerCurrencySelector && footerCurrencyDropdown) {
                if (!footerCurrencySelector.contains(e.target) && !footerCurrencyDropdown.contains(e.target)) {
                    footerCurrencyDropdown.classList.add('hidden');
                }
            }
        });

        // Handle currency selection
        const footerCurrencyOptions = footerCurrencyDropdown.querySelectorAll('.footer-currency-option');
        footerCurrencyOptions.forEach(option => {
            option.addEventListener('click', function (e) {
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
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && footerCurrencyDropdown && !footerCurrencyDropdown.classList.contains('hidden')) {
                footerCurrencyDropdown.classList.add('hidden');
            }
        });
    }

    // Back to Top Button
    const backToTopBtn = document.getElementById('backToTop');
    if (backToTopBtn) {
        // Show/hide button based on scroll position
        window.addEventListener('scroll', function () {
            if (window.pageYOffset > 300) {
                backToTopBtn.classList.remove('hidden');
            } else {
                backToTopBtn.classList.add('hidden');
            }
        });

        // Scroll to top when clicked
        backToTopBtn.addEventListener('click', function () {
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
function formatCurrency(amount, currencyCode) {
    const symbol = typeof CURRENCY_SYMBOL !== 'undefined' ? CURRENCY_SYMBOL : '₹';
    if (currencyCode === 'USD' && symbol === '$') {
        return new Intl.NumberFormat('en-US', {
            style: 'currency',
            currency: 'USD'
        }).format(amount);
    }
    return symbol + parseFloat(amount).toFixed(2);
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

const observer = new IntersectionObserver(function (entries) {
    entries.forEach(entry => {
        if (entry.isIntersecting) {
            entry.target.classList.add('fade-in');
            observer.unobserve(entry.target);
        }
    });
}, observerOptions);

// Observe all sections for fade-in animation
document.addEventListener('DOMContentLoaded', function () {
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
            parent.addEventListener('mouseenter', function () {
                megaMenu.style.opacity = '1';
                megaMenu.style.visibility = 'visible';
                megaMenu.style.transform = 'translateY(0)';
                megaMenu.style.transform = 'translateX(-50%)';
            });

            parent.addEventListener('mouseleave', function () {
                megaMenu.style.opacity = '0';
                megaMenu.style.visibility = 'hidden';
                megaMenu.style.transform = 'translateY(-10px)';
                megaMenu.style.transform = 'translateX(-50%)';
            });
        }
    });

    // Shop menu hover handling
    const shopMenuParents = document.querySelectorAll('.shop-menu-parent');
    shopMenuParents.forEach(parent => {
        const shopDropdown = parent.querySelector('.shop-dropdown');
        if (shopDropdown) {
            parent.addEventListener('mouseenter', function () {
                shopDropdown.style.opacity = '1';
                shopDropdown.style.visibility = 'visible';
                shopDropdown.style.transform = 'translateY(0)';
                shopDropdown.style.transform = 'translateX(-10%)';
            });
            parent.addEventListener('mouseleave', function () {
                shopDropdown.style.opacity = '0';
                shopDropdown.style.visibility = 'hidden';
                shopDropdown.style.transform = 'translateY(-10px)';
                shopDropdown.style.transform = 'translateX(-10%)';
            });
        }
    });

    // Pages dropdown menu hover handling
    const pagesMenuParents = document.querySelectorAll('.pages-menu-parent');
    pagesMenuParents.forEach(parent => {
        const pagesDropdown = parent.querySelector('.pages-dropdown');
        if (pagesDropdown) {
            // Keep menu open when hovering over it
            parent.addEventListener('mouseenter', function () {
                pagesDropdown.style.opacity = '1';
                pagesDropdown.style.visibility = 'visible';
                pagesDropdown.style.transform = 'translateY(0)';
                pagesDropdown.style.transform = 'translateX(-10%)';
            });

            parent.addEventListener('mouseleave', function () {
                pagesDropdown.style.opacity = '0';
                pagesDropdown.style.visibility = 'hidden';
                pagesDropdown.style.transform = 'translateY(-10px)';
                pagesDropdown.style.transform = 'translateX(-10%)';
            });
        }
    });
});

// Mobile Menu Drawer Functions
function openMobileMenu() {
    const drawer = document.getElementById('mobileMenuDrawer');
    const overlay = document.getElementById('mobileMenuOverlay');
    if (drawer && overlay) {
        drawer.classList.remove('hidden');
        drawer.classList.remove('-translate-x-full');
        drawer.style.transform = 'translateX(0)'; // Reset transform
        overlay.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }
}

function closeMobileMenu() {
    const drawer = document.getElementById('mobileMenuDrawer');
    const overlay = document.getElementById('mobileMenuOverlay');

    // Close all submenus
    const submenus = ['shop', 'products', 'pages', 'shop-layouts', 'shop-pages'];
    submenus.forEach(name => {
        const submenu = document.getElementById(name + 'Submenu');
        if (submenu) {
            submenu.classList.add('translate-x-full');
            submenu.classList.add('hidden');
        }
    });

    if (drawer && overlay) {
        drawer.classList.add('-translate-x-full');
        setTimeout(() => {
            drawer.classList.add('hidden');
        }, 300);
        overlay.classList.add('hidden');
        document.body.style.overflow = '';
    }
}

// Submenu Functions
function openSubmenu(submenuName) {
    const drawer = document.getElementById('mobileMenuDrawer');
    const submenu = document.getElementById(submenuName + 'Submenu');
    if (submenu) {
        // Slide main menu to the left
        if (drawer) {
            drawer.style.transform = 'translateX(-100%)';
        }
        // Slide submenu in from the right
        submenu.classList.remove('hidden');
        setTimeout(() => {
            submenu.classList.remove('translate-x-full');
        }, 10);
    }
}

function closeSubmenu(submenuName) {
    const drawer = document.getElementById('mobileMenuDrawer');
    const submenu = document.getElementById(submenuName + 'Submenu');
    if (submenu) {
        // Slide submenu out to the right
        submenu.classList.add('translate-x-full');
        // Slide main menu back from the left
        if (drawer) {
            drawer.style.transform = 'translateX(0)';
        }
        setTimeout(() => {
            submenu.classList.add('hidden');
        }, 300);
    }
}

function openSubSubmenu(subSubmenuName) {
    const parentSubmenu = document.querySelector('[id$="Submenu"]:not(.hidden):not([class*="translate-x-full"])');
    const subSubmenu = document.getElementById(subSubmenuName + 'Submenu');
    if (subSubmenu) {
        // Slide parent submenu to the left
        if (parentSubmenu && parentSubmenu !== subSubmenu) {
            parentSubmenu.style.transform = 'translateX(-100%)';
        }
        // Slide sub-submenu in from the right
        subSubmenu.classList.remove('hidden');
        setTimeout(() => {
            subSubmenu.classList.remove('translate-x-full');
        }, 10);
    }
}

function closeSubSubmenu(subSubmenuName) {
    const parentSubmenu = document.querySelector('[id$="Submenu"]:not(.hidden):not([class*="translate-x-full"])');
    const subSubmenu = document.getElementById(subSubmenuName + 'Submenu');
    if (subSubmenu) {
        // Slide sub-submenu out to the right
        subSubmenu.classList.add('translate-x-full');
        // Slide parent submenu back from the left
        if (parentSubmenu && parentSubmenu !== subSubmenu) {
            parentSubmenu.style.transform = 'translateX(0)';
        }
        setTimeout(() => {
            subSubmenu.classList.add('hidden');
        }, 300);
    }
}

// Button Loading State Utility
function setBtnLoading(btn, isLoading) {
    if (!btn) return;
    
    if (isLoading) {
        if (!btn.hasAttribute('data-original-html')) {
            btn.setAttribute('data-original-html', btn.innerHTML);
            
            // Lock dimensions
            const width = btn.offsetWidth;
            const height = btn.offsetHeight;
            
            btn.style.width = width + 'px';
            btn.style.height = height + 'px';
            
            // Maintain display type if possible, or force inline-flex for centering
            // If it was block, inline-flex might break layout slightly (e.g. margins), 
            // so let's try to keep it simple but ensure centering.
            // Using grid/place-items-center is also an option, but inline-flex is standard.
            // We'll set justify/align for centering.
            btn.style.display = 'inline-flex';
            btn.style.alignItems = 'center';
            btn.style.justifyContent = 'center';
        }
        btn.disabled = true;
        btn.innerHTML = `<i class="fas fa-spinner fa-spin"></i>`;
        btn.classList.add('opacity-75', 'cursor-not-allowed');
    } else {
        const originalHtml = btn.getAttribute('data-original-html');
        if (originalHtml !== null) {
            btn.innerHTML = originalHtml;
            // Restore dimensions and styles
            btn.style.width = '';
            btn.style.height = '';
            btn.style.display = '';
            btn.style.alignItems = '';
            btn.style.justifyContent = '';
            btn.removeAttribute('data-original-html');
        }
        btn.disabled = false;
        btn.classList.remove('opacity-75', 'cursor-not-allowed');
    }
}

// Make functions globally available
window.openMobileMenu = openMobileMenu;
window.closeMobileMenu = closeMobileMenu;
window.openSubmenu = openSubmenu;
window.closeSubmenu = closeSubmenu;
window.openSubSubmenu = openSubSubmenu;
window.closeSubSubmenu = closeSubSubmenu;
window.setBtnLoading = setBtnLoading;

/**
 * Native Share Function
 */
async function sharePage(title, text, url) {
    // Fallback URL to current if not provided
    const shareUrl = url || window.location.href;
    const shareTitle = title || document.title;
    const shareText = text || 'Check this out!';

    if (navigator.share) {
        try {
            await navigator.share({
                title: shareTitle,
                text: shareText,
                url: shareUrl
            });
            console.log('Successfully shared');
        } catch (error) {
            if (error.name !== 'AbortError') {
                console.error('Error sharing:', error);
            }
        }
    } else {
        // Fallback: Copy to clipboard
        try {
            await navigator.clipboard.writeText(shareUrl);
            console.log('Link copied to clipboard!');
        } catch (err) {
            console.error('Could not copy text: ', err);
            // Last resort: just a prompt
            window.prompt('Copy and share this link:', shareUrl);
        }
    }
}

window.sharePage = sharePage;

