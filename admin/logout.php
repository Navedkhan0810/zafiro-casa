<?php
session_start();
include("../backend/config/db.php");
include_once("../backend/includes/admin_reports.php");

if (isset($_SESSION["admin_id"])) {
    $sessionId = session_id();
    $stmt = $conn->prepare("UPDATE admin_login_activity SET is_active = 0, logout_time = NOW() WHERE admin_id = ? AND session_id = ?");
    $stmt->bind_param("is", $_SESSION["admin_id"], $sessionId);
    $stmt->execute();
    adminReportLog($conn, "admin_logout", "Admin logged out.", "admin", $_SESSION["admin_id"], $_SESSION["admin_username"] ?? "");
}

session_unset();
session_destroy();
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), "", time() - 42000, $params["path"], $params["domain"], $params["secure"], $params["httponly"]);
}
header("Location: login.php");
exit;
?>
