<?php
session_start();
include("../backend/config/db.php");
include_once("../backend/includes/user_auth.php");
include_once("../backend/includes/csrf.php");
include_once("../backend/includes/image_paths.php");

if (!isset($_SESSION['user_id'])) {
    header("Location: auth.php");
    exit;
}

$userId = (int) $_SESSION['user_id'];
$orderId = trim($_GET['order_id'] ?? '');
$numericOrderId = ctype_digit($orderId) ? (int) $orderId : 0;
$message = '';
$messageType = '';

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

$stmt = $conn->prepare("SELECT o.*, p.name, p.image FROM orders o LEFT JOIN products p ON p.id = o.product_id WHERE (o.id = ? OR o.order_id = ? OR o.order_code = ?) AND o.user_id = ? LIMIT 1");
$stmt->bind_param("issi", $numericOrderId, $orderId, $orderId, $userId);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();
$displayOrderId = $order ? ($order['order_id'] ?: ($order['order_code'] ?: $order['id'])) : '';
$productId = (int) ($order['product_id'] ?? 0);
$existingReview = null;

if ($order) {
    $reviewStmt = $conn->prepare("SELECT * FROM reviews WHERE user_id = ? AND product_id = ? AND order_id = ? LIMIT 1");
    $reviewStmt->bind_param("iis", $userId, $productId, $displayOrderId);
    $reviewStmt->execute();
    $existingReview = $reviewStmt->get_result()->fetch_assoc();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $order && !$existingReview) {
    csrf_require();
    $status = strtolower(trim($order['order_status'] ?? ''));
    $rating = (int) ($_POST['rating'] ?? 0);
    $reviewText = trim($_POST['review_text'] ?? '');

    if ($status !== 'delivered') {
        $message = 'You can review only delivered orders.';
        $messageType = 'error';
    } elseif ($rating < 1 || $rating > 5) {
        $message = 'Please select a rating from 1 to 5.';
        $messageType = 'error';
    } else {
        $save = $conn->prepare("INSERT INTO reviews (user_id, product_id, order_id, rating, review_text, status) VALUES (?, ?, ?, ?, ?, 'pending')");
        $save->bind_param("iisis", $userId, $productId, $displayOrderId, $rating, $reviewText);
        if ($save->execute()) {
            $message = 'Review submitted successfully. It will appear after admin approval.';
            $messageType = 'success';
            $existingReview = ['rating' => $rating, 'review_text' => $reviewText, 'status' => 'pending'];
        } else {
            $message = 'Review could not be saved.';
            $messageType = 'error';
        }
    }
}

include("../backend/includes/header.php");
?>
<main class="account-simple-page review-order-page">
    <section class="account-card review-order-card">
        <?php if ($message): ?><div class="auth-alert <?php echo htmlspecialchars($messageType); ?>"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
        <?php if ($order): ?>
            <div class="review-product-summary">
                <div class="review-product-icon">
                    <?php if (!empty($order['image'])): ?>
                        <img src="<?php echo htmlspecialchars(zafiroPublicImageUrl($order['image'])); ?>" alt="<?php echo htmlspecialchars($order['name'] ?? 'Product'); ?>">
                    <?php else: ?>
                        <i class="fa-solid fa-box-open"></i>
                    <?php endif; ?>
                </div>
                <div>
                    <span>Zafiro Casa</span>
                    <h1>Review Order</h1>
                    <h2><?php echo htmlspecialchars($order['name'] ?? 'Product'); ?></h2>
                    <p>Order ID: <?php echo htmlspecialchars($displayOrderId); ?></p>
                </div>
            </div>
            <?php if (strtolower(trim($order['order_status'] ?? '')) !== 'delivered'): ?>
                <div class="review-side-panel"><p>You can review only after this order is delivered.</p></div>
            <?php elseif ($existingReview): ?>
                <div class="review-side-panel">
                    <div class="rating-stars"><?php echo str_repeat('&#9733;', (int) $existingReview['rating']) . str_repeat('&#9734;', 5 - (int) $existingReview['rating']); ?></div>
                    <p><?php echo htmlspecialchars($existingReview['review_text'] ?? ''); ?></p>
                    <p>Status: <?php echo htmlspecialchars($existingReview['status']); ?></p>
                </div>
            <?php else: ?>
                <form method="POST" class="review-form review-side-panel">
                    <?php echo csrf_field(); ?>
                    <label>Rating
                        <select name="rating" class="review-rating-select" required>
                            <option value="">Select rating</option>
                            <option value="5">&#9733; &#9733; &#9733; &#9733; &#9733;  5 Stars</option>
                            <option value="4">&#9733; &#9733; &#9733; &#9733; &#9734;  4 Stars</option>
                            <option value="3">&#9733; &#9733; &#9733; &#9734; &#9734;  3 Stars</option>
                            <option value="2">&#9733; &#9733; &#9734; &#9734; &#9734;  2 Stars</option>
                            <option value="1">&#9733; &#9734; &#9734; &#9734; &#9734;  1 Star</option>
                        </select>
                    </label>
                    <label class="review-comment-field">Order Comment
                        <textarea name="review_text" class="review-textarea" placeholder="Write any note about your order..." required></textarea>
                    </label>
                    <button type="submit" class="account-btn small review-submit-btn">Submit Review <i class="fa-solid fa-arrow-right"></i></button>
                </form>
            <?php endif; ?>
        <?php else: ?>
            <p>Order not found.</p>
        <?php endif; ?>
    </section>
</main>
<?php include("../backend/includes/footer.php"); ?>
