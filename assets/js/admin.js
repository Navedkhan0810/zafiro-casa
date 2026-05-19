document.addEventListener("DOMContentLoaded", function () {
    var toggle = document.getElementById("toggleAdminPassword");
    var password = document.getElementById("adminPassword");
    initAdminOtpCountdown();

    if (toggle && password) {
        toggle.addEventListener("click", function () {
            var isPassword = password.type === "password";
            password.type = isPassword ? "text" : "password";
            toggle.textContent = isPassword ? "Hide" : "Show";
        });
    }

    initStoreActivityChart();

    function initStoreActivityChart(attempts) {
        attempts = attempts || 0;
        var weeklyCanvas = document.getElementById("weeklyStoreChart");
        if (!weeklyCanvas) return;
        if (!window.Chart) {
            if (attempts < 10) setTimeout(function () { initStoreActivityChart(attempts + 1); }, 150);
            return;
        }

        var activity = {};
        try {
            activity = JSON.parse(weeklyCanvas.dataset.activity || "{}");
        } catch (error) {
            activity = {};
        }
        var views = ["daily", "weekly", "monthly", "yearly"];
        var activeView = "daily";
        var title = document.getElementById("storeActivityTitle");

        var storeChart = new Chart(weeklyCanvas, {
            type: "line",
            data: {
                labels: activity.daily ? activity.daily.labels : [],
                datasets: [{
                    label: "Orders",
                    data: activity.daily ? activity.daily.orders : [],
                    borderColor: "#C8A96B",
                    backgroundColor: "rgba(200, 169, 107, 0.18)",
                    fill: true,
                    tension: 0.42,
                    borderWidth: 3,
                    pointRadius: 4,
                    pointBackgroundColor: "#C8A96B"
                }, {
                    label: "Sales",
                    data: activity.daily ? activity.daily.sales : [],
                    borderColor: "#111827",
                    backgroundColor: "rgba(17, 24, 39, 0.08)",
                    fill: false,
                    tension: 0.42,
                    borderWidth: 2,
                    pointRadius: 3,
                    pointBackgroundColor: "#111827"
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: true }
                },
                scales: {
                    x: {
                        ticks: { color: "#6B7280" },
                        grid: { color: "rgba(17, 24, 39, 0.08)" }
                    },
                    y: {
                        beginAtZero: true,
                        ticks: { color: "#6B7280", precision: 0 },
                        grid: { color: "rgba(17, 24, 39, 0.08)" }
                    }
                }
            }
        });

        function setChartView(view) {
            if (!activity[view]) return;
            activeView = view;
            storeChart.data.labels = activity[view].labels || [];
            storeChart.data.datasets[0].data = activity[view].orders || [];
            storeChart.data.datasets[1].data = activity[view].sales || [];
            if (title) title.textContent = activity[view].title || "Store Activity";
            storeChart.update();
        }

        document.querySelectorAll("[data-chart-view-prev], [data-chart-view-next]").forEach(function (button) {
            button.addEventListener("click", function () {
                var index = views.indexOf(activeView);
                var next = button.hasAttribute("data-chart-view-next") ? index + 1 : index - 1;
                setChartView(views[(next + views.length) % views.length]);
            });
        });
    }

    initAdminProductPreview();
    initManageProducts();
    initAdminProductSearch();
    initAdminCategoryManager();
    initAdminOrderManager();
    initAdminUserManager();
    initAdminReviewManager();
    initAdminSettingsPage();
    initAdminNotifications();
    initAdminNotificationPageClicks();
    initManageNotificationsPage();
    initReportsFilters();
    initReportsCharts();
    initReportsHeatmapTips();
    initAdminSidebarLayout();
    applyAdminProfileImageAdjustments();
});

function initAdminSidebarLayout() {
    var toggle = document.querySelector(".admin-sidebar-toggle");
    var closeLayer = document.querySelector("[data-admin-sidebar-close]");
    var links = document.querySelectorAll(".admin-sidebar .admin-menu a");

    function setOpen(open) {
        document.body.classList.toggle("admin-sidebar-open", open);
        if (toggle) toggle.setAttribute("aria-expanded", open ? "true" : "false");
    }

    if (toggle) {
        toggle.addEventListener("click", function () {
            setOpen(!document.body.classList.contains("admin-sidebar-open"));
        });
    }

    if (closeLayer) closeLayer.addEventListener("click", function () { setOpen(false); });
    links.forEach(function (link) {
        link.addEventListener("click", function () {
            if (window.innerWidth <= 1024) setOpen(false);
        });
    });
}

function zafiroConfirmSubmit(event, form, message, confirmText) {
    event.preventDefault();
    showConfirm(message, function () {
        HTMLFormElement.prototype.submit.call(form);
    }, { confirmText: confirmText || "Confirm" });
}

