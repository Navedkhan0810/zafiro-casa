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
<script>
(function () {
    var orderId = <?php echo json_encode($orderId); ?>;
    var userId = document.body.dataset.userId || "";
    if (!userId || !orderId) return;

    var orderKey = "zafiroOrders_user_" + userId;
    var cartKey = "zafiroCart_user_" + userId;
    var orders = JSON.parse(localStorage.getItem(orderKey) || "[]");
    var order = orders.find(function (item) {
        return item.order_id === orderId;
    });
    if (!order || !Array.isArray(order.items)) return;

    var orderedIds = order.items.map(function (item) {
        return String(item.product_id || "");
    }).filter(Boolean);
    var cart = JSON.parse(localStorage.getItem(cartKey) || "[]").filter(function (item) {
        return !orderedIds.includes(String(item.product_id));
    });
    localStorage.setItem(cartKey, JSON.stringify(cart));

    var cartCount = document.getElementById("cartCount");
    if (cartCount) {
        cartCount.textContent = cart.reduce(function (sum, item) {
            return sum + (item.quantity || 1);
        }, 0);
    }
})();
</script>
<?php include("../backend/includes/footer.php"); ?>
