<?php
$orderId = htmlspecialchars($_GET["order_id"] ?? "");
include("../backend/includes/header.php");
?>
<main class="account-simple-page order-success-page">
    <section class="account-card order-success-card">
        <div class="success-icon">✓</div>
        <h1>Payment successful</h1>
        <p>Your PhonePe test payment is verified.</p>
        <p>Order ID: <strong><?php echo $orderId; ?></strong></p>
        <div class="account-actions-row">
            <a class="account-btn" href="order-tracking.php?order_id=<?php echo $orderId; ?>">Track Order</a>
            <a class="account-btn outline" href="index.php">Continue Shopping</a>
        </div>
    </section>
</main>
<script>
if (document.body.dataset.userId) localStorage.removeItem("zafiroCart_user_" + document.body.dataset.userId);
localStorage.removeItem("zafiroBuyNowItem");
</script>
<?php include("../backend/includes/footer.php"); ?>
