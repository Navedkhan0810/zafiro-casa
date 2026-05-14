<?php
session_start();
include("../backend/config/db.php");
include_once("../backend/includes/user_auth.php");

if (!isset($_SESSION['user_id'])) {
    header("Location: auth.php");
    exit;
}

$order = null;
$notFound = false;
$orderId = trim($_GET['order_id'] ?? '');

if ($orderId !== '') {
    $userId = (int) $_SESSION['user_id'];
    $stmt = $conn->prepare("SELECT o.*, p.name FROM orders o LEFT JOIN products p ON p.id = o.product_id WHERE (o.id = ? OR o.order_id = ? OR o.order_code = ?) AND o.user_id = ? LIMIT 1");
    $numericOrderId = ctype_digit($orderId) ? (int) $orderId : 0;
    $stmt->bind_param("issi", $numericOrderId, $orderId, $orderId, $userId);
    $stmt->execute();
    $order = $stmt->get_result()->fetch_assoc();
    $notFound = !$order;
}

include("../backend/includes/header.php");
?>
<main class="page-bg luxury-bg tracking-bg">
    <div class="page-content account-simple-page">
    <section class="account-card">
        <h1>Order Tracking</h1>
        <form class="account-input-row" action="order-tracking.php" method="GET">
            <input type="text" name="order_id" value="<?php echo htmlspecialchars($orderId); ?>" placeholder="Enter Order ID">
            <button type="submit" class="account-btn">Track</button>
        </form>
    </section>

    <?php if ($notFound): ?>
        <section class="account-card"><p>Order not found.</p></section>
    <?php elseif ($order): ?>
        <section class="account-card">
            <h2><?php echo htmlspecialchars($order['name'] ?? 'Order #' . $order['id']); ?></h2>
            <div class="tracking-timeline">
                <p><strong>Order Date:</strong> <?php echo htmlspecialchars($order['order_date'] ?? 'Pending'); ?></p>
                <p><strong>Shipping Date:</strong> <?php echo htmlspecialchars($order['shipping_date'] ?? 'Pending'); ?></p>
                <p><strong>Delivery Date:</strong> <?php echo htmlspecialchars($order['delivery_date'] ?? 'Pending'); ?></p>
                <p><strong>Delivery Day:</strong> <?php echo !empty($order['delivery_date']) ? date('l', strtotime($order['delivery_date'])) : 'Pending'; ?></p>
                <?php if (strtolower($order['order_status']) === 'returned' || strtolower($order['order_status']) === 'return requested'): ?>
                    <p><strong>Return Date:</strong> <?php echo htmlspecialchars($order['return_date'] ?? 'Pending'); ?></p>
                    <p><strong>Refund Date:</strong> <?php echo htmlspecialchars($order['refund_date'] ?? 'Pending'); ?></p>
                <?php endif; ?>
            </div>
        </section>
    <?php elseif ($orderId !== ''): ?>
        <section class="account-card" id="localOrderTracking" data-order-id="<?php echo htmlspecialchars($orderId); ?>">
            <p>Loading order details...</p>
        </section>
    <?php endif; ?>
    </div>
</main>
<?php include("../backend/includes/footer.php"); ?>
