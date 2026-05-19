<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once(__DIR__ . "/../backend/config/db.php");
require_once(__DIR__ . "/../backend/includes/phonepe_helper.php");

ensurePhonePeSchema($conn);
$orderId = trim((string) ($_GET["merchantOrderId"] ?? $_GET["order_id"] ?? $_POST["merchantOrderId"] ?? $_POST["order_id"] ?? ""));
if ($orderId === "") {
    phonepeLog("PhonePe callback missing order id");
    header("Location: payment_failed.php");
    exit;
}

$result = phonepeVerifyAndUpdate($conn, $orderId);
$status = strtolower((string) ($result["status"] ?? "failed"));
if ($status === "paid") {
    header("Location: payment_success.php?order_id=" . urlencode($orderId));
    exit;
}

if ($status === "pending") {
    header("Location: payment_pending.php?order_id=" . urlencode($orderId));
    exit;
}
header("Location: payment_failed.php?order_id=" . urlencode($orderId) . "&status=failed");
exit;

