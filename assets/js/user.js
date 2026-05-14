document.addEventListener("DOMContentLoaded", function () {
    initCheckoutScrollReset();
    clearGuestCommerceData();
    initNavbar();
    initStickyCategoryNav();
    initSmoothLinks();
    initSearchHistory();
    initLazyDynamicMedia();
    initPageAnimations();
    initCategoryButtons();
    initProductDetail();
    initContactForm();
    initOtpCountdown();
    initLeftHeroSlider();
    initHomeVideoAutopause();
    initScrollTop();
    initAlerts();
    initHomepagePremiumEffects();
    initRipples();
    initProfilePage();
    initProfileImageAdjustments();
    applySavedProfileImageAdjustments();
    initHelpCenter();
    saveRecentlyViewedProduct();
    initCommerceActions();
    renderWishlistPage();
    renderCartPage();
    renderCheckoutPage();
    renderPlaceOrderPage();
    renderLocalOrderTracking();
    renderMyOrdersPage();
    updateHeaderCounts();
});

function initStickyCategoryNav() {
    var nav = document.querySelector(".category-nav");
    if (!nav) return;

    var spacer = document.createElement("div");
    spacer.className = "category-nav-spacer";
    spacer.style.display = "none";
    nav.parentNode.insertBefore(spacer, nav.nextSibling);

    var navTop = 0;
    function measure() {
        nav.classList.remove("is-fixed");
        spacer.style.display = "none";
        navTop = nav.getBoundingClientRect().top + window.pageYOffset;
        spacer.style.height = nav.offsetHeight + "px";
        update();
    }

    function update() {
        var shouldFix = window.pageYOffset >= navTop;
        nav.classList.toggle("is-fixed", shouldFix);
        spacer.style.display = shouldFix ? "block" : "none";
        spacer.style.height = nav.offsetHeight + "px";
    }

    window.addEventListener("scroll", update, { passive: true });
    window.addEventListener("resize", measure);
    setTimeout(measure, 100);
}

function initLazyDynamicMedia() {
    document.querySelectorAll("img:not([loading]):not(#leftHeroSlideImage)").forEach(function (img) {
        img.loading = "lazy";
        img.decoding = "async";
    });
}

function initHomeVideoAutopause() {
    var videos = document.querySelectorAll(".home-video-card video");
    if (!videos.length || !("IntersectionObserver" in window)) return;

    var observer = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
            if (entry.isIntersecting) entry.target.play().catch(function () {});
            else entry.target.pause();
        });
    }, { threshold: 0.2 });

    videos.forEach(function (video) {
        observer.observe(video);
    });
}

function initLeftHeroSlider() {
    var hero = document.getElementById("mainHeroSlider");
    if (!hero) return;

    var slides = JSON.parse(hero.dataset.heroSlides || "[]");
    if (!slides.length) {
        slides = [
            { image_path: "https://images.unsplash.com/photo-1618220179428-22790b461013?auto=format&fit=crop&w=1400&q=80" },
            { image_path: "https://images.unsplash.com/photo-1600210492493-0946911123ea?auto=format&fit=crop&w=1400&q=80" },
            { image_path: "https://images.unsplash.com/photo-1616486338812-3dadae4b4ace?auto=format&fit=crop&w=1400&q=80" }
        ];
    }
    var index = 0;

    function applyHeroSlide(slide) {
        var image = typeof slide === "string" ? slide : slide.image_path;
        var img = document.getElementById("leftHeroSlideImage");
        hero.classList.add("is-changing");

        setTimeout(function () {
            hero.style.backgroundImage = "url('" + image + "')";
            if (img) img.src = image;
            updateHeroContent(slide);
            hero.classList.remove("is-changing");
        }, 260);
    }

    function updateHeroContent(slide) {
        if (typeof slide !== "string") {
            var title = document.getElementById("heroSlideTitle");
            var subtitle = document.getElementById("heroSlideSubtitle");
            var button = document.getElementById("heroSlideButton");
            if (title) title.textContent = slide.title || "Zafiro Casa Luxury Living";
            if (subtitle) subtitle.textContent = slide.subtitle || "";
            if (button) {
                button.textContent = slide.button_text || "Explore Collection";
                button.href = slide.button_link || "#categories";
            }
        }
    }

    var firstImage = typeof slides[0] === "string" ? slides[0] : slides[0].image_path;
    hero.style.backgroundImage = "url('" + firstImage + "')";
    updateHeroContent(slides[0]);

    if (slides.length > 1) {
        setInterval(function () {
            index = (index + 1) % slides.length;
            applyHeroSlide(slides[index]);
        }, 5000);
    }
}

function initOtpCountdown() {
    var box = document.querySelector("[data-otp-expiry]");
    if (!box) return;

    var timer = document.getElementById("otpTimer");
    var verifyBtn = document.getElementById("verifyOtpBtn");
    var resendBtn = document.getElementById("resendOtpBtn");
    var expiry = new Date(box.dataset.otpExpiry.replace(" ", "T")).getTime();

    function tick() {
        var left = Math.max(0, Math.floor((expiry - Date.now()) / 1000));
        var mm = String(Math.floor(left / 60)).padStart(2, "0");
        var ss = String(left % 60).padStart(2, "0");
        if (timer) timer.textContent = mm + ":" + ss;

        if (left <= 0) {
            box.textContent = "OTP expired. Please resend OTP.";
            if (verifyBtn) verifyBtn.disabled = true;
            if (resendBtn) resendBtn.classList.remove("disabled");
            return;
        }

        setTimeout(tick, 1000);
    }

    tick();
}

function initNavbar() {
    var header = document.querySelector("header");
    var nav = document.querySelector("nav");
    var toggle = document.querySelector(".menu-toggle, .nav-toggle, [data-menu-toggle]");
    var page = location.pathname.split("/").pop() || "index.php";

    document.querySelectorAll("nav a").forEach(function (link) {
        var href = link.getAttribute("href") || "";
        if (href.split("#")[0] === page) link.classList.add("active");
    });

    if (toggle && nav && !toggle.dataset.bound) {
        toggle.dataset.bound = "true";
        toggle.addEventListener("click", function () {
            nav.classList.toggle("open");
            toggle.classList.toggle("active");
        });
    }

    if (header) {
        window.addEventListener("scroll", function () {
            header.classList.toggle("is-scrolled", window.scrollY > 20);
        });
    }
}

function initSmoothLinks() {
    document.querySelectorAll('a[href^="#"]').forEach(function (link) {
        link.addEventListener("click", function (e) {
            var target = document.querySelector(link.getAttribute("href"));
            if (!target) return;
            e.preventDefault();
            target.scrollIntoView({ behavior: "smooth", block: "start" });
        });
    });
}

