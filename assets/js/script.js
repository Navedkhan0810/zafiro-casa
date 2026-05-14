document.addEventListener("DOMContentLoaded", function () {
    injectEnhancementStyles();
    initNavbar();
    initSmoothScroll();
    initPageAnimations();
    initCategoryControls();
    initProductDetail();
    initContactForm();
    initScrollTopButton();
    initAutoHideAlerts();
    initButtonRipple();
});

function injectEnhancementStyles() {
    if (document.getElementById("zafiro-js-enhancements")) return;
    var style = document.createElement("style");
    style.id = "zafiro-js-enhancements";
    style.textContent = [
        "header.is-scrolled { box-shadow: 0 8px 22px rgba(0, 0, 0, 0.24); }",
        "nav a.active { color: #d4af37; }",
        ".js-fade-ready { opacity: 0; transform: translateY(14px); transition: opacity 700ms ease, transform 700ms ease; }",
        ".js-fade-ready.js-fade-in { opacity: 1; transform: translateY(0); }",
        ".js-card-ready { opacity: 0; transform: translateY(18px); transition: opacity 550ms ease, transform 550ms ease; }",
        ".js-card-ready.js-visible { opacity: 1; transform: translateY(0); }",
        ".js-product-enter { animation: zafiroProductEnter 500ms ease both; }",
        "@keyframes zafiroProductEnter { from { opacity: 0; transform: translateY(16px); } to { opacity: 1; transform: translateY(0); } }",
        ".detail-img img.is-zoomed { transform: scale(1.06); }",
        ".scroll-top { position: fixed; right: 22px; bottom: 22px; width: 42px; height: 42px; border: 0; border-radius: 50%; background: #111; color: #fff; cursor: pointer; opacity: 0; pointer-events: none; transform: translateY(10px); transition: opacity 250ms ease, transform 250ms ease, background 250ms ease; z-index: 1200; }",
        ".scroll-top.visible { opacity: 1; pointer-events: auto; transform: translateY(0); }",
        ".scroll-top:hover { background: #d4af37; color: #111; }",
        ".toast { position: fixed; left: 50%; bottom: 26px; transform: translate(-50%, 12px); background: #111; color: #fff; padding: 11px 16px; border-radius: 6px; opacity: 0; transition: opacity 250ms ease, transform 250ms ease; z-index: 1400; }",
        ".toast.visible { opacity: 1; transform: translate(-50%, 0); }",
        ".toast.error { background: #b42318; }",
        ".toast.success { background: #146c43; }",
        ".form-message { margin-top: 10px; font-size: 14px; }",
        ".form-message.error { color: #ffb4ab; }",
        ".form-message.success { color: #b7f7c8; }",
        ".is-hiding { opacity: 0; transition: opacity 300ms ease; }",
        "button, .product-btn, .hero-btn { position: relative; overflow: hidden; }",
        ".ripple { position: absolute; border-radius: 50%; transform: scale(0); animation: zafiroRipple 600ms linear; background: rgba(255, 255, 255, 0.45); pointer-events: none; }",
        "@keyframes zafiroRipple { to { transform: scale(4); opacity: 0; } }"
    ].join("");
    document.head.appendChild(style);
}

function initNavbar() {
    var header = document.querySelector("header");
    var nav = document.querySelector("nav");
    var menuButton = document.querySelector(".menu-toggle, .nav-toggle, [data-menu-toggle]");
    var currentPage = window.location.pathname.split("/").pop() || "index.php";

    document.querySelectorAll("nav a").forEach(function (link) {
        var linkPage = link.getAttribute("href").split("#")[0];
        if (linkPage === currentPage) link.classList.add("active");
    });

    if (menuButton && nav) {
        menuButton.addEventListener("click", function () {
            nav.classList.toggle("open");
            menuButton.classList.toggle("active");
        });
    }

    if (header) {
        updateStickyHeader(header);
        window.addEventListener("scroll", function () { updateStickyHeader(header); });
    }
}

function updateStickyHeader(header) {
    header.classList.toggle("is-scrolled", window.scrollY > 20);
}

function initSmoothScroll() {
    document.querySelectorAll('a[href^="#"]').forEach(function (link) {
        link.addEventListener("click", function (event) {
            var target = document.querySelector(link.getAttribute("href"));
            if (!target) return;
            event.preventDefault();
            target.scrollIntoView({ behavior: "smooth", block: "start" });
        });
    });
}

function initPageAnimations() {
    var animatedCards = document.querySelectorAll(".products .card, .category-card, .product-card");
    if (!("IntersectionObserver" in window)) return;
    var observer = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
            if (entry.isIntersecting) {
                entry.target.classList.add("js-visible");
                observer.unobserve(entry.target);
            }
        });
    }, { threshold: 0.15 });
    animatedCards.forEach(function (card, index) {
        card.classList.add("js-card-ready");
        card.style.transitionDelay = Math.min(index * 60, 360) + "ms";
        observer.observe(card);
    });
}

function initCategoryControls() {
    document.querySelectorAll(".product-card").forEach(function (card, index) {
        card.classList.add("js-product-enter");
        card.style.animationDelay = Math.min(index * 70, 420) + "ms";
    });
}

function initProductDetail() {
    var mainImage = document.querySelector(".detail-img img, .product-main-image, [data-main-image]");
    if (!mainImage) return;
    mainImage.addEventListener("mouseenter", function () { mainImage.classList.add("is-zoomed"); });
    mainImage.addEventListener("mouseleave", function () { mainImage.classList.remove("is-zoomed"); });
}

function initContactForm() {}
function initScrollTopButton() {}
function initAutoHideAlerts() {}
function initButtonRipple() {}
