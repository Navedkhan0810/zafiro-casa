<?php
session_start();
header("Content-Type: application/json");
include("../backend/config/db.php");
include_once("../backend/includes/user_auth.php");

if (empty($_SESSION["user_id"])) {
    http_response_code(401);
    echo json_encode(["success" => false, "message" => "Please sign in to place order."]);
    exit;
}

function save_order_column_exists($conn, $columnName) {
    $stmt = $conn->prepare("SELECT COUNT(*) AS total FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'orders' AND column_name = ?");
    $stmt->bind_param("s", $columnName);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    return (int) ($row["total"] ?? 0) > 0;
}

$conn->query("CREATE TABLE IF NOT EXISTS orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id VARCHAR(40) NULL,
    user_id INT NULL,
    product_id INT NULL,
    customer_name VARCHAR(120) NULL,
    customer_email VARCHAR(160) NULL,
    customer_phone VARCHAR(30) NULL,
    customer_contact VARCHAR(30) NULL,
    delivery_address TEXT NULL,
    total_amount DECIMAL(10,2) DEFAULT 0,
    payment_status VARCHAR(50) DEFAULT 'Pending',
    order_status VARCHAR(50) DEFAULT 'Pending',
    order_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    confirmed_date DATETIME NULL,
    shipping_date DATE NULL,
    delivery_date DATE NULL,
    return_date DATE NULL,
    refund_date DATE NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)");

$extraColumns = [
    "order_id" => "ALTER TABLE orders ADD COLUMN order_id VARCHAR(40) NULL",
    "order_code" => "ALTER TABLE orders ADD COLUMN order_code VARCHAR(40) NULL",
    "customer_email" => "ALTER TABLE orders ADD COLUMN customer_email VARCHAR(160) NULL",
    "customer_phone" => "ALTER TABLE orders ADD COLUMN customer_phone VARCHAR(30) NULL",
    "delivery_address" => "ALTER TABLE orders ADD COLUMN delivery_address TEXT NULL",
    "total_amount" => "ALTER TABLE orders ADD COLUMN total_amount DECIMAL(10,2) DEFAULT 0",
    "product_name" => "ALTER TABLE orders ADD COLUMN product_name VARCHAR(180) NULL",
    "quantity" => "ALTER TABLE orders ADD COLUMN quantity INT DEFAULT 1",
    "payment_method" => "ALTER TABLE orders ADD COLUMN payment_method VARCHAR(80) NULL",
    "total" => "ALTER TABLE orders ADD COLUMN total DECIMAL(10,2) DEFAULT 0",
    "confirmed_date" => "ALTER TABLE orders ADD COLUMN confirmed_date DATETIME NULL",
    "created_at" => "ALTER TABLE orders ADD COLUMN created_at DATETIME DEFAULT CURRENT_TIMESTAMP"
];

