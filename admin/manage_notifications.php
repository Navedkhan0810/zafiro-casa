<?php
include("auth.php");
include("../backend/config/db.php");
include_once("../backend/includes/admin_reports.php");

$message = "";
$messageType = "";

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
$conn->query("ALTER TABLE notifications ADD COLUMN IF NOT EXISTS target_type VARCHAR(50) NULL");
$conn->query("ALTER TABLE notifications ADD COLUMN IF NOT EXISTS target_id VARCHAR(100) NULL");
$conn->query("ALTER TABLE notifications ADD COLUMN IF NOT EXISTS link_url VARCHAR(255) NULL");

$productOptions = $conn->query("SELECT id, name FROM products ORDER BY name ASC LIMIT 200");
$orderOptions = $conn->query("SELECT COALESCE(NULLIF(order_id, ''), NULLIF(order_code, ''), id) AS order_ref FROM orders ORDER BY order_date DESC, id DESC LIMIT 200");

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = $_POST["action"] ?? "create";

    if ($action === "delete") {
        $id = (int) ($_POST["notification_id"] ?? 0);
        $stmt = $conn->prepare("DELETE FROM notifications WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        adminReportLog($conn, "delete_notification", "Deleted notification #" . $id . ".", "notification", $id);
        header("Location: manage_notifications.php");
        exit;
    }

    if ($action === "mark_read") {
        $id = (int) ($_POST["notification_id"] ?? 0);
        $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        header("Content-Type: application/json");
        echo json_encode(["success" => true]);
        exit;
    }

    $title = trim($_POST["title"] ?? "");
    $body = trim($_POST["message"] ?? "");
    $type = trim($_POST["type"] ?? "general");
    $allowedTypes = ["general", "offer", "product", "order", "system"];
    if (!in_array($type, $allowedTypes, true)) $type = "general";
    $targetType = "";
    $targetId = "";
    $linkUrl = trim($_POST["link_url"] ?? "");
    if ($linkUrl !== "" && !preg_match("/^(?:[A-Za-z0-9_\-\.\/]+\.php(?:\?[A-Za-z0-9_\-=&%.]+)?|#[A-Za-z0-9_-]+)$/", $linkUrl)) {
        $linkUrl = "";
    }
    if (in_array($type, ["offer", "product"], true)) {
        $targetType = "product";
        $targetId = preg_match("/^\d+$/", (string) ($_POST["product_id"] ?? "")) ? trim($_POST["product_id"]) : "";
        if ($linkUrl === "" && $targetId !== "") $linkUrl = "product.php?id=" . (int) $targetId;
    } elseif ($type === "order") {
        $targetType = "order";
        $targetId = preg_match("/^[A-Za-z0-9_-]{1,60}$/", (string) ($_POST["order_id"] ?? "")) ? trim($_POST["order_id"]) : "";
        if ($linkUrl === "" && $targetId !== "") $linkUrl = "order-tracking.php?order_id=" . rawurlencode($targetId);
    } else {
        $targetType = "general";
    }

    if ($title === "" || $body === "") {
        $message = "Title and message are required.";
        $messageType = "error";
    } else {
        $stmt = $conn->prepare("INSERT INTO notifications (user_id, title, message, type, target_type, target_id, link_url) VALUES (NULL, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssss", $title, $body, $type, $targetType, $targetId, $linkUrl);
        $message = $stmt->execute() ? "Notification sent to all users." : "Notification could not be sent.";
        $messageType = $stmt->affected_rows >= 0 ? "success" : "error";
        if ($stmt->affected_rows > 0) adminReportLog($conn, "send_notification", "Sent notification: " . $title, "notification", $stmt->insert_id, $title);
    }
}

$list = $conn->query("SELECT * FROM notifications ORDER BY created_at DESC, id DESC");

include("includes/admin_header.php");
include("includes/admin_sidebar.php");
?>
<main class="admin-main">
    <header class="admin-topbar admin-dark-topbar"><div><span>Zafiro Casa</span><h1>Manage Notifications</h1><p>Create offer, sale, product, order and return alerts.</p></div></header>
    <?php if ($message): ?><div class="admin-popup <?php echo htmlspecialchars($messageType); ?>"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
    <form method="POST" class="admin-form-card">
        <input type="hidden" name="action" value="create">
        <div class="admin-form-grid">
            <label>Title<input type="text" name="title" required></label>
            <label>Notification Type<select name="type" id="notificationTypeSelect"><option value="offer">Offer</option><option value="product">Product</option><option value="order">Order</option><option value="general">General</option></select></label>
            <label id="notificationProductTarget">Target Product<select name="product_id" id="notificationProductSelect"><option value="">Select product</option><?php while ($product = $productOptions->fetch_assoc()): ?><option value="<?php echo (int) $product["id"]; ?>"><?php echo htmlspecialchars($product["name"]); ?></option><?php endwhile; ?></select></label>
            <label id="notificationOrderTarget">Target Order<select name="order_id" id="notificationOrderSelect"><option value="">Select order</option><?php while ($order = $orderOptions->fetch_assoc()): ?><option value="<?php echo htmlspecialchars($order["order_ref"]); ?>"><?php echo htmlspecialchars($order["order_ref"]); ?></option><?php endwhile; ?></select></label>
            <label>Custom Link<input type="text" name="link_url" id="notificationLinkInput" placeholder="Auto generated from selected target"></label>
            <label class="admin-form-wide">Message<textarea name="message" required></textarea></label>
        </div>
        <button type="submit" class="admin-btn admin-submit-btn">Send to All Users</button>
    </form>
    <section class="admin-table-card">
        <table class="admin-table admin-clickable-table"><thead><tr><th>Title</th><th>Type</th><th>Date</th><th>Read</th><th>Status</th><th>Action</th></tr></thead><tbody>
        <?php while ($row = $list->fetch_assoc()): ?>
            <tr class="admin-notification-row" data-id="<?php echo (int) $row["id"]; ?>" data-title="<?php echo htmlspecialchars($row["title"]); ?>" data-message="<?php echo htmlspecialchars($row["message"]); ?>" data-type="<?php echo htmlspecialchars($row["type"]); ?>" data-target="<?php echo empty($row["user_id"]) ? "All Users" : "User #" . (int) $row["user_id"]; ?>" data-date="<?php echo htmlspecialchars($row["created_at"]); ?>" data-read="<?php echo empty($row["is_read"]) ? "Unread" : "Read"; ?>" data-status="<?php echo htmlspecialchars($row["status"] ?? "active"); ?>">
                <td><?php echo htmlspecialchars($row["title"]); ?></td>
                <td><?php echo htmlspecialchars($row["type"]); ?></td>
                <td><?php echo htmlspecialchars($row["created_at"]); ?></td>
                <td><?php echo empty($row["is_read"]) ? "Unread" : "Read"; ?></td>
                <td><?php echo htmlspecialchars($row["status"] ?? "active"); ?></td>
                <td>
                    <form method="POST" class="inline-admin-form delete-notification-form">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="notification_id" value="<?php echo (int) $row["id"]; ?>">
                        <button type="submit" class="admin-action-link danger">Delete</button>
                    </form>
                </td>
            </tr>
        <?php endwhile; ?>
        </tbody></table>
    </section>
    <div class="admin-modal" id="manageNotificationModal">
        <div class="admin-modal-card">
            <button type="button" class="admin-modal-close" id="closeManageNotificationModal">Close</button>
            <h2 id="manageNotificationTitle">Notification</h2>
            <div class="admin-notification-detail-data" id="manageNotificationDetails"></div>
        </div>
    </div>
<?php include("includes/admin_footer.php"); ?>


