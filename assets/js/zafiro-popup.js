(function () {
    function removePopup(overlay) {
        if (!overlay) return;
        overlay.classList.remove("open");
        setTimeout(function () {
            overlay.remove();
        }, 220);
    }

    function createPopup(options) {
        var overlay = document.createElement("div");
        overlay.className = "zc-popup-overlay";
        overlay.innerHTML =
            '<div class="zc-popup-card" role="dialog" aria-modal="true">' +
                '<div class="zc-popup-icon">' + (options.icon || "i") + "</div>" +
                '<h3 class="zc-popup-title">' + options.title + "</h3>" +
                '<p class="zc-popup-message"></p>' +
                '<div class="zc-popup-actions"></div>' +
            "</div>";

        overlay.querySelector(".zc-popup-message").textContent = options.message || "";
        var actions = overlay.querySelector(".zc-popup-actions");

        if (options.confirm) {
            var cancel = document.createElement("button");
            cancel.type = "button";
            cancel.className = "zc-popup-btn secondary";
            cancel.textContent = options.cancelText || "Cancel";
            cancel.addEventListener("click", function () {
                removePopup(overlay);
            });

            var confirm = document.createElement("button");
            confirm.type = "button";
            confirm.className = "zc-popup-btn primary";
            confirm.textContent = options.confirmText || "Confirm";
            confirm.addEventListener("click", function () {
                removePopup(overlay);
                if (typeof options.onConfirm === "function") options.onConfirm();
            });

            actions.appendChild(cancel);
            actions.appendChild(confirm);
        } else {
            var ok = document.createElement("button");
            ok.type = "button";
            ok.className = "zc-popup-btn primary";
            ok.textContent = options.okText || "OK";
            ok.addEventListener("click", function () {
                removePopup(overlay);
            });
            actions.appendChild(ok);
        }

        overlay.addEventListener("click", function (event) {
            if (event.target === overlay && !options.confirm) removePopup(overlay);
        });

        document.body.appendChild(overlay);
        requestAnimationFrame(function () {
            overlay.classList.add("open");
        });

        if (options.autoClose) {
            setTimeout(function () {
                removePopup(overlay);
            }, options.autoClose);
        }
    }

    window.showSuccess = function (message) {
        createPopup({ title: "Success", message: message, icon: "✓", autoClose: 2200 });
    };

    window.showError = function (message) {
        createPopup({ title: "Error", message: message, icon: "!", autoClose: 3200 });
    };

    window.showInfo = function (message) {
        createPopup({ title: "Notice", message: message, icon: "i", autoClose: 2600 });
    };

    window.showConfirm = function (message, onConfirm, options) {
        options = options || {};
        createPopup({
            title: options.title || "Confirm Action",
            message: message,
            icon: options.icon || "!",
            confirm: true,
            confirmText: options.confirmText || "Confirm",
            cancelText: options.cancelText || "Cancel",
            onConfirm: onConfirm
        });
    };
})();
