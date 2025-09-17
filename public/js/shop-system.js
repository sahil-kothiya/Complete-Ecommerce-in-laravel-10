/**
 * Unified Shop System - Enhanced with High-Performance JSON Product Rendering
 * Maintains old HTML structure for filters and UI; fetches JSON for products and renders client-side for speed.
 * Single loading state for product grid only; modular, reusable utilities; professional structure.
 * Target: <500ms total page load time for product rendering.
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
        animationSpeed: 300,
        currency: '$',
        endpoints: {
            filterData: '/api/filters'  // Matches HighPerformanceFilterController
        },
        sortOptions: [
            { value: 'latest', text: 'Latest' },
            { value: 'price_low_high', text: 'Price: Low to High' },
            { value: 'price_high_low', text: 'Price: High to Low' },
            { value: 'rating_high_low', text: 'Rating: High to Low' },
            { value: 'name_a_z', text: 'Name: A to Z' },
            { value: 'name_z_a', text: 'Name: Z to A' }
        ],
        showOptions: [
            { value: '12', text: '12' },
            { value: '24', text: '24' },
            { value: '36', text: '36' },
            { value: '48', text: '48' }
        ]
    };

    /**
     * Reusable Utility Functions
     */
    const Utils = {
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

        getCategorySlug() {
            const pathSegments = window.location.pathname.split('/');
            const productCatIndex = pathSegments.indexOf('product-cat');
            if (productCatIndex !== -1 && pathSegments.length > productCatIndex + 1) {
                return pathSegments.slice(productCatIndex + 1).join('/');
            }
            return '';
        },

        showLoadingState(container) {
            if (!container) return;
            container.innerHTML = `
                <div class="filter-loading-overlay" style="position: relative;">
                    <div class="loading-spinner">
                        <div class="spinner"></div>
                        <p>Loading products...</p>
                    </div>
                </div>
            `;
        },

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

        collectFormData(form) {
            if (!form) return new URLSearchParams();
            const params = new URLSearchParams();
            const checkboxGroups = {};

            form.querySelectorAll('input[type="checkbox"]:checked').forEach(checkbox => {
                const name = checkbox.name.replace('[]', '');
                const value = checkbox.value;
                if (!checkboxGroups[name]) checkboxGroups[name] = [];
                checkboxGroups[name].push(value);
            });

            Object.entries(checkboxGroups).forEach(([name, values]) => {
                if (values.length > 0) {
                    let paramName = name;
                    if (name === 'brand') paramName = 'brands';
                    else if (name === 'min_rating') paramName = 'ratings';
                    else if (name === 'min_discount') paramName = 'discounts';
                    params.append(paramName, values.join(','));
                }
            });

            form.querySelectorAll('select, input[type="text"], input[type="hidden"]').forEach(element => {
                if (element.name && element.value && element.name !== 'price_range') {
                    params.set(element.name, element.value);
                }
            });

            return params;
        },

        truncateText(text, maxLength) {
            if (text.length <= maxLength) return text;
            return text.substring(0, maxLength - 3) + '...';
        },

        formatPrice(value, currency = CONFIG.currency) {
            if (typeof value !== 'number' || isNaN(value)) {
                console.warn('Invalid price value:', value);
                return `${currency}0.00`;
            }
            return `${currency}${value.toFixed(2)}`;
        },

        generateStars(rating) {
            let stars = '';
            for (let i = 1; i <= 5; i++) {
                stars += `<i class="fa fa-star${i <= rating ? '' : '-o'}"></i>`;
            }
            return stars;
        },

        // NEW: Populate selects dynamically
        populateSelects() {
            const sortBySelect = document.getElementById('sortBy');
            const showSelect = document.getElementById('show');

            if (sortBySelect) {
                // Clear existing options except first
                Array.from(sortBySelect.options).slice(1).forEach(option => option.remove());
                CONFIG.sortOptions.forEach(option => {
                    const opt = document.createElement('option');
                    opt.value = option.value;
                    opt.textContent = option.text;
                    if (sortBySelect.value === option.value) opt.selected = true;
                    sortBySelect.appendChild(opt);
                });
            }

            if (showSelect) {
                // Clear existing options except first
                Array.from(showSelect.options).slice(1).forEach(option => option.remove());
                CONFIG.showOptions.forEach(option => {
                    const opt = document.createElement('option');
                    opt.value = option.value;
                    opt.textContent = option.text;
                    if (showSelect.value === option.value) opt.selected = true;
                    showSelect.appendChild(opt);
                });
            }
        }
    };

    /**
     * Price Slider Initialization (NEW/FIXED)
     */
    class PriceSlider {
        constructor() {
            this.init();
        }

        init() {
            const sliderRange = document.getElementById('slider-range');
            const amount = document.getElementById('amount');
            if (!sliderRange || !amount) return;

            const minPrice = parseFloat(sliderRange.dataset.min) || 0;
            const maxPrice = parseFloat(sliderRange.dataset.max) || CONFIG.maxPrice;
            let currentMin = minPrice;
            let currentMax = maxPrice;

            // Parse current price range from URL/form
            const currentRange = document.getElementById('price_range')?.value;
            if (currentRange) {
                [currentMin, currentMax] = currentRange.split('-').map(parseFloat);
            }

            // Initialize jQuery UI Slider (assumes jQuery & jQuery UI loaded)
            if (typeof $.ui !== 'undefined' && $.ui.slider) {
                $(sliderRange).slider({
                    range: true,
                    min: minPrice,
                    max: maxPrice,
                    values: [currentMin, currentMax],
                    slide: (event, ui) => {
                        amount.value = `${Utils.formatPrice(ui.values[0])} - ${Utils.formatPrice(ui.values[1])}`;
                        document.getElementById('price_range').value = `${ui.values[0]}-${ui.values[1]}`;
                    },
                    change: (event, ui) => {
                        this.applyFilters();  // Trigger filter update on change
                    }
                });

                amount.value = `${Utils.formatPrice(currentMin)} - ${Utils.formatPrice(currentMax)}`;
            } else {
                console.warn('jQuery UI Slider not loaded. Add <link rel="stylesheet" href="https://code.jquery.com/ui/1.13.2/themes/ui-lightness/jquery-ui.css"> and <script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script> to layout.');
            }
        }

        applyFilters() {
            // Trigger the main filter apply
            window.unifiedShopSystem?.applyFilters();
        }
    }

    /**
     * Image Slider System - Handles auto-sliding and lazy loading for product images.
     * Fixed: On hover, auto-advances one image at a time with full display before next.
     * Pauses on current image when hover ends (no reset to first). Resumes from current on re-hover.
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

        initializeSliders() {
            document.querySelectorAll('.slider-wrapper[data-slider]:not([data-slider-initialized])').forEach(wrapper => {
                wrapper.setAttribute('data-slider-initialized', 'true');
                this.setupSlider(wrapper);
            });
        }

        setupSlider(wrapper) {
            const track = wrapper.querySelector('.slider-track');
            const images = track?.querySelectorAll('.slider-image');
            if (!images || images.length === 0) return;

            let index = 0;
            let intervalId = null;

            // Apply inline styles as requested
            track.style.width = '100%'; // Initial width, will be adjusted dynamically
            track.style.transform = 'translateX(0)'; // Start at first image

            // Ensure proper sizing and loading for images
            images.forEach(img => {
                img.style.flex = '0 0 100%';
                img.style.width = '100%';
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

            // Dynamically set track width based on number of images
            track.style.width = `${100}%`;

            if (images.length === 1) {
                return;
            }

            wrapper.addEventListener('mouseenter', () => {
                if (intervalId) clearInterval(intervalId);
                intervalId = setInterval(() => {
                    index = (index + 1) % images.length;
                    track.style.transform = `translateX(-${index * 100}%)`;
                }, CONFIG.sliderAnimationSpeed);
            });

            wrapper.addEventListener('mouseleave', () => {
                if (intervalId) clearInterval(intervalId);
                // Pause on current image, no reset
            });
        }

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
            this.isLoading = false;
            this.abortController = null;
            this.init();
        }

        init() {
            this.bindEvents();
            this.initializeFiltersFromURL();
            this.initializePriceRange();
            this.updateActiveFiltersDisplay();
            setTimeout(() => this._applyFilters(1), 100);
        }

        bindEvents() {
            const form = document.getElementById('productFilterForm');
            if (!form) return;

            form.addEventListener('change', (e) => {
                if (e.target.matches('select[name="sortBy"], select[name="show"]')) {
                    this.debouncedApply(1);
                } else if (e.target.matches('input[type="checkbox"]')) {
                    this.handleFilterChange(e.target);
                }
            });

            const filterButton = form.querySelector('.filter_button, button[type="submit"]');
            if (filterButton) {
                filterButton.addEventListener('click', (e) => {
                    e.preventDefault();
                    this.debouncedApply(1);
                });
            }

            document.addEventListener('click', (e) => {
                const filterTag = e.target.closest('.active-filter-tag');
                if (filterTag) {
                    e.preventDefault();
                    this.removeActiveFilter(filterTag);
                }
            });

            document.addEventListener('click', (e) => {
                if (e.target.closest('#clear-all-filters')) {
                    e.preventDefault();
                    this.clearAllFilters();
                }
            });

            document.addEventListener('click', (e) => {
                if (e.target.matches('#product-grids .pagination a.page-link')) {
                    e.preventDefault();
                    const page = parseInt(e.target.dataset.page) || 1;
                    this._applyFilters(page);
                }
            });

            window.addEventListener('popstate', () => {
                this.initializeFiltersFromURL();
                this.debouncedApply(1);
            });
        }

        initializeFiltersFromURL() {
            const urlParams = new URLSearchParams(window.location.search);
            let hasUrlFilters = false;

            ['brands', 'ratings', 'discounts'].forEach(param => {
                const values = urlParams.get(param);
                if (values) {
                    const actualParam = param === 'brands' ? 'brand' : (param === 'ratings' ? 'min_rating' : 'min_discount');
                    values.split(',').forEach(value => {
                        const checkbox = document.querySelector(`input[name="${actualParam}[]"][value="${value}"]`);
                        if (checkbox) {
                            checkbox.checked = true;
                            this.addToActiveFilters(actualParam, value, checkbox.dataset.filterLabel || value);
                            hasUrlFilters = true;
                        }
                    });
                }
            });

            const availabilityValues = urlParams.get('availability');
            if (availabilityValues) {
                availabilityValues.split(',').forEach(value => {
                    const checkbox = document.querySelector(`input[name="availability[]"][value="${value}"]`);
                    if (checkbox) {
                        checkbox.checked = true;
                        this.addToActiveFilters('availability', value, checkbox.dataset.filterLabel || value);
                        hasUrlFilters = true;
                    }
                });
            }

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

            const amountElement = document.getElementById('amount');
            if (amountElement) amountElement.value = `${currency}${currentMin} - ${currency}${currentMax}`;
        }

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

        addToActiveFilters(type, value, label) {
            if (!this.activeFilters[type]) this.activeFilters[type] = {};
            this.activeFilters[type][value] = label;
        }

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

        resetPriceRange() {
            const slider = document.getElementById('slider-range');
            const priceInput = document.getElementById('price_range');
            const amountDisplay = document.getElementById('amount');

            if (slider && window.jQuery && jQuery.fn.slider) {
                const minDefault = 0;
                const maxDefault = CONFIG.maxPrice;
                jQuery(slider).slider('values', [minDefault, maxDefault]);

                if (priceInput) priceInput.value = `${minDefault}-${maxDefault}`;
                if (amountDisplay) amountDisplay.value = `${CONFIG.currency}${minDefault} - ${CONFIG.currency}${maxDefault}`;
            }
        }

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

        async _applyFilters(page = 1) {
            if (this.isLoading) return;
            this.isLoading = true;

            const form = document.getElementById('productFilterForm');
            const productGrids = document.getElementById('product-grids');
            if (!form || !productGrids) {
                this.isLoading = false;
                return;
            }

            Utils.showLoadingState(productGrids);

            if (this.abortController) {
                this.abortController.abort();
            }
            this.abortController = new AbortController();

            const params = Utils.collectFormData(form);

            const priceRange = document.getElementById('price_range');
            if (priceRange?.value && priceRange.value !== `0-${CONFIG.maxPrice}`) {
                params.set('price_range', priceRange.value);
            }

            params.set('page', page);

            let endpoint = CONFIG.endpoints.filterData;
            const categorySlug = Utils.getCategorySlug();
            if (categorySlug) {
                endpoint += `/${categorySlug}`;
            }

            try {
                const response = await fetch(`${endpoint}?${params.toString()}`, {
                    method: 'GET',
                    signal: this.abortController.signal,
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });

                if (!response.ok) throw new Error(`Server returned ${response.status}`);

                const data = await response.json();

                if (data.success && productGrids) {
                    const productsHtml = data.products.map(product => this.renderProductCard(product)).join('');
                    const paginationHtml = this.renderPagination(data.pagination || {});

                    productGrids.innerHTML = `
                        <div class="product-grid-container">
                            <div class="product-grid-row">
                                ${productsHtml}
                            </div>
                        </div>
                        ${paginationHtml}
                    `;

                    productGrids.style.opacity = '0.5';
                    setTimeout(() => {
                        productGrids.style.opacity = '1';
                    }, CONFIG.animationSpeed / 2);

                    if (window.imageSliderSystem) window.imageSliderSystem.reinitialize();
                    this.attachProductInteractions();

                    const currentUrl = new URL(window.location);
                    const newParams = new URLSearchParams();
                    params.forEach((value, key) => {
                        if (key !== 'page' || (key === 'page' && value !== '1')) {
                            newParams.set(key, value);
                        }
                    });
                    const newUrl = `${currentUrl.pathname}?${newParams.toString()}`;
                    history.replaceState({ path: newUrl }, '', newUrl);

                    if (data.message && window.Swal) {
                        Swal.fire({
                            icon: 'info',
                            title: 'Filter Results',
                            text: data.message,
                            timer: 3000,
                            showConfirmButton: false
                        });
                    }

                    if (data.meta) {
                        const performanceInfo = document.getElementById('performance-info');
                        if (performanceInfo) {
                            performanceInfo.innerHTML = `
                                Loaded ${data.meta.total_products} products in ${data.meta.processing_time_ms}ms
                                ${data.meta.cache_hit ? '(Cache Hit)' : '(Cache Miss)'}
                            `;
                            performanceInfo.style.display = 'block';
                        }
                    }
                } else {
                    Utils.showError(productGrids, data.message || 'Failed to apply filters.');
                }
            } catch (error) {
                if (error.name !== 'AbortError') {
                    console.error('Filter error:', error);
                    Utils.showError(productGrids, `Failed to apply filters: ${error.message}`);
                }
            } finally {
                this.isLoading = false;
                this.abortController = null;
            }
        }

        renderProductCard(product) {
            // Handle image URLs - check if path already contains storage prefix
            const baseImageUrl = 'http://127.0.0.1:8000/storage/photos/1/Products/';
            const primaryImage = product.images && product.images.length > 0 
                ? (product.images[0].startsWith('storage/photos/1/Products/') 
                    ? `http://127.0.0.1:8000/${product.images[0]}` 
                    : `${baseImageUrl}${product.images[0]}`)
                : '/images/no-image.png';
            
            let imageSliderHtml = '';
            if (product.images && product.images.length > 0) {
                imageSliderHtml = product.images.map(img => {
                    const imgSrc = img.startsWith('storage/photos/1/Products/') 
                        ? `http://127.0.0.1:8000/${img}` 
                        : `${baseImageUrl}${img}`;
                    return `<img src="${imgSrc}" class="slider-image lazy" alt="${product.title}" 
                            loading="lazy" width="235" height="235" decoding="async" 
                            onerror="this.src='/images/no-image.png'; this.onerror=null;">`;
                }).join('');
            } else {
                imageSliderHtml = `
                    <img src="/images/no-image.png" class="slider-image lazy" alt="${product.title}" 
                         loading="lazy" width="235" height="235" decoding="async">
                `;
            }

            const brand = product.brand || { title: '', slug: '' };
            // Handle price: use product.price.final if available, else fallback to product.discounted_price or product.price
            const price = product.price?.final ?? product.discounted_price ?? product.price ?? 0;
            const originalPrice = product.price?.original ?? product.price ?? 0;
            const discount = product.discount || (product.price?.discount_percentage > 0 ? product.price.discount_percentage : 0);
            const rating = product.rating || { average: 0, total: 0 };
            const stock = product.stock > 0;

            let badgesHtml = '';
            if (discount > 0) {
                badgesHtml += `<span class="badge badge-primary badge-status badge-cg">${discount}% Off</span>`;
            } else if (product.condition === 'new') {
                badgesHtml += `<span class="badge badge-success badge-status">New</span>`;
            } else if (!stock) {
                badgesHtml += `<span class="badge badge-danger badge-status">Sold Out</span>`;
            }

            let ratingHtml = '';
            if (rating.average > 0) {
                ratingHtml = `
                    <div class="mb-1 rating-container">
                        <small class="text-warning">
                            ${Utils.generateStars(Math.round(rating.average))}
                            <span class="text-muted rating-count">(${rating.total})</span>
                        </small>
                    </div>
                `;
            }

            let priceHtml = '';
            if (discount > 0) {
                priceHtml = `
                    <span class="text-primary font-weight-bold current-price">
                        ${Utils.formatPrice(price)}
                    </span>
                    <small class="text-muted ml-2 original-price">
                        <del>${Utils.formatPrice(originalPrice)}</del>
                    </small>
                `;
            } else {
                priceHtml = `
                    <span class="font-weight-bold text-primary current-price">
                        ${Utils.formatPrice(price)}
                    </span>
                `;
            }

            return `
                <div class="product-card-container mb-4 isotope-item category-${product.cat_id} px-3"
                     data-product-id="${product.id}"
                     data-product-brand="${brand.slug}"
                     data-product-price="${price}"
                     data-product-discount="${discount}"
                     data-product-rating="${rating.average}">
                    <div class="card h-100 border-0 d-flex flex-column product-card shadow-sm rounded">
                        <div class="position-relative product-image-container">
                            <div class="slider-wrapper w-100 h-100" data-slider>
                                <div class="slider-track d-flex h-100">
                                    ${imageSliderHtml}
                                </div>
                            </div>
                            ${badgesHtml}
                        </div>
                        
                        <div class="card-body d-flex flex-column px-3 py-2">
                            <h6 class="text-dark text-truncate mb-1">
                                <a href="/product/${product.slug}" class="text-dark product-title">
                                    ${Utils.truncateText(product.title, 50)}
                                </a>
                            </h6>
                            
                            ${brand.title ? `
                                <small class="text-muted mb-1 brand-info">
                                    <i class="fa fa-tag"></i> ${brand.title}
                                </small>
                            ` : ''}
                            
                            ${ratingHtml}
                            
                            <div class="mb-2 price-container">
                                ${priceHtml}
                            </div>
                            
                            <div class="mt-auto action-buttons">
                                <a href="/cart/add/${product.id}"
                                   class="btn btn-sm btn-block btn-dark text-uppercase mb-3 text-center add-to-cart-btn ${!stock ? 'disabled' : ''}">
                                    <i class="ti-shopping-cart mr-1"></i>
                                    ${stock ? 'Add to Cart' : 'Out of Stock'}
                                </a>
                                
                                <div class="d-flex justify-content-between align-items-center small text-muted px-1 secondary-actions">
                                    <a href="/wishlist/add/${product.id}" class="text-decoration-none wishlist-link">
                                        <i class="ti-heart mr-1"></i> Wishlist
                                    </a>
                                    <a href="#" class="text-decoration-none text-muted hover-text-dark quick-view-link"
                                       data-product-id="${product.id}" data-toggle="modal" data-target="#productModal${product.id}">
                                        <i class="ti-eye mr-1"></i> Quick View
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        }

        renderPagination(pagination) {
            if (!pagination || pagination.total <= pagination.per_page) {
                return '<div class="row mt-4"><div class="col-12 d-flex justify-content-center"><p class="text-muted text-center">End of results.</p></div></div>';
            }

            let html = `
                <div class="row mt-4">
                    <div class="col-12 d-flex justify-content-center">
                        <div class="pagination-wrapper">
                            <nav aria-label="Product pagination">
                                <ul class="pagination">
            `;

            html += `
                <li class="page-item ${pagination.current_page === 1 ? 'disabled' : ''}">
                    <a class="page-link" href="#" data-page="${pagination.current_page - 1}">
                        Previous
                    </a>
                </li>
            `;

            const startPage = Math.max(1, pagination.current_page - 2);
            const endPage = Math.min(pagination.last_page, pagination.current_page + 2);
            for (let i = startPage; i <= endPage; i++) {
                html += `
                    <li class="page-item ${i === pagination.current_page ? 'active' : ''}">
                        <a class="page-link" href="#" data-page="${i}">${i}</a>
                    </li>
                `;
            }

            html += `
                <li class="page-item ${pagination.current_page === pagination.last_page ? 'disabled' : ''}">
                    <a class="page-link" href="#" data-page="${pagination.current_page + 1}">
                        Next
                    </a>
                </li>
            `;

            html += `
                                </ul>
                            </nav>
                        </div>
                    </div>
                </div>
            `;

            return html;
        }

        attachProductInteractions() {
            document.querySelectorAll('#product-grids .add-to-cart-btn:not(.disabled)').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    e.preventDefault();
                    if (!btn.dataset.cartInitialized) {
                        btn.dataset.cartInitialized = 'true';
                        const originalText = btn.innerHTML;
                        btn.innerHTML = '<i class="fa fa-spinner fa-spin mr-1"></i> Adding...';
                        btn.classList.add('disabled');
                        setTimeout(() => {
                            btn.innerHTML = '<i class="fa fa-check mr-1"></i> Added!';
                            btn.classList.add('btn-success');
                            btn.classList.remove('btn-dark');
                            setTimeout(() => {
                                btn.innerHTML = originalText;
                                btn.classList.remove('disabled', 'btn-success');
                                btn.classList.add('btn-dark');
                                delete btn.dataset.cartInitialized;
                            }, CONFIG.cartSuccessDelay);
                        }, CONFIG.cartAnimationDelay);
                    }
                });
            });

            document.querySelectorAll('#product-grids .wishlist-link').forEach(link => {
                link.addEventListener('click', (e) => {
                    e.preventDefault();
                    if (!link.dataset.wishlistInitialized) {
                        link.dataset.wishlistInitialized = 'true';
                        const heartIcon = link.querySelector('i');
                        if (heartIcon) {
                            const originalColor = heartIcon.style.color;
                            heartIcon.style.transform = 'scale(1.3)';
                            heartIcon.style.color = 'red';
                            setTimeout(() => {
                                heartIcon.style.transform = 'scale(1)';
                                setTimeout(() => {
                                    heartIcon.style.color = originalColor;
                                    delete link.dataset.wishlistInitialized;
                                }, 1000);
                            }, 200);
                        }
                    }
                });
            });

            document.querySelectorAll('#product-grids .quick-view-link').forEach(link => {
                link.addEventListener('click', (e) => {
                    e.preventDefault();
                    const productId = link.dataset.productId;
                    if (productId) {
                        const modal = document.getElementById(`productModal${productId}`);
                        if (modal && (typeof bootstrap !== 'undefined' ? bootstrap.Modal : jQuery?.fn.modal)) {
                            if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                                new bootstrap.Modal(modal).show();
                            } else if (typeof jQuery !== 'undefined' && jQuery.fn.modal) {
                                jQuery(modal).modal('show');
                            }
                        }
                    }
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

        setupCartButtons() {
            document.addEventListener('click', (e) => {
                const button = e.target.closest('.btn-dark:not(.disabled)');
                if (button?.href?.includes('add-to-cart') || button?.classList.contains('add-to-cart-btn')) {
                    e.preventDefault();
                    this.handleAddToCart(button);
                }
            });
        }

        handleAddToCart(button) {
            if (button.dataset.cartInitialized) return;
            button.dataset.cartInitialized = 'true';

            const originalText = button.innerHTML;
            const originalHref = button.href || button.getAttribute('data-href');

            button.innerHTML = '<i class="fa fa-spinner fa-spin mr-1"></i> Adding...';
            button.classList.add('disabled');
            button.href = 'javascript:void(0)';

            setTimeout(() => {
                button.innerHTML = '<i class="fa fa-check mr-1"></i> Added!';
                button.classList.add('btn-success');
                button.classList.remove('btn-dark');

                setTimeout(() => {
                    button.innerHTML = originalText;
                    if (originalHref) button.href = originalHref;
                    button.classList.remove('disabled', 'btn-success');
                    button.classList.add('btn-dark');
                    delete button.dataset.cartInitialized;
                }, CONFIG.cartSuccessDelay);
            }, CONFIG.cartAnimationDelay);
        }

        setupWishlistButtons() {
            document.addEventListener('click', (e) => {
                const button = e.target.closest('[href*="add-to-wishlist"], .wishlist-link');
                if (button && !button.dataset.wishlistInitialized) {
                    e.preventDefault();
                    button.dataset.wishlistInitialized = 'true';
                    this.handleWishlistToggle(button);
                }
            });
        }

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

        setupQuickViewButtons() {
            document.addEventListener('click', (e) => {
                const button = e.target.closest('[data-toggle="modal"], .quick-view-link');
                if (button && !button.dataset.quickviewInitialized) {
                    e.preventDefault();
                    button.dataset.quickviewInitialized = 'true';

                    const modalId = button.getAttribute('data-target') || button.getAttribute('onclick')?.match(/#([^']*)/)?.[1];
                    if (modalId) {
                        const modal = document.querySelector(modalId);
                        if (modal) this.showModal(modal);
                    }

                    setTimeout(() => delete button.dataset.quickviewInitialized, 1000);
                }
            });
        }

        showModal(modal) {
            if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                new bootstrap.Modal(modal).show();
            } else if (typeof jQuery !== 'undefined' && jQuery.fn.modal) {
                jQuery(modal).modal('show');
            }
        }

        setupModalHandling() {
            document.querySelectorAll('[id^="productModal"], #quickViewModal:not([data-modal-initialized])').forEach(modal => {
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

        initialize() {
            console.log('Initializing unified shop system with JSON rendering...');

            this.imageSliderSystem = new ImageSliderSystem();
            this.filterSystem = new FilterSystem();
            this.productInteractionSystem = new ProductInteractionSystem();

            window.imageSliderSystem = this.imageSliderSystem;
            window.filterSystem = this.filterSystem;
            window.productInteractionSystem = this.productInteractionSystem;

            this.setupContentObserver();

            console.log('Unified shop system initialized successfully');
        }

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
                if (this.productInteractionSystem) {
                    this.productInteractionSystem.setupCartButtons();
                    this.productInteractionSystem.setupWishlistButtons();
                    this.productInteractionSystem.setupQuickViewButtons();
                }
            });

            observer.observe(productGrids, { childList: true, subtree: true });
        }
    }

    // Inject CSS if not present
    if (!document.getElementById('unified-shop-system-styles')) {
        const styleSheet = document.createElement('style');
        styleSheet.id = 'unified-shop-system-styles';
        document.head.appendChild(styleSheet);
    }

    // Initialize
    window.unifiedShopSystem = new UnifiedShopSystem();
    window.unifiedShopSystemInitialized = true;
})();