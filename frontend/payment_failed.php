<?php
$orderId = htmlspecialchars($_GET["order_id"] ?? "");
include("../backend/includes/header.php");
?>
<main class="account-simple-page order-success-page">
    <section class="account-card order-success-card">
        <div class="success-icon">!</div>
        <h1>Payment failed or pending</h1>
        <p>PhonePe sandbox did not confirm this payment.</p>
        <?php if ($orderId): ?><p>Order ID: <strong><?php echo $orderId; ?></strong></p><?php endif; ?>
        <div class="account-actions-row">
            <a class="account-btn" href="place-order.php">Try Again</a>
            <a class="account-btn outline" href="cart.php">Back to Cart</a>
        </div>
    </section>
</main>
<?php include("../backend/includes/footer.php"); ?>
