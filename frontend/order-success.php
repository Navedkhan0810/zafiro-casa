<?php
$orderId = htmlspecialchars($_GET['order_id'] ?? 'ZC1001');
$deliveryDate = date('d M Y', strtotime('+7 days'));
include("../backend/includes/header.php");
?>
<main class="account-simple-page order-success-page">
    <section class="account-card order-success-card">
        <div class="success-icon">✓</div>
        <h1>Order placed successfully</h1>
        <p>Your order ID is <strong><?php echo $orderId; ?></strong></p>
        <p>Estimated delivery: <strong><?php echo $deliveryDate; ?></strong></p>
        <div class="account-actions-row">
            <a class="account-btn" href="order-tracking.php?order_id=<?php echo $orderId; ?>">Track Order</a>
            <a class="account-btn outline" href="index.php">Continue Shopping</a>
        </div>
    </section>
</main>
<?php include("../backend/includes/footer.php"); ?>
