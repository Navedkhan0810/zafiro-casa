<?php
include("auth.php");
include("../backend/config/db.php");

function tableExists($conn, $tableName) {
    $stmt = $conn->prepare("SELECT COUNT(*) AS total FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?");
    $stmt->bind_param("s", $tableName);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    return (int) ($row["total"] ?? 0) > 0;
}

function columnExists($conn, $tableName, $columnName) {
    if (!tableExists($conn, $tableName)) return false;
    $stmt = $conn->prepare("SELECT COUNT(*) AS total FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?");
    $stmt->bind_param("ss", $tableName, $columnName);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    return (int) ($row["total"] ?? 0) > 0;
}

function safeTableName($tableName) {
    return preg_match('/^[a-zA-Z0-9_]+$/', $tableName) === 1;
}

function getCount($conn, $tableName, $where = "") {
    if (!safeTableName($tableName) || !tableExists($conn, $tableName)) return 0;
    $sql = "SELECT COUNT(*) AS total FROM `$tableName`" . ($where ? " WHERE $where" : "");
    $result = $conn->query($sql);
    $row = $result ? $result->fetch_assoc() : null;
    return (int) ($row["total"] ?? 0);
}

function getDashboardSetting($conn, $key, $default = "0") {
    if (!tableExists($conn, "admin_settings")) return $default;
    $stmt = $conn->prepare("SELECT setting_value FROM admin_settings WHERE setting_key = ? LIMIT 1");
    $stmt->bind_param("s", $key);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    return $row["setting_value"] ?? $default;
}

function ensureNotificationsSchema($conn) {
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

    foreach ([
        "reference_id" => "VARCHAR(120) NULL",
        "reference_type" => "VARCHAR(60) NULL",
        "is_deleted" => "TINYINT(1) DEFAULT 0",
        "deleted_at" => "DATETIME NULL"
    ] as $column => $definition) {
        if (!columnExists($conn, "admin_notifications", $column)) {
            $conn->query("ALTER TABLE admin_notifications ADD COLUMN `$column` $definition");
        }
    }
    $conn->query("DELETE FROM admin_notifications WHERE COALESCE(is_deleted, 0) = 1 AND deleted_at IS NOT NULL AND deleted_at < DATE_SUB(NOW(), INTERVAL 30 DAY)");
}

function addAdminNotification($conn, $title, $message, $type, $sourceKey) {
    $referenceId = preg_replace('/^[a-z_]+/', '', $sourceKey);
    $stmt = $conn->prepare("INSERT IGNORE INTO admin_notifications (title, message, type, source_key, reference_id, reference_type) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssss", $title, $message, $type, $sourceKey, $referenceId, $type);
    $stmt->execute();
}

function addLowStockNotification($conn, $productId, $productName, $stockQty) {
    $sourceKey = "stock_" . (int) $productId;
    $check = $conn->prepare("SELECT id FROM admin_notifications WHERE type = 'stock' AND source_key = ? AND is_read = 0 AND COALESCE(is_deleted, 0) = 0 LIMIT 1");
    $check->bind_param("s", $sourceKey);
    $check->execute();
    if ($check->get_result()->fetch_assoc()) return;
    addAdminNotification($conn, "Low stock alert", ($productName ?: "Product") . " has only " . (int) $stockQty . " item(s) left.", "stock", $sourceKey);
}