function initSearchHistory() {
    var form = document.querySelector(".search-wrapper");
    var input = document.getElementById("searchInput");
    var button = document.getElementById("searchBtn");
    var dropdown = document.getElementById("searchDropdown");
    var key = "zafiroSearchHistory";
    var suggestions = [
        "Sofa", "Sofa Cum Bed", "Seating", "Living Storage", "TV Units",
        "Beds", "Bedroom Table", "Wardrobe", "Mattress", "Pillows",
        "Dining Sets", "Dining Chair", "Dining Table", "Kitchen Storage",
        "Storage", "Shoe Rack", "Study Table", "Office Chair",
        "Outdoor Furniture", "Balcony Furniture", "Wall Decor", "Lamps",
        "Mirrors", "Modular Kitchen"
    ];

    if (!form || !input || !button || !dropdown) return;

    function getHistory() {
        return JSON.parse(localStorage.getItem(key) || "[]");
    }

    function saveHistory(term) {
        var clean = term.trim();
        if (!clean) return;

        var history = getHistory().filter(function (item) {
            return item.toLowerCase() !== clean.toLowerCase();
        });

        history.unshift(clean);
        localStorage.setItem(key, JSON.stringify(history.slice(0, 8)));
    }

    function searchNow(term) {
        var clean = term.trim();
        if (!clean) {
            showToast("Please enter a search term", "error");
            return;
        }
        saveHistory(clean);
        window.location.href = form.getAttribute("action") + "?q=" + encodeURIComponent(clean);
    }

    function renderHistory() {
        var history = getHistory();

        if (!history.length) {
            dropdown.classList.remove("open");
            dropdown.innerHTML = "";
            return;
        }

        dropdown.innerHTML = '<div class="search-dropdown-title">Recent searches</div>' + history.map(function (item) {
            return '<div class="search-row history-row"><button type="button" class="history-item" data-term="' + escapeHtml(item) + '"><span class="history-icon">↺</span><span>' + escapeHtml(item) + '</span></button><button type="button" class="history-remove" data-term="' + escapeHtml(item) + '">×</button></div>';
        }).join("") + '<button type="button" class="history-clear">Clear all</button>';

        dropdown.classList.add("open");
    }

    function renderSuggestions(value) {
        var keyword = value.trim().toLowerCase();
        var matches = suggestions.filter(function (item) {
            return item.toLowerCase().includes(keyword);
        }).slice(0, 8);

        if (!keyword) {
            renderHistory();
            return;
        }

        if (!matches.length) {
            dropdown.innerHTML = '<div class="search-empty">No suggestions found</div>';
            dropdown.classList.add("open");
            return;
        }

        dropdown.innerHTML = '<div class="search-dropdown-title">Suggestions</div>' + matches.map(function (item) {
            return '<button type="button" class="suggestion-item" data-term="' + escapeHtml(item) + '"><span class="suggestion-icon">⌕</span><span>' + escapeHtml(item) + '</span></button>';
        }).join("");

        dropdown.classList.add("open");
    }

    input.addEventListener("focus", renderHistory);
    input.addEventListener("input", function () {
        renderSuggestions(input.value);
    });

    form.addEventListener("submit", function (e) {
        e.preventDefault();
        searchNow(input.value);
    });

    button.addEventListener("click", function (e) {
        e.preventDefault();
        searchNow(input.value);
    });

    dropdown.addEventListener("click", function (e) {
        if (e.target.classList.contains("history-clear")) {
            localStorage.removeItem(key);
            renderHistory();
            return;
        }

        if (e.target.classList.contains("history-remove")) {
            var removeTerm = e.target.dataset.term;
            var filtered = getHistory().filter(function (item) {
                return item !== removeTerm;
            });
            localStorage.setItem(key, JSON.stringify(filtered));
            renderHistory();
            return;
        }

        var itemButton = e.target.closest(".history-item, .suggestion-item");
        if (itemButton) {
            input.value = itemButton.dataset.term;
            searchNow(input.value);
        }
    });

    document.addEventListener("click", function (e) {
        if (!form.contains(e.target)) {
            dropdown.classList.remove("open");
        }
    });
}

function escapeHtml(value) {
    return String(value).replace(/[&<>"']/g, function (char) {
        return {
            "&": "&amp;",
            "<": "&lt;",
            ">": "&gt;",
            '"': "&quot;",
            "'": "&#039;"
        }[char];
    });
}

function initPageAnimations() {
    var hero = document.querySelector(".hero, .hero-grid");
    var cards = document.querySelectorAll(".card, .product-card, .category-card, .mega-card");

    if (hero) {
        hero.style.opacity = "0";
        hero.style.transition = "opacity .7s ease";
        requestAnimationFrame(function () { hero.style.opacity = "1"; });
    }

    if (!cards.length || !("IntersectionObserver" in window)) return;

    var observer = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
            if (!entry.isIntersecting) return;
            entry.target.style.opacity = "1";
            entry.target.style.transform = "translateY(0)";
            observer.unobserve(entry.target);
        });
    }, { threshold: 0.15 });

    cards.forEach(function (card, index) {
        card.style.opacity = "0";
        card.style.transform = "translateY(16px)";
        card.style.transition = "opacity .45s ease, transform .45s ease";
        card.style.transitionDelay = Math.min(index * 50, 300) + "ms";
        observer.observe(card);
    });
}

function initCategoryButtons() {
    document.querySelectorAll(".filter-btn, [data-filter], .category-filter button").forEach(function (btn) {
        btn.addEventListener("click", function () {
            btn.parentElement.querySelectorAll("button, .filter-btn").forEach(function (item) {
                item.classList.remove("active");
            });
            btn.classList.add("active");
        });
    });
}

function initProductDetail() {
    var mainImg = document.querySelector(".detail-img img, [data-main-image]");
    var thumbs = document.querySelectorAll(".thumbnail img, [data-thumbnail]");
    var qty = document.querySelector('input[name="quantity"], .quantity-input, [data-quantity-input]');
    var minus = document.querySelector(".quantity-minus, .quantity-decrease, [data-quantity-minus]");
    var plus = document.querySelector(".quantity-plus, .quantity-increase, [data-quantity-plus]");

    thumbs.forEach(function (thumb) {
        thumb.addEventListener("click", function () {
            if (!mainImg) return;
            mainImg.src = thumb.dataset.full || thumb.src;
        });
    });

    if (mainImg) {
        mainImg.addEventListener("mouseenter", function () { mainImg.style.transform = "scale(1.05)"; });
        mainImg.addEventListener("mouseleave", function () { mainImg.style.transform = ""; });
    }

    if (qty) {
        qty.addEventListener("input", function () { if (+qty.value < 1 || !qty.value) qty.value = 1; });
        if (minus) minus.addEventListener("click", function () { qty.value = Math.max(1, (+qty.value || 1) - 1); });
        if (plus) plus.addEventListener("click", function () { qty.value = (+qty.value || 1) + 1; });
    }
}

function initContactForm() {
    var form = document.querySelector(".contact-form form");
    if (!form) return;

    form.addEventListener("submit", function (e) {
        var name = form.querySelector('[name="name"]');
        var firstName = form.querySelector('[name="first_name"]');
        var lastName = form.querySelector('[name="last_name"]');
        var phone = form.querySelector('[name="phone"], [name="contact"], input[type="tel"]');
        var message = form.querySelector('[name="message"], textarea');
        var error = "";

        if (name && firstName && lastName) {
            name.value = (firstName.value.trim() + " " + lastName.value.trim()).trim();
        }

        if (firstName && !firstName.value.trim()) error = "First name is required.";
        else if (lastName && !lastName.value.trim()) error = "Last name is required.";
        else if (name && !name.value.trim()) error = "Name is required.";
        else if (phone && !/^\d{10}$/.test(phone.value.replace(/\D/g, ""))) error = "Phone number must be 10 digits.";
        else if (message && !message.value.trim()) error = "Message is required.";

        showFormMessage(form, error || "Sending message...", error ? "error" : "success");
        if (error) {
            e.preventDefault();
            showToast(error, "error");
        }
    });
}

function showFormMessage(form, text, type) {
    var box = form.querySelector(".form-message") || document.createElement("div");
    box.className = "form-message " + type;
    box.textContent = text;
    box.style.marginTop = "10px";
    box.style.color = type === "error" ? "#ffb4ab" : "#b7f7c8";
    if (!box.parentElement) form.appendChild(box);
}

