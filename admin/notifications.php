<?php
include("auth.php");
include("../backend/config/db.php");

$message = "";
$messageType = "";

$conn->query("CREATE TABLE IF NOT EXISTS admin_notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(180) NOT NULL,
    message TEXT NOT NULL,
    type VARCHAR(60) NOT NULL,
    source_key VARCHAR(180) NULL UNIQUE,
    reference_id VARCHAR(120) NULL,
    reference_type VARCHAR(60) NULL,
    is_read TINYINT(1) DEFAULT 0,
    is_deleted TINYINT(1) DEFAULT 0,
    deleted_at DATETIME NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

function notificationColumnExists($conn, $column) {
    $stmt = $conn->prepare("SELECT COUNT(*) AS total FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'admin_notifications' AND column_name = ?");
    $stmt->bind_param("s", $column);
    $stmt->execute();
    return (int) ($stmt->get_result()->fetch_assoc()["total"] ?? 0) > 0;
}

foreach ([
    "reference_id" => "VARCHAR(120) NULL",
    "reference_type" => "VARCHAR(60) NULL",
    "is_deleted" => "TINYINT(1) DEFAULT 0",
    "deleted_at" => "DATETIME NULL"
] as $column => $definition) {
    if (!notificationColumnExists($conn, $column)) {
        $conn->query("ALTER TABLE admin_notifications ADD COLUMN `$column` $definition");
    }
}

$conn->query("DELETE FROM admin_notifications WHERE COALESCE(is_deleted, 0) = 1 AND deleted_at IS NOT NULL AND deleted_at < DATE_SUB(NOW(), INTERVAL 30 DAY)");

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = $_POST["action"] ?? "";
    $id = (int) ($_POST["id"] ?? 0);

    try {
        if ($action === "mark_read" && $id > 0) {
            $stmt = $conn->prepare("UPDATE admin_notifications SET is_read = 1 WHERE id = ? AND COALESCE(is_deleted, 0) = 0");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            if ($stmt->affected_rows < 1) {
                throw new RuntimeException("Notification could not be updated.");
            }
            $message = "Notification marked as read.";
        } elseif ($action === "mark_all_read") {
            $conn->query("UPDATE admin_notifications SET is_read = 1 WHERE COALESCE(is_deleted, 0) = 0");
            $message = "All notifications marked as read.";
        } elseif ($action === "delete" && $id > 0) {
            $stmt = $conn->prepare("UPDATE admin_notifications SET is_deleted = 1, is_read = 1, deleted_at = NOW() WHERE id = ? AND COALESCE(is_deleted, 0) = 0");
            $stmt->bind_param("i", $id);
            if (!$stmt->execute() || $stmt->affected_rows < 1) {
                throw new RuntimeException("Notification could not be deleted.");
            }
            $message = "Notification moved to Trash.";
        } elseif ($action === "restore" && $id > 0) {
            $stmt = $conn->prepare("UPDATE admin_notifications SET is_deleted = 0, deleted_at = NULL WHERE id = ? AND COALESCE(is_deleted, 0) = 1");
            $stmt->bind_param("i", $id);
            if (!$stmt->execute() || $stmt->affected_rows < 1) {
                throw new RuntimeException("Notification could not be restored.");
            }
            $message = "Notification restored.";
        } elseif ($action === "permanent_delete" && $id > 0) {
            $stmt = $conn->prepare("DELETE FROM admin_notifications WHERE id = ? AND COALESCE(is_deleted, 0) = 1");
            $stmt->bind_param("i", $id);
            if (!$stmt->execute() || $stmt->affected_rows < 1) {
                throw new RuntimeException("Notification could not be permanently deleted.");
            }
            $message = "Notification permanently deleted.";
        } else {
            throw new RuntimeException("Invalid notification action.");
        }
        $messageType = "success";
    } catch (Throwable $error) {
        $message = $error->getMessage();
        $messageType = "error";
    }
}