function generateDashboardNotifications($conn) {
    ensureNotificationsSchema($conn);

    if (getDashboardSetting($conn, "order_notifications", "1") === "1" && tableExists($conn, "orders")) {
        $orderKey = columnExists($conn, "orders", "order_code") ? "COALESCE(NULLIF(order_code, ''), NULLIF(order_id, ''), id)" : "COALESCE(NULLIF(order_id, ''), id)";
        $result = $conn->query("SELECT id, $orderKey AS order_key, customer_name, total_amount, total, order_date FROM orders ORDER BY COALESCE(order_date, created_at) DESC, id DESC LIMIT 10");
        while ($result && $row = $result->fetch_assoc()) {
            $orderId = $row["order_key"] ?: $row["id"];
            $amount = (float) ($row["total_amount"] ?: ($row["total"] ?? 0));
            addAdminNotification($conn, "New order placed", "Order " . $orderId . " by " . ($row["customer_name"] ?: "Guest Customer") . " for ₹" . number_format($amount, 2), "order", "order_" . $orderId);
        }
    }

    if (getDashboardSetting($conn, "review_notifications", "1") === "1" && tableExists($conn, "product_reviews")) {
        $result = $conn->query("SELECT id, customer_name, product_name, rating FROM product_reviews ORDER BY created_at DESC, id DESC LIMIT 10");
        while ($result && $row = $result->fetch_assoc()) {
            addAdminNotification($conn, "New customer review", ($row["customer_name"] ?: "Customer") . " rated " . ($row["product_name"] ?: "a product") . " " . (int) $row["rating"] . " star(s).", "review", "review_" . $row["id"]);
        }
    }

    if (getDashboardSetting($conn, "user_registration_alerts", "1") === "1" && tableExists($conn, "users")) {
        $result = $conn->query("SELECT id, full_name, username FROM users ORDER BY created_at DESC, id DESC LIMIT 10");
        while ($result && $row = $result->fetch_assoc()) {
            addAdminNotification($conn, "New user registered", ($row["full_name"] ?: $row["username"] ?: "A customer") . " created an account.", "user", "user_" . $row["id"]);
        }
    }

    if (getDashboardSetting($conn, "low_stock_alerts", "1") === "1" && tableExists($conn, "products") && columnExists($conn, "products", "stock_quantity")) {
        $nameColumn = columnExists($conn, "products", "product_name") ? "product_name" : "name";
        $result = $conn->query("SELECT id, $nameColumn AS product_name, stock_quantity FROM products WHERE stock_quantity <= 5 ORDER BY stock_quantity ASC, id DESC LIMIT 10");
        while ($result && $row = $result->fetch_assoc()) {
            addLowStockNotification($conn, (int) $row["id"], $row["product_name"], (int) $row["stock_quantity"]);
        }
    }

    if (getDashboardSetting($conn, "email_notifications", "0") === "1") {
        addAdminNotification($conn, "Email notifications enabled", "Admin email notifications are currently enabled for Zafiro Casa.", "email", "email_notifications_enabled");
    }
}

function ensureUserStatusColumns($conn) {
    if (!tableExists($conn, "users")) return;
    if (!columnExists($conn, "users", "status")) {
        $conn->query("ALTER TABLE users ADD COLUMN status ENUM('active','deleted') DEFAULT 'active'");
    }
    if (!columnExists($conn, "users", "deleted_at")) {
        $conn->query("ALTER TABLE users ADD COLUMN deleted_at DATETIME NULL");
    }
}

ensureUserStatusColumns($conn);
generateDashboardNotifications($conn);

$totalProducts = getCount($conn, "products");
$hasOrderCode = columnExists($conn, "orders", "order_code");
$totalOrders = 0;
$pendingOrders = 0;
$todayOrders = 0;
$activeUsers = tableExists($conn, "users") ? getCount($conn, "users", "(status = 'active' OR deleted_at IS NULL)") : 0;
$deletedAccounts = tableExists($conn, "users") ? getCount($conn, "users", "(status = 'deleted' OR deleted_at IS NOT NULL)") : 0;
$totalCategories = 0;
$totalSales = 0;
$recentOrders = [];
$activityCharts = [
    "daily" => ["title" => "Daily Store Activity", "labels" => [], "orders" => [], "sales" => []],
    "weekly" => ["title" => "Weekly Store Activity", "labels" => [], "orders" => [], "sales" => []],
    "monthly" => ["title" => "Monthly Store Activity", "labels" => [], "orders" => [], "sales" => []],
    "yearly" => ["title" => "Yearly Store Activity", "labels" => [], "orders" => [], "sales" => []],
];
$dailyOrders = [];
$dailySales = [];
$weeklyOrders = [];
$weeklySales = [];
$monthlyOrders = [];
$monthlySales = [];
$yearlyOrders = [];
$yearlySales = [];

$dailyHours = range(0, 23, 2);
foreach ($dailyHours as $hour) {
    $activityCharts["daily"]["labels"][] = date("g A", mktime($hour, 0));
    $dailyOrders[$hour] = 0;
    $dailySales[$hour] = 0;
}

for ($i = 6; $i >= 0; $i--) {
    $day = date("Y-m-d", strtotime("-$i days"));
    $activityCharts["weekly"]["labels"][] = date("d M", strtotime($day));
    $weeklyOrders[$day] = 0;
    $weeklySales[$day] = 0;
}

$daysInMonth = (int) date("t");
for ($i = 1; $i <= $daysInMonth; $i++) {
    $day = date("Y-m-") . str_pad((string) $i, 2, "0", STR_PAD_LEFT);
    $activityCharts["monthly"]["labels"][] = date("d M", strtotime($day));
    $monthlyOrders[$day] = 0;
    $monthlySales[$day] = 0;
}

