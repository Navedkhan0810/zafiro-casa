<?php
include("auth.php");
include("../backend/config/db.php");

$message = "";
$messageType = "";

function orderTableExists($conn, $table) {
    $stmt = $conn->prepare("SELECT COUNT(*) AS total FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?");
    $stmt->bind_param("s", $table);
    $stmt->execute();
    return (int) ($stmt->get_result()->fetch_assoc()["total"] ?? 0) > 0;
}

function orderColumnExists($conn, $table, $column) {
    $stmt = $conn->prepare("SELECT COUNT(*) AS total FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?");
    $stmt->bind_param("ss", $table, $column);
    $stmt->execute();
    return (int) ($stmt->get_result()->fetch_assoc()["total"] ?? 0) > 0;
}

function ensureOrdersSchema($conn) {
    $conn->query("CREATE TABLE IF NOT EXISTS orders (
        id INT AUTO_INCREMENT PRIMARY KEY,
        order_id VARCHAR(40) NULL,
        user_id INT NULL,
        customer_name VARCHAR(120) NULL,
        customer_email VARCHAR(160) NULL,
        customer_phone VARCHAR(30) NULL,
        delivery_address TEXT NULL,
        total_amount DECIMAL(10,2) DEFAULT 0,
        payment_method VARCHAR(80) NULL,
        payment_status VARCHAR(50) DEFAULT 'Pending',
        order_status VARCHAR(50) DEFAULT 'Pending',
        order_date DATETIME DEFAULT CURRENT_TIMESTAMP,
        confirmed_date DATETIME NULL,
        shipping_date DATETIME NULL,
        delivery_date DATETIME NULL,
        return_date DATETIME NULL,
        refund_date DATETIME NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    $columns = [
        "order_id" => "VARCHAR(40) NULL",
        "order_code" => "VARCHAR(40) NULL",
        "user_id" => "INT NULL",
        "product_id" => "INT NULL",
        "customer_name" => "VARCHAR(120) NULL",
        "customer_email" => "VARCHAR(160) NULL",
        "customer_phone" => "VARCHAR(30) NULL",
        "customer_contact" => "VARCHAR(30) NULL",
        "delivery_address" => "TEXT NULL",
        "total_amount" => "DECIMAL(10,2) DEFAULT 0",
        "total" => "DECIMAL(10,2) DEFAULT 0",
        "product_name" => "VARCHAR(180) NULL",
        "quantity" => "INT DEFAULT 1",
        "payment_method" => "VARCHAR(80) NULL",
        "payment_status" => "VARCHAR(50) DEFAULT 'Pending'",
        "payment_id" => "VARCHAR(255) NULL",
        "transaction_id" => "VARCHAR(255) NULL",
        "paid_at" => "DATETIME NULL",
        "order_status" => "VARCHAR(50) DEFAULT 'Pending'",
        "order_date" => "DATETIME DEFAULT CURRENT_TIMESTAMP",
        "confirmed_date" => "DATETIME NULL",
        "shipping_date" => "DATETIME NULL",
        "delivery_date" => "DATETIME NULL",
        "return_date" => "DATETIME NULL",
        "refund_date" => "DATETIME NULL",
        "created_at" => "DATETIME DEFAULT CURRENT_TIMESTAMP"
    ];

    foreach ($columns as $column => $definition) {
        if (!orderColumnExists($conn, "orders", $column)) {
            $conn->query("ALTER TABLE orders ADD COLUMN `$column` $definition");
        }
    }

    $conn->query("CREATE TABLE IF NOT EXISTS order_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        order_id VARCHAR(40) NOT NULL,
        product_id INT NULL,
        product_name VARCHAR(180) NULL,
        product_image VARCHAR(255) NULL,
        quantity INT DEFAULT 1,
        price DECIMAL(10,2) DEFAULT 0,
        subtotal DECIMAL(10,2) DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    $conn->query("UPDATE orders SET order_id = order_code WHERE (order_id IS NULL OR order_id = '') AND order_code IS NOT NULL AND order_code <> ''");
    $conn->query("UPDATE orders SET order_code = order_id WHERE (order_code IS NULL OR order_code = '') AND order_id IS NOT NULL AND order_id <> ''");
    $conn->query("UPDATE orders SET customer_phone = customer_contact WHERE (customer_phone IS NULL OR customer_phone = '') AND customer_contact IS NOT NULL");
    $conn->query("UPDATE orders SET total_amount = total WHERE (total_amount IS NULL OR total_amount = 0) AND total IS NOT NULL");
}

function orderCountValue($conn, $sql) {
    $result = $conn->query($sql);
    return (int) (($result ? $result->fetch_assoc() : [])["total"] ?? 0);
}

function orderMoneyValue($conn, $sql) {
    $result = $conn->query($sql);
    return (float) (($result ? $result->fetch_assoc() : [])["total"] ?? 0);
}

function fetchOrderItems($conn, $orderKey, $orderRow) {
    $items = [];
    $stmt = $conn->prepare("SELECT * FROM order_items WHERE order_id = ? ORDER BY id ASC");
    $stmt->bind_param("s", $orderKey);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $items[] = $row;
    }

    if (!$items) {
        $items[] = [
            "product_id" => $orderRow["product_id"] ?? 0,
            "product_name" => $orderRow["product_name"] ?? "Order Item",
            "product_image" => $orderRow["old_product_image"] ?? "",
            "quantity" => $orderRow["quantity"] ?? 1,
            "price" => $orderRow["total_amount"] ?: ($orderRow["total"] ?? 0),
            "subtotal" => $orderRow["total_amount"] ?: ($orderRow["total"] ?? 0)
        ];
    }

    return $items;
}

ensureOrdersSchema($conn);

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = $_POST["action"] ?? "";
    $orderKey = trim($_POST["order_key"] ?? "");

    if ($orderKey !== "" && $action === "update_order_status") {
        $newStatus = trim($_POST["order_status"] ?? "Pending");
        $dateColumns = [
            "Confirmed" => "confirmed_date",
            "Shipped" => "shipping_date",
            "Delivered" => "delivery_date",
            "Returned" => "return_date",
            "Refunded" => "refund_date"
        ];
        $dateSql = isset($dateColumns[$newStatus]) ? ", {$dateColumns[$newStatus]} = NOW()" : "";
        $stmt = $conn->prepare("UPDATE orders SET order_status = ? $dateSql WHERE order_id = ? OR order_code = ?");
        $stmt->bind_param("sss", $newStatus, $orderKey, $orderKey);
        $message = $stmt->execute() ? "Order status updated." : "Order status could not be updated.";
        $messageType = $stmt->affected_rows >= 0 ? "success" : "error";
    }

    if ($orderKey !== "" && $action === "update_payment_status") {
        $newStatus = trim($_POST["payment_status"] ?? "Pending");
        $dateSql = $newStatus === "Refunded" ? ", refund_date = NOW(), order_status = 'Refunded'" : "";
        $stmt = $conn->prepare("UPDATE orders SET payment_status = ? $dateSql WHERE order_id = ? OR order_code = ?");
        $stmt->bind_param("sss", $newStatus, $orderKey, $orderKey);
        $message = $stmt->execute() ? "Payment status updated." : "Payment status could not be updated.";
        $messageType = $stmt->affected_rows >= 0 ? "success" : "error";
    }

    if ($orderKey !== "" && $action === "delete_order") {
        $stmt = $conn->prepare("DELETE FROM orders WHERE order_id = ? OR order_code = ?");
        $stmt->bind_param("ss", $orderKey, $orderKey);
        $deleted = $stmt->execute();
        $itemStmt = $conn->prepare("DELETE FROM order_items WHERE order_id = ?");
        $itemStmt->bind_param("s", $orderKey);
        $itemStmt->execute();
        $message = $deleted ? "Order deleted successfully." : "Order could not be deleted.";
        $messageType = $deleted ? "success" : "error";
    }
}