function initScrollTop() {
    var btn = document.createElement("button");
    btn.type = "button";
    btn.innerHTML = '<i class="fa-solid fa-arrow-up"></i><span>Top</span>';
    btn.className = "scroll-top";
    document.body.appendChild(btn);

    window.addEventListener("scroll", function () {
        btn.style.display = window.scrollY > 350 ? "block" : "none";
    });
    btn.addEventListener("click", function () {
        window.scrollTo({ top: 0, behavior: "smooth" });
    });
}

function initAlerts() {
    var params = new URLSearchParams(window.location.search);
    if (params.get("contact") === "sent") {
        showSuccess("Message sent successfully!");
        params.delete("contact");
        var cleanUrl = window.location.pathname + (params.toString() ? "?" + params.toString() : "") + window.location.hash;
        window.history.replaceState({}, "", cleanUrl);
    }

    document.querySelectorAll(".alert, .message, .notice").forEach(function (alert) {
        setTimeout(function () { alert.style.display = "none"; }, 4000);
    });
}

function showToast(text, type) {
    var toast = document.createElement("div");
    toast.textContent = text;
    toast.className = "toast " + (type || "");
    toast.style.cssText = "position:fixed;left:50%;bottom:70px;transform:translateX(-50%);background:#111;color:#fff;padding:10px 14px;z-index:1000;";
    document.body.appendChild(toast);
    setTimeout(function () { toast.remove(); }, 3000);
}

function initRipples() {
    document.querySelectorAll("button, .product-btn, .hero-btn").forEach(function (btn) {
        btn.addEventListener("click", function () {
            btn.classList.add("clicked");
            setTimeout(function () { btn.classList.remove("clicked"); }, 250);
        });
    });
}

function initHomepagePremiumEffects() {
    var fadeItems = document.querySelectorAll(".premium-fade");
    if (!fadeItems.length) return;

    var observer = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
            if (entry.isIntersecting) entry.target.classList.add("visible");
        });
    }, { threshold: 0.16 });

    fadeItems.forEach(function (item) {
        observer.observe(item);
    });

    if (window.matchMedia("(prefers-reduced-motion: reduce)").matches || window.innerWidth < 900) return;

    var parallaxCards = document.querySelectorAll(".premium-parallax-card");
    var ticking = false;
    window.addEventListener("scroll", function () {
        if (ticking) return;
        ticking = true;
        requestAnimationFrame(function () {
            parallaxCards.forEach(function (card) {
                var box = card.getBoundingClientRect();
                if (box.top < window.innerHeight && box.bottom > 0) {
                    card.style.setProperty("--premium-shift", Math.round(box.top * -0.015) + "px");
                    card.style.transform = "translateY(var(--premium-shift))";
                }
            });
            ticking = false;
        });
    }, { passive: true });
}

function initProfilePage() {
    document.querySelectorAll("[data-profile-message]").forEach(function (button) {
        button.addEventListener("click", function () {
            showToast(button.dataset.profileMessage, "info");
        });
    });

    var recentWrap = document.getElementById("recentProducts");
    var wishlistWrap = document.getElementById("wishlistProducts");
    var recent = JSON.parse(localStorage.getItem("zafiroRecentlyViewed") || "[]");
    if (recentWrap && recent.length) {
        var validRecent = recent.map(normalizeRecentProduct).filter(function (item) {
            return item.id;
        });
        localStorage.setItem("zafiroRecentlyViewed", JSON.stringify(validRecent.slice(0, 8)));
        recentWrap.innerHTML = validRecent.length ? validRecent.slice(0, 3).map(function (item) {
            return '<a class="recent-product-card" href="product-view.php?id=' + encodeURIComponent(item.id) + '"><img class="recent-product-img" src="' + escapeHtml(item.image || "") + '" alt="' + escapeHtml(item.name || "Product") + '" loading="lazy" decoding="async"><p>' + escapeHtml(item.name || "Product") + '</p></a>';
        }).join("") : "<p>No recently viewed products.</p>";
    }

    var wishlist = JSON.parse(localStorage.getItem("zafiroWishlist") || "[]");
    if (wishlistWrap && wishlist.length) {
        wishlistWrap.innerHTML = wishlist.slice(0, 6).map(function (item) {
            return '<div class="recent-product-card"><img src="' + escapeHtml(item.image || "") + '" alt="' + escapeHtml(item.name || "Product") + '" loading="lazy" decoding="async"><p>' + escapeHtml(item.name || "Product") + '</p></div>';
        }).join("");
    }
}

function normalizeRecentProduct(item) {
    var id = item.id || item.product_id || "";
    if (!id && item.product_url) {
        try {
            id = new URL(item.product_url, window.location.href).searchParams.get("id") || "";
        } catch (error) {}
    }
    return {
        id: id,
        product_id: id,
        name: item.name || item.product_name || "Product",
        image: item.image || "",
        price: item.price || ""
    };
}

function initProfileImageAdjustments() {
    var preview = document.getElementById("profileImagePreview");
    var defaultAvatar = document.getElementById("profileDefaultAvatar");
    var fileInput = document.getElementById("profileImageInput");
    var changeButton = document.getElementById("changeProfilePhotoBtn");
    var removeButton = document.getElementById("removeProfilePhotoBtn");
    var removeInput = document.getElementById("removeProfileImageInput");
    var positionXInput = document.getElementById("profileImagePositionX");
    var positionYInput = document.getElementById("profileImagePositionY");
    var zoomInput = document.getElementById("profileImageZoom");
    var modal = document.getElementById("profilePhotoModal");
    var modalPreview = document.getElementById("profileModalPreview");
    var confirmButton = document.getElementById("confirmProfilePhotoBtn");
    var cancelButton = document.getElementById("cancelProfilePhotoBtn");
    var controls = document.querySelectorAll("[data-profile-adjust]");
    if (!preview || !fileInput || !modal || !modalPreview) return;

    var storageKey = "zafiroProfileImageAdjust_" + (getCurrentUserId() || "guest");
    var state = {
        x: parseFloat(preview.dataset.positionX || (positionXInput && positionXInput.value) || "50"),
        y: parseFloat(preview.dataset.positionY || (positionYInput && positionYInput.value) || "50"),
        zoom: parseFloat(preview.dataset.zoom || (zoomInput && zoomInput.value) || "1")
    };
    var pendingSrc = "";
    var confirmedSrc = preview.getAttribute("src") || "";

    function clamp(value, min, max) {
        return Math.max(min, Math.min(max, value));
    }

    function applyState() {
        [preview, modalPreview].forEach(function (image) {
            if (!image || image.tagName.toLowerCase() !== "img") return;
            if (!image.getAttribute("src")) return;
            image.style.objectPosition = state.x + "% " + state.y + "%";
            image.style.transform = "scale(" + state.zoom + ")";
            image.style.transformOrigin = state.x + "% " + state.y + "%";
        });
        if (positionXInput) positionXInput.value = state.x;
        if (positionYInput) positionYInput.value = state.y;
        if (zoomInput) zoomInput.value = state.zoom;
        localStorage.setItem(storageKey, JSON.stringify(state));
    }

    function showImage(src) {
        preview.src = src;
        preview.classList.remove("is-hidden");
        if (defaultAvatar) defaultAvatar.classList.add("is-hidden");
        applyState();
    }

    function showDefaultAvatar() {
        preview.removeAttribute("src");
        preview.classList.add("is-hidden");
        if (defaultAvatar) defaultAvatar.classList.remove("is-hidden");
    }

    function openModal(src) {
        pendingSrc = src;
        modalPreview.src = src;
        modal.classList.add("open");
        applyState();
    }

    function closeModal() {
        modal.classList.remove("open");
    }

    if (changeButton) {
        changeButton.addEventListener("click", function () {
            fileInput.click();
        });
    }

    fileInput.addEventListener("change", function () {
        var file = fileInput.files && fileInput.files[0];
        if (!file) return;
        if (!file.type.match(/^image\/(jpeg|png|webp)$/)) {
            showToast("Only JPG, PNG, and WEBP images are allowed.", "error");
            fileInput.value = "";
            return;
        }
        var reader = new FileReader();
        reader.onload = function (event) {
            state = { x: 50, y: 50, zoom: 1 };
            openModal(event.target.result);
        };
        reader.readAsDataURL(file);
    });

    if (confirmButton) {
        confirmButton.addEventListener("click", function () {
            if (!pendingSrc) return;
            confirmedSrc = pendingSrc;
            if (removeInput) removeInput.value = "";
            showImage(confirmedSrc);
            closeModal();
        });
    }

    if (cancelButton) {
        cancelButton.addEventListener("click", function () {
            fileInput.value = "";
            pendingSrc = "";
            if (confirmedSrc) {
                showImage(confirmedSrc);
            } else {
                showDefaultAvatar();
            }
            closeModal();
        });
    }

    if (removeButton) {
        removeButton.addEventListener("click", function () {
            fileInput.value = "";
            pendingSrc = "";
            confirmedSrc = "";
            if (removeInput) removeInput.value = "1";
            showDefaultAvatar();
        });
    }

    modal.addEventListener("click", function (event) {
        if (event.target === modal && cancelButton) cancelButton.click();
    });

    controls.forEach(function (button) {
        button.addEventListener("click", function () {
            var action = button.dataset.profileAdjust;
            if (action === "up") state.y = clamp(state.y - 5, 0, 100);
            if (action === "down") state.y = clamp(state.y + 5, 0, 100);
            if (action === "left") state.x = clamp(state.x - 5, 0, 100);
            if (action === "right") state.x = clamp(state.x + 5, 0, 100);
            if (action === "zoom-in") state.zoom = clamp(Math.round((state.zoom + 0.05) * 100) / 100, 1, 1.6);
            if (action === "zoom-out") state.zoom = clamp(Math.round((state.zoom - 0.05) * 100) / 100, 1, 1.6);
            if (action === "reset") state = { x: 50, y: 50, zoom: 1 };
            applyState();
        });
    });

    if (confirmedSrc) {
        showImage(confirmedSrc);
    } else {
        showDefaultAvatar();
    }
}

