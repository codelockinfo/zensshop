/**
 * Optimized Lazy Loading for Sections
 * Version 11.0 - AJAX Newsletter Handling
 */
const sections = [
  { id: "categories-section", endpoint: "categories" },
  { id: "best-selling-section", endpoint: "best-selling" },
  { id: "special-offers-section", endpoint: "special-offers" },
  { id: "videos-section", endpoint: "videos" },
  { id: "trending-section", endpoint: "trending" },
  { id: "philosophy-section", endpoint: "philosophy" },
  { id: "features-section", endpoint: "features" },
  { id: "newsletter-section", endpoint: "newsletter" },
  { id: "footer-features-section", endpoint: "footer_features" },
  { id: "related-products-section", endpoint: "related-products" },
  { id: "recently-viewed-section", endpoint: "recently-viewed" },
];

// Global registry for slider instances to handle shared events like resize
const activeSliders = new Map();
let isCachePolicyAllowed = null;

function initLazyLoading() {
  if (!("IntersectionObserver" in window)) {
    sections.forEach((s) => loadSection(s.id, s.endpoint));
    return;
  }

  const observer = new IntersectionObserver(
    (entries, obs) => {
      entries.forEach((entry) => {
        if (entry.isIntersecting) {
          const section = sections.find((s) => s.id === entry.target.id);
          if (section) {
            loadSection(section.id, section.endpoint);
            obs.unobserve(entry.target);
          }
        }
      });
    },
    { rootMargin: "200px" },
  );

  sections.forEach((s) => {
    const el = document.getElementById(s.id);
    if (el) {
      if (s.id === "categories-section") {
        loadSection(s.id, s.endpoint);
      } else {
        observer.observe(el);
      }
    }
  });

  // Single global resize listener for all sliders
  window.addEventListener(
    "resize",
    debounce(() => {
      activeSliders.forEach((slider) => {
        if (typeof slider.refresh === "function") {
          slider.refresh();
        }
      });
    }, 200),
  );
}

async function loadSection(id, endpoint) {
  const container = document.getElementById(id);
  if (!container || container.dataset.loaded === "true") return;

  // Caching check
  const isCachingAllowed = () => {
    if (isCachePolicyAllowed !== null) return isCachePolicyAllowed;
    const consentRaw = localStorage.getItem("cookieConsent");
    if (!consentRaw) return (isCachePolicyAllowed = false);
    try {
      const data = JSON.parse(consentRaw);
      isCachePolicyAllowed =
        data && data.value === "allowed" && data.expires > Date.now();
    } catch (e) {
      isCachePolicyAllowed = consentRaw === "allowed";
    }
    return isCachePolicyAllowed;
  };

  // 1. Try to load from Cache first
  if (isCachingAllowed()) {
    const cachedHtml = localStorage.getItem("cache_" + endpoint);
    if (cachedHtml) {
      container.innerHTML = cachedHtml;
      container.classList.remove("section-loading");
      container.classList.add("section-announce");
      scheduleInitialization(container);
    }
  }

  container.dataset.loaded = "true";

  try {
    const baseUrl =
      typeof LANDING_BASE_URL !== "undefined"
        ? LANDING_BASE_URL
        : window.location.origin +
          window.location.pathname.substring(
            0,
            window.location.pathname.lastIndexOf("/"),
          );
    const productParam = container.hasAttribute("data-product-id")
      ? `&product_id=${container.getAttribute("data-product-id")}`
      : "";
    const response = await fetch(
      `${baseUrl}/api/sections.php?section=${endpoint}${productParam}`,
    );
    const html = await response.text();

    if (html && html.trim() !== "") {
      if (container.innerHTML.trim() !== html.trim()) {
        container.innerHTML = html;
        container.classList.remove("section-loading");
        container.classList.add("section-announce");
        scheduleInitialization(container);
      }
      if (isCachingAllowed()) {
        localStorage.setItem("cache_" + endpoint, html);
      }
    } else {
      // If no content, hide the entire section container to avoid gaps
      container.style.display = "none";
      container.classList.remove("section-loading");
    }
  } catch (error) {
    console.error(`Error loading section ${endpoint}:`, error);
    container.dataset.loaded = "false";
  }
}

