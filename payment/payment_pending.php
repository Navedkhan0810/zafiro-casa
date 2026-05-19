<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$orderId = trim((string) ($_GET["order_id"] ?? ""));
$safeOrderId = htmlspecialchars($orderId, ENT_QUOTES, "UTF-8");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Pending | Zafiro Casa</title>
    <link rel="stylesheet" href="../assets/css/style.css?v=24">
    <link rel="stylesheet" href="../assets/css/profile-pages.css?v=1">
</head>
<body>
<main class="account-simple-page order-success-page">
    <section class="account-card order-success-card">
        <div class="success-icon">...</div>
        <h1>Payment pending</h1>
        <p>PhonePe has not confirmed this payment yet. Please check again after a few minutes.</p>
        <?php if ($safeOrderId !== ""): ?>
            <p>Order ID: <strong><?php echo $safeOrderId; ?></strong></p>
        <?php endif; ?>
        <div class="account-actions-row">
            <a class="account-btn" href="../frontend/order-tracking.php?order_id=<?php echo urlencode($orderId); ?>">Track Order</a>
            <a class="account-btn outline" href="../frontend/cart.php">Back to Cart</a>
        </div>
    </section>
</main>
</body>
</html>