function applySavedProfileImageAdjustments() {
    document.querySelectorAll("[data-profile-adjust-preview]").forEach(function (image) {
        if (!image.getAttribute("src")) return;
        var x = parseFloat(image.dataset.positionX || "50");
        var y = parseFloat(image.dataset.positionY || "50");
        var zoom = parseFloat(image.dataset.zoom || "1");
        image.style.objectPosition = x + "% " + y + "%";
        image.style.transform = "scale(" + zoom + ")";
        image.style.transformOrigin = x + "% " + y + "%";
    });
}

function initHelpCenter() {
    document.querySelectorAll(".help-toggle").forEach(function (button) {
        button.addEventListener("click", function () {
            button.classList.toggle("active");
            var panel = button.nextElementSibling;
            if (panel) panel.classList.toggle("open");
        });
    });
}

function saveRecentlyViewedProduct() {
    var detail = document.querySelector(".product-detail-view");
    if (!detail) return;

    var product = {
        id: detail.dataset.productId,
        product_id: detail.dataset.productId,
        name: detail.dataset.productName,
        image: detail.dataset.productImage,
        price: detail.dataset.productPrice
    };

    if (!product.id) return;

    var recent = JSON.parse(localStorage.getItem("zafiroRecentlyViewed") || "[]").filter(function (item) {
        return (item.id || item.product_id) !== product.id;
    });

    recent.unshift(product);
    localStorage.setItem("zafiroRecentlyViewed", JSON.stringify(recent.slice(0, 8)));
}

function getProductData(element) {
    var product = element.closest("[data-product-id]");
    if (!product) return null;

    return {
        product_id: product.dataset.productId,
        product_name: product.dataset.productName,
        price: product.dataset.productPrice,
        image: product.dataset.productImage,
        product_url: product.dataset.productUrl || window.location.href,
        quantity: parseInt(product.dataset.productQuantity || "1", 10) || 1
    };
}

function getStore(key) {
    var storageKey = getScopedStoreKey(key);
    if (!storageKey) return [];
    return JSON.parse(localStorage.getItem(storageKey) || "[]");
}

function setStore(key, value) {
    var storageKey = getScopedStoreKey(key);
    if (!storageKey) {
        updateHeaderCounts();
        return;
    }
    localStorage.setItem(storageKey, JSON.stringify(value));
    updateHeaderCounts();
}

function isUserLoggedIn() {
    return document.body && document.body.dataset.auth === "1";
}

function getCurrentUserId() {
    return document.body ? (document.body.dataset.userId || "") : "";
}

function getScopedStoreKey(key) {
    var scopedKeys = ["zafiroCart", "zafiroWishlist", "zafiroOrders"];
    if (!scopedKeys.includes(key)) return key;
    if (!isUserLoggedIn() || !getCurrentUserId()) return null;
    return key + "_user_" + getCurrentUserId();
}

function clearGuestCommerceData() {
    if (isUserLoggedIn()) return;
    ["zafiroCart", "zafiroWishlist", "zafiroOrders", "zafiroBuyNowItem"].forEach(function (key) {
        localStorage.removeItem(key);
    });
}

function initCommerceActions() {
    document.addEventListener("click", async function (event) {
        var wishlistButton = event.target.closest(".wishlist-btn");
        var cartButton = event.target.closest(".add-cart-btn");
        var shareButton = event.target.closest(".share-btn");

        if (wishlistButton) {
            event.preventDefault();
            event.stopPropagation();
            if (!isUserLoggedIn()) {
                showToast("Please sign in to use wishlist", "error");
                window.location.href = "auth.php";
                return;
            }
            var wishProduct = getProductData(wishlistButton);
            if (wishProduct) toggleWishlist(wishProduct, wishlistButton);
        }

        if (cartButton) {
            event.preventDefault();
            event.stopPropagation();
            if (!isUserLoggedIn()) {
                showToast("Please sign in to add products to cart", "error");
                window.location.href = "auth.php";
                return;
            }
            var cartProduct = getProductData(cartButton);
            if (cartProduct) addToCart(cartProduct);
        }

        if (shareButton) {
            event.preventDefault();
            event.stopPropagation();
            var shareProduct = getProductData(shareButton);
            if (shareProduct) await shareProductLink(shareProduct);
        }
    });

    markWishlistButtons();
}

function toggleWishlist(product, button) {
    var wishlist = getStore("zafiroWishlist");
    var exists = wishlist.some(function (item) {
        return item.product_id === product.product_id;
    });

    if (exists) {
        wishlist = wishlist.filter(function (item) {
            return item.product_id !== product.product_id;
        });
        button.classList.remove("active");
        showToast("Removed from wishlist", "info");
    } else {
        wishlist.unshift(product);
        button.classList.add("active");
        showToast("Added to wishlist", "success");
    }

    setStore("zafiroWishlist", wishlist);
    renderWishlistPage();
}

function addToCart(product) {
    var cart = getStore("zafiroCart");
    var quantity = Math.max(1, parseInt(product.quantity || "1", 10) || 1);
    var existing = cart.find(function (item) {
        return item.product_id === product.product_id;
    });

    if (existing) {
        existing.quantity += quantity;
    } else {
        product.quantity = quantity;
        cart.unshift(product);
    }

    setStore("zafiroCart", cart);
    showToast("Added to cart", "success");
    renderCartPage();
}