function scheduleInitialization(container) {
  if ("requestIdleCallback" in window) {
    requestIdleCallback(() => initializeSectionContent(container));
  } else {
    setTimeout(() => initializeSectionContent(container), 0);
  }
}

function initializeSectionContent(container) {
  const bestSelling = container.querySelector("#bestSellingSlider");
  if (bestSelling)
    setupCustomSlider(bestSelling, "bestSellingPrev", "bestSellingNext");

  const trending = container.querySelector("#trendingSlider");
  if (trending) setupCustomSlider(trending, "trendingPrev", "trendingNext");

  const videoSlider = container.querySelector("#videoSectionSlider");
  if (videoSlider) {
    container.querySelectorAll("video").forEach((v) => {
      v.muted = true;
      v.playsInline = true;
      v.setAttribute("autoplay", "");
      v.play().catch(() => {});
    });
    setupCustomSlider(videoSlider, "videoSectionPrev", "videoSectionNext");
  }

  if (typeof initializeProductCards === "function") {
    initializeProductCards(container);
  }

  // Initialize Newsletter Form if present
  const newsletterForm = container.querySelector("#globalNewsletterForm");
  if (newsletterForm) {
    initGlobalNewsletter(newsletterForm);
  }

  // Related Products Slider
  const relatedSlider = container.querySelector(".people-bought-slider");
  if (relatedSlider && typeof Swiper !== "undefined") {
    new Swiper(relatedSlider, {
        slidesPerView: 1,
        spaceBetween: 20,
        loop: true,
        autoplay: { delay: 5000, disableOnInteraction: false },
        navigation: {
            nextEl: '.people-bought-next',
            prevEl: '.people-bought-prev',
        },
        breakpoints: {
            640: { slidesPerView: 2, spaceBetween: 20 },
            1024: { slidesPerView: 3, spaceBetween: 30 },
            1280: { slidesPerView: 4, spaceBetween: 30 },
        }
    });
  }

  // Recently Viewed Slider
  const recentSlider = container.querySelector(".recently-viewed-slider");
  if (recentSlider && typeof Swiper !== "undefined") {
    new Swiper(recentSlider, {
        slidesPerView: 1,
        spaceBetween: 20,
        loop: true,
        autoplay: { delay: 5000, disableOnInteraction: false },
        navigation: {
            nextEl: '.recently-viewed-next',
            prevEl: '.recently-viewed-prev',
        },
        breakpoints: {
            640: { slidesPerView: 2, spaceBetween: 20 },
            1024: { slidesPerView: 3, spaceBetween: 30 },
            1280: { slidesPerView: 4, spaceBetween: 30 },
        }
    });
  }
}

