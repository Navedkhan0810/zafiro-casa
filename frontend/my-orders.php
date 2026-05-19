<?php
session_start();
include("../backend/config/db.php");
include_once("../backend/includes/user_auth.php");
include_once("../backend/includes/image_paths.php");

if (!isset($_SESSION['user_id'])) {
    header("Location: auth.php");
    exit;
}

$userId = (int) $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT o.*, p.name, p.image, p.price, oi.product_image AS order_item_image FROM orders o LEFT JOIN products p ON p.id = o.product_id LEFT JOIN order_items oi ON oi.order_id = COALESCE(NULLIF(o.order_id, ''), NULLIF(o.order_code, ''), o.id) WHERE o.user_id = ? GROUP BY o.id ORDER BY o.order_date DESC");
$stmt->bind_param("i", $userId);
$stmt->execute();
$orders = $stmt->get_result();

$conn->query("CREATE TABLE IF NOT EXISTS reviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    product_id INT NOT NULL,
    order_id VARCHAR(60) NOT NULL,
    rating INT NOT NULL,
    review_text TEXT NULL,
    status VARCHAR(20) DEFAULT 'pending',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_order_product_review (user_id, product_id, order_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

include("../backend/includes/header.php");
?>
<main class="account-simple-page">
    <?php include("../backend/includes/profile_back_button.php"); ?>
    <section class="section-title"><span>Zafiro Casa</span><h2>My Orders</h2></section>
    <section class="order-list" id="localOrdersList">
        <?php if ($orders && $orders->num_rows > 0): ?>
            <?php while ($order = $orders->fetch_assoc()): ?>
                <article class="order-card">
                    <img class="order-product-img" src="<?php echo htmlspecialchars(zafiroPublicImageUrl($order['order_item_image'] ?: ($order['image'] ?? ''))); ?>" alt="<?php echo htmlspecialchars($order['name'] ?? 'Product'); ?>">
                    <div class="order-details">
                        <h3><?php echo htmlspecialchars($order['name'] ?? 'Product unavailable'); ?></h3>
                        <?php $displayOrderId = $order['order_id'] ?: ($order['order_code'] ?: $order['id']); ?>
                        <p>Order ID: <?php echo htmlspecialchars($displayOrderId); ?></p>
                        <p>Order Status: <?php echo htmlspecialchars($order['order_status']); ?></p>
                        <p>Payment Status: <?php echo htmlspecialchars($order['payment_status']); ?></p>
                        <p>Order Date: <?php echo htmlspecialchars($order['order_date']); ?></p>
                        <p>&#8377;<?php echo htmlspecialchars($order['total_amount'] ?: ($order['total'] ?: ($order['price'] ?? ''))); ?></p>
                    </div>
                    <div class="order-actions">
                        <a class="account-btn small" href="order-tracking.php?order_id=<?php echo urlencode($displayOrderId); ?>">Track Order</a>
                        <?php
                        $pid = (int) ($order['product_id'] ?? 0);
                        $reviewCheck = $conn->prepare("SELECT id FROM reviews WHERE user_id = ? AND product_id = ? AND order_id = ? LIMIT 1");
                        $reviewCheck->bind_param("iis", $userId, $pid, $displayOrderId);
                        $reviewCheck->execute();
                        $hasReview = $reviewCheck->get_result()->num_rows > 0;
                        ?>
                        <?php if ($hasReview): ?>
                            <span class="account-btn small muted">Review Submitted</span>
                        <?php elseif (strtolower(trim($order['order_status'] ?? '')) === 'delivered'): ?>
                            <a class="account-btn small outline" href="order-review.php?order_id=<?php echo urlencode($displayOrderId); ?>">Write Review</a>
                        <?php endif; ?>
                        <a class="account-btn small danger-btn" href="return-order.php?order_id=<?php echo urlencode($displayOrderId); ?>">Return Order</a>
                    </div>
                </article>
            <?php endwhile; ?>
        <?php else: ?>
            <section class="account-card" id="noOrdersMessage"><p>No orders found.</p></section>
        <?php endif; ?>
    </section>
</main>
<?php include("../backend/includes/footer.php"); ?>