async function shareProductLink(product) {
    var absoluteUrl = new URL(product.product_url, window.location.href).href;

    if (navigator.share) {
        await navigator.share({ title: product.product_name, url: absoluteUrl });
        return;
    }

    await navigator.clipboard.writeText(absoluteUrl);
    showToast("Product link copied", "success");
}

function markWishlistButtons() {
    var wishlist = getStore("zafiroWishlist");
    document.querySelectorAll(".wishlist-btn").forEach(function (button) {
        var product = getProductData(button);
        if (!product) return;
        button.classList.toggle("active", wishlist.some(function (item) {
            return item.product_id === product.product_id;
        }));
    });
}

function renderWishlistPage() {
    var wrap = document.getElementById("wishlistProducts");
    if (!wrap) return;

    var wishlist = getStore("zafiroWishlist");
    if (!wishlist.length) {
        wrap.innerHTML = "<p>Your wishlist is empty.</p>";
        return;
    }

    wrap.innerHTML = wishlist.map(function (item) {
        return '<div class="commerce-card" data-product-id="' + escapeHtml(item.product_id) + '" data-product-name="' + escapeHtml(item.product_name) + '" data-product-price="' + escapeHtml(item.price) + '" data-product-image="' + escapeHtml(item.image) + '" data-product-url="' + escapeHtml(item.product_url) + '"><img src="' + escapeHtml(item.image) + '" alt="' + escapeHtml(item.product_name) + '" loading="lazy" decoding="async"><div class="commerce-card-body"><strong>' + escapeHtml(item.product_name) + '</strong><span>₹' + escapeHtml(item.price) + '</span><a class="account-btn small" href="' + escapeHtml(item.product_url) + '">View Details</a><button type="button" class="account-btn small add-cart-btn">Add to Cart</button><button type="button" class="account-btn small danger-btn remove-wishlist-btn">Remove</button></div></div>';
    }).join("");

    wrap.querySelectorAll(".remove-wishlist-btn").forEach(function (button) {
        button.addEventListener("click", function () {
            var product = getProductData(button);
            if (!product) return;
            setStore("zafiroWishlist", getStore("zafiroWishlist").filter(function (item) {
                return item.product_id !== product.product_id;
            }));
            showToast("Removed from wishlist", "info");
            renderWishlistPage();
        });
    });
}

function updateCartQuantity(id, delta) {
    var cart = getStore("zafiroCart").map(function (item) {
        if (item.product_id === id) item.quantity += delta;
        return item;
    }).filter(function (item) {
        return item.quantity > 0;
    });
    setStore("zafiroCart", cart);
    renderCartPage();
}

function renderCartPage() {
    var wrap = document.getElementById("cartProducts");
    var summary = document.getElementById("cartSummary");
    if (!wrap || !summary) return;

    var cart = getStore("zafiroCart");
    if (!cart.length) {
        wrap.innerHTML = '<div class="empty-cart"><h2>Your cart is empty</h2><a href="index.php" class="account-btn small">Continue Shopping</a></div>';
        summary.innerHTML = "";
        return;
    }

    var subtotal = 0;
    wrap.innerHTML = cart.map(function (item) {
        var price = parseFloat(String(item.price).replace(/,/g, "")) || 0;
        var quantity = item.quantity || 1;
        subtotal += price * quantity;
        var productUrl = item.product_url || ("product.php?id=" + item.product_id);
        var productInfo = item.category || item.description || "Premium Furniture";
        return '<div class="cart-item" data-product-id="' + escapeHtml(item.product_id) + '"><a class="cart-image-link" href="' + escapeHtml(productUrl) + '"><img src="' + escapeHtml(item.image) + '" alt="' + escapeHtml(item.product_name) + '" loading="lazy" decoding="async"></a><div class="cart-item-info"><a class="cart-product-title" href="' + escapeHtml(productUrl) + '">' + escapeHtml(item.product_name) + '</a><p class="cart-description">' + escapeHtml(productInfo) + '</p><p class="cart-price">&#8377;' + escapeHtml(item.price) + '</p><p class="delivery-estimate">Delivery by 5-7 business days</p><div class="qty-controls"><button type="button" class="cart-minus">-</button><span>' + quantity + '</span><button type="button" class="cart-plus">+</button></div><div class="cart-link-actions"><a href="' + escapeHtml(productUrl) + '">View Product</a><button type="button" class="cart-remove">Remove</button><button type="button" class="buy-now-btn">Buy This Now</button></div></div><div class="cart-item-price-right"></div></div>';
    }).join("");

    var delivery = subtotal > 0 ? 499 : 0;
    summary.innerHTML = '<h2>Price Summary</h2><p><span>Total Items</span><strong>' + cart.reduce(function (sum, item) { return sum + (item.quantity || 1); }, 0) + '</strong></p><p><span>Subtotal</span><strong>&#8377;' + subtotal.toLocaleString("en-IN") + '</strong></p><p><span>Delivery Charges</span><strong>&#8377;' + delivery.toLocaleString("en-IN") + '</strong></p><hr><h3><span>Final Total</span><strong>&#8377;' + (subtotal + delivery).toLocaleString("en-IN") + '</strong></h3><button class="account-btn place-order-btn" type="button">Checkout</button>';

    wrap.querySelectorAll(".cart-item").forEach(function (itemEl) {
        var id = itemEl.dataset.productId;
        itemEl.querySelector(".cart-plus").addEventListener("click", function () {
            updateCartQuantity(id, 1);
        });
        itemEl.querySelector(".cart-minus").addEventListener("click", function () {
            updateCartQuantity(id, -1);
        });
        itemEl.querySelector(".cart-remove").addEventListener("click", function () {
            setStore("zafiroCart", getStore("zafiroCart").filter(function (item) {
                return item.product_id !== id;
            }));
            renderCartPage();
        });
        itemEl.querySelector(".buy-now-btn").addEventListener("click", function () {
            var selected = getStore("zafiroCart").find(function (item) {
                return item.product_id === id;
            });
            if (!selected) return;
            localStorage.setItem("zafiroBuyNowItem", JSON.stringify(selected));
            markCheckoutScrollReset();
            window.location.href = "checkout.php?mode=buy_now";
        });
    });

    var checkoutBtn = summary.querySelector(".place-order-btn");
    if (checkoutBtn) {
        checkoutBtn.addEventListener("click", function () {
            markCheckoutScrollReset();
            window.location.href = "checkout.php?mode=cart";
        });
    }
}