for ($i = 1; $i <= 12; $i++) {
    $monthKey = date("Y-") . str_pad((string) $i, 2, "0", STR_PAD_LEFT);
    $activityCharts["yearly"]["labels"][] = date("M", mktime(0, 0, 0, $i, 1));
    $yearlyOrders[$monthKey] = 0;
    $yearlySales[$monthKey] = 0;
}

if (tableExists($conn, "categories")) {
    $totalCategories = getCount($conn, "categories");
} elseif (columnExists($conn, "products", "category")) {
    $result = $conn->query("SELECT COUNT(DISTINCT category) AS total FROM products WHERE category IS NOT NULL AND category <> ''");
    $row = $result ? $result->fetch_assoc() : null;
    $totalCategories = (int) ($row["total"] ?? 0);
}

if (tableExists($conn, "orders")) {
    $orderCountSql = $hasOrderCode ? "SELECT COUNT(DISTINCT order_code) AS total FROM orders WHERE order_code IS NOT NULL AND order_code <> ''" : "SELECT COUNT(*) AS total FROM orders";
    $result = $conn->query($orderCountSql);
    $row = $result ? $result->fetch_assoc() : null;
    $totalOrders = (int) ($row["total"] ?? 0);

    if (columnExists($conn, "orders", "order_status")) {
        $pendingSql = $hasOrderCode ? "SELECT COUNT(DISTINCT order_code) AS total FROM orders WHERE LOWER(order_status) = 'pending'" : "SELECT COUNT(*) AS total FROM orders WHERE LOWER(order_status) = 'pending'";
        $result = $conn->query($pendingSql);
        $row = $result ? $result->fetch_assoc() : null;
        $pendingOrders = (int) ($row["total"] ?? 0);
    }

    if (columnExists($conn, "orders", "order_date")) {
        $todaySql = $hasOrderCode ? "SELECT COUNT(DISTINCT order_code) AS total FROM orders WHERE DATE(order_date) = CURDATE()" : "SELECT COUNT(*) AS total FROM orders WHERE DATE(order_date) = CURDATE()";
        $result = $conn->query($todaySql);
        $row = $result ? $result->fetch_assoc() : null;
        $todayOrders = (int) ($row["total"] ?? 0);

        $weeklySql = $hasOrderCode
            ? "SELECT DATE(order_date) AS period_key, COUNT(DISTINCT order_code) AS orders_total, COALESCE(SUM(total), 0) AS sales_total FROM orders WHERE order_date >= DATE_SUB(CURDATE(), INTERVAL 6 DAY) GROUP BY DATE(order_date)"
            : "SELECT DATE(order_date) AS period_key, COUNT(*) AS orders_total, COALESCE(SUM(total), 0) AS sales_total FROM orders WHERE order_date >= DATE_SUB(CURDATE(), INTERVAL 6 DAY) GROUP BY DATE(order_date)";
        $weeklyResult = $conn->query($weeklySql);
        while ($weeklyResult && $weeklyRow = $weeklyResult->fetch_assoc()) {
            if (isset($weeklyOrders[$weeklyRow["period_key"]])) {
                $weeklyOrders[$weeklyRow["period_key"]] = (int) $weeklyRow["orders_total"];
                $weeklySales[$weeklyRow["period_key"]] = (float) $weeklyRow["sales_total"];
            }
        }

        $dailySql = $hasOrderCode
            ? "SELECT FLOOR(HOUR(order_date) / 2) * 2 AS period_key, COUNT(DISTINCT order_code) AS orders_total, COALESCE(SUM(total), 0) AS sales_total FROM orders WHERE DATE(order_date) = CURDATE() GROUP BY FLOOR(HOUR(order_date) / 2)"
            : "SELECT FLOOR(HOUR(order_date) / 2) * 2 AS period_key, COUNT(*) AS orders_total, COALESCE(SUM(total), 0) AS sales_total FROM orders WHERE DATE(order_date) = CURDATE() GROUP BY FLOOR(HOUR(order_date) / 2)";
        $dailyResult = $conn->query($dailySql);
        while ($dailyResult && $dailyRow = $dailyResult->fetch_assoc()) {
            $hourKey = (int) $dailyRow["period_key"];
            if (isset($dailyOrders[$hourKey])) {
                $dailyOrders[$hourKey] = (int) $dailyRow["orders_total"];
                $dailySales[$hourKey] = (float) $dailyRow["sales_total"];
            }
        }

        $monthlySql = $hasOrderCode
            ? "SELECT DATE(order_date) AS period_key, COUNT(DISTINCT order_code) AS orders_total, COALESCE(SUM(total), 0) AS sales_total FROM orders WHERE YEAR(order_date) = YEAR(CURDATE()) AND MONTH(order_date) = MONTH(CURDATE()) GROUP BY DATE(order_date)"
            : "SELECT DATE(order_date) AS period_key, COUNT(*) AS orders_total, COALESCE(SUM(total), 0) AS sales_total FROM orders WHERE YEAR(order_date) = YEAR(CURDATE()) AND MONTH(order_date) = MONTH(CURDATE()) GROUP BY DATE(order_date)";
        $monthlyResult = $conn->query($monthlySql);
        while ($monthlyResult && $monthlyRow = $monthlyResult->fetch_assoc()) {
            if (isset($monthlyOrders[$monthlyRow["period_key"]])) {
                $monthlyOrders[$monthlyRow["period_key"]] = (int) $monthlyRow["orders_total"];
                $monthlySales[$monthlyRow["period_key"]] = (float) $monthlyRow["sales_total"];
            }
        }

        $yearlySql = $hasOrderCode
            ? "SELECT DATE_FORMAT(order_date, '%Y-%m') AS period_key, COUNT(DISTINCT order_code) AS orders_total, COALESCE(SUM(total), 0) AS sales_total FROM orders WHERE YEAR(order_date) = YEAR(CURDATE()) GROUP BY DATE_FORMAT(order_date, '%Y-%m')"
            : "SELECT DATE_FORMAT(order_date, '%Y-%m') AS period_key, COUNT(*) AS orders_total, COALESCE(SUM(total), 0) AS sales_total FROM orders WHERE YEAR(order_date) = YEAR(CURDATE()) GROUP BY DATE_FORMAT(order_date, '%Y-%m')";
        $yearlyResult = $conn->query($yearlySql);
        while ($yearlyResult && $yearlyRow = $yearlyResult->fetch_assoc()) {
            if (isset($yearlyOrders[$yearlyRow["period_key"]])) {
                $yearlyOrders[$yearlyRow["period_key"]] = (int) $yearlyRow["orders_total"];
                $yearlySales[$yearlyRow["period_key"]] = (float) $yearlyRow["sales_total"];
            }
        }
    }
}

