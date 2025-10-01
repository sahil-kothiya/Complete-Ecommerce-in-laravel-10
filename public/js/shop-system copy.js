// Updated unified shop system with fixed image slider
(function() {
    'use strict';
    
    if (window.unifiedShopSystemInitialized) {
        console.log('Unified shop system already initialized, skipping...');
        return;
    }

    const CONFIG = {
        maxPrice: window.maxPrice || 5000,
        filterDebounceTime: 300,
        sliderAnimationSpeed: 800,
        cartAnimationDelay: 800,
        cartSuccessDelay: 1500,
        currency: '$',
        endpoints: {
            filterData: '/api/filters'
        }
    };

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
            const pathParts = window.location.pathname.split('/');
            const catIndex = pathParts.indexOf('product-cat');
            return catIndex !== -1 && pathParts.length > catIndex + 1 
                ? pathParts.slice(catIndex + 1).join('/') 
                : '';
        },

        showLoadingState(container) {
            if (!container) return;
            container.innerHTML = `
                <div class="filter-loading-overlay">
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
            const formData = new URLSearchParams();
            const checkboxGroups = {};

            form.querySelectorAll('input[type="checkbox"]:checked').forEach(input => {
                const name = input.name.replace('[]', '');
                const value = input.value;
                if (!checkboxGroups[name]) checkboxGroups[name] = [];
                checkboxGroups[name].push(value);
            });

            Object.entries(checkboxGroups).forEach(([name, values]) => {
                if (values.length > 0) {
                    let paramName = name;
                    if (name === 'brand') paramName = 'brands';
                    else if (name === 'min_rating') paramName = 'ratings';
                    else if (name === 'min_discount') paramName = 'discounts';
                    formData.append(paramName, values.join(','));
                }
            });

            form.querySelectorAll('select, input[type="text"], input[type="hidden"]').forEach(input => {
                if (input.name && input.value && input.name !== 'price_range') {
                    formData.set(input.name, input.value);
                }
            });

            return formData;
        },

        truncateText: (text, maxLength) => text.length <= maxLength ? text : text.substring(0, maxLength - 3) + '...',

        formatPrice: (price, currency = CONFIG.currency) => {
            if (typeof price !== 'number' || isNaN(price)) {
                console.warn('Invalid price value:', price);
                return `${currency}0.00`;
            }
            return `${currency}${price.toFixed(2)}`;
        },

        generateStars(rating) {
            let stars = '';
            for (let i = 1; i <= 5; i++) {
                stars += `<i class="fa fa-star${i <= rating ? '' : '-o'}"></i>`;
            }
            return stars;
        }
    };

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

            document.addEventListener('click', (e) => {
                if (e.target.matches('#product-grids .pagination a.page-link')) {
                    e.preventDefault();
                    const page = parseInt(e.target.dataset.page) || 1;
                    this._applyFilters(page);
                }
            });
        }

        initializeFiltersFromURL() {
            const urlParams = new URLSearchParams(window.location.search);
            
            ['brands', 'ratings', 'discounts'].forEach(param => {
                const value = urlParams.get(param);
                if (value) {
                    const inputName = param === 'brands' ? 'brand' : 
                                    param === 'ratings' ? 'min_rating' : 'min_discount';
                    value.split(',').forEach(val => {
                        const input = document.querySelector(`input[name="${inputName}[]"][value="${val}"]`);
                        if (input) input.checked = true;
                    });
                }
            });
        }

        handleFilterChange(input) {
            this.debouncedApply(1);
        }

        async _applyFilters(page = 1) {
            if (this.isLoading) return;
            
            this.isLoading = true;
            const form = document.getElementById('productFilterForm');
            const container = document.getElementById('product-grids');
            
            if (!form || !container) {
                this.isLoading = false;
                return;
            }

            Utils.showLoadingState(container);

            if (this.abortController) this.abortController.abort();
            this.abortController = new AbortController();

            const formData = Utils.collectFormData(form);
            const priceRange = document.getElementById('price_range');
            
            if (priceRange?.value && priceRange.value !== `0-${CONFIG.maxPrice}`) {
                formData.set('price_range', priceRange.value);
            }
            
            formData.set('page', page);

            let endpoint = CONFIG.endpoints.filterData;
            const categorySlug = Utils.getCategorySlug();
            if (categorySlug) endpoint += `/${categorySlug}`;

            try {
                const response = await fetch(`${endpoint}?${formData.toString()}`, {
                    method: 'GET',
                    signal: this.abortController.signal,
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });

                if (!response.ok) throw new Error(`Server returned ${response.status}`);

                const data = await response.json();

                if (data.success && container) {
                    // Check if no results and show similar products
                    if (data.no_results && data.similar_products && data.similar_products.length > 0) {
                        const noResultsHTML = this.renderNoResultsSection(data.similar_products);
                        container.innerHTML = noResultsHTML;
                    } else {
                        const productsHTML = data.products.map(p => this.renderProductCard(p)).join('');
                        const paginationHTML = this.renderPagination(data.pagination || {});
                        
                        container.innerHTML = `
                            <div class="product-grid-container">
                                <div class="product-grid-row">
                                    ${productsHTML}
                                </div>
                            </div>
                            ${paginationHTML}
                        `;
                    }

                    // Fade-in animation
                    container.style.opacity = '0.5';
                    setTimeout(() => { 
                        container.style.opacity = '1'; 
                    }, 150);

                    // CRITICAL: Reinitialize sliders after DOM update
                    setTimeout(() => {
                        if (window.imageSliderSystem) {
                            window.imageSliderSystem.reinitialize();
                        }
                    }, 200);

                    this.attachProductInteractions();

                    // Update URL
                    const url = new URL(window.location);
                    const newParams = new URLSearchParams();
                    formData.forEach((value, key) => {
                        if (key !== 'page' || (key === 'page' && value !== '1')) {
                            newParams.set(key, value);
                        }
                    });
                    const newUrl = `${url.pathname}?${newParams.toString()}`;
                    history.replaceState({ path: newUrl }, '', newUrl);
                } else {
                    Utils.showError(container, data.message || 'Failed to apply filters.');
                }
            } catch (error) {
                if (error.name !== 'AbortError') {
                    console.error('Filter error:', error);
                    Utils.showError(container, `Failed to apply filters: ${error.message}`);
                }
            } finally {
                this.isLoading = false;
                this.abortController = null;
            }
        }

        renderNoResultsSection(similarProducts) {
            const productsHTML = similarProducts.map(p => this.renderProductCard(p)).join('');
            
            return `
                <div class="no-results-container mb-5">
                    <div class="text-center py-5">
                        <i class="fa fa-search mb-3" style="font-size: 48px; color: #ccc;"></i>
                        <h3 class="mb-2"><strong>No Products Found</strong></h3>
                        <p class="text-muted mb-4">Showing similar products you might like</p>
                    </div>
                    
                    <div class="similar-products-section">
                        <div class="product-grid-container">
                            <div class="product-grid-row">
                                ${productsHTML}
                            </div>
                        </div>
                    </div>
                </div>
            `;
        }

        renderProductCard(product) {
            const images = product.images && product.images.length > 0 ? product.images : ['/images/default-placeholder.jpg'];
            const imagesHTML = images.map(img => {
                const imgSrc = img.startsWith('storage/') ? `http://127.0.0.1:8000/${img}` : img;
                return `<img src="${imgSrc}" class="slider-image lazy" alt="${product.title}"
                            loading="lazy" width="235" height="235" decoding="async"
                            onerror="this.src='/images/no-image.png'; this.onerror=null;">`;
            }).join('');

            const brand = product.brand || { title: '', slug: '' };
            const finalPrice = product.price?.final ?? product.price ?? 0;
            const originalPrice = product.price?.original ?? product.price ?? 0;
            const discount = product.discount || (product.price?.discount_percentage > 0 ? product.price.discount_percentage : 0);
            const rating = product.rating || { average: 0, total: 0 };
            const inStock = product.stock > 0;

            let badges = '';
            if (discount > 0) {
                badges += `<span class="badge badge-primary badge-status badge-cg">${discount}% Off</span>`;
            } else if (product.condition === 'new') {
                badges += '<span class="badge badge-success badge-status">New</span>';
            } else if (!inStock) {
                badges += '<span class="badge badge-danger badge-status">Sold Out</span>';
            }

            const ratingHTML = rating.average > 0 ? `
                <div class="mb-1 rating-container">
                    <small class="text-warning">
                        ${Utils.generateStars(Math.round(rating.average))}
                        <span class="text-muted rating-count">(${rating.total})</span>
                    </small>
                </div>
            ` : '';

            const priceHTML = discount > 0 ? `
                <span class="text-primary font-weight-bold current-price">
                    ${Utils.formatPrice(finalPrice)}
                </span>
                <small class="text-muted ml-2 original-price">
                    <del>${Utils.formatPrice(originalPrice)}</del>
                </small>
            ` : `
                <span class="font-weight-bold text-primary current-price">
                    ${Utils.formatPrice(finalPrice)}
                </span>
            `;

            return `
                <div class="product-card-container mb-4 isotope-item px-3"
                     data-product-id="${product.id}">
                    <div class="card h-100 border-0 d-flex flex-column product-card shadow-sm rounded">
                        <div class="position-relative product-image-container">
                            <div class="slider-wrapper w-100 h-100" data-slider>
                                <div class="slider-track d-flex h-100">
                                    ${imagesHTML}
                                </div>
                            </div>
                            ${badges}
                        </div>

                        <div class="card-body d-flex flex-column px-3 py-2">
                            <h6 class="text-dark text-truncate mb-1">
                                <a href="/product-detail/${product.slug}" class="text-dark product-title">
                                    ${Utils.truncateText(product.title, 50)}
                                </a>
                            </h6>

                            ${brand.title ? `<small class="text-muted mb-1 brand-info"><i class="fa fa-tag"></i> ${brand.title}</small>` : ''}

                            ${ratingHTML}

                            <div class="mb-2 price-container">
                                ${priceHTML}
                            </div>

                            <div class="mt-auto action-buttons">
                                <a href="/cart/add/${product.id}"
                                   class="btn btn-sm btn-block btn-dark text-uppercase mb-3 text-center add-to-cart-btn ${inStock ? '' : 'disabled'}">
                                    <i class="ti-shopping-cart mr-1"></i>
                                    ${inStock ? 'Add to Cart' : 'Out of Stock'}
                                </a>

                                <div class="d-flex justify-content-between align-items-center small text-muted px-1 secondary-actions">
                                    <a href="/wishlist/add/${product.id}" class="text-decoration-none wishlist-link">
                                        <i class="ti-heart mr-1"></i> Wishlist
                                    </a>
                                    <a href="#" class="text-decoration-none text-muted hover-text-dark quick-view-link"
                                       data-product-id="${product.id}">
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
            // Cart buttons
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

            // Wishlist buttons
            document.querySelectorAll('#product-grids .wishlist-link').forEach(link => {
                link.addEventListener('click', (e) => {
                    e.preventDefault();
                    if (!link.dataset.wishlistInitialized) {
                        link.dataset.wishlistInitialized = 'true';
                        const icon = link.querySelector('i');
                        if (icon) {
                            const originalColor = icon.style.color;
                            icon.style.transform = 'scale(1.3)';
                            icon.style.color = 'red';
                            setTimeout(() => {
                                icon.style.transform = 'scale(1)';
                                setTimeout(() => {
                                    icon.style.color = originalColor;
                                    delete link.dataset.wishlistInitialized;
                                }, 1000);
                            }, 200);
                        }
                    }
                });
            });
        }
    }

    class ImageSliderSystem {
        constructor() {
            this.activeSliders = new Map();
            this.init();
        }

        init() {
            this.initializeSliders();
        }

        initializeSliders() {
            const wrappers = document.querySelectorAll('.slider-wrapper[data-slider]:not([data-slider-initialized])');
            
            wrappers.forEach(wrapper => {
                wrapper.setAttribute('data-slider-initialized', 'true');
                this.setupSlider(wrapper);
            });
        }

        setupSlider(wrapper) {
            const track = wrapper.querySelector('.slider-track');
            const images = track?.querySelectorAll('.slider-image');
            
            if (!images || images.length === 0) {
                console.warn('No images found in slider');
                return;
            }

            // Clean up any existing slider for this wrapper
            if (this.activeSliders.has(wrapper)) {
                this.cleanupSlider(wrapper);
            }

            let currentIndex = 0;
            let interval = null;

            // Set up track styles
            track.style.width = '100%';
            track.style.transform = 'translateX(0)';
            track.style.transition = 'transform 0.5s ease-in-out';

            // Set up image styles
            images.forEach(img => {
                img.style.flex = '0 0 100%';
                img.style.width = '100%';
                img.style.height = '100%';
                img.style.objectFit = 'cover';
            });

            // Only setup auto-slide if there are multiple images
            if (images.length > 1) {
                const startSlider = () => {
                    if (interval) clearInterval(interval);
                    interval = setInterval(() => {
                        currentIndex = (currentIndex + 1) % images.length;
                        track.style.transform = `translateX(-${currentIndex * 100}%)`;
                    }, CONFIG.sliderAnimationSpeed);
                };

                const stopSlider = () => {
                    if (interval) {
                        clearInterval(interval);
                        interval = null;
                    }
                    // Reset to first image
                    currentIndex = 0;
                    track.style.transform = 'translateX(0)';
                };

                // Mouse events
                wrapper.addEventListener('mouseenter', startSlider);
                wrapper.addEventListener('mouseleave', stopSlider);

                // Store references for cleanup
                this.activeSliders.set(wrapper, {
                    interval,
                    startSlider,
                    stopSlider,
                    mouseenterHandler: startSlider,
                    mouseleaveHandler: stopSlider
                });
            }
        }

        cleanupSlider(wrapper) {
            const sliderData = this.activeSliders.get(wrapper);
            if (sliderData) {
                if (sliderData.interval) {
                    clearInterval(sliderData.interval);
                }
                wrapper.removeEventListener('mouseenter', sliderData.mouseenterHandler);
                wrapper.removeEventListener('mouseleave', sliderData.mouseleaveHandler);
                this.activeSliders.delete(wrapper);
            }
        }

        reinitialize() {
            // Clear all existing sliders first
            document.querySelectorAll('.slider-wrapper[data-slider-initialized]').forEach(wrapper => {
                this.cleanupSlider(wrapper);
                wrapper.removeAttribute('data-slider-initialized');
            });

            // Initialize all sliders fresh
            this.initializeSliders();
        }
    }

    // Initialize system
    window.unifiedShopSystem = new class UnifiedShopSystem {
        constructor() {
            this.imageSliderSystem = null;
            this.filterSystem = null;
            this.init();
        }

        init() {
            const initialize = () => this.initialize();
            
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', initialize);
            } else {
                initialize();
            }
        }

        initialize() {
            this.imageSliderSystem = new ImageSliderSystem();
            this.filterSystem = new FilterSystem();
            
            window.imageSliderSystem = this.imageSliderSystem;
            window.filterSystem = this.filterSystem;
        }
    };

    window.unifiedShopSystemInitialized = true;

})();