function renderCheckoutPage() {
    var wrap = document.getElementById("checkoutProducts");
    var summary = document.getElementById("checkoutSummary");
    if (!wrap || !summary) return;

    var params = new URLSearchParams(window.location.search);
    var mode = params.get("mode") === "buy_now" ? "buy_now" : "cart";
    var modeField = document.getElementById("checkoutMode");
    if (modeField) modeField.value = mode;
    var items = mode === "buy_now" ? [JSON.parse(localStorage.getItem("zafiroBuyNowItem") || "null")].filter(Boolean) : getStore("zafiroCart");

    if (!items.length) {
        wrap.innerHTML = '<div class="empty-cart"><h2>No products selected for checkout.</h2><a href="cart.php" class="account-btn small">Back to Cart</a></div>';
        summary.innerHTML = "";
        return;
    }

    var subtotal = 0;
    wrap.innerHTML = items.map(function (item) {
        var price = parseFloat(String(item.price).replace(/,/g, "")) || 0;
        var quantity = item.quantity || 1;
        subtotal += price * quantity;
        var productUrl = item.product_url || ("product.php?id=" + item.product_id);
        return '<article class="checkout-item"><a href="' + escapeHtml(productUrl) + '"><img src="' + escapeHtml(item.image) + '" alt="' + escapeHtml(item.product_name) + '" loading="lazy" decoding="async"></a><div><a class="cart-product-title" href="' + escapeHtml(productUrl) + '">' + escapeHtml(item.product_name) + '</a><p>Quantity: ' + quantity + '</p><p class="cart-price">&#8377;' + escapeHtml(item.price) + '</p></div></article>';
    }).join("");

    var delivery = subtotal > 0 ? 499 : 0;
    summary.innerHTML = '<h2>Order Summary</h2><p><span>Mode</span><strong>' + (mode === "buy_now" ? "Buy Now" : "Cart") + '</strong></p><p><span>Subtotal</span><strong>&#8377;' + subtotal.toLocaleString("en-IN") + '</strong></p><p><span>Delivery Charges</span><strong>&#8377;' + delivery.toLocaleString("en-IN") + '</strong></p><hr><h3><span>Total</span><strong>&#8377;' + (subtotal + delivery).toLocaleString("en-IN") + '</strong></h3><button class="account-btn place-order-btn" type="button">Continue to Place Order</button>';

    var placeOrderBtn = summary.querySelector(".place-order-btn");
    if (placeOrderBtn) {
        placeOrderBtn.addEventListener("click", function (event) {
            event.preventDefault();
            markCheckoutScrollReset();
            var target = window.location.pathname.indexOf("order_summary.php") !== -1 ? "place-order.php" : "order_summary.php";
            window.location.href = target + "?mode=" + encodeURIComponent(mode);
        });
    }
}

function renderPlaceOrderPage() {
    var flow = document.getElementById("placeOrderFlow");
    if (!flow) return;

    var params = new URLSearchParams(window.location.search);
    var mode = params.get("mode") === "buy_now" ? "buy_now" : "cart";
    var items = mode === "buy_now" ? [JSON.parse(localStorage.getItem("zafiroBuyNowItem") || "null")].filter(Boolean) : getStore("zafiroCart");
    var addressForm = document.getElementById("placeAddressForm");
    var stepStateKey = "zafiroCheckoutMaxStep:" + mode;
    var paymentStateKey = "zafiroCheckoutPayment:" + mode;
    var selectedPayment = sessionStorage.getItem(paymentStateKey) || "UPI";
    var maxStep = Math.max(1, parseInt(sessionStorage.getItem(stepStateKey) || "1", 10) || 1);

    if (!items.length) {
        flow.innerHTML = '<div class="empty-cart"><h2>No products selected.</h2><a href="cart.php" class="account-btn small">Back to Cart</a></div>';
        return;
    }

    renderSavedAddresses(addressForm);
    renderPlaceOrderItems(items);
    renderPlacePayment(selectedPayment);
    setActivePaymentButton(selectedPayment);
    if (hasStoredCheckoutAddress(addressForm)) {
        unlockCheckoutStep(2);
    }
    initCheckoutStepHistory();
    updateCheckoutStepper();

    document.getElementById("addressContinueBtn").addEventListener("click", function (event) {
        event.preventDefault();
        if (!hasCheckoutAddress(addressForm)) return;
        saveCheckoutAddress(addressForm);
        unlockCheckoutStep(2);
        showPlaceStep(2, true);
        updateCheckoutStepper();
    });

    flow.querySelectorAll("[data-go-step]").forEach(function (button) {
        button.addEventListener("click", function (event) {
            event.preventDefault();
            var nextStep = parseInt(button.dataset.goStep, 10);
            if (nextStep === 3) {
                if (!hasCheckoutAddress(addressForm)) return;
                unlockCheckoutStep(3);
            }
            showPlaceStep(nextStep, true);
            updateCheckoutStepper();
        });
    });

    flow.querySelectorAll("[data-step-btn]").forEach(function (button) {
        button.addEventListener("click", function () {
            var step = parseInt(button.dataset.stepBtn, 10);
            if (!canOpenCheckoutStep(step)) {
                showToast(step === 2 ? "Please save delivery address first." : "Please review your order before payment.", "error");
                return;
            }
            showPlaceStep(step, true);
            updateCheckoutStepper();
        });
    });

    document.getElementById("paymentMethods").querySelectorAll("button").forEach(function (button) {
        button.addEventListener("click", function () {
            selectedPayment = button.dataset.payment;
            sessionStorage.setItem(paymentStateKey, selectedPayment);
            document.getElementById("paymentMethods").querySelectorAll("button").forEach(function (item) {
                item.classList.remove("active");
            });
            button.classList.add("active");
            renderPlacePayment(selectedPayment);
        });
    });

    function renderPlacePayment(method) {
        var details = document.getElementById("paymentDetails");
        if (method === "UPI") {
            details.innerHTML = '<h2><i class="fa-solid fa-mobile-screen-button"></i> UPI Payment</h2><p>You will be redirected to secure UPI payment.</p><button type="button" class="account-btn place-final-btn">Pay Now</button>';
        } else if (method === "Card") {
            details.innerHTML = '<h2><i class="fa-solid fa-credit-card"></i> Debit/Credit Card</h2><p>You will be redirected to secure card payment.</p><button type="button" class="account-btn place-final-btn">Pay Now</button>';
        } else if (method === "Cash on Delivery") {
            details.innerHTML = '<h2><i class="fa-solid fa-money-bill-wave"></i> Cash on Delivery</h2><p>Pay when your furniture is delivered.</p><button type="button" class="account-btn place-final-btn">Place Order</button>';
        } else {
            details.innerHTML = '<h2>' + escapeHtml(method) + '</h2><p>This payment option will be connected soon.</p><button type="button" class="account-btn place-final-btn">Place Order</button>';
        }

        details.querySelector(".place-final-btn").addEventListener("click", async function () {
            if (method === "UPI" || method === "Card") {
                await createPhonePePayment(items, mode, addressForm, method);
            } else {
                await createFinalOrder(items, mode, method, addressForm);
            }
        });
    }

    function unlockCheckoutStep(step) {
        maxStep = Math.max(maxStep, step);
        sessionStorage.setItem(stepStateKey, String(maxStep));
        updateCheckoutStepper();
    }

    function canOpenCheckoutStep(step) {
        if (step === 1) return true;
        if (!hasStoredCheckoutAddress(addressForm)) return false;
        if (step === 2 && hasStoredCheckoutAddress(addressForm)) {
            unlockCheckoutStep(2);
            return true;
        }
        if (step === 3 && hasStoredCheckoutAddress(addressForm) && maxStep >= 3) return true;
        return step <= maxStep;
    }

    function updateCheckoutStepper() {
        var activeStep = parseInt((flow.querySelector(".place-step.active") || {}).dataset && flow.querySelector(".place-step.active").dataset.step || "1", 10);
        flow.querySelectorAll("[data-step-btn]").forEach(function (button) {
            var step = parseInt(button.dataset.stepBtn, 10);
            var unlocked = step === 1 || step <= maxStep || (step === 2 && hasStoredCheckoutAddress(addressForm));
            button.classList.toggle("active", step === activeStep);
            button.classList.toggle("completed", step < activeStep && unlocked);
            button.classList.toggle("locked", !unlocked);
            button.disabled = !unlocked;
            button.setAttribute("aria-disabled", unlocked ? "false" : "true");
        });
    }

    function initCheckoutStepHistory() {
        var initialStep = getVisibleCheckoutStep();
        history.replaceState({ checkoutStep: initialStep }, "", window.location.href);
        window.addEventListener("popstate", function (event) {
            var step = event.state && event.state.checkoutStep ? parseInt(event.state.checkoutStep, 10) : 1;
            if (!canOpenCheckoutStep(step)) step = 1;
            showPlaceStep(step, false);
            updateCheckoutStepper();
        });
    }

    function setActivePaymentButton(method) {
        document.getElementById("paymentMethods").querySelectorAll("button").forEach(function (button) {
            button.classList.toggle("active", button.dataset.payment === method);
        });
    }
}

