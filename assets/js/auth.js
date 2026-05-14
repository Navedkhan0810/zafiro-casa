document.addEventListener("DOMContentLoaded", function () {
    var tabs = document.querySelectorAll("[data-auth-tab]");
    var loginForm = document.getElementById("loginForm");
    var registerForm = document.getElementById("registerForm");

    function setTab(tabName) {
        document.querySelectorAll(".auth-tab").forEach(function (tab) {
            tab.classList.toggle("active", tab.dataset.authTab === tabName);
        });

        if (loginForm) loginForm.classList.toggle("active", tabName === "login");
        if (registerForm) registerForm.classList.toggle("active", tabName === "register");
    }

    tabs.forEach(function (tab) {
        tab.addEventListener("click", function () {
            setTab(tab.dataset.authTab);
        });
    });

    document.querySelectorAll(".password-toggle").forEach(function (button) {
        button.addEventListener("click", function () {
            var input = button.parentElement.querySelector("input");
            if (!input) return;

            var isPassword = input.type === "password";
            input.type = isPassword ? "text" : "password";
            button.innerHTML = isPassword ? '<i class="fa-regular fa-eye-slash"></i>' : '<i class="fa-regular fa-eye"></i>';
        });
    });

    if (location.hash === "#register") {
        setTab("register");
    }
});
