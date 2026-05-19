<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$status = strtolower((string) ($_GET["status"] ?? "failed"));
$orderId = trim((string) ($_GET["order_id"] ?? ""));
$safeOrderId = htmlspecialchars($orderId, ENT_QUOTES, "UTF-8");
$isPending = $status === "pending";
$title = $isPending ? "Payment pending" : "Payment failed";
$message = $isPending ? "PhonePe has not confirmed this payment yet. Please check again after a few minutes." : "PhonePe did not complete this payment. You can try again or choose Cash on Delivery.";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($title, ENT_QUOTES, "UTF-8"); ?> | Zafiro Casa</title>
    <link rel="stylesheet" href="../assets/css/style.css?v=24">
    <link rel="stylesheet" href="../assets/css/profile-pages.css?v=1">
</head>
<body>
<main class="account-simple-page order-success-page">
    <section class="account-card order-success-card">
        <div class="success-icon"><?php echo $isPending ? "..." : "X"; ?></div>
        <h1><?php echo htmlspecialchars($title, ENT_QUOTES, "UTF-8"); ?></h1>
        <p><?php echo htmlspecialchars($message, ENT_QUOTES, "UTF-8"); ?></p>
        <?php if ($safeOrderId !== ""): ?>
            <p>Order ID: <strong><?php echo $safeOrderId; ?></strong></p>
        <?php endif; ?>
        <div class="account-actions-row">
            <a class="account-btn" href="../frontend/place-order.php">Try Again</a>
            <a class="account-btn outline" href="../frontend/cart.php">Back to Cart</a>
        </div>
    </section>
</main>
</body>
</html>