async function createPhonePePayment(items, mode, form, paymentMethod) {
    if (!saveCheckoutAddress(form)) return;
    try {
        var response = await fetch("payment_create.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({
                mode: mode,
                payment_method: paymentMethod,
                items: items,
                address: Object.fromEntries(new FormData(form).entries())
            })
        });
        var result = await response.json();
        if (result.success && result.redirect_url) {
            markCheckoutScrollReset();
            window.location.href = result.redirect_url;
            return;
        }
        showPaymentMessage(result.message || "Payment service is currently unavailable. Please try Cash on Delivery or try again later.");
    } catch (error) {
        showPaymentMessage("Payment service is currently unavailable. Please try Cash on Delivery or try again later.");
    }
}

function showPaymentMessage(message) {
    var details = document.getElementById("paymentDetails");
    if (!details) {
        showToast(message, "error");
        return;
    }
    var box = details.querySelector(".payment-error-message") || document.createElement("div");
    box.className = "payment-error-message";
    box.textContent = message;
    details.prepend(box);
}

function showPlaceStep(step, pushHistory) {
    document.querySelectorAll(".place-step").forEach(function (panel) {
        panel.classList.toggle("active", panel.dataset.step === String(step));
    });
    document.querySelectorAll("[data-step-btn]").forEach(function (button) {
        button.classList.toggle("active", button.dataset.stepBtn === String(step));
    });
    if (pushHistory && history.state && history.state.checkoutStep !== step) {
        history.pushState({ checkoutStep: step }, "", window.location.href);
    }
    resetCheckoutScroll(true);
}

function initCheckoutScrollReset() {
    if ("scrollRestoration" in history) {
        history.scrollRestoration = "manual";
    }

    var checkoutPage = /(?:checkout|order_summary|place-order|order-success|payment_success|payment_failed)\.php$/i.test(window.location.pathname) ||
        document.querySelector(".checkout-page, .place-order-page, .order-success-page");

    if (!checkoutPage) return;

    resetCheckoutScroll(false);
    window.addEventListener("pageshow", function () {
        resetCheckoutScroll(false);
    });
}

function markCheckoutScrollReset() {
    try {
        sessionStorage.setItem("zafiroCheckoutScrollTop", "1");
    } catch (error) {}
}

function resetCheckoutScroll(smooth) {
    var behavior = smooth ? "smooth" : "auto";
    window.requestAnimationFrame(function () {
        window.scrollTo({ top: 0, left: 0, behavior: behavior });
        if (!smooth) {
            document.documentElement.scrollTop = 0;
            document.body.scrollTop = 0;
        }
    });

    if (!smooth) {
        setTimeout(function () {
            window.scrollTo({ top: 0, left: 0, behavior: "auto" });
            document.documentElement.scrollTop = 0;
            document.body.scrollTop = 0;
        }, 120);
    }
}

function renderSavedAddresses(form) {
    var list = document.getElementById("savedAddressList");
    if (!list) return;
    var dbCards = list.querySelectorAll(".db-saved-address");

    if (dbCards.length) {
        dbCards.forEach(function (card) {
            var radio = card.querySelector('input[name="saved_address"]');
            radio.addEventListener("change", function () {
                fillAddressForm(form, getAddressFromCard(card));
            });
        });
        fillAddressForm(form, getAddressFromCard(dbCards[0]));
        localStorage.setItem("zafiroSelectedAddress", JSON.stringify(getAddressFromCard(dbCards[0])));
        return;
    }

    list.innerHTML = '<div class="empty-cart"><h2>No saved address found.</h2><a class="account-btn small" href="address.php">Add Address</a></div>';
}

function getAddressFromCard(card) {
    return {
        full_name: card.dataset.fullName || "",
        mobile: card.dataset.mobile || "",
        alternate_phone: "",
        pincode: card.dataset.pincode || "",
        city: card.dataset.city || "",
        state: card.dataset.state || "",
        full_address: card.dataset.fullAddress || "",
        landmark: card.dataset.landmark || "",
        address_type: card.dataset.addressType || "Home"
    };
}

function fillAddressForm(form, address) {
    Object.keys(address).forEach(function (key) {
        if (form.elements[key]) form.elements[key].value = address[key];
    });
}

function saveCheckoutAddress(form) {
    if (!hasCheckoutAddress(form)) return false;
    var address = Object.fromEntries(new FormData(form).entries());
    localStorage.setItem("zafiroSelectedAddress", JSON.stringify(address));
    return true;
}

function hasCheckoutAddress(form) {
    var required = form.querySelectorAll("[required]");
    for (var i = 0; i < required.length; i++) {
        if (!required[i].value.trim()) {
            required[i].focus();
            showToast("Please select or add a saved address.", "error");
            return false;
        }
    }
    return true;
}

function hasStoredCheckoutAddress(form) {
    if (!form) return false;
    var required = form.querySelectorAll("[required]");
    for (var i = 0; i < required.length; i++) {
        if (!required[i].value.trim()) return false;
    }
    return true;
}

function getVisibleCheckoutStep() {
    var active = document.querySelector(".place-step.active");
    return active && active.dataset.step ? parseInt(active.dataset.step, 10) : 1;
}

function renderPlaceOrderItems(items) {
    var list = document.getElementById("placeOrderItems");
    var summary = document.getElementById("placeOrderSummary");
    var paymentSummary = document.getElementById("paymentAmountSummary");
    var totals = calculateOrderTotals(items);

    list.innerHTML = items.map(function (item) {
        var quantity = item.quantity || 1;
        var price = parseFloat(String(item.price).replace(/,/g, "")) || 0;
        return '<article class="place-product"><img src="' + escapeHtml(item.image) + '" alt="' + escapeHtml(item.product_name) + '" loading="lazy" decoding="async"><div><h3>' + escapeHtml(item.product_name) + '</h3><p>Quantity: ' + quantity + '</p><p>Delivery by 5-7 business days</p></div><strong>&#8377;' + (price * quantity).toLocaleString("en-IN") + '</strong></article>';
    }).join("");

    summary.innerHTML = '<h2>Price Details</h2><p><span>Total Items</span><strong>' + totals.count + '</strong></p><p><span>Subtotal</span><strong>&#8377;' + totals.subtotal.toLocaleString("en-IN") + '</strong></p><p><span>Delivery Charges</span><strong>&#8377;' + totals.delivery.toLocaleString("en-IN") + '</strong></p><p><span>Discount</span><strong>- &#8377;' + totals.discount.toLocaleString("en-IN") + '</strong></p><hr><h3><span>Final Total</span><strong>&#8377;' + totals.finalTotal.toLocaleString("en-IN") + '</strong></h3>';
    paymentSummary.innerHTML = '<h2>Amount Summary</h2><p><span>MRP Total</span><strong>&#8377;' + totals.subtotal.toLocaleString("en-IN") + '</strong></p><p><span>Delivery Charges</span><strong>&#8377;' + totals.delivery.toLocaleString("en-IN") + '</strong></p><p><span>Discounts</span><strong>- &#8377;' + totals.discount.toLocaleString("en-IN") + '</strong></p><hr><h3><span>Final Amount</span><strong>&#8377;' + totals.finalTotal.toLocaleString("en-IN") + '</strong></h3>';
}

