document.addEventListener("DOMContentLoaded", function () {
    initUserNotifications();
});

function initUserNotifications() {
    var count = document.getElementById("notificationCount");
    if (!count) return;

    fetch("notifications.php?ajax=summary")
        .then(function (response) { return response.json(); })
        .then(function (data) {
            count.textContent = data.count || 0;
        })
        .catch(function () {});
}
