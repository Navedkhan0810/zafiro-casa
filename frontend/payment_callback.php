<?php
session_start();
include("../backend/config/db.php");
include_once("../backend/includes/phonepe_helper.php");

ensurePhonePeSchema($conn);
$orderId = trim($_GET["order_id"] ?? $_POST["order_id"] ?? "");
if ($orderId === "") {
    error_log("PhonePe callback missing order_id.");
    header("Location: payment_failed.php");
    exit;
}

$result = phonepeVerifyAndUpdate($conn, $orderId);
header("Location: " . ($result["success"] ? "payment_success.php" : "payment_failed.php") . "?order_id=" . urlencode($orderId));
exit;