foreach ($extraColumns as $column => $sql) {
    if (!save_order_column_exists($conn, $column)) {
        $conn->query($sql);
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

$payload = json_decode(file_get_contents("php://input"), true);
if (!$payload || empty($payload["order_id"]) || empty($payload["items"])) {
    echo json_encode(["success" => false]);
    exit;
}

$orderCode = trim((string) $payload["order_id"]);
if (!preg_match('/^ZC[0-9]{4,}$/', $orderCode)) {
    echo json_encode(["success" => false, "message" => "Invalid order id."]);
    exit;
}

$userId = (int) $_SESSION["user_id"];
$duplicate = $conn->prepare("SELECT id FROM orders WHERE order_code = ? LIMIT 1");
$duplicate->bind_param("s", $orderCode);
$duplicate->execute();
if ($duplicate->get_result()->num_rows > 0) {
    echo json_encode(["success" => false, "message" => "Duplicate order request blocked."]);
    exit;
}

$address = $payload["address"] ?? [];
$customerName = $address["full_name"] ?? "Guest Customer";
$customerContact = $address["mobile"] ?? "";
$customerEmail = $_SESSION["email"] ?? ($address["email"] ?? "");
$deliveryAddressParts = array_filter([
    $address["full_address"] ?? "",
    $address["landmark"] ?? "",
    $address["city"] ?? "",
    $address["state"] ?? "",
    $address["pincode"] ?? ""
]);
$deliveryAddress = implode(", ", $deliveryAddressParts);
$paymentStatus = $payload["payment_status"] ?? "Pending";
$orderStatus = $payload["order_status"] ?? "Placed";
$paymentMethod = $payload["payment_method"] ?? "Pending";
$orderDate = date("Y-m-d H:i:s", strtotime($payload["order_date"] ?? "now"));
$shippingDate = date("Y-m-d", strtotime($payload["shipping_date"] ?? "+2 days"));
$deliveryDate = date("Y-m-d", strtotime($payload["delivery_date"] ?? "+7 days"));
$verifiedItems = [];
$subtotal = 0.0;
$productStmt = $conn->prepare("SELECT id, name, image, price, original_price, discount_price FROM products WHERE id = ? LIMIT 1");
foreach ($payload["items"] as $item) {
    $productId = (int) ($item["product_id"] ?? 0);
    $quantity = max(1, min(20, (int) ($item["quantity"] ?? 1)));
    if ($productId <= 0) continue;
    $productStmt->bind_param("i", $productId);
    $productStmt->execute();
    $product = $productStmt->get_result()->fetch_assoc();
    if (!$product) continue;
    $price = (float) ($product["discount_price"] ?: $product["original_price"] ?: $product["price"] ?: 0);
    $lineTotal = $price * $quantity;
    $subtotal += $lineTotal;
    $verifiedItems[] = [
        "product_id" => $productId,
        "product_name" => $product["name"],
        "image" => $product["image"],
        "quantity" => $quantity,
        "price" => $price,
        "subtotal" => $lineTotal
    ];
}

if (!$verifiedItems) {
    echo json_encode(["success" => false, "message" => "No valid products found."]);
    exit;
}

$delivery = $subtotal > 0 ? 499 : 0;
$discount = round($subtotal * 0.08);
$total = $subtotal + $delivery - $discount;
$firstItem = $verifiedItems[0];
$firstProductId = (int) $firstItem["product_id"];
$firstProductName = $firstItem["product_name"];
$firstQuantity = (int) $firstItem["quantity"];
$stmt = $conn->prepare("INSERT INTO orders (order_id, order_code, user_id, product_id, customer_name, customer_email, customer_phone, customer_contact, delivery_address, payment_status, order_status, order_date, shipping_date, delivery_date, product_name, quantity, payment_method, total, total_amount) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
$stmt->bind_param("ssiisssssssssssisdd", $orderCode, $orderCode, $userId, $firstProductId, $customerName, $customerEmail, $customerContact, $customerContact, $deliveryAddress, $paymentStatus, $orderStatus, $orderDate, $shippingDate, $deliveryDate, $firstProductName, $firstQuantity, $paymentMethod, $total, $total);
$stmt->execute();

$itemStmt = $conn->prepare("INSERT INTO order_items (order_id, product_id, product_name, product_image, quantity, price, subtotal) VALUES (?, ?, ?, ?, ?, ?, ?)");
foreach ($verifiedItems as $item) {
    $productId = (int) $item["product_id"];
    $productName = (string) $item["product_name"];
    $productImage = (string) $item["image"];
    $quantity = (int) $item["quantity"];
    $price = (float) $item["price"];
    $lineSubtotal = (float) $item["subtotal"];
    $itemStmt->bind_param("sissidd", $orderCode, $productId, $productName, $productImage, $quantity, $price, $lineSubtotal);
    $itemStmt->execute();
}

echo json_encode(["success" => true, "order_id" => $orderCode]);
?>
