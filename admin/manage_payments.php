<?php
include("auth.php");
include("../backend/config/db.php");

function paymentMoney($value) {
    return "₹" . number_format((float) $value, 2);
}

function paymentStatusClass($status) {
    $status = strtolower(trim((string) $status));
    if (in_array($status, ["paid", "success", "successful", "completed"], true)) return "paid";
    if (in_array($status, ["failed", "failure"], true)) return "failed";
    if ($status === "refunded") return "refunded";
    if (in_array($status, ["pending", "not paid", "unpaid"], true)) return "pending";
    return "pending";
}

function ensurePaymentsTable($conn) {
    $conn->query("CREATE TABLE IF NOT EXISTS payments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        order_id INT NULL,
        user_id INT NULL,
        payment_gateway VARCHAR(50) DEFAULT 'PhonePe',
        gateway_order_id VARCHAR(255) NULL,
        gateway_payment_id VARCHAR(255) NULL,
        transaction_id VARCHAR(255) NULL,
        amount DECIMAL(10,2) DEFAULT 0,
        status VARCHAR(50) DEFAULT 'pending',
        response_json TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function paymentQueryParts(&$params, &$types) {
    $where = [];
    $search = trim($_GET["search"] ?? "");
    $status = trim($_GET["payment_status"] ?? "");
    $method = trim($_GET["payment_method"] ?? "");
    $dateFrom = trim($_GET["date_from"] ?? "");
    $dateTo = trim($_GET["date_to"] ?? "");
    $amountMin = trim($_GET["amount_min"] ?? "");
    $amountMax = trim($_GET["amount_max"] ?? "");

    if ($search !== "") {
        $where[] = "(o.order_id LIKE ? OR o.order_code LIKE ? OR o.customer_name LIKE ? OR o.customer_email LIKE ? OR o.customer_phone LIKE ? OR o.customer_contact LIKE ? OR p.gateway_payment_id LIKE ? OR p.transaction_id LIKE ? OR o.payment_id LIKE ? OR o.transaction_id LIKE ?)";
        $term = "%" . $search . "%";
        for ($i = 0; $i < 10; $i++) $params[] = $term;
        $types .= "ssssssssss";
    }
    if ($status !== "") {
        $where[] = "LOWER(COALESCE(p.status, o.payment_status, 'Not Paid')) = LOWER(?)";
        $params[] = $status;
        $types .= "s";
    }
    if ($method !== "") {
        $where[] = "LOWER(COALESCE(o.payment_method, p.payment_gateway, '')) LIKE LOWER(?)";
        $params[] = "%" . $method . "%";
        $types .= "s";
    }
    if ($dateFrom !== "") {
        $where[] = "DATE(COALESCE(o.paid_at, p.created_at, o.order_date, o.created_at)) >= ?";
        $params[] = $dateFrom;
        $types .= "s";
    }
    if ($dateTo !== "") {
        $where[] = "DATE(COALESCE(o.paid_at, p.created_at, o.order_date, o.created_at)) <= ?";
        $params[] = $dateTo;
        $types .= "s";
    }
    if ($amountMin !== "" && is_numeric($amountMin)) {
        $where[] = "COALESCE(p.amount, o.total_amount, o.total, 0) >= ?";
        $params[] = (float) $amountMin;
        $types .= "d";
    }
    if ($amountMax !== "" && is_numeric($amountMax)) {
        $where[] = "COALESCE(p.amount, o.total_amount, o.total, 0) <= ?";
        $params[] = (float) $amountMax;
        $types .= "d";
    }

    return $where ? " WHERE " . implode(" AND ", $where) : "";
}

ensurePaymentsTable($conn);

$baseSelect = "SELECT
    COALESCE(p.id, 0) AS payment_row_id,
    COALESCE(p.gateway_payment_id, o.payment_id, CONCAT('ORD-', o.id)) AS payment_ref,
    COALESCE(NULLIF(o.order_id, ''), NULLIF(o.order_code, ''), o.id) AS order_ref,
    o.id AS order_numeric_id,
    o.customer_name,
    o.customer_email,
    COALESCE(NULLIF(o.customer_phone, ''), o.customer_contact) AS customer_phone,
    COALESCE(o.payment_method, p.payment_gateway, 'Not Available') AS payment_method,
    COALESCE(p.status, o.payment_status, 'Not Paid') AS payment_status,
    COALESCE(p.amount, o.total_amount, o.total, 0) AS amount_paid,
    COALESCE(NULLIF(p.transaction_id, ''), NULLIF(o.transaction_id, ''), NULLIF(p.gateway_payment_id, ''), NULLIF(o.payment_id, ''), NULLIF(p.gateway_order_id, ''), '') AS transaction_ref,
    COALESCE(o.paid_at, p.created_at, o.order_date, o.created_at) AS payment_date,
    COALESCE(o.order_status, 'Pending') AS order_status,
    o.delivery_address,
    o.product_name,
    o.quantity,
    p.response_json
FROM orders o
LEFT JOIN payments p ON p.order_id = o.id OR p.gateway_order_id = o.order_code OR p.gateway_order_id = o.order_id";

$params = [];
$types = "";
$whereSql = paymentQueryParts($params, $types);

if (isset($_GET["export"]) && $_GET["export"] === "csv") {
    $stmt = $conn->prepare($baseSelect . $whereSql . " ORDER BY payment_date DESC, o.id DESC");
    if ($params) $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $rows = $stmt->get_result();
    header("Content-Type: text/csv");
    header("Content-Disposition: attachment; filename=payment-history-" . date("Y-m-d") . ".csv");
    $out = fopen("php://output", "w");
    fputcsv($out, ["Payment ID", "Order ID", "Customer Name", "Customer Email/Phone", "Payment Method", "Payment Status", "Amount Paid", "Transaction ID", "Payment Date", "Order Status"]);
    while ($row = $rows->fetch_assoc()) {
        fputcsv($out, [$row["payment_ref"], $row["order_ref"], $row["customer_name"], trim(($row["customer_email"] ?? "") . " " . ($row["customer_phone"] ?? "")), $row["payment_method"], $row["payment_status"], $row["amount_paid"], $row["transaction_ref"], $row["payment_date"], $row["order_status"]]);
    }
    exit;
}

$stmt = $conn->prepare($baseSelect . $whereSql . " ORDER BY payment_date DESC, o.id DESC LIMIT 100");
if ($params) $stmt->bind_param($types, ...$params);
$stmt->execute();
$payments = $stmt->get_result();

$summary = [
    "Total Revenue" => "SELECT COALESCE(SUM(CASE WHEN LOWER(COALESCE(p.status, o.payment_status)) IN ('paid','success','successful','completed') THEN COALESCE(p.amount, o.total_amount, o.total, 0) ELSE 0 END),0) AS total FROM orders o LEFT JOIN payments p ON p.order_id=o.id OR p.gateway_order_id=o.order_code OR p.gateway_order_id=o.order_id",
    "Today's Revenue" => "SELECT COALESCE(SUM(CASE WHEN LOWER(COALESCE(p.status, o.payment_status)) IN ('paid','success','successful','completed') AND DATE(COALESCE(o.paid_at,p.created_at,o.order_date))=CURDATE() THEN COALESCE(p.amount, o.total_amount, o.total, 0) ELSE 0 END),0) AS total FROM orders o LEFT JOIN payments p ON p.order_id=o.id OR p.gateway_order_id=o.order_code OR p.gateway_order_id=o.order_id",
    "Pending Payments" => "SELECT COUNT(*) AS total FROM orders o LEFT JOIN payments p ON p.order_id=o.id OR p.gateway_order_id=o.order_code OR p.gateway_order_id=o.order_id WHERE LOWER(COALESCE(p.status,o.payment_status,'pending'))='pending'",
    "Failed Payments" => "SELECT COUNT(*) AS total FROM orders o LEFT JOIN payments p ON p.order_id=o.id OR p.gateway_order_id=o.order_code OR p.gateway_order_id=o.order_id WHERE LOWER(COALESCE(p.status,o.payment_status,''))='failed'",
    "Refunded Amount" => "SELECT COALESCE(SUM(CASE WHEN LOWER(COALESCE(p.status,o.payment_status,''))='refunded' THEN COALESCE(p.amount,o.total_amount,o.total,0) ELSE 0 END),0) AS total FROM orders o LEFT JOIN payments p ON p.order_id=o.id OR p.gateway_order_id=o.order_code OR p.gateway_order_id=o.order_id",
    "UPI Revenue" => "SELECT COALESCE(SUM(CASE WHEN LOWER(COALESCE(o.payment_method,p.payment_gateway,'')) LIKE '%upi%' AND LOWER(COALESCE(p.status,o.payment_status,'')) IN ('paid','success','successful','completed') THEN COALESCE(p.amount,o.total_amount,o.total,0) ELSE 0 END),0) AS total FROM orders o LEFT JOIN payments p ON p.order_id=o.id OR p.gateway_order_id=o.order_code OR p.gateway_order_id=o.order_id",
    "Card Revenue" => "SELECT COALESCE(SUM(CASE WHEN LOWER(COALESCE(o.payment_method,p.payment_gateway,'')) LIKE '%card%' AND LOWER(COALESCE(p.status,o.payment_status,'')) IN ('paid','success','successful','completed') THEN COALESCE(p.amount,o.total_amount,o.total,0) ELSE 0 END),0) AS total FROM orders o LEFT JOIN payments p ON p.order_id=o.id OR p.gateway_order_id=o.order_code OR p.gateway_order_id=o.order_id",
    "COD Amount" => "SELECT COALESCE(SUM(CASE WHEN (LOWER(COALESCE(o.payment_method,p.payment_gateway,'')) LIKE '%cash%' OR LOWER(COALESCE(o.payment_method,p.payment_gateway,'')) LIKE '%cod%') THEN COALESCE(p.amount,o.total_amount,o.total,0) ELSE 0 END),0) AS total FROM orders o LEFT JOIN payments p ON p.order_id=o.id OR p.gateway_order_id=o.order_code OR p.gateway_order_id=o.order_id"
];

include("includes/admin_header.php");
include("includes/admin_sidebar.php");
?>
<main class="admin-main payment-admin-page">
    <header class="admin-topbar admin-dark-topbar payment-page-header">
        <div><span>Zafiro Casa</span><h1>Payment History & Reports</h1><p>Customer-wise payment tracking and reports.</p></div>
        <button class="admin-btn" onclick="window.print()"><i class="fa-solid fa-print"></i> Export PDF / Print</button>
    </header>

    <section class="payment-summary-grid">
        <?php foreach ($summary as $label => $sql): ?>
            <?php $value = (float) (($conn->query($sql)->fetch_assoc())["total"] ?? 0); ?>
            <article><span><?php echo htmlspecialchars($label); ?></span><strong><?php echo strpos($label, "Payments") !== false ? (int) $value : paymentMoney($value); ?></strong></article>
        <?php endforeach; ?>
    </section>

    <section class="admin-form-card payment-filter-card">
        <form method="GET" action="manage_payments.php" class="payment-filter-form">
            <input type="search" name="search" placeholder="Search customer, order ID, transaction ID" value="<?php echo htmlspecialchars($_GET["search"] ?? ""); ?>">
            <select name="payment_status">
                <option value="">All Status</option>
                <?php foreach (["Paid", "Pending", "Failed", "Refunded", "Not Paid"] as $status): ?><option value="<?php echo $status; ?>" <?php echo ($_GET["payment_status"] ?? "") === $status ? "selected" : ""; ?>><?php echo $status; ?></option><?php endforeach; ?>
            </select>
            <select name="payment_method">
                <option value="">All Methods</option>
                <?php foreach (["UPI", "QR", "Card", "Cash on Delivery", "PhonePe", "Online"] as $method): ?><option value="<?php echo $method; ?>" <?php echo ($_GET["payment_method"] ?? "") === $method ? "selected" : ""; ?>><?php echo $method; ?></option><?php endforeach; ?>
            </select>
            <input type="date" name="date_from" value="<?php echo htmlspecialchars($_GET["date_from"] ?? ""); ?>">
            <input type="date" name="date_to" value="<?php echo htmlspecialchars($_GET["date_to"] ?? ""); ?>">
            <input type="number" step="0.01" name="amount_min" placeholder="Min amount" value="<?php echo htmlspecialchars($_GET["amount_min"] ?? ""); ?>">
            <input type="number" step="0.01" name="amount_max" placeholder="Max amount" value="<?php echo htmlspecialchars($_GET["amount_max"] ?? ""); ?>">
            <button class="admin-btn" type="submit">Apply</button>
            <a class="admin-btn admin-btn-light" href="manage_payments.php">Reset</a>
            <a class="admin-btn" href="manage_payments.php?<?php echo htmlspecialchars(http_build_query(array_merge($_GET, ["export" => "csv"]))); ?>">Export CSV</a>
        </form>
    </section>

    <section class="admin-panel-card payment-table-card">
        <div class="payment-table-scroll">
        <table class="admin-table">
            <thead><tr><th>Payment ID</th><th>Order ID</th><th>Customer</th><th>Email/Phone</th><th>Method</th><th>Status</th><th>Amount</th><th>Transaction / UPI Ref</th><th>Date & Time</th><th>Order Status</th><th>Action</th></tr></thead>
            <tbody>
                <?php if ($payments && $payments->num_rows > 0): ?>
                    <?php while ($row = $payments->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($row["payment_ref"]); ?></td>
                            <td><?php echo htmlspecialchars($row["order_ref"]); ?></td>
                            <td class="payment-customer-cell"><?php echo htmlspecialchars($row["customer_name"] ?: "Guest"); ?></td>
                            <td class="payment-contact-cell"><span><?php echo htmlspecialchars($row["customer_email"] ?: "N/A"); ?></span><small><?php echo htmlspecialchars($row["customer_phone"] ?: "N/A"); ?></small></td>
                            <td><span class="status-pill"><?php echo htmlspecialchars($row["payment_method"]); ?></span></td>
                            <td><span class="payment-badge <?php echo paymentStatusClass($row["payment_status"]); ?>"><?php echo htmlspecialchars($row["payment_status"]); ?></span></td>
                            <td class="payment-amount-cell"><?php echo paymentMoney($row["amount_paid"]); ?></td>
                            <td class="payment-transaction-cell"><?php echo htmlspecialchars($row["transaction_ref"] ?: (strtolower((string) $row["payment_status"]) === "pending" ? "Pending / N/A" : "N/A")); ?></td>
                            <td class="payment-date-cell"><?php echo htmlspecialchars($row["payment_date"]); ?></td>
                            <td class="payment-order-status-cell"><?php echo htmlspecialchars($row["order_status"]); ?></td>
                            <td class="payment-action-cell"><button type="button" class="admin-action-link" onclick="document.getElementById('paymentDetail<?php echo (int) $row['order_numeric_id']; ?>').classList.add('open')">View Details</button></td>
                        </tr>
                        <tr class="payment-detail-row"><td colspan="11">
                            <div class="admin-modal" id="paymentDetail<?php echo (int) $row["order_numeric_id"]; ?>">
                                <div class="admin-modal-card payment-detail-modal">
                                    <button type="button" class="admin-search-close payment-detail-close" onclick="this.closest('.admin-modal').classList.remove('open')">&times;</button>
                                    <h2>Payment Details</h2>
                                    <section><h3>Customer Details</h3><dl><dt>Customer Name</dt><dd><?php echo htmlspecialchars($row["customer_name"] ?: "Guest"); ?></dd><dt>Email</dt><dd><?php echo htmlspecialchars($row["customer_email"] ?: "N/A"); ?></dd><dt>Phone</dt><dd><?php echo htmlspecialchars($row["customer_phone"] ?: "N/A"); ?></dd></dl></section>
                                    <section><h3>Order Details</h3><dl><dt>Order ID</dt><dd><?php echo htmlspecialchars($row["order_ref"]); ?></dd><dt>Product</dt><dd><?php echo htmlspecialchars($row["product_name"] ?: "N/A"); ?> x <?php echo (int) $row["quantity"]; ?></dd><dt>Order Status</dt><dd><?php echo htmlspecialchars($row["order_status"]); ?></dd></dl></section>
                                    <section><h3>Payment Details</h3><dl><dt>Amount</dt><dd><?php echo paymentMoney($row["amount_paid"]); ?></dd><dt>Payment Method</dt><dd><?php echo htmlspecialchars($row["payment_method"]); ?></dd><dt>Payment Status</dt><dd><span class="payment-badge <?php echo paymentStatusClass($row["payment_status"]); ?>"><?php echo htmlspecialchars($row["payment_status"]); ?></span></dd><dt>Transaction / UPI Reference</dt><dd><?php echo htmlspecialchars($row["transaction_ref"] ?: "N/A"); ?></dd></dl></section>
                                    <section><h3>Billing / Shipping Address</h3><p><?php echo htmlspecialchars($row["delivery_address"] ?: "N/A"); ?></p></section>
                                    <section><h3>Payment Timeline</h3><dl><dt>Payment Date & Time</dt><dd><?php echo htmlspecialchars($row["payment_date"]); ?></dd><dt>Order Status</dt><dd><?php echo htmlspecialchars($row["order_status"]); ?></dd></dl></section>
                                    <?php if (!empty($row["response_json"])): ?><section><h3>Gateway Response</h3><pre><?php echo htmlspecialchars($row["response_json"]); ?></pre></section><?php endif; ?>
                                </div>
                            </div>
                        </td></tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="11">No payment records found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
        </div>
    </section>
</main>
<?php include("includes/admin_footer.php"); ?>
