!(function () {
    "use strict";
    if (window.unifiedShopSystemInitialized) {
        console.log("Unified shop system already initialized, skipping...");
        return;
    }
    let e = {
            maxPrice: window.maxPrice || 5e3,
            filterDebounceTime: 300,
            sliderAnimationSpeed: 800,
            cartAnimationDelay: 800,
            cartSuccessDelay: 1500,
            paginationDelay: 50,
            animationSpeed: 300,
            currency: "$",
            endpoints: { filterData: "/api/filters" },
            sortOptions: [
                { value: "latest", text: "Latest" },
                { value: "price_low_high", text: "Price: Low to High" },
                { value: "price_high_low", text: "Price: High to Low" },
                { value: "rating_high_low", text: "Rating: High to Low" },
                { value: "name_a_z", text: "Name: A to Z" },
                { value: "name_z_a", text: "Name: Z to A" },
            ],
            showOptions: [
                { value: "12", text: "12" },
                { value: "24", text: "24" },
                { value: "36", text: "36" },
                { value: "48", text: "48" },
            ],
        },
        t = {
            debounce(e, t) {
                let i;
                return function a(...s) {
                    let l = () => {
                        clearTimeout(i), e(...s);
                    };
                    clearTimeout(i), (i = setTimeout(l, t));
                };
            },
            getCategorySlug() {
                let e = window.location.pathname.split("/"),
                    t = e.indexOf("product-cat");
                return -1 !== t && e.length > t + 1 ? e.slice(t + 1).join("/") : "";
            },
            showLoadingState(e) {
                e &&
                    (e.innerHTML = `
                <div class="filter-loading-overlay" style="position: relative;">
                    <div class="loading-spinner">
                        <div class="spinner"></div>
                        <p>Loading products...</p>
                    </div>
                </div>
            `);
            },
            showError(e, t = "An error occurred. Please try again.") {
                e &&
                    (e.innerHTML = `
                <div class="alert alert-danger text-center">
                    <i class="fa fa-times-circle"></i>
                    <p>${t}</p>
                    <button class="btn btn-primary" onclick="location.reload()">Refresh Page</button>
                </div>
            `);
            },
            collectFormData(e) {
                if (!e) return new URLSearchParams();
                let t = new URLSearchParams(),
                    i = {};
                return (
                    e.querySelectorAll('input[type="checkbox"]:checked').forEach((e) => {
                        let t = e.name.replace("[]", ""),
                            a = e.value;
                        i[t] || (i[t] = []), i[t].push(a);
                    }),
                    Object.entries(i).forEach(([e, i]) => {
                        if (i.length > 0) {
                            let a = e;
                            "brand" === e ? (a = "brands") : "min_rating" === e ? (a = "ratings") : "min_discount" === e && (a = "discounts"), t.append(a, i.join(","));
                        }
                    }),
                    e.querySelectorAll('select, input[type="text"], input[type="hidden"]').forEach((e) => {
                        e.name && e.value && "price_range" !== e.name && t.set(e.name, e.value);
                    }),
                    t
                );
            },
            truncateText: (e, t) => (e.length <= t ? e : e.substring(0, t - 3) + "..."),
            formatPrice: (t, i = e.currency) => ("number" != typeof t || isNaN(t) ? (console.warn("Invalid price value:", t), `${i}0.00`) : `${i}${t.toFixed(2)}`),
            generateStars(e) {
                let t = "";
                for (let i = 1; i <= 5; i++) t += `<i class="fa fa-star${i <= e ? "" : "-o"}"></i>`;
                return t;
            },
            populateSelects() {
                let t = document.getElementById("sortBy"),
                    i = document.getElementById("show");
                t &&
                    (Array.from(t.options)
                        .slice(1)
                        .forEach((e) => e.remove()),
                    e.sortOptions.forEach((e) => {
                        let i = document.createElement("option");
                        (i.value = e.value), (i.textContent = e.text), t.value === e.value && (i.selected = !0), t.appendChild(i);
                    })),
                    i &&
                        (Array.from(i.options)
                            .slice(1)
                            .forEach((e) => e.remove()),
                        e.showOptions.forEach((e) => {
                            let t = document.createElement("option");
                            (t.value = e.value), (t.textContent = e.text), i.value === e.value && (t.selected = !0), i.appendChild(t);
                        }));
            },
        };
    class i {
        constructor() {
            this.init();
        }
        init() {
            let i = document.getElementById("slider-range"),
                a = document.getElementById("amount");
            if (!i || !a) return;
            let s = parseFloat(i.dataset.min) || 0,
                l = parseFloat(i.dataset.max) || e.maxPrice,
                r = s,
                n = l,
                d = document.getElementById("price_range")?.value;
            d && ([r, n] = d.split("-").map(parseFloat)),
                void 0 !== $.ui && $.ui.slider
                    ? ($(i).slider({
                          range: !0,
                          min: s,
                          max: l,
                          values: [r, n],
                          slide(e, i) {
                              (a.value = `${t.formatPrice(i.values[0])} - ${t.formatPrice(i.values[1])}`), (document.getElementById("price_range").value = `${i.values[0]}-${i.values[1]}`);
                          },
                          change: (e, t) => {
                              this.applyFilters();
                          },
                      }),
                      (a.value = `${t.formatPrice(r)} - ${t.formatPrice(n)}`))
                    : console.warn(
                          'jQuery UI Slider not loaded. Add <link rel="stylesheet" href="https://code.jquery.com/ui/1.13.2/themes/ui-lightness/jquery-ui.css"> and <script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script> to layout.'
                      );
        }
        applyFilters() {
            window.unifiedShopSystem?.applyFilters();
        }
    }
    class a {
        constructor() {
            (this.observers = new Map()), this.init();
        }
        init() {
            this.initializeSliders(), this.setupLazyLoading();
        }
        initializeSliders() {
            document.querySelectorAll(".slider-wrapper[data-slider]:not([data-slider-initialized])").forEach((e) => {
                e.setAttribute("data-slider-initialized", "true"), this.setupSlider(e);
            });
        }
        setupSlider(t) {
            let i = t.querySelector(".slider-track"),
                a = i?.querySelectorAll(".slider-image");
            if (!a || 0 === a.length) return;
            let s = 0,
                l = null;
            (i.style.width = "100%"),
                (i.style.transform = "translateX(0)"),
                a.forEach((e) => {
                    (e.style.flex = "0 0 100%"),
                        (e.style.width = "100%"),
                        e.complete
                            ? e.setAttribute("loaded", "")
                            : (e.addEventListener("load", () => e.setAttribute("loaded", "")),
                              e.addEventListener("error", () => {
                                  (e.src = "/images/no-image.png"), e.setAttribute("loaded", "");
                              }));
                }),
                (i.style.width = "100%"),
                1 !== a.length &&
                    (t.addEventListener("mouseenter", () => {
                        l && clearInterval(l),
                            (l = setInterval(() => {
                                (s = (s + 1) % a.length), (i.style.transform = `translateX(-${100 * s}%)`);
                            }, e.sliderAnimationSpeed));
                    }),
                    t.addEventListener("mouseleave", () => {
                        l && clearInterval(l);
                    }));
        }
        setupLazyLoading() {
            if (!("IntersectionObserver" in window) || window.imageObserverInitialized) return;
            window.imageObserverInitialized = !0;
            let e = new IntersectionObserver((t) => {
                t.forEach((t) => {
                    if (t.isIntersecting) {
                        let i = t.target;
                        i.classList.remove("lazy"), e.unobserve(i);
                    }
                });
            });
            document.querySelectorAll(".slider-image.lazy").forEach((t) => e.observe(t));
        }
        reinitialize() {
            this.initializeSliders();
        }
    }
    class s {
        constructor() {
            (this.activeFilters = {}), (this.debouncedApply = t.debounce(this._applyFilters.bind(this), e.filterDebounceTime)), (this.isLoading = !1), (this.abortController = null), this.init();
        }
        init() {
            this.bindEvents(), this.initializeFiltersFromURL(), this.initializePriceRange(), this.updateActiveFiltersDisplay(), setTimeout(() => this._applyFilters(1), 100);
        }
        bindEvents() {
            let e = document.getElementById("productFilterForm");
            if (!e) return;
            e.addEventListener("change", (e) => {
                e.target.matches('select[name="sortBy"], select[name="show"]') ? this.debouncedApply(1) : e.target.matches('input[type="checkbox"]') && this.handleFilterChange(e.target);
            });
            let t = e.querySelector('.filter_button, button[type="submit"]');
            t &&
                t.addEventListener("click", (e) => {
                    e.preventDefault(), this.debouncedApply(1);
                }),
                document.addEventListener("click", (e) => {
                    let t = e.target.closest(".active-filter-tag");
                    t && (e.preventDefault(), this.removeActiveFilter(t));
                }),
                document.addEventListener("click", (e) => {
                    e.target.closest("#clear-all-filters") && (e.preventDefault(), this.clearAllFilters());
                }),
                document.addEventListener("click", (e) => {
                    if (e.target.matches("#product-grids .pagination a.page-link")) {
                        e.preventDefault();
                        let t = parseInt(e.target.dataset.page) || 1;
                        this._applyFilters(t);
                    }
                }),
                window.addEventListener("popstate", () => {
                    this.initializeFiltersFromURL(), this.debouncedApply(1);
                });
        }
        initializeFiltersFromURL() {
            let t = new URLSearchParams(window.location.search),
                i = !1;
            ["brands", "ratings", "discounts"].forEach((e) => {
                let a = t.get(e);
                if (a) {
                    let s = "brands" === e ? "brand" : "ratings" === e ? "min_rating" : "min_discount";
                    a.split(",").forEach((e) => {
                        let t = document.querySelector(`input[name="${s}[]"][value="${e}"]`);
                        t && ((t.checked = !0), this.addToActiveFilters(s, e, t.dataset.filterLabel || e), (i = !0));
                    });
                }
            });
            let a = t.get("availability");
            a &&
                a.split(",").forEach((e) => {
                    let t = document.querySelector(`input[name="availability[]"][value="${e}"]`);
                    t && ((t.checked = !0), this.addToActiveFilters("availability", e, t.dataset.filterLabel || e), (i = !0));
                });
            let s = t.get("price_range");
            if (s && s !== `0-${e.maxPrice}`) {
                let l = document.getElementById("price_range");
                if (l) {
                    l.value = s;
                    let [r, n] = s.split("-");
                    this.addToActiveFilters("price", s, `${e.currency}${r} - ${e.currency}${n}`), (i = !0);
                }
            }
            return (
                ["sortBy", "show"].forEach((e) => {
                    let a = t.get(e),
                        s = document.getElementById(e);
                    a && s && a !== s.querySelector("option")?.value && ((s.value = a), (i = !0));
                }),
                i
            );
        }
        initializePriceRange() {
            let t = document.getElementById("slider-range");
            if (!t || !window.jQuery || !jQuery.fn.slider) return;
            let i = 0,
                a = e.maxPrice,
                s = t.dataset.currency || e.currency,
                l = `${i}-${a}`,
                r = document.getElementById("price_range");
            r?.value && (l = r.value);
            let [n, d] = l.split("-").map(Number);
            jQuery(t).slider({
                range: !0,
                min: i,
                max: a,
                values: [n, d],
                slide(e, t) {
                    let i = document.getElementById("amount");
                    i && (i.value = `${s}${t.values[0]} - ${s}${t.values[1]}`), r && (r.value = `${t.values[0]}-${t.values[1]}`);
                },
                stop: () => this.debouncedApply(1),
            });
            let c = document.getElementById("amount");
            c && (c.value = `${s}${n} - ${s}${d}`);
        }
        handleFilterChange(e) {
            let t = e.dataset.filterType || e.name.replace("[]", ""),
                i = e.value,
                a = e.dataset.filterLabel || i;
            e.checked ? this.addToActiveFilters(t, i, a) : this.removeFromActiveFilters(t, i), this.updateActiveFiltersDisplay(), this.debouncedApply(1);
        }
        addToActiveFilters(e, t, i) {
            this.activeFilters[e] || (this.activeFilters[e] = {}), (this.activeFilters[e][t] = i);
        }
        removeFromActiveFilters(e, t = null) {
            null === t ? delete this.activeFilters[e] : this.activeFilters[e] && (delete this.activeFilters[e][t], 0 === Object.keys(this.activeFilters[e]).length && delete this.activeFilters[e]);
        }
        removeActiveFilter(e) {
            let t = e.dataset.filterType,
                i = e.dataset.filterValue;
            e.classList.add("removing"),
                setTimeout(() => {
                    let e = document.querySelector(`input[name="${t}[]"][value="${i}"]`);
                    e && (e.checked = !1), "price" === t && this.resetPriceRange(), this.removeFromActiveFilters(t, i), this.updateActiveFiltersDisplay(), this.debouncedApply(1);
                }, 300);
        }
        clearAllFilters() {
            let e = document.querySelectorAll(".active-filter-tag");
            e.forEach((e, t) => {
                setTimeout(() => e.classList.add("removing"), 50 * t);
            }),
                setTimeout(() => {
                    document.querySelectorAll('#productFilterForm input[type="checkbox"]').forEach((e) => (e.checked = !1)), this.resetPriceRange(), (this.activeFilters = {}), this.updateActiveFiltersDisplay();
                    let e = new URL(window.location);
                    (e.search = ""), history.replaceState({}, "", e), this.debouncedApply(1);
                }, 500);
        }
        resetPriceRange() {
            let t = document.getElementById("slider-range"),
                i = document.getElementById("price_range"),
                a = document.getElementById("amount");
            if (t && window.jQuery && jQuery.fn.slider) {
                let s = e.maxPrice;
                jQuery(t).slider("values", [0, s]), i && (i.value = `0-${s}`), a && (a.value = `${e.currency}0 - ${e.currency}${s}`);
            }
        }
        updateActiveFiltersDisplay() {
            let e = document.getElementById("active-filters-list"),
                t = document.getElementById("clear-all-filters");
            if (!e) return;
            let i = Object.keys(this.activeFilters).length > 0;
            i ? ((e.style.display = "flex"), (e.innerHTML = this.generateActiveFiltersHTML()), t && (t.style.display = "inline-block")) : ((e.style.display = "none"), (e.innerHTML = ""), t && (t.style.display = "none"));
        }
        generateActiveFiltersHTML() {
            let e = "";
            return (
                Object.entries(this.activeFilters).forEach(([t, i]) => {
                    Object.entries(i).forEach(([i, a]) => {
                        e += `
                        <button class="active-filter-tag ${t}-filter"
                                data-filter-type="${t}"
                                data-filter-value="${i}"
                                title="Remove ${a} filter">
                            ${a} <i class="fa fa-times"></i>
                        </button>
                    `;
                    });
                }),
                e
            );
        }
        async _applyFilters(i = 1) {
            if (this.isLoading) return;
            this.isLoading = !0;
            let a = document.getElementById("productFilterForm"),
                s = document.getElementById("product-grids");
            if (!a || !s) {
                this.isLoading = !1;
                return;
            }
            t.showLoadingState(s), this.abortController && this.abortController.abort(), (this.abortController = new AbortController());
            let l = t.collectFormData(a),
                r = document.getElementById("price_range");
            r?.value && r.value !== `0-${e.maxPrice}` && l.set("price_range", r.value), l.set("page", i);
            let n = e.endpoints.filterData,
                d = t.getCategorySlug();
            d && (n += `/${d}`);
            try {
                let c = await fetch(`${n}?${l.toString()}`, { method: "GET", signal: this.abortController.signal, headers: { Accept: "application/json", "X-Requested-With": "XMLHttpRequest" } });
                if (!c.ok) throw Error(`Server returned ${c.status}`);
                let o = await c.json();
                if (o.success && s) {
                    let html = '';
                    if (o.meta?.is_similar) {
                        html += `
                            <div class="row mb-4">
                                <div class="col-12">
                                    <div class="alert alert-info d-flex align-items-center">
                                        <i class="fa fa-info-circle me-2"></i>
                                        ${o.meta.message || 'No products match your filters. Showing similar products.'}
                                    </div>
                                </div>
                            </div>
                        `;
                        // Update URL to page 1
                        let url = new URL(window.location);
                        let params = new URLSearchParams(url.search);
                        params.set('page', '1');
                        let newSearch = params.toString();
                        let newUrl = url.pathname + (newSearch ? '?' + newSearch : '');
                        history.replaceState({ path: newUrl }, '', newUrl);
                    }
                    let u = o.products.map((e) => this.renderProductCard(e)).join(""),
                        p = this.renderPagination(o.pagination || {});
                    (s.innerHTML = `
                        ${html}
                        <div class="product-grid-container">
                            <div class="product-grid-row">
                                ${u}
                            </div>
                        </div>
                        ${p}
                    `),
                        (s.style.opacity = "0.5"),
                        setTimeout(() => {
                            s.style.opacity = "1";
                        }, e.animationSpeed / 2),
                        window.imageSliderSystem && window.imageSliderSystem.reinitialize(),
                        this.attachProductInteractions();
                    let h = new URL(window.location),
                        g = new URLSearchParams();
                    l.forEach((e, t) => {
                        ("page" !== t || ("page" === t && "1" !== e)) && g.set(t, e);
                    });
                    let m = `${h.pathname}?${g.toString()}`;
                    if ((history.replaceState({ path: m }, "", m), o.message && window.Swal && Swal.fire({ icon: "info", title: "Filter Results", text: o.message, timer: 3e3, showConfirmButton: !1 }), o.meta)) {
                        let v = document.getElementById("performance-info");
                        v &&
                            ((v.innerHTML = `
                                Loaded ${o.meta.total_products} products in ${o.meta.processing_time_ms}ms
                                ${o.meta.cache_hit ? "(Cache Hit)" : "(Cache Miss)"}
                            `),
                            (v.style.display = "block"));
                    }
                } else t.showError(s, o.message || "Failed to apply filters.");
            } catch (y) {
                "AbortError" !== y.name && (console.error("Filter error:", y), t.showError(s, `Failed to apply filters: ${y.message}`));
            } finally {
                (this.isLoading = !1), (this.abortController = null);
            }
        }
        renderProductCard(e) {
            e.images && e.images.length > 0 && (e.images[0].startsWith("storage/photos/1/Products/"), e.images[0]);
            let i = "";
            i =
                e.images && e.images.length > 0
                    ? e.images
                          .map((t) => {
                              let i = t.startsWith("storage/photos/1/Products/") ? `http://127.0.0.1:8000/${t}` : `http://127.0.0.1:8000/storage/photos/1/Products/${t}`;
                              return `<img src="${i}" class="slider-image lazy" alt="${e.title}"
                            loading="lazy" width="235" height="235" decoding="async"
                            onerror="this.src='/images/no-image.png'; this.onerror=null;">`;
                          })
                          .join("")
                    : `
                    <img src="/images/no-image.png" class="slider-image lazy" alt="${e.title}"
                         loading="lazy" width="235" height="235" decoding="async">
                `;
            let a = e.brand || { title: "", slug: "" },
                s = e.price?.final ?? e.discounted_price ?? e.price ?? 0,
                l = e.price?.original ?? e.price ?? 0,
                r = e.discount || (e.price?.discount_percentage > 0 ? e.price.discount_percentage : 0),
                n = e.rating || { average: 0, total: 0 },
                d = e.stock > 0,
                c = "";
            r > 0
                ? (c += `<span class="badge badge-primary badge-status badge-cg">${r}% Off</span>`)
                : "new" === e.condition
                ? (c += '<span class="badge badge-success badge-status">New</span>')
                : d || (c += '<span class="badge badge-danger badge-status">Sold Out</span>');
            let o = "";
            n.average > 0 &&
                (o = `
                    <div class="mb-1 rating-container">
                        <small class="text-warning">
                            ${t.generateStars(Math.round(n.average))}
                            <span class="text-muted rating-count">(${n.total})</span>
                        </small>
                    </div>
                `);
            let u = "";
            return `
                <div class="product-card-container mb-4 isotope-item category-${e.cat_id} px-3"
                     data-product-id="${e.id}"
                     data-product-brand="${a.slug}"
                     data-product-price="${s}"
                     data-product-discount="${r}"
                     data-product-rating="${n.average}">
                    <div class="card h-100 border-0 d-flex flex-column product-card shadow-sm rounded">
                        <div class="position-relative product-image-container">
                            <div class="slider-wrapper w-100 h-100" data-slider>
                                <div class="slider-track d-flex h-100">
                                    ${i}
                                </div>
                            </div>
                            ${c}
                        </div>

                        <div class="card-body d-flex flex-column px-3 py-2">
                            <h6 class="text-dark text-truncate mb-1">
                                <a href="/product-detail/${e.slug}" class="text-dark product-title">
                                    ${t.truncateText(e.title, 50)}
                                </a>
                            </h6>

                            ${a.title ? `<small class="text-muted mb-1 brand-info"><i class="fa fa-tag"></i>${a.title}</small>` : ""}

                            ${o}

                            <div class="mb-2 price-container">
                                ${(u =
                                    r > 0
                                        ? `
                    <span class="text-primary font-weight-bold current-price">
                        ${t.formatPrice(s)}
                    </span>
                    <small class="text-muted ml-2 original-price">
                        <del>${t.formatPrice(l)}</del>
                    </small>
                `
                                        : `
                    <span class="font-weight-bold text-primary current-price">
                        ${t.formatPrice(s)}
                    </span>
                `)}
                            </div>

                            <div class="mt-auto action-buttons">
                                <a href="/cart/add/${e.id}"
                                   class="btn btn-sm btn-block btn-dark text-uppercase mb-3 text-center add-to-cart-btn ${d ? "" : "disabled"}">
                                    <i class="ti-shopping-cart mr-1"></i>
                                    ${d ? "Add to Cart" : "Out of Stock"}
                                </a>

                                <div class="d-flex justify-content-between align-items-center small text-muted px-1 secondary-actions">
                                    <a href="/wishlist/add/${e.id}" class="text-decoration-none wishlist-link">
                                        <i class="ti-heart mr-1"></i> Wishlist
                                    </a>
                                    <a href="#" class="text-decoration-none text-muted hover-text-dark quick-view-link"
                                       data-product-id="${e.id}" data-toggle="modal" data-target="#productModal${e.id}">
                                        <i class="ti-eye mr-1"></i> Quick View
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        }
        renderPagination(e) {
            if (!e || e.total <= e.per_page) return '<div class="row mt-4"><div class="col-12 d-flex justify-content-center"><p class="text-muted text-center">End of results.</p></div></div>';
            let t = `
                <div class="row mt-4">
                    <div class="col-12 d-flex justify-content-center">
                        <div class="pagination-wrapper">
                            <nav aria-label="Product pagination">
                                <ul class="pagination">
            `;
            t += `
                <li class="page-item ${1 === e.current_page ? "disabled" : ""}">
                    <a class="page-link" href="#" data-page="${e.current_page - 1}">
                        Previous
                    </a>
                </li>
            `;
            let i = Math.max(1, e.current_page - 2),
                a = Math.min(e.last_page, e.current_page + 2);
            for (let s = i; s <= a; s++)
                t += `
                    <li class="page-item ${s === e.current_page ? "active" : ""}">
                        <a class="page-link" href="#" data-page="${s}">${s}</a>
                    </li>
                `;
            return (
                (t += `
                <li class="page-item ${e.current_page === e.last_page ? "disabled" : ""}">
                    <a class="page-link" href="#" data-page="${e.current_page + 1}">
                        Next
                    </a>
                </li>
            `),
                (t += `
                                </ul>
                            </nav>
                        </div>
                    </div>
                </div>
            `)
            );
        }
        attachProductInteractions() {
            document.querySelectorAll("#product-grids .add-to-cart-btn:not(.disabled)").forEach((t) => {
                t.addEventListener("click", (i) => {
                    if ((i.preventDefault(), !t.dataset.cartInitialized)) {
                        t.dataset.cartInitialized = "true";
                        let a = t.innerHTML;
                        (t.innerHTML = '<i class="fa fa-spinner fa-spin mr-1"></i> Adding...'),
                            t.classList.add("disabled"),
                            setTimeout(() => {
                                (t.innerHTML = '<i class="fa fa-check mr-1"></i> Added!'),
                                    t.classList.add("btn-success"),
                                    t.classList.remove("btn-dark"),
                                    setTimeout(() => {
                                        (t.innerHTML = a), t.classList.remove("disabled", "btn-success"), t.classList.add("btn-dark"), delete t.dataset.cartInitialized;
                                    }, e.cartSuccessDelay);
                            }, e.cartAnimationDelay);
                    }
                });
            }),
                document.querySelectorAll("#product-grids .wishlist-link").forEach((e) => {
                    e.addEventListener("click", (t) => {
                        if ((t.preventDefault(), !e.dataset.wishlistInitialized)) {
                            e.dataset.wishlistInitialized = "true";
                            let i = e.querySelector("i");
                            if (i) {
                                let a = i.style.color;
                                (i.style.transform = "scale(1.3)"),
                                    (i.style.color = "red"),
                                    setTimeout(() => {
                                        (i.style.transform = "scale(1)"),
                                            setTimeout(() => {
                                                (i.style.color = a), delete e.dataset.wishlistInitialized;
                                            }, 1e3);
                                    }, 200);
                            }
                        }
                    });
                }),
                document.querySelectorAll("#product-grids .quick-view-link").forEach((e) => {
                    e.addEventListener("click", (t) => {
                        t.preventDefault();
                        let i = e.dataset.productId;
                        if (i) {
                            let a = document.getElementById(`productModal${i}`);
                            a &&
                                ("undefined" != typeof bootstrap ? bootstrap.Modal : jQuery?.fn.modal) &&
                                ("undefined" != typeof bootstrap && bootstrap.Modal ? new bootstrap.Modal(a).show() : "undefined" != typeof jQuery && jQuery.fn.modal && jQuery(a).modal("show"));
                        }
                    });
                });
        }
    }
    class l {
        constructor() {
            this.init();
        }
        init() {
            this.setupCartButtons(), this.setupWishlistButtons(), this.setupQuickViewButtons(), this.setupModalHandling();
        }
        setupCartButtons() {
            document.addEventListener("click", (e) => {
                let t = e.target.closest(".btn-dark:not(.disabled)");
                (t?.href?.includes("add-to-cart") || t?.classList.contains("add-to-cart-btn")) && (e.preventDefault(), this.handleAddToCart(t));
            });
        }
        handleAddToCart(t) {
            if (t.dataset.cartInitialized) return;
            t.dataset.cartInitialized = "true";
            let i = t.innerHTML,
                a = t.href || t.getAttribute("data-href");
            (t.innerHTML = '<i class="fa fa-spinner fa-spin mr-1"></i> Adding...'),
                t.classList.add("disabled"),
                (t.href = "javascript:void(0)"),
                setTimeout(() => {
                    (t.innerHTML = '<i class="fa fa-check mr-1"></i> Added!'),
                        t.classList.add("btn-success"),
                        t.classList.remove("btn-dark"),
                        setTimeout(() => {
                            (t.innerHTML = i), a && (t.href = a), t.classList.remove("disabled", "btn-success"), t.classList.add("btn-dark"), delete t.dataset.cartInitialized;
                        }, e.cartSuccessDelay);
                }, e.cartAnimationDelay);
        }
        setupWishlistButtons() {
            document.addEventListener("click", (e) => {
                let t = e.target.closest('[href*="add-to-wishlist"], .wishlist-link');
                t && !t.dataset.wishlistInitialized && (e.preventDefault(), (t.dataset.wishlistInitialized = "true"), this.handleWishlistToggle(t));
            });
        }
        handleWishlistToggle(e) {
            let t = e.querySelector("i");
            if (t) {
                let i = t.style.color;
                (t.style.transform = "scale(1.3)"),
                    (t.style.color = "red"),
                    setTimeout(() => {
                        (t.style.transform = "scale(1)"),
                            setTimeout(() => {
                                (t.style.color = i), delete e.dataset.wishlistInitialized;
                            }, 1e3);
                    }, 200);
            }
        }
        setupQuickViewButtons() {
            document.addEventListener("click", (e) => {
                let t = e.target.closest('[data-toggle="modal"], .quick-view-link');
                if (t && !t.dataset.quickviewInitialized) {
                    e.preventDefault(), (t.dataset.quickviewInitialized = "true");
                    let i = t.getAttribute("data-target") || t.getAttribute("onclick")?.match(/#([^']*)/)?.[1];
                    if (i) {
                        let a = document.querySelector(i);
                        a && this.showModal(a);
                    }
                    setTimeout(() => delete t.dataset.quickviewInitialized, 1e3);
                }
            });
        }
        showModal(e) {
            "undefined" != typeof bootstrap && bootstrap.Modal ? new bootstrap.Modal(e).show() : "undefined" != typeof jQuery && jQuery.fn.modal && jQuery(e).modal("show");
        }
        setupModalHandling() {
            document.querySelectorAll('[id^="productModal"], #quickViewModal:not([data-modal-initialized])').forEach((e) => {
                (e.dataset.modalInitialized = "true"), e.addEventListener("hidden.bs.modal", () => e.setAttribute("inert", "")), e.addEventListener("show.bs.modal", () => e.removeAttribute("inert"));
            });
        }
    }
    if (!document.getElementById("unified-shop-system-styles")) {
        let r = document.createElement("style");
        (r.id = "unified-shop-system-styles"), document.head.appendChild(r);
    }
    (window.unifiedShopSystem = new (class e {
        constructor() {
            (this.imageSliderSystem = null), (this.filterSystem = null), (this.productInteractionSystem = null), this.init();
        }
        init() {
            let e = () => this.initialize();
            "loading" === document.readyState ? document.addEventListener("DOMContentLoaded", e) : e();
        }
        initialize() {
            (this.imageSliderSystem = new a()),
                (this.filterSystem = new s()),
                (this.productInteractionSystem = new l()),
                (window.imageSliderSystem = this.imageSliderSystem),
                (window.filterSystem = this.filterSystem),
                (window.productInteractionSystem = this.productInteractionSystem),
                this.setupContentObserver();
        }
        setupContentObserver() {
            let e = document.getElementById("product-grids");
            if (!e) return;
            let t = new MutationObserver((e) => {
                let t = !1;
                e.forEach((e) => {
                    "childList" === e.type && e.addedNodes.length > 0 && (t = !0);
                }),
                    t && this.imageSliderSystem && this.imageSliderSystem.reinitialize(),
                    this.productInteractionSystem && (this.productInteractionSystem.setupCartButtons(), this.productInteractionSystem.setupWishlistButtons(), this.productInteractionSystem.setupQuickViewButtons());
            });
            t.observe(e, { childList: !0, subtree: !0 });
        }
    })()),
        (window.unifiedShopSystemInitialized = !0);
})();