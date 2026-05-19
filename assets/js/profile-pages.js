document.addEventListener("DOMContentLoaded", function () {
    document.querySelectorAll(".profile-back-btn, .zafiro-back-btn, .zc-back-btn").forEach(function (button) {
        button.addEventListener("click", function (event) {
            button.classList.add("is-leaving");
            if (button.dataset.historyBack === "true") {
                event.preventDefault();
                if (window.history.length > 1) {
                    window.history.back();
                } else {
                    window.location.href = button.getAttribute("href") || "index.php";
                }
            }
        });
    });
});