function calculateOrderTotals(items) {
    var subtotal = 0;
    var count = 0;
    items.forEach(function (item) {
        var quantity = item.quantity || 1;
        count += quantity;
        subtotal += (parseFloat(String(item.price).replace(/,/g, "")) || 0) * quantity;
    });
    var delivery = subtotal > 0 ? 499 : 0;
    var discount = Math.round(subtotal * 0.08);
    return { count: count, subtotal: subtotal, delivery: delivery, discount: discount, finalTotal: subtotal + delivery - discount };
}

async function createFinalOrder(items, mode, paymentMethod, form) {
    if (!saveCheckoutAddress(form)) return;
    var totals = calculateOrderTotals(items);
    var orderDate = new Date();
    var shippingDate = new Date(orderDate);
    var deliveryDate = new Date(orderDate);
    shippingDate.setDate(shippingDate.getDate() + 2);
    deliveryDate.setDate(deliveryDate.getDate() + 7);

    var order = {
        order_id: generateOrderId(),
        mode: mode,
        items: items,
        total: totals.finalTotal,
        payment_method: paymentMethod,
        payment_status: paymentMethod === "Cash on Delivery" ? "Pending" : "Payment Submitted",
        order_status: "Placed",
        order_date: orderDate.toISOString(),
        shipping_date: shippingDate.toISOString(),
        delivery_date: deliveryDate.toISOString(),
        address: Object.fromEntries(new FormData(form).entries())
    };

    var orders = getStore("zafiroOrders");
    orders.unshift(order);
    setStore("zafiroOrders", orders);

    try {
        await fetch("save-order.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify(order)
        });
    } catch (error) {
        showToast("Order saved locally. Database sync failed.", "info");
    }

    if (mode === "cart") {
        setStore("zafiroCart", []);
    } else {
        localStorage.removeItem("zafiroBuyNowItem");
    }

    markCheckoutScrollReset();
    window.location.href = "order-success.php?order_id=" + encodeURIComponent(order.order_id);
}

function submitCheckoutOrder(form, items, mode, total) {
    if (!form) return;

    var requiredFields = form.querySelectorAll("[required]");
    for (var i = 0; i < requiredFields.length; i++) {
        if (!requiredFields[i].value.trim()) {
            requiredFields[i].focus();
            showToast("Please complete delivery address and payment details.", "error");
            return;
        }
    }

    var payment = form.querySelector('input[name="payment_method"]:checked');
    if (!payment) {
        showToast("Please select a payment method.", "error");
        return;
    }

    var orderId = generateOrderId();
    var orderDate = new Date();
    var shippingDate = new Date(orderDate);
    var deliveryDate = new Date(orderDate);
    shippingDate.setDate(shippingDate.getDate() + 2);
    deliveryDate.setDate(deliveryDate.getDate() + 7);

    var order = {
        order_id: orderId,
        mode: mode,
        items: items,
        total: total,
        payment_method: payment.value,
        payment_status: "Pending",
        order_status: "Placed",
        order_date: orderDate.toISOString(),
        shipping_date: shippingDate.toISOString(),
        delivery_date: deliveryDate.toISOString(),
        address: Object.fromEntries(new FormData(form).entries())
    };

    var orders = getStore("zafiroOrders");
    orders.unshift(order);
    setStore("zafiroOrders", orders);

    if (mode === "cart") {
        setStore("zafiroCart", []);
    } else {
        localStorage.removeItem("zafiroBuyNowItem");
    }

    var payload = document.getElementById("orderPayload");
    if (payload) payload.value = JSON.stringify(order);
    form.submit();
}

function generateOrderId() {
    var next = parseInt(localStorage.getItem("zafiroOrderCounter") || "1000", 10) + 1;
    localStorage.setItem("zafiroOrderCounter", String(next));
    return "ZC" + next;
}

function renderLocalOrderTracking() {
    var holder = document.getElementById("localOrderTracking");
    if (!holder) return;

    var orderId = holder.dataset.orderId || new URLSearchParams(window.location.search).get("order_id") || "";
    if (!orderId) return;

    var order = getStore("zafiroOrders").find(function (item) {
        return item.order_id === orderId;
    });

    if (!order) {
        holder.innerHTML = "<p>Order not found.</p>";
        return;
    }

    holder.innerHTML = '<h2>Order ' + escapeHtml(order.order_id) + '</h2><div class="tracking-timeline"><p><strong>Order Date:</strong> ' + formatOrderDate(order.order_date) + '</p><p><strong>Shipping Date:</strong> ' + formatOrderDate(order.shipping_date) + '</p><p><strong>Delivery Date:</strong> ' + formatOrderDate(order.delivery_date) + '</p><p><strong>Status:</strong> ' + escapeHtml(order.order_status) + '</p></div>';
}

function renderMyOrdersPage() {
    var list = document.getElementById("localOrdersList");
    if (!list) return;

    var orders = getStore("zafiroOrders");
    var empty = document.getElementById("noOrdersMessage");
    if (!orders.length) return;

    if (empty) empty.remove();
    list.innerHTML = orders.map(function (order) {
        var firstItem = order.items && order.items.length ? order.items[0] : {};
        var extraCount = order.items && order.items.length > 1 ? '<p>+' + (order.items.length - 1) + ' more item(s)</p>' : '';
        var productUrl = firstItem.product_url || ("product.php?id=" + firstItem.product_id);
        return '<article class="order-card"><a href="' + escapeHtml(productUrl) + '"><img src="' + escapeHtml(firstItem.image || '') + '" alt="' + escapeHtml(firstItem.product_name || 'Product') + '" loading="lazy" decoding="async"></a><div><h3>' + escapeHtml(firstItem.product_name || 'Order Items') + '</h3>' + extraCount + '<p>Order ID: ' + escapeHtml(order.order_id) + '</p><p>Quantity: ' + getOrderQuantity(order) + '</p><p>Price: &#8377;' + Number(order.total || 0).toLocaleString("en-IN") + '</p><p>Order Date: ' + formatOrderDate(order.order_date) + '</p><p>Payment Method: ' + escapeHtml(order.payment_method || 'Pending') + '</p><p>Order Status: ' + escapeHtml(order.order_status || 'Placed') + '</p></div><div class="order-actions"><a class="account-btn small" href="order-tracking.php?order_id=' + encodeURIComponent(order.order_id) + '">Track Order</a><a class="account-btn small outline" href="order-review.php?order_id=' + encodeURIComponent(order.order_id) + '">Review Order</a><a class="account-btn small danger-btn" href="return-order.php?order_id=' + encodeURIComponent(order.order_id) + '">Return Order</a></div></article>';
    }).join("");
}

function getOrderQuantity(order) {
    if (!order.items) return 0;
    return order.items.reduce(function (sum, item) {
        return sum + (item.quantity || 1);
    }, 0);
}

function formatOrderDate(value) {
    if (!value) return "Pending";
    return new Date(value).toLocaleDateString("en-IN", { day: "2-digit", month: "short", year: "numeric" });
}

function updateHeaderCounts() {
    var wishlistCount = document.getElementById("wishlistCount");
    var cartCount = document.getElementById("cartCount");

    if (wishlistCount) wishlistCount.textContent = getStore("zafiroWishlist").length;
    if (cartCount) {
        cartCount.textContent = getStore("zafiroCart").reduce(function (sum, item) {
            return sum + item.quantity;
        }, 0);
    }
}