$activityCharts["daily"]["orders"] = array_values($dailyOrders);
$activityCharts["daily"]["sales"] = array_values($dailySales);
$activityCharts["weekly"]["orders"] = array_values($weeklyOrders);
$activityCharts["weekly"]["sales"] = array_values($weeklySales);
$activityCharts["monthly"]["orders"] = array_values($monthlyOrders);
$activityCharts["monthly"]["sales"] = array_values($monthlySales);
$activityCharts["yearly"]["orders"] = array_values($yearlyOrders);
$activityCharts["yearly"]["sales"] = array_values($yearlySales);

if (columnExists($conn, "orders", "total")) {
    $result = $conn->query("SELECT COALESCE(SUM(total), 0) AS total_sales FROM orders");
    $row = $result ? $result->fetch_assoc() : null;
    $totalSales = (float) ($row["total_sales"] ?? 0);
}

if (tableExists($conn, "orders")) {
    if ($hasOrderCode) {
        $result = $conn->query("SELECT order_code, MAX(customer_name) AS customer_name, GROUP_CONCAT(product_name SEPARATOR ', ') AS product_names, MAX(payment_method) AS payment_method, MAX(order_status) AS order_status, MAX(payment_status) AS payment_status, MAX(order_date) AS order_date FROM orders GROUP BY order_code ORDER BY MAX(order_date) DESC, MAX(id) DESC LIMIT 6");
    } else {
        $result = $conn->query("SELECT * FROM orders ORDER BY order_date DESC, id DESC LIMIT 6");
    }
    while ($result && $row = $result->fetch_assoc()) {
        $recentOrders[] = $row;
    }
}

$stats = [
    ["label" => "Total Products", "value" => $totalProducts, "hint" => "Furniture catalog"],
    ["label" => "Total Categories", "value" => $totalCategories, "hint" => "Shopping sections"],
    ["label" => "Total Orders", "value" => $totalOrders, "hint" => "All placed orders"],
    ["label" => "Pending Orders", "value" => $pendingOrders, "hint" => "Awaiting action"],
    ["label" => "Active Users", "value" => $activeUsers, "hint" => "Customer accounts"],
    ["label" => "Deleted Accounts", "value" => $deletedAccounts, "hint" => "Removed users"],
    ["label" => "Total Sales", "value" => "₹" . number_format($totalSales, 2), "hint" => "Recorded revenue"],
    ["label" => "Today Orders", "value" => $todayOrders, "hint" => "Orders today"],
];

