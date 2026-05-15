<?php
session_start();
header("Content-Type: application/json");
include("../backend/config/db.php");
include_once("../backend/includes/user_auth.php");
include_once("../backend/includes/phonepe_helper.php");
include_once("../config/app.php");

if (empty($_SESSION["user_id"])) {
    echo json_encode(["success" => false, "message" => "Please login first."]);
    exit;
}

ensurePhonePeSchema($conn);
$payload = json_decode(file_get_contents("php://input"), true) ?: [];
$items = $payload["items"] ?? [];
$address = $payload["address"] ?? [];
$requestedMethod = in_array(($payload["payment_method"] ?? "UPI"), ["UPI", "Card"], true) ? $payload["payment_method"] : "UPI";

if (phonepeMissingCredentials()) {
    echo json_encode(phonepeUnavailableResponse());
    exit;
}

if (!$items || !$address) {
    echo json_encode(["success" => false, "message" => "Invalid order data."]);
    exit;
}

$orderCode = "ZC" . date("ymdHis") . random_int(10, 99);
$userId = (int) $_SESSION["user_id"];
$customerName = trim($address["full_name"] ?? "Customer");
$customerPhone = trim($address["mobile"] ?? "");
$customerEmail = $_SESSION["email"] ?? "";
$deliveryAddress = implode(", ", array_filter([$address["full_address"] ?? "", $address["landmark"] ?? "", $address["city"] ?? "", $address["state"] ?? "", $address["pincode"] ?? ""]));
$subtotal = 0;
$cleanItems = [];

$productStmt = $conn->prepare("SELECT id, name, image, price, original_price, discount_price FROM products WHERE id = ? LIMIT 1");
foreach ($items as $item) {
    $productId = (int) ($item["product_id"] ?? 0);
    $qty = max(1, min(20, (int) ($item["quantity"] ?? 1)));
    $productStmt->bind_param("i", $productId);
    $productStmt->execute();
    $product = $productStmt->get_result()->fetch_assoc();
    if (!$product) continue;
    $price = (float) ($product["discount_price"] ?: $product["price"] ?: $product["original_price"]);
    $subtotal += $price * $qty;
    $cleanItems[] = [$productId, $product["name"], $product["image"], $qty, $price, $price * $qty];
}

if (!$cleanItems) {
    echo json_encode(["success" => false, "message" => "No valid products found."]);
    exit;
}

$delivery = 499;
$discount = round($subtotal * 0.08);
$total = max(1, $subtotal + $delivery - $discount);
$first = $cleanItems[0];
$firstProductId = (int) $first[0];
$firstProductName = (string) $first[1];
$firstQuantity = (int) $first[3];

$stmt = $conn->prepare("INSERT INTO orders (order_id, order_code, user_id, product_id, customer_name, customer_email, customer_phone, customer_contact, delivery_address, payment_status, order_status, order_date, product_name, quantity, payment_method, total, total_amount) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', 'Placed', NOW(), ?, ?, ?, ?, ?)");
$stmt->bind_param("ssiissssssisdd", $orderCode, $orderCode, $userId, $firstProductId, $customerName, $customerEmail, $customerPhone, $customerPhone, $deliveryAddress, $firstProductName, $firstQuantity, $requestedMethod, $total, $total);
$stmt->execute();
$orderDbId = (int) $conn->insert_id;

$itemStmt = $conn->prepare("INSERT INTO order_items (order_id, product_id, product_name, product_image, quantity, price, subtotal) VALUES (?, ?, ?, ?, ?, ?, ?)");
foreach ($cleanItems as $ci) {
    $ciProductId = (int) $ci[0];
    $ciName = (string) $ci[1];
    $ciImage = (string) $ci[2];
    $ciQty = (int) $ci[3];
    $ciPrice = (float) $ci[4];
    $ciSubtotal = (float) $ci[5];
    $itemStmt->bind_param("sissidd", $orderCode, $ciProductId, $ciName, $ciImage, $ciQty, $ciPrice, $ciSubtotal);
    $itemStmt->execute();
}

$token = phonepeAccessToken();
if (!$token) {
    echo json_encode(phonepeUnavailableResponse());
    exit;
}

$redirectUrl = zafiro_url("frontend/payment_callback.php?order_id=" . urlencode($orderCode));
$cfg = phonepeConfig();
$body = json_encode([
    "merchantOrderId" => $orderCode,
    "amount" => (int) round($total * 100),
    "expireAfter" => 1200,
    "paymentFlow" => [
        "type" => "PG_CHECKOUT",
        "message" => "Zafiro Casa order " . $orderCode,
        "merchantUrls" => ["redirectUrl" => $redirectUrl]
    ]
]);
$res = phonepeJson(rtrim($cfg["base_url"], "/") . "/checkout/v2/pay", "POST", ["Content-Type: application/json", "Authorization: O-Bearer " . $token], $body);
$redirect = $res["data"]["redirectUrl"] ?? $res["data"]["data"]["redirectUrl"] ?? "";

$pay = $conn->prepare("INSERT INTO payments (order_id, user_id, gateway_order_id, amount, status, response_json) VALUES (?, ?, ?, ?, 'pending', ?)");
$raw = $res["raw"] ?? "";
$pay->bind_param("iisds", $orderDbId, $userId, $orderCode, $total, $raw);
$pay->execute();

if (!$redirect) {
    error_log("PhonePe checkout redirect missing for order {$orderCode}: HTTP " . ($res["code"] ?? 0) . " " . substr((string) ($res["raw"] ?? ""), 0, 500));
}

echo json_encode(["success" => (bool) $redirect, "redirect_url" => $redirect, "order_id" => $orderCode, "message" => $redirect ? "Redirecting to payment." : "Payment service is temporarily unavailable."]);
