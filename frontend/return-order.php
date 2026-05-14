<?php
session_start();
include("../backend/config/db.php");
include_once("../backend/includes/user_auth.php");

if (!isset($_SESSION['user_id'])) {
    header("Location: auth.php");
    exit;
}

$userId = (int) $_SESSION['user_id'];
$orderId = trim($_GET['order_id'] ?? $_POST['order_id'] ?? '');
$numericOrderId = ctype_digit($orderId) ? (int) $orderId : 0;
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $reason = trim($_POST['reason'] ?? '');
    $comment = trim($_POST['comment'] ?? '');
    $conn->query("CREATE TABLE IF NOT EXISTS order_returns (id INT AUTO_INCREMENT PRIMARY KEY, order_id VARCHAR(40) NULL, user_id INT NULL, reason VARCHAR(160) NULL, comment TEXT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP)");
    $stmt = $conn->prepare("INSERT INTO order_returns (order_id, user_id, reason, comment) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("siss", $orderId, $userId, $reason, $comment);
    $stmt->execute();
    $update = $conn->prepare("UPDATE orders SET order_status = 'Return Requested', return_date = NOW() WHERE (id = ? OR order_id = ? OR order_code = ?) AND user_id = ?");
    $update->bind_param("issi", $numericOrderId, $orderId, $orderId, $userId);
    $update->execute();
    $message = 'Return request submitted.';
}

$stmt = $conn->prepare("SELECT o.*, p.name, p.image, p.price FROM orders o LEFT JOIN products p ON p.id = o.product_id WHERE (o.id = ? OR o.order_id = ? OR o.order_code = ?) AND o.user_id = ? LIMIT 1");
$stmt->bind_param("issi", $numericOrderId, $orderId, $orderId, $userId);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();

include("../backend/includes/header.php");
?>
<main class="account-simple-page">
    <section class="account-card">
        <h1>Return Order</h1>
        <?php if ($message): ?><div class="auth-alert success"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
        <?php if ($order): ?>
            <p><strong><?php echo htmlspecialchars($order['name'] ?? 'Product'); ?></strong></p>
            <p>Order ID: <?php echo htmlspecialchars($order['order_id'] ?: ($order['order_code'] ?: $order['id'])); ?></p>
            <form action="return-order.php" method="POST">
                <input type="hidden" name="order_id" value="<?php echo htmlspecialchars($order['order_id'] ?: ($order['order_code'] ?: $order['id'])); ?>">
                <select name="reason" class="language-select" required>
                    <option value="">Select return reason</option>
                    <option>Damaged product</option>
                    <option>Wrong item received</option>
                    <option>Quality issue</option>
                    <option>Changed my mind</option>
                </select>
                <textarea name="comment" class="review-textarea" placeholder="Comment"></textarea>
                <button class="account-btn small" type="submit">Submit Return Request</button>
            </form>
        <?php else: ?>
            <p>Order not found.</p>
        <?php endif; ?>
    </section>
</main>
<?php include("../backend/includes/footer.php"); ?>