$filter = trim($_GET["filter"] ?? "all");
$where = "WHERE COALESCE(is_deleted, 0) = 0";

if ($filter === "unread") $where .= " AND is_read = 0";
if ($filter === "read") $where .= " AND is_read = 1";
if ($filter === "orders") $where .= " AND type = 'order'";
if ($filter === "reviews") $where .= " AND type = 'review'";
if ($filter === "system") $where .= " AND type IN ('system', 'email', 'stock', 'user')";
if ($filter === "trash") $where = "WHERE COALESCE(is_deleted, 0) = 1";

$notifications = [];
$result = $conn->query("SELECT * FROM admin_notifications $where ORDER BY created_at DESC, id DESC");
while ($result && $row = $result->fetch_assoc()) {
    $notifications[] = $row;
}

$unreadCount = (int) (($conn->query("SELECT COUNT(*) AS total FROM admin_notifications WHERE is_read = 0 AND COALESCE(is_deleted, 0) = 0")->fetch_assoc())["total"] ?? 0);

function notificationIcon($type) {
    return match ($type) {
        "order" => "fa-bag-shopping",
        "review" => "fa-star",
        "stock" => "fa-boxes-stacked",
        "email" => "fa-envelope",
        "user" => "fa-user-plus",
        default => "fa-bell"
    };
}

function notificationTargetUrl($notification) {
    $type = strtolower((string) ($notification["type"] ?? ""));
    $referenceId = (string) ($notification["reference_id"] ?? "");
    $sourceKey = (string) ($notification["source_key"] ?? "");
    if ($referenceId === "" && $sourceKey !== "") {
        $referenceId = preg_replace('/^[a-z_]+/', '', $sourceKey);
    }

    if ($type === "order" && $referenceId !== "") {
        return "manage_orders.php?focus_order=" . urlencode($referenceId);
    }
    if ($type === "review" && $referenceId !== "") {
        return "reviews.php?focus_review=" . urlencode($referenceId);
    }
    if ($type === "stock" && $referenceId !== "") {
        $productId = preg_replace('/[^0-9].*$/', '', $referenceId);
        return $productId !== "" ? "edit_product.php?id=" . urlencode($productId) : "";
    }
    if ($type === "user" && $referenceId !== "") {
        return "manage_users.php?focus_user=" . urlencode($referenceId);
    }
    return "";
}

