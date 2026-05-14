<?php
session_start();
include("../backend/config/db.php");

if (isset($_SESSION["admin_id"])) {
    $sessionId = session_id();
    $stmt = $conn->prepare("UPDATE admin_login_activity SET is_active = 0, logout_time = NOW() WHERE admin_id = ? AND session_id = ?");
    $stmt->bind_param("is", $_SESSION["admin_id"], $sessionId);
    $stmt->execute();
}

session_unset();
session_destroy();
header("Location: login.php");
exit;
?>
