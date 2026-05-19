<?php
session_start();
include("../backend/config/db.php");
include_once("../backend/includes/user_auth.php");
include_once("../backend/includes/csrf.php");

if (!isset($_SESSION['user_id'])) {
    if (isset($_GET['ajax'])) {
        header('Content-Type: application/json');
        echo json_encode(['count' => 0, 'latest' => null]);
        exit;
    }
    header("Location: auth.php");
    exit;
}

$userId = (int) $_SESSION['user_id'];
$conn->query("CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    title VARCHAR(180) NOT NULL,
    message TEXT NOT NULL,
    type VARCHAR(40) DEFAULT 'system',
    is_read TINYINT(1) DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$conn->query("ALTER TABLE notifications ADD COLUMN IF NOT EXISTS status VARCHAR(20) DEFAULT 'active'");
$conn->query("ALTER TABLE notifications ADD COLUMN IF NOT EXISTS is_visible TINYINT(1) DEFAULT 1");
$conn->query("ALTER TABLE notifications ADD COLUMN IF NOT EXISTS product_id INT NULL");
$conn->query("ALTER TABLE notifications ADD COLUMN IF NOT EXISTS order_id VARCHAR(60) NULL");
$conn->query("ALTER TABLE notifications ADD COLUMN IF NOT EXISTS link_url VARCHAR(255) NULL");
$conn->query("ALTER TABLE notifications ADD COLUMN IF NOT EXISTS target_type VARCHAR(50) NULL");
$conn->query("ALTER TABLE notifications ADD COLUMN IF NOT EXISTS target_id VARCHAR(100) NULL");
$conn->query("CREATE TABLE IF NOT EXISTS notification_reads (
    id INT AUTO_INCREMENT PRIMARY KEY,
    notification_id INT NOT NULL,
    user_id INT NOT NULL,
    read_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_notification_user (notification_id, user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

function userNotificationWhere() {
    return "WHERE (n.user_id IS NULL OR n.user_id = ?) AND LOWER(COALESCE(n.status, 'active')) = 'active' AND COALESCE(n.is_visible, 1) = 1";
}

function notificationTypeMeta($type) {
    $type = strtolower(trim((string) $type));
    if (strpos($type, 'offer') !== false) return ['label' => 'OFFER', 'icon' => 'fa-tag', 'class' => 'offer'];
    if (strpos($type, 'product') !== false) return ['label' => 'PRODUCT', 'icon' => 'fa-couch', 'class' => 'product'];
    if (strpos($type, 'order') !== false) return ['label' => 'ORDER', 'icon' => 'fa-truck-fast', 'class' => 'order'];
    return ['label' => 'GENERAL', 'icon' => 'fa-bell', 'class' => 'general'];
}

function notificationTargetUrl($row) {
    if (!empty($row['link_url']) && !preg_match('/^(https?:)?\/\//i', $row['link_url'])) return $row['link_url'];
    $targetType = strtolower((string) ($row['target_type'] ?? ''));
    if (($targetType === 'product' || $targetType === 'offer') && !empty($row['target_id'])) return 'product.php?id=' . (int) $row['target_id'];
    if ($targetType === 'order' && !empty($row['target_id'])) return 'order-tracking.php?order_id=' . urlencode($row['target_id']);
    if (!empty($row['product_id'])) return 'product.php?id=' . (int) $row['product_id'];
    if (!empty($row['order_id'])) return 'order-tracking.php?order_id=' . urlencode($row['order_id']);
    return '';
}

if (isset($_GET['ajax'])) {
    header('Content-Type: application/json');
    $countStmt = $conn->prepare("SELECT COUNT(*) AS total FROM notifications n LEFT JOIN notification_reads r ON r.notification_id = n.id AND r.user_id = ? " . userNotificationWhere() . " AND r.id IS NULL");
    $countStmt->bind_param("ii", $userId, $userId);
    $countStmt->execute();
    $count = (int) ($countStmt->get_result()->fetch_assoc()['total'] ?? 0);

    $latestStmt = $conn->prepare("SELECT n.id, n.title, n.message, n.type, n.created_at, IF(r.id IS NULL, 0, 1) AS is_read FROM notifications n LEFT JOIN notification_reads r ON r.notification_id = n.id AND r.user_id = ? " . userNotificationWhere() . " ORDER BY n.created_at DESC, n.id DESC LIMIT 8");
    $latestStmt->bind_param("ii", $userId, $userId);
    $latestStmt->execute();
    $items = [];
    $result = $latestStmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $items[] = $row;
    }
    echo json_encode(['count' => $count, 'items' => $items, 'latest' => $items[0] ?? null]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'mark_all_read') {
    csrf_require();
    $markStmt = $conn->prepare("INSERT IGNORE INTO notification_reads (notification_id, user_id) SELECT n.id, ? FROM notifications n " . userNotificationWhere());
    $markStmt->bind_param("ii", $userId, $userId);
    $markStmt->execute();
    header("Location: notifications.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'mark_one_read') {
    csrf_require();
    $notificationId = (int) ($_POST['notification_id'] ?? 0);
    if ($notificationId > 0) {
        $markStmt = $conn->prepare("INSERT IGNORE INTO notification_reads (notification_id, user_id) SELECT n.id, ? FROM notifications n " . userNotificationWhere() . " AND n.id = ?");
        $markStmt->bind_param("iii", $userId, $userId, $notificationId);
        $markStmt->execute();
    }
    $redirectTo = trim($_POST['redirect_to'] ?? '');
    if ($redirectTo !== '' && !preg_match('/^(https?:)?\/\//i', $redirectTo)) {
        header("Location: " . $redirectTo);
        exit;
    }
    header("Location: notifications.php");
    exit;
}

$stmt = $conn->prepare("SELECT n.*, IF(r.id IS NULL, 0, 1) AS read_by_user FROM notifications n LEFT JOIN notification_reads r ON r.notification_id = n.id AND r.user_id = ? " . userNotificationWhere() . " ORDER BY n.created_at DESC, n.id DESC");
$stmt->bind_param("ii", $userId, $userId);
$stmt->execute();
$notifications = $stmt->get_result();

include("../backend/includes/header.php");
?>
<main class="account-simple-page notifications-page-wrap">
    <?php
    $pageBackText = "Back to Profile";
    $pageBackHref = "profile.php";
    include("../backend/includes/page_back_button.php");
    ?>
    <section class="account-card notifications-page-card">
        <div class="notifications-page-head">
            <div>
                <span>Zafiro Casa</span>
                <h1>Notifications</h1>
            </div>
            <form method="POST" action="notifications.php">
                <input type="hidden" name="action" value="mark_all_read">
                <button type="submit" class="account-btn small notification-read-all"><i class="fa-regular fa-circle-check"></i> Mark all as read</button>
            </form>
        </div>
        <div class="notification-list">
            <?php if ($notifications && $notifications->num_rows > 0): ?>
                <?php while ($row = $notifications->fetch_assoc()): ?>
                    <?php $meta = notificationTypeMeta($row['type'] ?? ''); $targetUrl = notificationTargetUrl($row); ?>
                    <form method="POST" action="notifications.php" class="notification-card-form">
                    <input type="hidden" name="action" value="mark_one_read">
                    <input type="hidden" name="notification_id" value="<?php echo (int) $row['id']; ?>">
                    <input type="hidden" name="redirect_to" value="<?php echo htmlspecialchars($targetUrl); ?>">
                    <button type="submit" class="notification-card <?php echo empty($row['read_by_user']) ? 'unread' : ''; ?>">
                        <span class="notification-type-icon <?php echo htmlspecialchars($meta['class']); ?>"><i class="fa-solid <?php echo htmlspecialchars($meta['icon']); ?>"></i></span>
                        <span class="notification-card-body">
                            <span class="notification-type-badge"><?php echo htmlspecialchars($meta['label']); ?></span>
                            <h3><?php echo htmlspecialchars($row['title']); ?></h3>
                            <p><?php echo htmlspecialchars($row['message']); ?></p>
                            <small><i class="fa-regular fa-calendar-days"></i> <?php echo htmlspecialchars($row['created_at']); ?></small>
                        </span>
                        <span class="notification-card-side">
                            <span class="notification-read-badge"><?php echo empty($row['read_by_user']) ? 'Unread' : 'Read'; ?></span>
                            <i class="fa-solid fa-chevron-right"></i>
                        </span>
                    </button>
                    </form>
                <?php endwhile; ?>
            <?php else: ?>
                <p>No notifications yet.</p>
            <?php endif; ?>
        </div>
    </section>
</main>
<?php include("../backend/includes/footer.php"); ?>