include("includes/admin_header.php");
include("includes/admin_sidebar.php");
?>
<main class="admin-main">
    <header class="admin-topbar admin-dark-topbar">
        <div>
            <span>Zafiro Casa Luxury Living</span>
            <h1>Notifications</h1>
            <p>View all system alerts and admin notifications.</p>
        </div>
        <form method="POST" action="notifications.php">
            <input type="hidden" name="action" value="mark_all_read">
            <button type="submit" class="admin-btn">Mark all as read</button>
        </form>
    </header>

    <?php if ($message): ?>
        <div class="admin-popup <?php echo $messageType === 'success' ? 'success' : 'error'; ?>"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>

    <section class="admin-form-card manage-filter-card">
        <div class="admin-notification-filters">
            <a class="<?php echo $filter === 'all' ? 'active' : ''; ?>" href="notifications.php">All</a>
            <a class="<?php echo $filter === 'unread' ? 'active' : ''; ?>" href="notifications.php?filter=unread">Unread</a>
            <a class="<?php echo $filter === 'read' ? 'active' : ''; ?>" href="notifications.php?filter=read">Read</a>
            <a class="<?php echo $filter === 'orders' ? 'active' : ''; ?>" href="notifications.php?filter=orders">Orders</a>
            <a class="<?php echo $filter === 'reviews' ? 'active' : ''; ?>" href="notifications.php?filter=reviews">Reviews</a>
            <a class="<?php echo $filter === 'system' ? 'active' : ''; ?>" href="notifications.php?filter=system">System Alerts</a>
            <a class="<?php echo $filter === 'trash' ? 'active' : ''; ?>" href="notifications.php?filter=trash">Trash / Recycle Bin</a>
            <span><?php echo $unreadCount; ?> unread</span>
        </div>
    </section>

    <section class="admin-notifications-page-list">
        <?php if ($notifications): ?>
            <?php foreach ($notifications as $notification): ?>
                <?php $targetUrl = notificationTargetUrl($notification); ?>
                <article class="admin-notification-page-card <?php echo (int) $notification["is_read"] === 0 ? 'unread' : ''; ?>"
                    data-admin-notification-card
                    data-notification-id="<?php echo (int) $notification["id"]; ?>"
                    data-target-url="<?php echo htmlspecialchars($targetUrl); ?>"
                    data-title="<?php echo htmlspecialchars($notification["title"]); ?>"
                    data-message="<?php echo htmlspecialchars($notification["message"]); ?>"
                    data-type="<?php echo htmlspecialchars($notification["type"]); ?>"
                    data-date="<?php echo htmlspecialchars($notification["created_at"]); ?>"
                    data-read="<?php echo (int) $notification["is_read"] === 0 ? 'Unread' : 'Read'; ?>">
                    <div class="admin-notification-page-icon">
                        <i class="fa-solid <?php echo htmlspecialchars(notificationIcon($notification["type"])); ?>"></i>
                    </div>
                    <div class="admin-notification-page-content">
                        <div>
                            <h2><?php echo htmlspecialchars($notification["title"]); ?></h2>
                            <span class="status-pill"><?php echo $filter === "trash" ? "Trash" : ((int) $notification["is_read"] === 0 ? "Unread" : "Read"); ?></span>
                        </div>
                        <p><?php echo htmlspecialchars($notification["message"]); ?></p>
                        <small><?php echo $filter === "trash" ? "Deleted: " . htmlspecialchars($notification["deleted_at"] ?: "N/A") : htmlspecialchars($notification["created_at"]); ?></small>
                    </div>
                    <div class="admin-notification-page-actions">
                        <?php if ($filter === "trash"): ?>
                            <form method="POST" action="notifications.php?filter=trash">
                                <input type="hidden" name="action" value="restore">
                                <input type="hidden" name="id" value="<?php echo (int) $notification["id"]; ?>">
                                <button type="submit" class="admin-action-link edit">Restore</button>
                            </form>
                            <form method="POST" action="notifications.php?filter=trash" class="permanent-delete-notification-form">
                                <input type="hidden" name="action" value="permanent_delete">
                                <input type="hidden" name="id" value="<?php echo (int) $notification["id"]; ?>">
                                <button type="submit" class="admin-action-link danger">Permanent Delete</button>
                            </form>
                        <?php elseif ((int) $notification["is_read"] === 0): ?>
                            <form method="POST" action="notifications.php">
                                <input type="hidden" name="action" value="mark_read">
                                <input type="hidden" name="id" value="<?php echo (int) $notification["id"]; ?>">
                                <button type="submit" class="admin-action-link edit">Mark as read</button>
                            </form>
                            <form method="POST" action="notifications.php">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?php echo (int) $notification["id"]; ?>">
                                <button type="submit" class="admin-action-link danger">Delete</button>
                            </form>
                        <?php else: ?>
                            <form method="POST" action="notifications.php">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?php echo (int) $notification["id"]; ?>">
                                <button type="submit" class="admin-action-link danger">Delete</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="empty-admin-state">
                <h2>No notifications found.</h2>
                <p>System alerts and admin notifications will appear here.</p>
            </div>
        <?php endif; ?>
    </section>
    <div class="admin-modal" id="adminNotificationDetailModal">
        <div class="admin-modal-card">
            <button type="button" class="admin-search-close" id="closeAdminNotificationDetailModal">&times;</button>
            <div id="adminNotificationDetailContent"></div>
        </div>
    </div>
<?php include("includes/admin_footer.php"); ?>
