/**
 * Unified Shop System - Optimized Product Cards, Filters, and E-commerce Features
 * Single loading state for product grid only; modular, reusable utilities; professional structure.
 */

(function () {
    'use strict';

    // Prevent multiple initializations
    if (window.unifiedShopSystemInitialized) {
        console.log('Unified shop system already initialized, skipping...');
        return;
    }

    // Configuration
    const CONFIG = {
        maxPrice: window.maxPrice || 5000,
        filterDebounceTime: 300,
        sliderAnimationSpeed: 800,
        cartAnimationDelay: 800,
        cartSuccessDelay: 1500,
        paginationDelay: 50,
        currency: '$'
    };

    /**
     * Reusable Utility Functions
     */
    const Utils = {
        /**
         * Debounces a function to limit execution frequency.
         * @param {Function} func - The function to debounce.
         * @param {number} wait - Wait time in ms.
         * @returns {Function} Debounced function.
         */
        debounce(func, wait) {
            let timeout;
            return function executedFunction(...args) {
                const later = () => {
                    clearTimeout(timeout);
                    func(...args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        },

        /**
         * Extracts category slug from current URL path.
         * @returns {string} Category slug or empty string.
         */
        getCategorySlug() {
            const pathSegments = window.location.pathname.split('/');
            const productCatIndex = pathSegments.indexOf('product-cat');
            if (productCatIndex !== -1 && pathSegments.length > productCatIndex + 1) {
                return pathSegments.slice(productCatIndex + 1).join('/');
            }
            return '';
        },

        /**
         * Shows loading state in the specified container (product grid only).
         * Overlays without hiding other sections.
         * @param {HTMLElement} container - The container to show loading in.
         */
        showLoadingState(container) {
            if (!container) return;
            // Ensure overlay doesn't affect parent sections; absolute positioning within container.
            container.innerHTML = `
                <div class="filter-loading-overlay" style="position: relative;">
                    <div class="loading-spinner">
                        <div class="spinner"></div>
                        <p>Loading products...</p>
                    </div>
                </div>
            `;
        },

        /**
         * Shows error message in the specified container.
         * @param {HTMLElement} container - The container to show error in.
         * @param {string} message - Error message.
         */
        showError(container, message = 'An error occurred. Please try again.') {
            if (!container) return;
            container.innerHTML = `
                <div class="alert alert-danger text-center">
                    <i class="fa fa-times-circle"></i>
                    <p>${message}</p>
                    <button class="btn btn-primary" onclick="location.reload()">Refresh Page</button>
                </div>
            `;
        },

        /**
         * Collects form data into URLSearchParams, grouping checkbox arrays.
         * @param {HTMLFormElement} form - The form to collect from.
         * @returns {URLSearchParams} Collected parameters.
         */
        collectFormData(form) {
            if (!form) return new URLSearchParams();
            const params = new URLSearchParams();
            const checkboxGroups = {};

            // Group checkboxes
            form.querySelectorAll('input[type="checkbox"]:checked').forEach(checkbox => {
                const name = checkbox.name.replace('[]', '');
                const value = checkbox.value;
                if (!checkboxGroups[name]) checkboxGroups[name] = [];
                checkboxGroups[name].push(value);
            });

            // Add grouped values
            Object.entries(checkboxGroups).forEach(([name, values]) => {
                if (values.length > 0) {
                    params.append(name, values.join(','));
                }
            });

            // Add other form elements (exclude price_range for separate handling)
            form.querySelectorAll('select, input[type="text"], input[type="hidden"]').forEach(element => {
                if (element.name && element.value && element.name !== 'price_range') {
                    params.set(element.name, element.value);
                }
            });

            return params;
        }
    };

    /**
     * Image Slider System - Handles auto-sliding and lazy loading for product images.
     */
    class ImageSliderSystem {
        constructor() {
            this.observers = new Map();
            this.init();
        }

        init() {
            this.initializeSliders();
            this.setupLazyLoading();
        }

        /**
         * Initializes sliders on all eligible wrappers.
         */
        initializeSliders() {
            document.querySelectorAll('.slider-wrapper[data-slider]:not([data-slider-initialized])').forEach(wrapper => {
                wrapper.setAttribute('data-slider-initialized', 'true');
                this.setupSlider(wrapper);
            });
        }

        /**
         * Sets up sliding logic for a single wrapper.
         * @param {HTMLElement} wrapper - The slider wrapper.
         */
        setupSlider(wrapper) {
            const track = wrapper.querySelector('.slider-track');
            const images = track?.querySelectorAll('.slider-image');
            if (!images || images.length === 0) return;

            let index = 0;
            let intervalId = null;

            // Handle image loading with fallback
            images.forEach(img => {
                if (img.complete) {
                    img.setAttribute('loaded', '');
                } else {
                    img.addEventListener('load', () => img.setAttribute('loaded', ''));
                    img.addEventListener('error', () => {
                        img.src = '/images/no-image.png';
                        img.setAttribute('loaded', '');
                    });
                }
            });

            // If only one image, just ensure it's visible and return
            if (images.length === 1) {
                track.style.transform = 'translateX(0%)';
                track.style.width = '100%';
                images[0].style.minWidth = '100%';
                return;
            }

            // Auto-slide on hover for multiple images
            wrapper.addEventListener('mouseenter', () => {
                if (intervalId) clearInterval(intervalId);
                intervalId = setInterval(() => {
                    index = (index + 1) % images.length;
                    track.style.transform = `translateX(-${index * 100}%)`;
                }, CONFIG.sliderAnimationSpeed);
            });

            wrapper.addEventListener('mouseleave', () => {
                if (intervalId) clearInterval(intervalId);
                index = 0;
                track.style.transform = 'translateX(0%)';
            });
        }

        /**
         * Sets up IntersectionObserver for lazy loading images.
         */
        setupLazyLoading() {
            if (!('IntersectionObserver' in window) || window.imageObserverInitialized) return;
            window.imageObserverInitialized = true;

            const imageObserver = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const img = entry.target;
                        img.classList.remove('lazy');
                        imageObserver.unobserve(img);
                    }
                });
            });

            document.querySelectorAll('.slider-image.lazy').forEach(img => imageObserver.observe(img));
        }

        /**
         * Reinitializes sliders after dynamic content updates.
         */
        reinitialize() {
            this.initializeSliders();
        }
    }

    /**
     * Filter System - Manages filter application, URL updates, and active filter UI.
     */
    class FilterSystem {
        constructor() {
            this.activeFilters = {};
            this.debouncedApply = Utils.debounce(this._applyFilters.bind(this), CONFIG.filterDebounceTime);
            this.init();
        }

        init() {
            this.bindEvents();
            this.initializeFiltersFromURL();
            this.initializePriceRange();
            this.updateActiveFiltersDisplay();
        }

        /**
         * Binds event listeners for form changes, buttons, and navigation.
         */
        bindEvents() {
            const form = document.getElementById('productFilterForm');
            if (!form) return;

            // Form changes
            form.addEventListener('change', (e) => {
                if (e.target.matches('select[name="sortBy"], select[name="show"]')) {
                    this.debouncedApply(1);
                } else if (e.target.matches('input[type="checkbox"]')) {
                    this.handleFilterChange(e.target);
                }
            });

            // Submit button
            const filterButton = form.querySelector('.filter_button, button[type="submit"]');
            if (filterButton) {
                filterButton.addEventListener('click', (e) => {
                    e.preventDefault();
                    this.debouncedApply(1);
                });
            }

            // Active filter removal
            document.addEventListener('click', (e) => {
                const filterTag = e.target.closest('.active-filter-tag');
                if (filterTag) {
                    e.preventDefault();
                    this.removeActiveFilter(filterTag);
                }
            });

            // Clear all filters
            document.addEventListener('click', (e) => {
                if (e.target.closest('#clear-all-filters')) {
                    e.preventDefault();
                    this.clearAllFilters();
                }
            });

            // Browser back/forward (reload for simplicity)
            window.addEventListener('popstate', () => location.reload());
        }

        /**
         * Initializes filters from URL parameters.
         * @returns {boolean} Whether any filters were set.
         */
        initializeFiltersFromURL() {
            const urlParams = new URLSearchParams(window.location.search);
            let hasUrlFilters = false;

            // Handle multi-value params (brands, ratings, discounts)
            ['brand', 'min_rating', 'min_discount'].forEach(param => {
                const values = urlParams.get(param);
                if (values) {
                    values.split(',').forEach(value => {
                        const checkbox = document.querySelector(`input[name="${param}[]"][value="${value}"]`);
                        if (checkbox) {
                            checkbox.checked = true;
                            this.addToActiveFilters(param, value, checkbox.dataset.filterLabel || value);
                            hasUrlFilters = true;
                        }
                    });
                }
            });

            // Price range
            const priceRangeParam = urlParams.get('price_range');
            if (priceRangeParam && priceRangeParam !== `0-${CONFIG.maxPrice}`) {
                const priceInput = document.getElementById('price_range');
                if (priceInput) {
                    priceInput.value = priceRangeParam;
                    const [min, max] = priceRangeParam.split('-');
                    this.addToActiveFilters('price', priceRangeParam, `${CONFIG.currency}${min} - ${CONFIG.currency}${max}`);
                    hasUrlFilters = true;
                }
            }

            // Single-value params (sortBy, show)
            ['sortBy', 'show'].forEach(param => {
                const value = urlParams.get(param);
                const element = document.getElementById(param);
                if (value && element && value !== element.querySelector('option')?.value) {
                    element.value = value;
                    hasUrlFilters = true;
                }
            });

            return hasUrlFilters;
        }

        /**
         * Initializes jQuery UI price range slider.
         */
        initializePriceRange() {
            const slider = document.getElementById('slider-range');
            if (!slider || !window.jQuery || !jQuery.fn.slider) return;

            const minValue = 0;
            const maxValue = CONFIG.maxPrice;
            const currency = slider.dataset.currency || CONFIG.currency;
            let priceRange = `${minValue}-${maxValue}`;
            const priceInput = document.getElementById('price_range');
            if (priceInput?.value) priceRange = priceInput.value;

            const [currentMin, currentMax] = priceRange.split('-').map(Number);

            jQuery(slider).slider({
                range: true,
                min: minValue,
                max: maxValue,
                values: [currentMin, currentMax],
                slide: (event, ui) => {
                    const amountElement = document.getElementById('amount');
                    if (amountElement) amountElement.value = `${currency}${ui.values[0]} - ${currency}${ui.values[1]}`;
                    if (priceInput) priceInput.value = `${ui.values[0]}-${ui.values[1]}`;
                },
                stop: () => this.debouncedApply(1)
            });

            // Set initial display
            const amountElement = document.getElementById('amount');
            if (amountElement) amountElement.value = `${currency}${currentMin} - ${currency}${currentMax}`;
        }

        /**
         * Handles checkbox filter changes.
         * @param {HTMLInputElement} checkbox - The changed checkbox.
         */
        handleFilterChange(checkbox) {
            const filterType = checkbox.dataset.filterType || checkbox.name.replace('[]', '');
            const filterValue = checkbox.value;
            const filterLabel = checkbox.dataset.filterLabel || filterValue;

            if (checkbox.checked) {
                this.addToActiveFilters(filterType, filterValue, filterLabel);
            } else {
                this.removeFromActiveFilters(filterType, filterValue);
            }

            this.updateActiveFiltersDisplay();
            this.debouncedApply(1);
        }

        /**
         * Adds a filter to active state.
         * @param {string} type - Filter type.
         * @param {string} value - Filter value.
         * @param {string} label - Display label.
         */
        addToActiveFilters(type, value, label) {
            if (!this.activeFilters[type]) this.activeFilters[type] = {};
            this.activeFilters[type][value] = label;
        }

        /**
         * Removes a filter from active state.
         * @param {string} type - Filter type.
         * @param {string|null} value - Specific value or null to remove all for type.
         */
        removeFromActiveFilters(type, value = null) {
            if (value === null) {
                delete this.activeFilters[type];
            } else if (this.activeFilters[type]) {
                delete this.activeFilters[type][value];
                if (Object.keys(this.activeFilters[type]).length === 0) {
                    delete this.activeFilters[type];
                }
            }
        }

        /**
         * Removes a specific active filter tag with animation.
         * @param {HTMLElement} filterTag - The filter tag element.
         */
        removeActiveFilter(filterTag) {
            const filterType = filterTag.dataset.filterType;
            const filterValue = filterTag.dataset.filterValue;

            filterTag.classList.add('removing');

            setTimeout(() => {
                const checkbox = document.querySelector(`input[name="${filterType}[]"][value="${filterValue}"]`);
                if (checkbox) checkbox.checked = false;

                if (filterType === 'price') this.resetPriceRange();

                this.removeFromActiveFilters(filterType, filterValue);
                this.updateActiveFiltersDisplay();
                this.debouncedApply(1);
            }, 300);
        }

        /**
         * Clears all filters with animation.
         */
        clearAllFilters() {
            const allFilterTags = document.querySelectorAll('.active-filter-tag');
            allFilterTags.forEach((tag, index) => {
                setTimeout(() => tag.classList.add('removing'), index * 50);
            });

            setTimeout(() => {
                document.querySelectorAll('#productFilterForm input[type="checkbox"]').forEach(cb => cb.checked = false);
                this.resetPriceRange();
                this.activeFilters = {};
                this.updateActiveFiltersDisplay();

                const currentUrl = new URL(window.location);
                currentUrl.search = '';
                history.replaceState({}, '', currentUrl);

                this.debouncedApply(1);
            }, 500);
        }

        /**
         * Resets price range to default.
         */
        resetPriceRange() {
            const slider = document.getElementById('slider-range');
            const priceInput = document.getElementById('price_range');
            const amountDisplay = document.getElementById('amount');

            if (slider && window.jQuery && jQuery.fn.slider) {
                const minDefault = 0;
                const maxDefault = CONFIG.maxPrice;
                jQuery(slider).slider('values', [minDefault, maxDefault]);

                if (priceInput) priceInput.value = '';
                if (amountDisplay) amountDisplay.value = `${CONFIG.currency}${minDefault} - ${CONFIG.currency}${maxDefault}`;
            }
        }

        /**
         * Updates the active filters UI display.
         */
        updateActiveFiltersDisplay() {
            const activeFiltersList = document.getElementById('active-filters-list');
            const clearAllBtn = document.getElementById('clear-all-filters');
            if (!activeFiltersList) return;

            const hasActiveFilters = Object.keys(this.activeFilters).length > 0;

            if (hasActiveFilters) {
                activeFiltersList.style.display = 'flex';
                activeFiltersList.innerHTML = this.generateActiveFiltersHTML();
                if (clearAllBtn) clearAllBtn.style.display = 'inline-block';
            } else {
                activeFiltersList.style.display = 'none';
                activeFiltersList.innerHTML = '';
                if (clearAllBtn) clearAllBtn.style.display = 'none';
            }
        }

        /**
         * Generates HTML for active filter tags.
         * @returns {string} HTML string.
         */
        generateActiveFiltersHTML() {
            let html = '';
            Object.entries(this.activeFilters).forEach(([type, values]) => {
                Object.entries(values).forEach(([value, label]) => {
                    html += `
                        <button class="active-filter-tag ${type}-filter" 
                                data-filter-type="${type}" 
                                data-filter-value="${value}"
                                title="Remove ${label} filter">
                            ${label} <i class="fa fa-times"></i>
                        </button>
                    `;
                });
            });
            return html;
        }

        /**
         * Applies filters via AJAX (debounced internally).
         * @param {number} page - Page number.
         * @private
         */
        _applyFilters(page = 1) {
            const form = document.getElementById('productFilterForm');
            const productGrids = document.getElementById('product-grids');
            if (!form || !productGrids) return;

            Utils.showLoadingState(productGrids); // Single loading state for product grid only

            const params = Utils.collectFormData(form);

            // Add price range
            const priceRange = document.getElementById('price_range');
            if (priceRange?.value && priceRange.value !== `0-${CONFIG.maxPrice}`) {
                params.set('price_range', priceRange.value);
            }

            // Add page and category
            params.set('page', page);
            const categorySlug = Utils.getCategorySlug();
            if (categorySlug) params.set('category_slug', categorySlug);

            // AJAX request
            const filterUrl = document.querySelector('meta[name="filter-route"]')?.content || '/apply-filters';
            fetch(`${filterUrl}?${params.toString()}`, {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
                .then(response => {
                    if (!response.ok) throw new Error(`Server returned ${response.status}`);
                    return response.json();
                })
                .then(data => {
                    if (data.success && productGrids) {
                        productGrids.innerHTML = data.html;
                        this.attachPaginationListeners();

                        // Reinitialize subsystems
                        if (window.imageSliderSystem) window.imageSliderSystem.reinitialize();

                        // Update URL without page=1
                        const currentUrl = new URL(window.location);
                        const newParams = new URLSearchParams();
                        params.forEach((value, key) => {
                            if (key !== 'page' || (key === 'page' && value !== '1')) {
                                newParams.set(key, value);
                            }
                        });
                        const newUrl = `${currentUrl.pathname}?${newParams.toString()}`;
                        history.replaceState({ path: newUrl }, '', newUrl);

                        // Optional message
                        if (data.message && window.Swal) {
                            Swal.fire({
                                icon: 'info',
                                title: 'Filter Results',
                                text: data.message,
                                timer: 3000,
                                showConfirmButton: false
                            });
                        }
                    } else {
                        Utils.showError(productGrids, data.message || 'Failed to apply filters.');
                    }
                })
                .catch(error => {
                    console.error('Filter error:', error);
                    Utils.showError(productGrids, `Failed to apply filters: ${error.message}`);
                });
        }

        /**
         * Attaches click listeners to pagination links.
         */
        attachPaginationListeners() {
            document.querySelectorAll('#product-grids .pagination a').forEach(link => {
                link.addEventListener('click', (e) => {
                    e.preventDefault();
                    const url = new URL(link.href);
                    const page = parseInt(url.searchParams.get('page') || 1);
                    this.debouncedApply(page);
                });
            });
        }
    }

    /**
     * Product Interaction System - Handles cart, wishlist, quick view, and modals.
     */
    class ProductInteractionSystem {
        constructor() {
            this.init();
        }

        init() {
            this.setupCartButtons();
            this.setupWishlistButtons();
            this.setupQuickViewButtons();
            this.setupModalHandling();
        }

        /**
         * Sets up add-to-cart button handlers.
         */
        setupCartButtons() {
            document.addEventListener('click', (e) => {
                const button = e.target.closest('.btn-dark:not(.disabled)');
                if (button?.href?.includes('add-to-cart')) {
                    e.preventDefault();
                    this.handleAddToCart(button);
                }
            });
        }

        /**
         * Animates add-to-cart action.
         * @param {HTMLElement} button - The cart button.
         */
        handleAddToCart(button) {
            if (button.dataset.cartInitialized) return;
            button.dataset.cartInitialized = 'true';

            const originalText = button.innerHTML;
            const originalHref = button.href;

            // Loading
            button.innerHTML = '<i class="fa fa-spinner fa-spin mr-1"></i> Adding...';
            button.classList.add('disabled');
            button.href = 'javascript:void(0)';

            setTimeout(() => {
                // Success
                button.innerHTML = '<i class="fa fa-check mr-1"></i> Added!';
                button.classList.add('btn-success');
                button.classList.remove('btn-dark');

                setTimeout(() => {
                    // Reset
                    button.innerHTML = originalText;
                    button.href = originalHref;
                    button.classList.remove('disabled', 'btn-success');
                    button.classList.add('btn-dark');
                    delete button.dataset.cartInitialized;
                }, CONFIG.cartSuccessDelay);
            }, CONFIG.cartAnimationDelay);
        }

        /**
         * Sets up wishlist toggle handlers.
         */
        setupWishlistButtons() {
            document.addEventListener('click', (e) => {
                const button = e.target.closest('[href*="add-to-wishlist"]');
                if (button && !button.dataset.wishlistInitialized) {
                    e.preventDefault();
                    button.dataset.wishlistInitialized = 'true';
                    this.handleWishlistToggle(button);
                }
            });
        }

        /**
         * Animates wishlist heart toggle.
         * @param {HTMLElement} button - The wishlist button.
         */
        handleWishlistToggle(button) {
            const heartIcon = button.querySelector('i');
            if (heartIcon) {
                const originalColor = heartIcon.style.color;
                heartIcon.style.transform = 'scale(1.3)';
                heartIcon.style.color = 'red';

                setTimeout(() => {
                    heartIcon.style.transform = 'scale(1)';
                    setTimeout(() => {
                        heartIcon.style.color = originalColor;
                        delete button.dataset.wishlistInitialized;
                    }, 1000);
                }, 200);
            }
        }

        /**
         * Sets up quick view button handlers.
         */
        setupQuickViewButtons() {
            document.addEventListener('click', (e) => {
                const button = e.target.closest('[onclick*="productModal"]');
                if (button && !button.dataset.quickviewInitialized) {
                    e.preventDefault();
                    button.dataset.quickviewInitialized = 'true';

                    const onclickAttr = button.getAttribute('onclick');
                    const modalId = onclickAttr.match(/#([^']*)/)?.[1];
                    if (modalId) {
                        const modal = document.getElementById(modalId);
                        if (modal) this.showModal(modal);
                    }

                    setTimeout(() => delete button.dataset.quickviewInitialized, 1000);
                }
            });
        }

        /**
         * Shows a modal using Bootstrap or jQuery.
         * @param {HTMLElement} modal - The modal element.
         */
        showModal(modal) {
            if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                new bootstrap.Modal(modal).show();
            } else if (typeof jQuery !== 'undefined' && jQuery.fn.modal) {
                jQuery(modal).modal('show');
            }
        }

        /**
         * Sets up modal event handlers for accessibility.
         */
        setupModalHandling() {
            document.querySelectorAll('[id^="productModal"]:not([data-modal-initialized])').forEach(modal => {
                modal.dataset.modalInitialized = 'true';

                modal.addEventListener('hidden.bs.modal', () => modal.setAttribute('inert', ''));
                modal.addEventListener('show.bs.modal', () => modal.removeAttribute('inert'));
            });
        }
    }

    /**
     * Main Unified Shop System - Orchestrates subsystems and content observation.
     */
    class UnifiedShopSystem {
        constructor() {
            this.imageSliderSystem = null;
            this.filterSystem = null;
            this.productInteractionSystem = null;
            this.init();
        }

        init() {
            const ready = () => this.initialize();
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', ready);
            } else {
                ready();
            }
        }

        /**
         * Initializes all subsystems and sets up observers.
         */
        initialize() {
            console.log('Initializing unified shop system...');

            this.imageSliderSystem = new ImageSliderSystem();
            this.filterSystem = new FilterSystem();
            this.productInteractionSystem = new ProductInteractionSystem();

            // Global references
            window.imageSliderSystem = this.imageSliderSystem;
            window.filterSystem = this.filterSystem;
            window.productInteractionSystem = this.productInteractionSystem;

            this.setupContentObserver();

            // Initial filter application (loads products via AJAX)
            setTimeout(() => this.filterSystem._applyFilters(1), 100);

            console.log('Unified shop system initialized successfully');
        }

        /**
         * Sets up MutationObserver for dynamic content updates in product grid.
         */
        setupContentObserver() {
            const productGrids = document.getElementById('product-grids');
            if (!productGrids) return;

            const observer = new MutationObserver((mutations) => {
                let shouldReinit = false;
                mutations.forEach((mutation) => {
                    if (mutation.type === 'childList' && mutation.addedNodes.length > 0) {
                        shouldReinit = true;
                    }
                });
                if (shouldReinit && this.imageSliderSystem) {
                    this.imageSliderSystem.reinitialize();
                }
            });

            observer.observe(productGrids, { childList: true, subtree: true });
        }
    }

    // Optimized CSS (injected once)
    const additionalCSS = `
        .filter-loading-overlay {
            position: relative;
            background: rgba(255, 255, 255, 0.9);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1000;
            backdrop-filter: blur(2px);
        }
        .loading-spinner { text-align: center; color: #f7941d; }
        .spinner {
            width: 40px; height: 40px; border: 4px solid #e9ecef; border-top: 4px solid #f7941d;
            border-radius: 50%; animation: spin 1s linear infinite; margin: 0 auto 10px;
        }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
        .loading-spinner p { margin: 0; font-weight: 500; color: #666; }
        .active-filter-tag {
            display: inline-flex; align-items: center; gap: 5px; padding: 4px 8px; margin: 2px;
            background: #f7941d; color: white; border: none; border-radius: 15px; font-size: 12px;
            cursor: pointer; transition: all 0.3s ease;
        }
        .active-filter-tag:hover { background: #e67c00; transform: scale(1.05); }
        .active-filter-tag.removing { animation: filterRemove 0.3s ease-out forwards; }
        @keyframes filterRemove { to { opacity: 0; transform: scale(0.8); } }
        .product-card-container.filtering { transform: scale(0.95); opacity: 0.7; transition: all 0.3s ease; }
        .product-card-container.fade-in { animation: fadeInScale 0.4s ease-out; }
        .product-card-container.fade-out { animation: fadeOutScale 0.3s ease-in; }
        @keyframes fadeInScale { from { opacity: 0; transform: scale(0.8); } to { opacity: 1; transform: scale(1); } }
        @keyframes fadeOutScale { to { opacity: 0; transform: scale(0.8); } }
        .slider-image[loaded] { opacity: 1; transition: opacity 0.3s ease; }
        .slider-image:not([loaded]) { opacity: 0; }
    `;

    // Inject CSS if not present
    if (!document.getElementById('unified-shop-system-styles')) {
        const styleSheet = document.createElement('style');
        styleSheet.id = 'unified-shop-system-styles';
        styleSheet.textContent = additionalCSS;
        document.head.appendChild(styleSheet);
    }

    // Initialize
    window.unifiedShopSystem = new UnifiedShopSystem();
    window.unifiedShopSystemInitialized = true;
})();