function zafiroError(message) {
    showError(message || "Something went wrong.");
}

function initAdminOtpCountdown() {
    var box = document.querySelector("[data-admin-otp-expiry]");
    if (!box) return;

    var timer = document.getElementById("adminOtpTimer");
    var verifyBtn = document.getElementById("adminVerifyOtpBtn");
    var resendBtn = document.getElementById("adminResendOtpBtn");
    var expiry = new Date(box.dataset.adminOtpExpiry.replace(" ", "T")).getTime();

    function tick() {
        var left = Math.max(0, Math.floor((expiry - Date.now()) / 1000));
        if (timer) {
            timer.textContent = String(Math.floor(left / 60)).padStart(2, "0") + ":" + String(left % 60).padStart(2, "0");
        }

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

function applyAdminProfileImageAdjustments() {
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

function initAdminProductPreview() {
    var nameInput = document.getElementById("productNameInput");
    var slugInput = document.getElementById("productSlugInput");
    var categoryInput = document.getElementById("productCategoryInput");
    var originalInput = document.getElementById("productOriginalPriceInput");
    var discountInput = document.getElementById("productDiscountPriceInput");
    var shortInput = document.getElementById("productShortInput");
    var imageInput = document.getElementById("mainProductImageInput");
    var galleryInputs = document.querySelectorAll(".galleryProductImageInput");
    var previewImage = document.getElementById("previewProductImage");
    var previewName = document.getElementById("previewProductName");
    var previewCategory = document.getElementById("previewCategory");
    var previewDescription = document.getElementById("previewDescription");
    var previewOriginal = document.getElementById("previewOriginalPrice");
    var previewDiscount = document.getElementById("previewDiscountPrice");
    var previewList = document.getElementById("adminImagePreviewList");

    if (!nameInput) return;

    function money(value) {
        return "₹" + (value || "0");
    }

    function updatePreview() {
        previewName.textContent = nameInput.value || "Product Name";
        previewCategory.textContent = categoryInput.value || "Category";
        previewDescription.textContent = shortInput.value || "Short description will appear here.";
        previewOriginal.textContent = money(originalInput.value);
        previewDiscount.textContent = money(discountInput.value || originalInput.value);
        if (!slugInput.value && nameInput.value) {
            slugInput.value = nameInput.value.toLowerCase().trim().replace(/[^a-z0-9]+/g, "-").replace(/^-|-$/g, "");
        }
    }

    updatePreview();

    [nameInput, categoryInput, originalInput, discountInput, shortInput].forEach(function (input) {
        input.addEventListener("input", updatePreview);
        input.addEventListener("change", updatePreview);
    });

    function addPreview(file, isMain, sourceInput) {
        if (!file || !file.type.match("image.*")) return;
        var reader = new FileReader();
        reader.onload = function (event) {
            if (isMain) previewImage.src = event.target.result;
            var item = document.createElement("div");
            item.className = "admin-image-preview";
            item.innerHTML = '<img src="' + event.target.result + '" alt="Preview"><button type="button">×</button>';
            item.querySelector("button").addEventListener("click", function () {
                item.remove();
                if (sourceInput) sourceInput.value = "";
            });
            previewList.appendChild(item);
        };
        reader.readAsDataURL(file);
    }

    if (imageInput) {
        imageInput.addEventListener("change", function () {
            addPreview(imageInput.files[0], true, imageInput);
        });
    }

    galleryInputs.forEach(function (galleryInput) {
        galleryInput.addEventListener("change", function () {
            addPreview(galleryInput.files[0], false, galleryInput);
        });
    });

    document.querySelectorAll(".admin-upload-box").forEach(function (box) {
        box.addEventListener("dragover", function (event) {
            event.preventDefault();
            box.classList.add("drag-over");
        });
        box.addEventListener("dragleave", function () {
            box.classList.remove("drag-over");
        });
        box.addEventListener("drop", function () {
            box.classList.remove("drag-over");
        });
    });
}

function initManageProducts() {
    document.querySelectorAll(".delete-product-form").forEach(function (form) {
        form.addEventListener("submit", function (event) {
            zafiroConfirmSubmit(event, form, "Delete this product permanently?", "Delete");
        });
    });
}

function initAdminProductSearch() {
    var openButton = document.getElementById("adminProductSearchToggle");
    var popup = document.getElementById("adminProductSearchPopup");
    var closeButton = document.getElementById("adminProductSearchClose");

    if (!openButton || !popup || !closeButton) return;

    openButton.addEventListener("click", function () {
        popup.classList.add("open");
        var input = popup.querySelector('input[type="search"]');
        if (input) input.focus();
    });

    closeButton.addEventListener("click", function () {
        popup.classList.remove("open");
    });

    popup.addEventListener("click", function (event) {
        if (event.target === popup) popup.classList.remove("open");
    });
}

function initAdminCategoryManager() {
    var modal = document.getElementById("categoryModal");
    var openButtons = [
        document.getElementById("openCategoryModal"),
        document.getElementById("openCategoryModalEmpty")
    ].filter(Boolean);
    var closeButtons = [
        document.getElementById("closeCategoryModal"),
        document.getElementById("cancelCategoryModal")
    ].filter(Boolean);
    var modalTitle = document.getElementById("categoryModalTitle");
    var idInput = document.getElementById("categoryIdInput");
    var oldNameInput = document.getElementById("oldCategoryNameInput");
    var nameInput = document.getElementById("categoryNameInput");
    var slugInput = document.getElementById("categorySlugInput");
    var parentInput = document.getElementById("categoryParentInput");
    var descriptionInput = document.getElementById("categoryDescriptionInput");
    var statusInput = document.getElementById("categoryStatusInput");
    var featuredInput = document.getElementById("categoryFeaturedInput");
    var searchOpen = document.getElementById("adminCategorySearchToggle");
    var searchPopup = document.getElementById("adminCategorySearchPopup");
    var searchClose = document.getElementById("adminCategorySearchClose");

    function createSlug(value) {
        return value.toLowerCase().trim().replace(/[^a-z0-9]+/g, "-").replace(/^-|-$/g, "");
    }

    function resetModal() {
        if (!modalTitle) return;
        modalTitle.textContent = parentInput ? "Add Subcategory" : "Add Category";
        idInput.value = "";
        if (oldNameInput) oldNameInput.value = "";
        nameInput.value = "";
        slugInput.value = "";
        slugInput.dataset.edited = "";
        if (parentInput) parentInput.value = "";
        descriptionInput.value = "";
        statusInput.value = "active";
        if (featuredInput) featuredInput.checked = false;
        var imagePreview = document.getElementById("categoryCurrentImagePreview");
        if (imagePreview) {
            imagePreview.src = "";
            imagePreview.classList.add("is-hidden");
        }
    }

    function openModal() {
        if (modal) modal.classList.add("open");
        if (nameInput) nameInput.focus();
    }

    function closeModal() {
        if (modal) modal.classList.remove("open");
    }

    openButtons.forEach(function (button) {
        button.addEventListener("click", function () {
            resetModal();
            openModal();
        });
    });

    closeButtons.forEach(function (button) {
        button.addEventListener("click", closeModal);
    });

    if (modal) {
        modal.addEventListener("click", function (event) {
            if (event.target === modal) closeModal();
        });
    }

    if (nameInput && slugInput) {
        nameInput.addEventListener("input", function () {
            if (!slugInput.dataset.edited) {
                slugInput.value = createSlug(nameInput.value);
            }
        });
        slugInput.addEventListener("input", function () {
            slugInput.dataset.edited = "1";
        });
    }

    document.querySelectorAll(".edit-category-btn").forEach(function (button) {
        button.addEventListener("click", function () {
            modalTitle.textContent = parentInput ? "Edit Subcategory" : "Edit Category";
            idInput.value = button.dataset.id || "";
            nameInput.value = button.dataset.name || "";
            if (oldNameInput) oldNameInput.value = button.dataset.name || "";
            slugInput.value = button.dataset.slug || "";
            slugInput.dataset.edited = "1";
            if (parentInput) parentInput.value = button.dataset.parent || "";
            descriptionInput.value = button.dataset.description || "";
            statusInput.value = button.dataset.status || "active";
            if (featuredInput) featuredInput.checked = button.dataset.featured === "1";
            var imagePreview = document.getElementById("categoryCurrentImagePreview");
            if (imagePreview) {
                imagePreview.src = button.dataset.image || "";
                imagePreview.classList.toggle("is-hidden", !button.dataset.image);
            }
            openModal();
        });
    });

    document.querySelectorAll(".delete-category-form").forEach(function (form) {
        form.addEventListener("submit", function (event) {
            var productCount = parseInt(form.dataset.products || "0", 10);
            if (productCount > 0) {
                event.preventDefault();
                zafiroError("This category contains products.");
                return;
            }
            zafiroConfirmSubmit(event, form, "Delete this category permanently?", "Delete");
        });
    });

    if (searchOpen && searchPopup && searchClose) {
        searchOpen.addEventListener("click", function () {
            searchPopup.classList.add("open");
            var input = searchPopup.querySelector('input[type="search"]');
            if (input) input.focus();
        });
        searchClose.addEventListener("click", function () {
            searchPopup.classList.remove("open");
        });
        searchPopup.addEventListener("click", function (event) {
            if (event.target === searchPopup) searchPopup.classList.remove("open");
        });
    }
}

function initAdminOrderManager() {
    var searchOpen = document.getElementById("adminOrderSearchToggle");
    var searchPopup = document.getElementById("adminOrderSearchPopup");
    var searchClose = document.getElementById("adminOrderSearchClose");
    var detailsModal = document.getElementById("orderDetailsModal");
    var detailsContent = document.getElementById("orderDetailsContent");
    var detailsClose = document.getElementById("closeOrderDetailsModal");

    if (searchOpen && searchPopup && searchClose) {
        searchOpen.addEventListener("click", function () {
            searchPopup.classList.add("open");
            var input = searchPopup.querySelector('input[type="search"]');
            if (input) input.focus();
        });
        searchClose.addEventListener("click", function () {
            searchPopup.classList.remove("open");
        });
        searchPopup.addEventListener("click", function (event) {
            if (event.target === searchPopup) searchPopup.classList.remove("open");
        });
    }

    document.querySelectorAll(".delete-order-form").forEach(function (form) {
        form.addEventListener("submit", function (event) {
            zafiroConfirmSubmit(event, form, "Delete this order permanently?", "Delete");
        });
    });

    document.querySelectorAll(".view-order-btn").forEach(function (button) {
        button.addEventListener("click", function () {
            var data = document.getElementById("orderData-" + button.dataset.order);
            if (!data || !detailsModal || !detailsContent) return;
            detailsContent.innerHTML = data.innerHTML;
            detailsModal.classList.add("open");
        });
    });

    var focusOrder = new URLSearchParams(window.location.search).get("focus_order");
    if (focusOrder) {
        document.querySelectorAll(".view-order-btn").forEach(function (button) {
            if (button.dataset.order === focusOrder) {
                button.scrollIntoView({ behavior: "smooth", block: "center" });
                setTimeout(function () { button.click(); }, 250);
            }
        });
    }

    if (detailsClose && detailsModal) {
        detailsClose.addEventListener("click", function () {
            detailsModal.classList.remove("open");
        });
        detailsModal.addEventListener("click", function (event) {
            if (event.target === detailsModal) detailsModal.classList.remove("open");
        });
    }
}

function initAdminUserManager() {
    var searchOpen = document.getElementById("adminUserSearchToggle");
    var searchPopup = document.getElementById("adminUserSearchPopup");
    var searchClose = document.getElementById("adminUserSearchClose");
    var profileModal = document.getElementById("userProfileModal");
    var profileContent = document.getElementById("userProfileContent");
    var profileClose = document.getElementById("closeUserProfileModal");
    var editModal = document.getElementById("editUserModal");
    var editClose = document.getElementById("closeEditUserModal");
    var editCancel = document.getElementById("cancelEditUserModal");

    if (searchOpen && searchPopup && searchClose) {
        searchOpen.addEventListener("click", function () {
            searchPopup.classList.add("open");
            var input = searchPopup.querySelector('input[type="search"]');
            if (input) input.focus();
        });
        searchClose.addEventListener("click", function () {
            searchPopup.classList.remove("open");
        });
        searchPopup.addEventListener("click", function (event) {
            if (event.target === searchPopup) searchPopup.classList.remove("open");
        });
    }

    document.querySelectorAll(".view-user-btn").forEach(function (button) {
        button.addEventListener("click", function () {
            var data = document.getElementById("userData-" + button.dataset.user);
            if (!data || !profileModal || !profileContent) return;
            profileContent.innerHTML = data.innerHTML;
            profileModal.classList.add("open");
        });
    });

    var focusUser = new URLSearchParams(window.location.search).get("focus_user");
    if (focusUser) {
        document.querySelectorAll(".view-user-btn").forEach(function (button) {
            if (button.dataset.user === focusUser) {
                button.scrollIntoView({ behavior: "smooth", block: "center" });
                setTimeout(function () { button.click(); }, 250);
            }
        });
    }

    if (profileClose && profileModal) {
        profileClose.addEventListener("click", function () {
            profileModal.classList.remove("open");
        });
        profileModal.addEventListener("click", function (event) {
            if (event.target === profileModal) profileModal.classList.remove("open");
        });
    }

    document.querySelectorAll(".edit-user-btn").forEach(function (button) {
        button.addEventListener("click", function () {
            document.getElementById("editUserId").value = button.dataset.id || "";
            document.getElementById("editUserFullName").value = button.dataset.name || "";
            document.getElementById("editUserUsername").value = button.dataset.username || "";
            document.getElementById("editUserEmail").value = button.dataset.email || "";
            document.getElementById("editUserPhone").value = button.dataset.phone || "";
            document.getElementById("editUserGender").value = button.dataset.gender || "";
            if (editModal) editModal.classList.add("open");
        });
    });

    [editClose, editCancel].filter(Boolean).forEach(function (button) {
        button.addEventListener("click", function () {
            if (editModal) editModal.classList.remove("open");
        });
    });

    if (editModal) {
        editModal.addEventListener("click", function (event) {
            if (event.target === editModal) editModal.classList.remove("open");
        });
    }

    document.querySelectorAll(".delete-user-form").forEach(function (form) {
        form.addEventListener("submit", function (event) {
            zafiroConfirmSubmit(event, form, "Delete this user safely? This will mark the account as deleted.", "Delete");
        });
    });

    document.querySelectorAll(".restore-user-form").forEach(function (form) {
        form.addEventListener("submit", function (event) {
            zafiroConfirmSubmit(event, form, "Are you sure you want to restore this user?", "Restore User");
        });
    });
}

function initAdminReviewManager() {
    var modal = document.getElementById("reviewDetailsModal");
    var content = document.getElementById("reviewDetailsContent");
    var close = document.getElementById("closeReviewDetailsModal");

    document.querySelectorAll(".view-review-btn").forEach(function (button) {
        button.addEventListener("click", function () {
            var data = document.getElementById("reviewData-" + button.dataset.review);
            if (!data || !modal || !content) return;
            content.innerHTML = data.innerHTML;
            modal.classList.add("open");
        });
    });

    var focusReview = new URLSearchParams(window.location.search).get("focus_review");
    if (focusReview) {
        document.querySelectorAll(".view-review-btn").forEach(function (button) {
            if (button.dataset.review === focusReview) {
                button.scrollIntoView({ behavior: "smooth", block: "center" });
                setTimeout(function () { button.click(); }, 250);
            }
        });
    }

    if (close && modal) {
        close.addEventListener("click", function () {
            modal.classList.remove("open");
        });
        modal.addEventListener("click", function (event) {
            if (event.target === modal) modal.classList.remove("open");
        });
    }

    document.querySelectorAll(".delete-review-form").forEach(function (form) {
        form.addEventListener("submit", function (event) {
            zafiroConfirmSubmit(event, form, "Remove this review permanently?", "Delete");
        });
    });

    document.querySelectorAll(".permanent-delete-notification-form").forEach(function (form) {
        form.addEventListener("submit", function (event) {
            zafiroConfirmSubmit(event, form, "Permanently delete this notification? This cannot be undone.", "Delete");
        });
    });
}

function initAdminSettingsPage() {
    var imageInput = document.getElementById("adminProfileImageInput");
    var imagePreview = document.getElementById("adminProfilePreview");
    if (imageInput && imagePreview) {
        imageInput.addEventListener("change", function () {
            var file = imageInput.files && imageInput.files[0];
            if (!file) return;
            imagePreview.src = URL.createObjectURL(file);
            imagePreview.classList.remove("is-hidden");
        });
    }

    var modal = document.getElementById("passwordSettingsModal");
    var openButtons = [
        document.getElementById("openPasswordModal"),
        document.getElementById("openPasswordModalSecondary")
    ].filter(Boolean);
    var closeButtons = [
        document.getElementById("closePasswordModal"),
        document.getElementById("cancelPasswordModal")
    ].filter(Boolean);

    openButtons.forEach(function (button) {
        button.addEventListener("click", function () {
            if (modal) modal.classList.add("open");
        });
    });

    closeButtons.forEach(function (button) {
        button.addEventListener("click", function () {
            if (modal) modal.classList.remove("open");
        });
    });

    if (modal) {
        modal.addEventListener("click", function (event) {
            if (event.target === modal) modal.classList.remove("open");
        });
    }

    var toggles = document.querySelectorAll("[data-admin-toggle]");
    function applyThemeState() {
        var dark = document.querySelector('[data-admin-toggle="dark_mode"]');
        var compact = document.querySelector('[data-admin-toggle="sidebar_compact_mode"]');
        document.body.classList.toggle("admin-dark-mode", !!(dark && dark.checked));
        document.body.classList.toggle("admin-sidebar-compact", !!(compact && compact.checked));
    }

    function syncToggleState(settings) {
        if (!settings) return;
        toggles.forEach(function (toggle) {
            var key = toggle.dataset.adminToggle;
            if (Object.prototype.hasOwnProperty.call(settings, key)) {
                toggle.checked = settings[key] === "1";
            }
        });
        applyThemeState();
    }

    toggles.forEach(function (toggle) {
        toggle.addEventListener("change", function () {
            var key = toggle.dataset.adminToggle;
            var previous = !toggle.checked;

            if (key === "dark_mode" && toggle.checked) {
                var light = document.querySelector('[data-admin-toggle="light_mode"]');
                if (light) light.checked = false;
            }
            if (key === "light_mode" && toggle.checked) {
                var dark = document.querySelector('[data-admin-toggle="dark_mode"]');
                if (dark) dark.checked = false;
            }
            if ((key === "dark_mode" || key === "light_mode") && !toggle.checked) {
                var opposite = document.querySelector('[data-admin-toggle="' + (key === "dark_mode" ? "light_mode" : "dark_mode") + '"]');
                if (opposite) opposite.checked = true;
            }

            applyThemeState();

            var body = new URLSearchParams();
            var csrfMeta = document.querySelector('meta[name="csrf-token"]');
            var csrfToken = csrfMeta ? csrfMeta.getAttribute("content") : "";
            body.set("key", key);
            body.set("value", toggle.checked ? "1" : "0");
            body.set("csrf_token", csrfToken || document.body.dataset.csrf || "");

            fetch("settings_toggle.php", {
                method: "POST",
                headers: { "Content-Type": "application/x-www-form-urlencoded" },
                body: body.toString()
            })
                .then(function (response) {
                    return response.json().then(function (data) {
                        if (!response.ok || !data.success) throw new Error(data.message || "Setting could not be saved.");
                        return data;
                    });
                })
                .then(function (data) {
                    syncToggleState(data.settings);
                })
                .catch(function (error) {
                    toggle.checked = previous;
                    applyThemeState();
                    zafiroError(error.message || "Setting could not be saved.");
                });
        });
    });

    applyThemeState();
}

function initAdminNotifications() {
    var bell = document.getElementById("adminNotificationBell");
    var panel = document.getElementById("adminNotificationPanel");
    var count = document.getElementById("adminNotificationCount");
    var markAll = document.getElementById("markAllNotificationsRead");

    if (!bell || !panel) return;
    if (bell.tagName && bell.tagName.toLowerCase() === "a") return;

    bell.addEventListener("click", function (event) {
        event.stopPropagation();
        panel.classList.toggle("open");
    });

    document.addEventListener("click", function (event) {
        if (!panel.contains(event.target) && event.target !== bell) {
            panel.classList.remove("open");
        }
    });

    function postNotification(action, id) {
        var body = new URLSearchParams();
        body.set("action", action);
        if (id) body.set("id", id);

        return fetch("notifications.php", {
            method: "POST",
            headers: { "Content-Type": "application/x-www-form-urlencoded" },
            body: body.toString()
        }).then(function (response) {
            return response.json().then(function (data) {
                if (!response.ok || !data.success) throw new Error(data.message || "Notification action failed.");
                return data;
            });
        });
    }

    function updateCount(value) {
        if (!count) return;
        count.textContent = value;
        count.classList.toggle("is-hidden", parseInt(value, 10) <= 0);
    }

    panel.querySelectorAll("[data-notification-action]").forEach(function (button) {
        button.addEventListener("click", function () {
            var item = button.closest(".admin-notification-item");
            if (!item) return;
            var action = button.dataset.notificationAction;
            postNotification(action, item.dataset.notificationId).then(function (data) {
                updateCount(data.unread);
                if (action === "delete") {
                    item.remove();
                } else {
                    item.classList.remove("unread");
                    button.remove();
                }
            }).catch(function (error) {
                zafiroError(error.message);
            });
        });
    });

    if (markAll) {
        markAll.addEventListener("click", function () {
            postNotification("mark_all_read").then(function (data) {
                updateCount(data.unread);
                panel.querySelectorAll(".admin-notification-item").forEach(function (item) {
                    item.classList.remove("unread");
                    var readButton = item.querySelector('[data-notification-action="mark_read"]');
                    if (readButton) readButton.remove();
                });
            }).catch(function (error) {
                zafiroError(error.message);
            });
        });
    }
}

function initAdminNotificationPageClicks() {
    var detailModal = document.getElementById("adminNotificationDetailModal");
    var detailContent = document.getElementById("adminNotificationDetailContent");
    var detailClose = document.getElementById("closeAdminNotificationDetailModal");

    function markRead(id) {
        if (!id) return Promise.resolve();
        var body = new URLSearchParams();
        body.set("action", "mark_read");
        body.set("id", id);
        body.set("csrf_token", (document.querySelector('meta[name="csrf-token"]') || {}).content || document.body.dataset.csrf || "");
        return fetch("notifications.php", {
            method: "POST",
            headers: { "Content-Type": "application/x-www-form-urlencoded" },
            body: body.toString()
        }).catch(function () {});
    }

    function showDetail(card) {
        if (!detailModal || !detailContent) return;
        detailContent.innerHTML = [
            "<h2>" + escapeAdminHtml(card.dataset.title || "Notification") + "</h2>",
            "<p><strong>Message:</strong> " + escapeAdminHtml(card.dataset.message || "") + "</p>",
            "<p><strong>Type:</strong> " + escapeAdminHtml(card.dataset.type || "system") + "</p>",
            "<p><strong>Date:</strong> " + escapeAdminHtml(card.dataset.date || "") + "</p>",
            "<p><strong>Status:</strong> " + escapeAdminHtml(card.dataset.read || "Unread") + "</p>"
        ].join("");
        detailModal.classList.add("open");
    }

    document.querySelectorAll("[data-admin-notification-card]").forEach(function (card) {
        card.addEventListener("click", function (event) {
            if (event.target.closest("button, a, form, select, input")) return;
            var targetUrl = card.dataset.targetUrl || "";
            card.classList.remove("unread");
            card.dataset.read = "Read";
            markRead(card.dataset.notificationId).finally(function () {
                if (targetUrl) {
                    window.location.href = targetUrl;
                } else {
                    showDetail(card);
                }
            });
        });
    });

    if (detailClose && detailModal) {
        detailClose.addEventListener("click", function () { detailModal.classList.remove("open"); });
        detailModal.addEventListener("click", function (event) {
            if (event.target === detailModal) detailModal.classList.remove("open");
        });
    }
}

function initManageNotificationsPage() {
    var modal = document.getElementById("manageNotificationModal");
    var details = document.getElementById("manageNotificationDetails");
    var title = document.getElementById("manageNotificationTitle");
    var close = document.getElementById("closeManageNotificationModal");
    var typeSelect = document.getElementById("notificationTypeSelect");
    var productTarget = document.getElementById("notificationProductTarget");
    var orderTarget = document.getElementById("notificationOrderTarget");
    var productSelect = document.getElementById("notificationProductSelect");
    var orderSelect = document.getElementById("notificationOrderSelect");
    var linkInput = document.getElementById("notificationLinkInput");

    function syncNotificationLink() {
        if (!typeSelect || !linkInput) return;
        if ((typeSelect.value === "offer" || typeSelect.value === "product") && productSelect && productSelect.value) {
            linkInput.value = "product.php?id=" + productSelect.value;
        } else if (typeSelect.value === "order" && orderSelect && orderSelect.value) {
            linkInput.value = "order-tracking.php?order_id=" + encodeURIComponent(orderSelect.value);
        } else if (typeSelect.value === "general") {
            linkInput.value = "";
        }
    }

    function syncNotificationTargets() {
        if (!typeSelect || !productTarget || !orderTarget) return;
        var type = typeSelect.value;
        productTarget.style.display = (type === "offer" || type === "product") ? "" : "none";
        orderTarget.style.display = type === "order" ? "" : "none";
        syncNotificationLink();
    }

    if (typeSelect) {
        typeSelect.addEventListener("change", syncNotificationTargets);
        syncNotificationTargets();
    }
    if (productSelect) productSelect.addEventListener("change", syncNotificationLink);
    if (orderSelect) orderSelect.addEventListener("change", syncNotificationLink);

    if (!modal || !details || !title) return;

    function openNotification(row) {
        title.textContent = row.dataset.title || "Notification";
        details.innerHTML = [
            "<p><strong>Message</strong><span>" + escapeAdminHtml(row.dataset.message || "") + "</span></p>",
            "<p><strong>Type</strong><span>" + escapeAdminHtml(row.dataset.type || "system") + "</span></p>",
            "<p><strong>Target</strong><span>" + escapeAdminHtml(row.dataset.target || "All Users") + "</span></p>",
            "<p><strong>Created</strong><span>" + escapeAdminHtml(row.dataset.date || "") + "</span></p>",
            "<p><strong>Read Status</strong><span>" + escapeAdminHtml(row.dataset.read || "Unread") + "</span></p>",
            "<p><strong>Status</strong><span>" + escapeAdminHtml(row.dataset.status || "active") + "</span></p>"
        ].join("");
        modal.classList.add("open");

        if (row.dataset.read === "Unread") {
            var body = new URLSearchParams();
            body.set("action", "mark_read");
            body.set("notification_id", row.dataset.id || "0");
            body.set("csrf_token", (document.querySelector('meta[name="csrf-token"]') || {}).content || document.body.dataset.csrf || "");
            fetch("manage_notifications.php", {
                method: "POST",
                headers: { "Content-Type": "application/x-www-form-urlencoded" },
                body: body.toString()
            }).then(function () {
                row.dataset.read = "Read";
                var readCell = row.children[3];
                if (readCell) readCell.textContent = "Read";
            }).catch(function () {});
        }
    }

    document.querySelectorAll(".admin-notification-row").forEach(function (row) {
        row.addEventListener("click", function (event) {
            if (event.target.closest("a, button, form")) return;
            openNotification(row);
        });
    });

    if (close) close.addEventListener("click", function () { modal.classList.remove("open"); });
    modal.addEventListener("click", function (event) {
        if (event.target === modal) modal.classList.remove("open");
    });
}

function escapeAdminHtml(value) {
    return String(value).replace(/[&<>"']/g, function (char) {
        return ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#039;" })[char];
    });
}

function initReportsFilters() {
    document.querySelectorAll(".admin-main input[type='date'], .admin-main input[type='datetime-local']").forEach(function (input) {
        ["click", "focus"].forEach(function (eventName) {
            input.addEventListener(eventName, function () {
                if (typeof input.showPicker === "function") {
                    try { input.showPicker(); } catch (error) {}
                }
            });
        });
    });

    document.querySelectorAll(".admin-main select:not([multiple])").forEach(function (select) {
        if (select.dataset.enhanced === "1") return;
        select.dataset.enhanced = "1";
        select.classList.add("admin-native-select-hidden");

        var custom = document.createElement("div");
        custom.className = "reports-custom-select admin-premium-select";
        var button = document.createElement("button");
        button.type = "button";
        button.className = "reports-select-button";
        button.disabled = select.disabled;
        var menu = document.createElement("div");
        menu.className = "reports-select-menu";
        var items = [];

        function syncButton() {
            var selected = select.options[select.selectedIndex];
            button.textContent = selected ? selected.text : "Select";
            button.disabled = select.disabled;
            items.forEach(function (node) {
                node.classList.toggle("active", node.dataset.value === select.value);
            });
        }

        Array.prototype.forEach.call(select.options, function (option) {
            var item = document.createElement("div");
            item.className = "reports-select-option";
            item.dataset.value = option.value;
            item.textContent = option.text;
            item.addEventListener("click", function () {
                select.value = option.value;
                select.dispatchEvent(new Event("change", { bubbles: true }));
                syncButton();
                custom.classList.remove("open");
            });
            items.push(item);
            menu.appendChild(item);
        });

        button.addEventListener("click", function (event) {
            event.stopPropagation();
            document.querySelectorAll(".admin-premium-select.open").forEach(function (node) {
                if (node !== custom) node.classList.remove("open");
            });
            custom.classList.toggle("open");
        });
        select.addEventListener("change", syncButton);
        document.addEventListener("click", function (event) {
            if (!custom.contains(event.target)) custom.classList.remove("open");
        });

        custom.appendChild(button);
        custom.appendChild(menu);
        select.insertAdjacentElement("afterend", custom);
        syncButton();
    });
}

function initReportsCharts() {
    if (!window.Chart) return;
    var overview = document.getElementById("reportsOverviewChart");
    if (overview) {
        var range = document.getElementById("reportsOverviewRange");
        var chart = new Chart(overview, {
            type: "line",
            data: {
                labels: JSON.parse(overview.dataset.weekLabels || "[]"),
                datasets: [{ data: JSON.parse(overview.dataset.weekValues || "[]"), borderColor: "#d4af37", backgroundColor: "rgba(212,175,55,.16)", fill: true, tension: .4, pointRadius: 4 }]
            },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { precision: 0 } } } }
        });
        if (range) range.addEventListener("change", function () {
            var month = range.value === "month";
            chart.data.labels = JSON.parse(overview.dataset[month ? "monthLabels" : "weekLabels"] || "[]");
            chart.data.datasets[0].data = JSON.parse(overview.dataset[month ? "monthValues" : "weekValues"] || "[]");
            chart.update();
        });
    }

    var distribution = document.getElementById("reportsDistributionChart");
    if (distribution) {
        new Chart(distribution, {
            type: "doughnut",
            data: {
                labels: JSON.parse(distribution.dataset.labels || "[]"),
                datasets: [{ data: JSON.parse(distribution.dataset.values || "[]"), backgroundColor: ["#d4af37", "#111827", "#7F1D1D", "#8a6a20", "#E5E0D8"], borderWidth: 0 }]
            },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: "bottom" } }, cutout: "64%" }
        });
    }
}

function initReportsHeatmapTips() {
    var tip;
    document.querySelectorAll("[data-report-tip]").forEach(function (cell) {
        cell.addEventListener("mouseenter", function () {
            tip = document.createElement("div");
            tip.className = "reports-heat-tooltip";
            tip.textContent = cell.dataset.reportTip || "";
            document.body.appendChild(tip);
        });
        cell.addEventListener("mousemove", function (event) {
            if (!tip) return;
            tip.style.left = event.clientX + 12 + "px";
            tip.style.top = event.clientY + 12 + "px";
        });
        cell.addEventListener("mouseleave", function () {
            if (tip) tip.remove();
            tip = null;
        });
    });
}