$notifications = [];
$notificationResult = $conn->query("SELECT * FROM admin_notifications WHERE COALESCE(is_deleted, 0) = 0 ORDER BY created_at DESC, id DESC LIMIT 12");
while ($notificationResult && $row = $notificationResult->fetch_assoc()) {
    $notifications[] = $row;
}
$unreadNotifications = (int) (($conn->query("SELECT COUNT(*) AS total FROM admin_notifications WHERE is_read = 0 AND COALESCE(is_deleted, 0) = 0")->fetch_assoc())["total"] ?? 0);

include("includes/admin_header.php");
include("includes/admin_sidebar.php");
?>
<main class="admin-main admin-dashboard-main">
    <header class="admin-topbar admin-dark-topbar">
        <div>
            <span>Zafiro Casa Luxury Living</span>
            <h1>Admin Dashboard</h1>
            <p>Furniture store activity, orders, and customer overview.</p>
        </div>
        <div class="admin-dashboard-actions">
            <div class="admin-notification-wrap">
                <a class="admin-notification-btn" id="adminNotificationBell" href="notifications.php" aria-label="Open notifications">
                    <i class="fa-regular fa-bell"></i>
                    <span id="adminNotificationCount" class="<?php echo $unreadNotifications > 0 ? '' : 'is-hidden'; ?>"><?php echo $unreadNotifications; ?></span>
                </a>
            </div>
            <strong><?php echo htmlspecialchars($_SESSION["admin_name"] ?? "Admin"); ?></strong>
        </div>
    </header>

    <section class="admin-dashboard-grid modern-stats-grid">
        <?php foreach ($stats as $index => $stat): ?>
            <article class="admin-stat-card modern-stat-card">
                <span><?php echo htmlspecialchars($stat["label"]); ?></span>
                <strong><?php echo htmlspecialchars((string) $stat["value"]); ?></strong>
                <small><?php echo htmlspecialchars($stat["hint"]); ?></small>
            </article>
        <?php endforeach; ?>
    </section>

    <section class="admin-insight-grid">
        <article class="admin-panel-card admin-chart-card">
            <div class="admin-card-head">
                <div>
                    <span>Sales & Orders</span>
                    <h2 id="storeActivityTitle">Daily Store Activity</h2>
                </div>
                <div class="admin-chart-side">
                    <strong><?php echo $totalOrders; ?> Orders</strong>
                    <div class="admin-chart-arrows" aria-label="Switch chart view">
                        <button type="button" data-chart-view-prev aria-label="Previous chart">‹</button>
                        <button type="button" data-chart-view-next aria-label="Next chart">›</button>
                    </div>
                </div>
            </div>
            <div class="admin-chart-wrap">
                <canvas
                    id="weeklyStoreChart"
                    data-activity="<?php echo htmlspecialchars(json_encode($activityCharts)); ?>"
                ></canvas>
            </div>
        </article>

        <article class="admin-panel-card admin-user-card">
            <div class="admin-card-head">
                <div>
                    <span>User Statistics</span>
                    <h2>Customer Accounts</h2>
                </div>
            </div>
            <div class="user-stat-ring">
                <strong><?php echo $activeUsers; ?></strong>
                <span>Active Users</span>
            </div>
            <p>Deleted Accounts: <strong><?php echo $deletedAccounts; ?></strong></p>
        </article>
    </section>

    <section class="admin-panel-card admin-table-card">
        <div class="admin-card-head">
            <div>
                <span>Orders</span>
                <h2>Recent Orders</h2>
            </div>
        </div>
        <div class="admin-table-wrap">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Order ID</th>
                        <th>Customer</th>
                        <th>Product</th>
                        <th>Payment Method</th>
                        <th>Status</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($recentOrders)): ?>
                        <?php foreach ($recentOrders as $order): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($order["order_code"] ?? $order["id"] ?? "N/A"); ?></td>
                                <td><?php echo htmlspecialchars($order["customer_name"] ?? ("User #" . ($order["user_id"] ?? "Guest"))); ?></td>
                                <td><?php echo htmlspecialchars($order["product_names"] ?? $order["product_name"] ?? ("Product #" . ($order["product_id"] ?? "N/A"))); ?></td>
                                <td><?php echo htmlspecialchars($order["payment_method"] ?? "N/A"); ?></td>
                                <td><span class="status-pill"><?php echo htmlspecialchars($order["order_status"] ?? "Pending"); ?></span></td>
                                <td><?php echo htmlspecialchars($order["order_date"] ?? "N/A"); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="6">No recent orders found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
<?php include("includes/admin_footer.php"); ?>