function initGlobalNewsletter(form) {
  if (form.dataset.initialized === "true") return;
  form.dataset.initialized = "true";

  form.addEventListener("submit", async function (e) {
    e.preventDefault();

    const emailInput = form.querySelector('input[name="email"]');
    const submitBtn = form.querySelector('button[type="submit"]');
    const messageDiv = document.getElementById("globalNewsletterMessage");
    const email = emailInput.value.trim();

    if (!email) return;

    const originalBtnText = submitBtn.innerHTML;
    submitBtn.disabled = true;
    submitBtn.innerHTML =
      '<i class="fas fa-spinner fa-spin mr-2"></i> Subscribing...';
    if (messageDiv) messageDiv.classList.add("hidden");

    try {
      const baseUrl =
        typeof LANDING_BASE_URL !== "undefined"
          ? LANDING_BASE_URL
          : window.location.origin +
            window.location.pathname.substring(
              0,
              window.location.pathname.lastIndexOf("/"),
            );
      const response = await fetch(`${baseUrl}/api/subscribe.php`, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ email: email }),
      });

      const result = await response.json();

      if (messageDiv) {
        messageDiv.classList.remove("hidden");
        if (result.success) {
          messageDiv.className =
            "text-green-700 bg-green-100 border border-green-200 text-sm mb-4 p-3 rounded fade-in";
          messageDiv.textContent =
            result.message || "Thank you for subscribing!";
          emailInput.value = "";
        } else {
          messageDiv.className =
            "text-red-700 bg-red-100 border border-red-200 text-sm mb-4 p-3 rounded fade-in";
          messageDiv.textContent =
            result.message || "Subscription failed. Please try again.";
        }

        // Auto-hide after 5 seconds
        setTimeout(() => {
          messageDiv.style.opacity = "0";
          messageDiv.style.transition = "opacity 0.5s ease-out";
          setTimeout(() => {
            messageDiv.classList.add("hidden");
            messageDiv.style.opacity = "";
            messageDiv.style.transition = "";
          }, 500);
        }, 5000);
      }
    } catch (error) {
      console.error("Newsletter error:", error);
      if (messageDiv) {
        messageDiv.classList.remove("hidden");
        messageDiv.className =
          "text-red-700 bg-red-100 border border-red-200 text-sm mb-4 p-3 rounded fade-in";
        messageDiv.textContent = "An error occurred. Please try again later.";

        // Auto-hide after 5 seconds
        setTimeout(() => {
          messageDiv.style.opacity = "0";
          messageDiv.style.transition = "opacity 0.5s ease-out";
          setTimeout(() => {
            messageDiv.classList.add("hidden");
            messageDiv.style.opacity = "";
            messageDiv.style.transition = "";
          }, 500);
        }, 5000);
      }
    } finally {
      submitBtn.disabled = false;
      submitBtn.innerHTML = originalBtnText;
    }
  });
}

