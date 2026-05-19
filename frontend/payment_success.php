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
var pendingMode = sessionStorage.getItem("zafiroPendingOrderMode");
var pendingItems = JSON.parse(sessionStorage.getItem("zafiroPendingOrderItems") || "[]");
if (pendingMode === "cart" && document.body.dataset.userId) {
    var cartKey = "zafiroCart_user_" + document.body.dataset.userId;
    var orderedIds = pendingItems.map(function (item) { return String(item.product_id || ""); });
    var cart = JSON.parse(localStorage.getItem(cartKey) || "[]").filter(function (item) {
        return !orderedIds.includes(String(item.product_id));
    });
    localStorage.setItem(cartKey, JSON.stringify(cart));
    var cartCount = document.getElementById("cartCount");
    if (cartCount) cartCount.textContent = cart.reduce(function (sum, item) { return sum + (item.quantity || 1); }, 0);
}
sessionStorage.removeItem("zafiroPendingOrderMode");
sessionStorage.removeItem("zafiroPendingOrderItems");
localStorage.removeItem("zafiroBuyNowItem");
</script>
<?php include("../backend/includes/footer.php"); ?>