$search = trim($_GET["search"] ?? "");
$orderStatus = trim($_GET["order_status"] ?? "");
$paymentStatus = trim($_GET["payment_status"] ?? "");
$paymentMethod = trim($_GET["payment_method"] ?? "");
$dateFrom = trim($_GET["date_from"] ?? "");
$dateTo = trim($_GET["date_to"] ?? "");
$quick = trim($_GET["quick"] ?? "");
$sort = trim($_GET["sort"] ?? "newest");
$page = max(1, (int) ($_GET["page"] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

$where = [];
$params = [];
$types = "";

if ($search !== "") {
    $where[] = "(o.order_id LIKE ? OR o.order_code LIKE ? OR o.customer_name LIKE ? OR o.customer_phone LIKE ? OR o.customer_contact LIKE ? OR o.customer_email LIKE ? OR o.product_name LIKE ? OR EXISTS (SELECT 1 FROM order_items oi WHERE oi.order_id = COALESCE(NULLIF(o.order_id, ''), o.order_code) AND oi.product_name LIKE ?))";
    $term = "%" . $search . "%";
    for ($i = 0; $i < 8; $i++) $params[] = $term;
    $types .= "ssssssss";
}
if ($orderStatus !== "") {
    $where[] = "LOWER(o.order_status) = LOWER(?)";
    $params[] = $orderStatus;
    $types .= "s";
}
if ($paymentStatus !== "") {
    $where[] = "LOWER(o.payment_status) = LOWER(?)";
    $params[] = $paymentStatus;
    $types .= "s";
}
if ($paymentMethod !== "") {
    if ($paymentMethod === "COD") {
        $where[] = "(LOWER(o.payment_method) LIKE ? OR LOWER(o.payment_method) LIKE ?)";
        $params[] = "%cod%";
        $params[] = "%cash%delivery%";
        $types .= "ss";
    } else {
        $where[] = "LOWER(o.payment_method) LIKE LOWER(?)";
        $params[] = "%" . $paymentMethod . "%";
        $types .= "s";
    }
}
if ($dateFrom !== "") {
    $where[] = "DATE(o.order_date) >= ?";
    $params[] = $dateFrom;
    $types .= "s";
}
if ($dateTo !== "") {
    $where[] = "DATE(o.order_date) <= ?";
    $params[] = $dateTo;
    $types .= "s";
}
if ($quick === "today") $where[] = "DATE(o.order_date) = CURDATE()";
if ($quick === "pending") $where[] = "LOWER(o.order_status) = 'pending'";
if ($quick === "delivered") $where[] = "LOWER(o.order_status) = 'delivered'";
if ($quick === "returned") $where[] = "LOWER(o.order_status) IN ('returned', 'return requested')";

$orderBy = "o.order_date DESC, o.id DESC";
if ($sort === "oldest") $orderBy = "o.order_date ASC, o.id ASC";
if ($sort === "high") $orderBy = "o.total_amount DESC, o.total DESC";
if ($sort === "low") $orderBy = "o.total_amount ASC, o.total ASC";

$countSql = "SELECT COUNT(DISTINCT COALESCE(NULLIF(o.order_id, ''), NULLIF(o.order_code, ''), o.id)) AS total FROM orders o" . ($where ? " WHERE " . implode(" AND ", $where) : "");
$countStmt = $conn->prepare($countSql);
if ($params) $countStmt->bind_param($types, ...$params);
$countStmt->execute();
$totalOrders = (int) ($countStmt->get_result()->fetch_assoc()["total"] ?? 0);

$sql = "SELECT o.*, p.image AS old_product_image FROM orders o LEFT JOIN products p ON p.id = o.product_id" . ($where ? " WHERE " . implode(" AND ", $where) : "") . " ORDER BY $orderBy LIMIT ? OFFSET ?";
$stmt = $conn->prepare($sql);
$queryParams = $params;
$queryParams[] = $perPage;
$queryParams[] = $offset;
$stmt->bind_param($types . "ii", ...$queryParams);
$stmt->execute();
$rawOrders = $stmt->get_result();

$orders = [];
$seen = [];
while ($row = $rawOrders->fetch_assoc()) {
    $key = $row["order_id"] ?: ($row["order_code"] ?: (string) $row["id"]);
    if (isset($seen[$key])) continue;
    $seen[$key] = true;
    $row["order_key"] = $key;
    $row["items"] = fetchOrderItems($conn, $key, $row);
    $orders[] = $row;
}

$stats = [
    "Total Orders" => orderCountValue($conn, "SELECT COUNT(DISTINCT COALESCE(NULLIF(order_id, ''), NULLIF(order_code, ''), id)) AS total FROM orders"),
    "Pending Orders" => orderCountValue($conn, "SELECT COUNT(DISTINCT COALESCE(NULLIF(order_id, ''), NULLIF(order_code, ''), id)) AS total FROM orders WHERE LOWER(order_status) = 'pending'"),
    "Confirmed Orders" => orderCountValue($conn, "SELECT COUNT(DISTINCT COALESCE(NULLIF(order_id, ''), NULLIF(order_code, ''), id)) AS total FROM orders WHERE LOWER(order_status) = 'confirmed'"),
    "Delivered Orders" => orderCountValue($conn, "SELECT COUNT(DISTINCT COALESCE(NULLIF(order_id, ''), NULLIF(order_code, ''), id)) AS total FROM orders WHERE LOWER(order_status) = 'delivered'"),
    "Returned Orders" => orderCountValue($conn, "SELECT COUNT(DISTINCT COALESCE(NULLIF(order_id, ''), NULLIF(order_code, ''), id)) AS total FROM orders WHERE LOWER(order_status) IN ('returned', 'return requested')"),
    "Total Sales" => "₹" . number_format(orderMoneyValue($conn, "SELECT COALESCE(SUM(CASE WHEN total_amount > 0 THEN total_amount ELSE total END), 0) AS total FROM orders"), 2)
];

$orderStatusOptions = ["Pending", "Confirmed", "Packed", "Shipped", "Out for Delivery", "Delivered", "Cancelled", "Return Requested", "Returned", "Refunded"];
$paymentStatusOptions = ["Pending", "Paid", "Failed", "Refunded"];
$paymentMethodOptions = ["COD", "UPI", "PhonePe", "Card", "Wallet"];

include("includes/admin_header.php");
include("includes/admin_sidebar.php");
?>
<main class="admin-main">
    <header class="admin-topbar admin-dark-topbar">
        <div>
            <span>Zafiro Casa Luxury Living</span>
            <h1>Manage Orders</h1>
            <p>View and manage customer orders, payments, delivery, returns, and refunds.</p>
        </div>
    </header>

    <?php if ($message): ?>
        <div class="admin-popup <?php echo $messageType === 'success' ? 'success' : 'error'; ?>"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>

    <section class="admin-dashboard-grid order-stats-grid">
        <?php foreach ($stats as $label => $value): ?>
            <article class="admin-stat-card">
                <span><?php echo htmlspecialchars($label); ?></span>
                <strong><?php echo htmlspecialchars((string) $value); ?></strong>
                <p>Live order data</p>
            </article>
        <?php endforeach; ?>
    </section>

    <section class="manage-orders-filter-section">
        <form method="GET" action="manage_orders.php" class="manage-orders-filter">
            <input type="hidden" name="search" value="<?php echo htmlspecialchars($search); ?>">
            <div class="admin-form-card manage-filter-card manage-orders-filter-box manage-orders-filter-primary">
                <div class="manage-orders-search-cell">
                    <button type="button" class="admin-search-toggle" id="adminOrderSearchToggle" aria-label="Open order search"><i class="fas fa-search"></i></button>
                </div>
                <select name="order_status">
                    <option value="">Order Status</option>
                    <?php foreach ($orderStatusOptions as $option): ?>
                        <option value="<?php echo htmlspecialchars($option); ?>" <?php echo $orderStatus === $option ? "selected" : ""; ?>><?php echo htmlspecialchars($option); ?></option>
                    <?php endforeach; ?>
                </select>
                <input type="date" name="date_from" value="<?php echo htmlspecialchars($dateFrom); ?>">
                <select name="payment_status">
                    <option value="">Payment Status</option>
                    <?php foreach ($paymentStatusOptions as $option): ?>
                        <option value="<?php echo htmlspecialchars($option); ?>" <?php echo $paymentStatus === $option ? "selected" : ""; ?>><?php echo htmlspecialchars($option); ?></option>
                    <?php endforeach; ?>
                </select>
                <input type="date" name="date_to" value="<?php echo htmlspecialchars($dateTo); ?>">
                </div>
            <div class="admin-form-card manage-filter-card manage-orders-filter-box manage-orders-filter-secondary">
                <select name="payment_method" aria-label="Payment Method">
                    <option value="">Payment Methods</option>
                    <?php foreach ($paymentMethodOptions as $option): ?>
                        <option value="<?php echo htmlspecialchars($option); ?>" <?php echo $paymentMethod === $option ? "selected" : ""; ?>><?php echo $option === "COD" ? "COD" : htmlspecialchars($option); ?></option>
                    <?php endforeach; ?>
                </select>
                <select name="quick">
                    <option value="">All Orders</option>
                    <option value="today" <?php echo $quick === "today" ? "selected" : ""; ?>>Today Orders</option>
                    <option value="pending" <?php echo $quick === "pending" ? "selected" : ""; ?>>Pending Orders</option>
                    <option value="delivered" <?php echo $quick === "delivered" ? "selected" : ""; ?>>Delivered Orders</option>
                    <option value="returned" <?php echo $quick === "returned" ? "selected" : ""; ?>>Returned Orders</option>
                </select>
                <select name="sort">
                    <option value="newest" <?php echo $sort === "newest" ? "selected" : ""; ?>>Newest First</option>
                    <option value="oldest" <?php echo $sort === "oldest" ? "selected" : ""; ?>>Oldest First</option>
                    <option value="high" <?php echo $sort === "high" ? "selected" : ""; ?>>Highest Amount</option>
                    <option value="low" <?php echo $sort === "low" ? "selected" : ""; ?>>Lowest Amount</option>
                </select>
                <button type="submit" class="admin-btn">Apply</button>
                <a class="admin-btn admin-btn-light" href="manage_orders.php">Reset</a>
                </div>
        </form>
        <div class="admin-search-popup" id="adminOrderSearchPopup">
            <form method="GET" action="manage_orders.php" class="admin-search-popup-card">
                <button type="button" class="admin-search-close" id="adminOrderSearchClose" aria-label="Close search">&times;</button>
                <h3>Search Orders</h3>
                <p>Search by order ID, customer, phone, email, or product name.</p>
                <input type="search" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Type order keyword...">
                <button type="submit" class="admin-btn">Search</button>
            </form>
        </div>
    </section>

    <section class="order-card-list">
        <?php if (!empty($orders)): ?>
            <?php foreach ($orders as $order): ?>
                <?php
                $items = $order["items"];
                $firstItem = $items[0] ?? [];
                $totalAmount = (float) ($order["total_amount"] ?: ($order["total"] ?? 0));
                $phone = $order["customer_phone"] ?: ($order["customer_contact"] ?? "");
                ?>
                <article class="admin-order-card">
                    <div class="admin-order-product">
                        <img src="<?php echo htmlspecialchars($firstItem["product_image"] ?: "../assets/images/placeholder.jpg"); ?>" alt="<?php echo htmlspecialchars($firstItem["product_name"] ?? "Product"); ?>" loading="lazy" decoding="async" width="180" height="150">
                        <div>
                            <span><?php echo htmlspecialchars($order["order_key"]); ?></span>
                            <h2><?php echo htmlspecialchars($firstItem["product_name"] ?? "Order Items"); ?></h2>
                            <p>Qty: <?php echo array_sum(array_map(fn($item) => (int) ($item["quantity"] ?? 1), $items)); ?><?php echo count($items) > 1 ? " • +" . (count($items) - 1) . " more item(s)" : ""; ?></p>
                        </div>
                    </div>

                    <div class="admin-order-details">
                        <span><strong>Customer</strong><?php echo htmlspecialchars($order["customer_name"] ?: "Guest Customer"); ?></span>
                        <span><strong>Phone</strong><?php echo htmlspecialchars($phone ?: "N/A"); ?></span>
                        <span><strong>Email</strong><?php echo htmlspecialchars($order["customer_email"] ?: "N/A"); ?></span>
                        <span><strong>Total</strong>₹<?php echo number_format($totalAmount, 2); ?></span>
                        <span><strong>Payment</strong><?php echo htmlspecialchars($order["payment_method"] ?: "N/A"); ?></span>
                        <span><strong>Payment Status</strong><?php echo htmlspecialchars($order["payment_status"] ?: "Pending"); ?></span>
                        <span><strong>Payment ID</strong><?php echo htmlspecialchars($order["payment_id"] ?: "N/A"); ?></span>
                        <span><strong>Transaction ID</strong><?php echo htmlspecialchars($order["transaction_id"] ?: "N/A"); ?></span>
                        <span><strong>Order Status</strong><?php echo htmlspecialchars($order["order_status"] ?: "Pending"); ?></span>
                        <span><strong>Order Date</strong><?php echo htmlspecialchars($order["order_date"] ?: "N/A"); ?></span>
                        <span><strong>Shipping Date</strong><?php echo htmlspecialchars($order["shipping_date"] ?: "Pending"); ?></span>
                        <span><strong>Delivery Date</strong><?php echo htmlspecialchars($order["delivery_date"] ?: "Pending"); ?></span>
                        <span><strong>Return Date</strong><?php echo htmlspecialchars($order["return_date"] ?: "N/A"); ?></span>
                        <span><strong>Refund Date</strong><?php echo htmlspecialchars($order["refund_date"] ?: "N/A"); ?></span>
                    </div>

                    <div class="admin-order-address">
                        <strong>Delivery Address</strong>
                        <p><?php echo htmlspecialchars($order["delivery_address"] ?: "No address saved."); ?></p>
                    </div>

                    <div class="admin-order-actions">
                        <button type="button" class="admin-action-link view-order-btn" data-order="<?php echo htmlspecialchars($order["order_key"]); ?>">View Details</button>
                        <form method="POST" action="manage_orders.php">
                            <input type="hidden" name="action" value="update_order_status">
                            <input type="hidden" name="order_key" value="<?php echo htmlspecialchars($order["order_key"]); ?>">
                            <select name="order_status">
                                <?php foreach ($orderStatusOptions as $option): ?>
                                    <option value="<?php echo htmlspecialchars($option); ?>" <?php echo strtolower($order["order_status"] ?? "") === strtolower($option) ? "selected" : ""; ?>><?php echo htmlspecialchars($option); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button type="submit" class="admin-action-link edit">Update Status</button>
                        </form>
                        <form method="POST" action="manage_orders.php">
                            <input type="hidden" name="action" value="update_payment_status">
                            <input type="hidden" name="order_key" value="<?php echo htmlspecialchars($order["order_key"]); ?>">
                            <select name="payment_status">
                                <?php foreach ($paymentStatusOptions as $option): ?>
                                    <option value="<?php echo htmlspecialchars($option); ?>" <?php echo strtolower($order["payment_status"] ?? "") === strtolower($option) ? "selected" : ""; ?>><?php echo htmlspecialchars($option); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button type="submit" class="admin-action-link edit">Update Payment</button>
                        </form>
                        <form method="POST" action="manage_orders.php" class="delete-order-form">
                            <input type="hidden" name="action" value="delete_order">
                            <input type="hidden" name="order_key" value="<?php echo htmlspecialchars($order["order_key"]); ?>">
                            <button type="submit" class="admin-action-link danger">Delete Order</button>
                        </form>
                    </div>

                    <div class="admin-order-modal-data" id="orderData-<?php echo htmlspecialchars($order["order_key"]); ?>">
                        <h2>Order <?php echo htmlspecialchars($order["order_key"]); ?></h2>
                        <p><strong>Customer:</strong> <?php echo htmlspecialchars($order["customer_name"] ?: "Guest Customer"); ?></p>
                        <p><strong>Phone:</strong> <?php echo htmlspecialchars($phone ?: "N/A"); ?></p>
                        <p><strong>Email:</strong> <?php echo htmlspecialchars($order["customer_email"] ?: "N/A"); ?></p>
                        <p><strong>Address:</strong> <?php echo htmlspecialchars($order["delivery_address"] ?: "No address saved."); ?></p>
                        <p><strong>Total Amount:</strong> ₹<?php echo number_format($totalAmount, 2); ?></p>
                        <p><strong>Payment:</strong> <?php echo htmlspecialchars(($order["payment_method"] ?: "N/A") . " / " . ($order["payment_status"] ?: "Pending")); ?></p>
                        <p><strong>Payment ID:</strong> <?php echo htmlspecialchars($order["payment_id"] ?: "N/A"); ?></p>
                        <p><strong>Transaction ID:</strong> <?php echo htmlspecialchars($order["transaction_id"] ?: "N/A"); ?></p>
                        <p><strong>Paid At:</strong> <?php echo htmlspecialchars($order["paid_at"] ?: "N/A"); ?></p>
                        <p><strong>Status:</strong> <?php echo htmlspecialchars($order["order_status"] ?: "Pending"); ?></p>
                        <h3>Products</h3>
                        <?php foreach ($items as $item): ?>
                            <p><?php echo htmlspecialchars($item["product_name"] ?? "Product"); ?> × <?php echo (int) ($item["quantity"] ?? 1); ?> - ₹<?php echo number_format((float) ($item["subtotal"] ?? 0), 2); ?></p>
                        <?php endforeach; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="empty-admin-state">
                <h2>No real orders found.</h2>
                <p>Orders from cart checkout and Buy This Now will appear here after checkout.</p>
            </div>
        <?php endif; ?>
    </section>
    <?php if ($totalOrders > $perPage): ?>
        <nav class="admin-pagination">
            <?php for ($i = 1, $pages = (int) ceil($totalOrders / $perPage); $i <= $pages; $i++): ?>
                <?php $pageUrl = "manage_orders.php?" . http_build_query(array_merge($_GET, ["page" => $i])); ?>
                <a class="<?php echo $i === $page ? "active" : ""; ?>" href="<?php echo htmlspecialchars($pageUrl); ?>"><?php echo $i; ?></a>
            <?php endfor; ?>
        </nav>
    <?php endif; ?>

    <div class="admin-modal" id="orderDetailsModal">
        <div class="admin-modal-card">
            <button type="button" class="admin-search-close" id="closeOrderDetailsModal">&times;</button>
            <div id="orderDetailsContent"></div>
        </div>
    </div>
<?php include("includes/admin_footer.php"); ?>