function setupCustomSlider(sliderElement, prevBtnId, nextBtnId) {
  if (!sliderElement || sliderElement.dataset.sliderInit === "true") return;
  sliderElement.dataset.sliderInit = "true";

  const wrapper = sliderElement.parentElement;
  const prevBtn = document.getElementById(prevBtnId);
  const nextBtn = document.getElementById(nextBtnId);

  let isDragging = false;
  let startX;
  let currentIndex = 0;
  let metrics = null;

  const getMetrics = (force = false) => {
    if (metrics && !force) return metrics;
    if (!sliderElement.children.length)
      return { itemWidth: 0, gap: 0, maxIndex: 0 };

    const item = sliderElement.children[0];
    const itemWidth = item.offsetWidth;
    const computedGap = window.getComputedStyle(sliderElement).gap;
    const gap = (computedGap && computedGap !== 'normal') ? parseInt(computedGap) : 24; // Default 24 if not set
    
    // Use Math.floor to allow scrolling to the very last partial item
    const visibleCount = Math.floor((wrapper.offsetWidth + gap) / (itemWidth + gap)) || 1;
    const maxIndex = Math.max(0, sliderElement.children.length - visibleCount);

    metrics = { itemWidth, gap, maxIndex, totalItems: sliderElement.children.length };
    return metrics;
  };

  const updatePosition = (smooth = true) => {
    const { itemWidth, gap, maxIndex, totalItems } = getMetrics();
    if (currentIndex > maxIndex) currentIndex = maxIndex;
    if (currentIndex < 0) currentIndex = 0;

    let targetX = -currentIndex * (itemWidth + gap);
    
    // Smart End Alignment: If we are at the last index, align to the right edge to avoid empty space
    // Check if simple scrolling leaves empty space
    if (currentIndex === maxIndex && maxIndex > 0) {
        const totalContentWidth = (totalItems * (itemWidth + gap)) - gap;
        const viewportWidth = wrapper.offsetWidth;
        if (totalContentWidth > viewportWidth) {
            targetX = -(totalContentWidth - viewportWidth);
        }
    }

    sliderElement.style.transition = smooth
      ? "transform 0.5s cubic-bezier(0.2, 0.8, 0.2, 1)"
      : "none";
    sliderElement.style.transform = `translateX(${targetX}px)`;

    if (prevBtn) {
      prevBtn.style.opacity = currentIndex === 0 ? "0" : "1";
      prevBtn.style.pointerEvents = currentIndex === 0 ? "none" : "auto";
      prevBtn.style.visibility = currentIndex === 0 ? "hidden" : "visible";
    }
    if (nextBtn) {
      // Logic for next button visibility needs to consider we might be at the end visually
      nextBtn.style.opacity = currentIndex >= maxIndex ? "0" : "1";
      nextBtn.style.pointerEvents = currentIndex >= maxIndex ? "none" : "auto";
      nextBtn.style.visibility =
        currentIndex >= maxIndex ? "hidden" : "visible";
    }
  };

  const startDragging = (e) => {
    isDragging = true;
    wrapper.style.cursor = "grabbing";
    startX = e.pageX || (e.touches ? e.touches[0].pageX : 0);
    sliderElement.style.transition = "none";

    window.addEventListener("mousemove", moveDragging, { passive: false });
    window.addEventListener("mouseup", stopDragging);
    window.addEventListener("touchmove", moveDragging, { passive: false });
    window.addEventListener("touchend", stopDragging);
  };

  const stopDragging = (e) => {
    if (!isDragging) return;
    isDragging = false;
    wrapper.style.cursor = "grab";

    window.removeEventListener("mousemove", moveDragging);
    window.removeEventListener("mouseup", stopDragging);
    window.removeEventListener("touchmove", moveDragging);
    window.removeEventListener("touchend", stopDragging);

    const endX = e.pageX || (e.changedTouches ? e.changedTouches[0].pageX : 0);
    const movedX = endX - startX;

    if (Math.abs(movedX) > 10) {
      const preventClick = (e) => {
        e.stopImmediatePropagation();
        e.preventDefault();
        window.removeEventListener("click", preventClick, true);
      };
      window.addEventListener("click", preventClick, true);
    }

    const { itemWidth, gap } = getMetrics();
    if (Math.abs(movedX) > 50) {
      const shift = Math.round(movedX / (itemWidth + gap));
      currentIndex -= shift;
    }
    updatePosition();
  };

  const moveDragging = (e) => {
    if (!isDragging) return;
    if (e.cancelable) e.preventDefault();

    const x = e.pageX || (e.touches ? e.touches[0].pageX : 0);
    const walk = x - startX;
    const { itemWidth, gap } = getMetrics();
    const currentX = -currentIndex * (itemWidth + gap);
    sliderElement.style.transform = `translateX(${currentX + walk}px)`;
  };

  wrapper.addEventListener("mousedown", startDragging);
  wrapper.addEventListener("touchstart", startDragging, { passive: true });

  sliderElement
    .querySelectorAll("img, a")
    .forEach((el) => el.setAttribute("draggable", "false"));

  if (prevBtn && nextBtn) {
    prevBtn.onclick = (e) => {
      e.preventDefault();
      if (currentIndex > 0) {
        currentIndex--;
        updatePosition();
      }
    };
    nextBtn.onclick = (e) => {
      e.preventDefault();
      const { maxIndex } = getMetrics();
      if (currentIndex < maxIndex) {
        currentIndex++;
        updatePosition();
      }
    };
  }

  activeSliders.set(sliderElement.id || Math.random(), {
    refresh: () => {
      metrics = null;
      updatePosition(false);
    },
  });

  updatePosition();
}

function debounce(func, wait) {
  let timeout;
  return function (...args) {
    clearTimeout(timeout);
    timeout = setTimeout(() => func.apply(this, args), wait);
  };
}

document.addEventListener("DOMContentLoaded", initLazyLoading);
