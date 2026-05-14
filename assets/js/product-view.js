document.addEventListener("DOMContentLoaded", function () {
    initProductGallery();
    initProductTabs();
    initProductQuantity();
    initProductBuyNow();
});

function initProductGallery() {
    var mainImage = document.querySelector("[data-product-view-main-image]");
    var thumbs = document.querySelectorAll("[data-product-thumb]");
    if (!mainImage || !thumbs.length) return;

    thumbs.forEach(function (thumb) {
        thumb.addEventListener("click", function () {
            var nextImage = thumb.dataset.productThumb;
            if (!nextImage || mainImage.getAttribute("src") === nextImage) return;

            thumbs.forEach(function (item) { item.classList.remove("active"); });
            thumb.classList.add("active");
            mainImage.classList.add("is-changing");

            window.setTimeout(function () {
                mainImage.setAttribute("src", nextImage);
                var shell = mainImage.closest("[data-product-id]");
                if (shell) shell.dataset.productImage = nextImage;
                mainImage.classList.remove("is-changing");
            }, 160);
        });
    });
}

function initProductTabs() {
    var buttons = document.querySelectorAll("[data-product-tab]");
    var panels = document.querySelectorAll("[data-product-panel]");
    if (!buttons.length || !panels.length) return;

    buttons.forEach(function (button) {
        button.addEventListener("click", function () {
            var target = button.dataset.productTab;
            buttons.forEach(function (item) { item.classList.remove("active"); });
            panels.forEach(function (panel) {
                panel.classList.toggle("active", panel.dataset.productPanel === target);
            });
            button.classList.add("active");
        });
    });
}

function initProductQuantity() {
    var shell = document.querySelector(".product-detail-view[data-product-id]");
    var value = document.querySelector("[data-qty-value]");
    var minus = document.querySelector("[data-qty-minus]");
    var plus = document.querySelector("[data-qty-plus]");
    if (!shell || !value || !minus || !plus) return;

    function setQuantity(next) {
        var quantity = Math.max(1, Math.min(10, next));
        value.textContent = String(quantity);
        shell.dataset.productQuantity = String(quantity);
    }

    setQuantity(1);
    minus.addEventListener("click", function () {
        setQuantity(parseInt(value.textContent || "1", 10) - 1);
    });
    plus.addEventListener("click", function () {
        setQuantity(parseInt(value.textContent || "1", 10) + 1);
    });
}

function initProductBuyNow() {
    var buttons = document.querySelectorAll(".buy-now-product-btn");
    if (!buttons.length) return;

    buttons.forEach(function (button) {
        button.addEventListener("click", function () {
            var product = button.closest("[data-product-id]");
            if (!product) return;

            if (document.body && document.body.dataset.auth !== "1") {
                window.location.href = "auth.php";
                return;
            }

            var item = {
                product_id: product.dataset.productId,
                product_name: product.dataset.productName,
                price: product.dataset.productPrice,
                image: product.dataset.productImage,
                product_url: product.dataset.productUrl || window.location.href,
                quantity: parseInt(product.dataset.productQuantity || "1", 10) || 1
            };

            localStorage.setItem("zafiroBuyNowItem", JSON.stringify(item));
            window.location.href = "checkout.php?mode=buy_now";
        });
    });
}
