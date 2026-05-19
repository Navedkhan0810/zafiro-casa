<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once(__DIR__ . "/../backend/config/db.php");
require_once(__DIR__ . "/../backend/includes/phonepe_helper.php");

$orderId = trim((string) ($_GET["order_id"] ?? $_GET["merchantOrderId"] ?? ""));
$safeOrderId = htmlspecialchars($orderId, ENT_QUOTES, "UTF-8");
if ($orderId === "") {
    header("Location: payment_failed.php");
    exit;
}

$result = phonepeVerifyAndUpdate($conn, $orderId);
$status = strtolower((string) ($result["status"] ?? "failed"));
if ($status !== "paid") {
    if ($status === "pending") {
    header("Location: payment_pending.php?order_id=" . urlencode($orderId));
    exit;
}
header("Location: payment_failed.php?order_id=" . urlencode($orderId) . "&status=failed");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Successful | Zafiro Casa</title>
    <link rel="stylesheet" href="../assets/css/style.css?v=24">
    <link rel="stylesheet" href="../assets/css/profile-pages.css?v=1">
</head>
<body data-user-id="<?php echo (int) ($_SESSION['user_id'] ?? 0); ?>">
<main class="account-simple-page order-success-page">
    <section class="account-card order-success-card">
        <div class="success-icon">OK</div>
        <h1>Payment successful</h1>
        <p>Your PhonePe test payment is verified.</p>
        <p>Order ID: <strong><?php echo $safeOrderId; ?></strong></p>
        <div class="account-actions-row">
            <a class="account-btn" href="../frontend/order-tracking.php?order_id=<?php echo urlencode($orderId); ?>">Track Order</a>
            <a class="account-btn outline" href="../frontend/index.php">Continue Shopping</a>
        </div>
    </section>
</main>
<script>
var pendingMode = sessionStorage.getItem("zafiroPendingOrderMode");
var pendingItems = JSON.parse(sessionStorage.getItem("zafiroPendingOrderItems") || "[]");
if (pendingMode === "cart" && document.body.dataset.userId && document.body.dataset.userId !== "0") {
    var cartKey = "zafiroCart_user_" + document.body.dataset.userId;
    var orderedIds = pendingItems.map(function (item) { return String(item.product_id || ""); });
    var cart = JSON.parse(localStorage.getItem(cartKey) || "[]").filter(function (item) {
        return !orderedIds.includes(String(item.product_id));
    });
    localStorage.setItem(cartKey, JSON.stringify(cart));
}
sessionStorage.removeItem("zafiroPendingOrderMode");
sessionStorage.removeItem("zafiroPendingOrderItems");
localStorage.removeItem("zafiroBuyNowItem");
</script>
</body>
</html>

