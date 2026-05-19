<?php
require_once(__DIR__ . "/../../config/app.php");

function phonepeConfig() {
    return require(__DIR__ . "/../../config/payment_config.php");
}

function phonepeLog($message, array $context = []) {
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $message;
    if ($context) {
        $line .= ' ' . json_encode($context, JSON_UNESCAPED_SLASHES);
    }
    error_log($line);

    $logDir = zafiro_project_root() . DIRECTORY_SEPARATOR . 'logs';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }
    if (is_dir($logDir) && is_writable($logDir)) {
        @file_put_contents($logDir . DIRECTORY_SEPARATOR . 'phonepe.log', $line . PHP_EOL, FILE_APPEND | LOCK_EX);
    }
}

function phonepeMissingCredentials() {
    $cfg = phonepeConfig();
    $required = ["client_id", "client_secret", "client_version", "base_url"];
    $missing = [];
    foreach ($required as $key) {
        if (trim((string) ($cfg[$key] ?? "")) === "") {
            $missing[] = $key;
        }
    }
    if ($missing) {
        phonepeLog("PhonePe config missing", ["missing" => $missing]);
    }
    return $missing;
}

function phonepeUnavailableResponse() {
    return ["success" => false, "message" => "Payment service is temporarily unavailable."];
}

function phonepePublicUrl($path = '') {
    $base = rtrim(zafiro_app_url(), '/');
    if ($base === '') {
        $scheme = zafiro_is_https() ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $base = $scheme . '://' . $host;
    }

    $basePath = trim(zafiro_base_path(), '/');
    if ($basePath !== '') {
        $baseParts = parse_url($base);
        $currentPath = trim((string) ($baseParts['path'] ?? ''), '/');
        if ($currentPath === '' || !preg_match('#(^|/)' . preg_quote($basePath, '#') . '$#', $currentPath)) {
            $base .= '/' . $basePath;
        }
    }

    return rtrim($base, '/') . '/' . ltrim($path, '/');
}

function phonepeConfiguredUrl($key, $fallbackPath, array $query = []) {
    $cfg = phonepeConfig();
    $url = trim((string) ($cfg[$key] ?? ''));
    if ($url === '' && $key === 'redirect_url') {
        $url = trim((string) ($cfg['callback_url'] ?? ''));
    }
    if ($url === '') {
        $url = phonepePublicUrl($fallbackPath);
    }
    if ($query) {
        $url .= (str_contains($url, '?') ? '&' : '?') . http_build_query($query);
    }
    return $url;
}

function phonepeJson($url, $method = "GET", $headers = [], $body = null) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_CONNECTTIMEOUT => 10
    ]);
    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    }
    $raw = curl_exec($ch);
    $err = curl_error($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($err) {
        return ["ok" => false, "error" => $err, "code" => $code, "raw" => $raw];
    }
    $json = json_decode((string) $raw, true);
    return ["ok" => $code >= 200 && $code < 300, "data" => is_array($json) ? $json : [], "code" => $code, "raw" => $raw];
}

function phonepeAccessToken() {
    $cfg = phonepeConfig();
    if (phonepeMissingCredentials()) {
        return null;
    }

    $url = rtrim($cfg["base_url"], "/") . "/v1/oauth/token";
    $body = http_build_query([
        "client_id" => $cfg["client_id"],
        "client_secret" => $cfg["client_secret"],
        "client_version" => $cfg["client_version"],
        "grant_type" => "client_credentials"
    ]);
    $res = phonepeJson($url, "POST", ["Content-Type: application/x-www-form-urlencoded"], $body);
    if (empty($res["ok"])) {
        phonepeLog("PhonePe token request failed", ["http_code" => $res["code"] ?? 0, "error" => $res["error"] ?? null]);
    }
    return $res["data"]["access_token"] ?? $res["data"]["data"]["access_token"] ?? null;
}

function phonepeColumnExists($conn, $table, $column) {
    $stmt = $conn->prepare("SELECT COUNT(*) AS total FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?");
    $stmt->bind_param("ss", $table, $column);
    $stmt->execute();
    return (int) ($stmt->get_result()->fetch_assoc()["total"] ?? 0) > 0;
}

function ensurePhonePeSchema($conn) {
    foreach ([
        "payment_id" => "VARCHAR(255) NULL",
        "transaction_id" => "VARCHAR(255) NULL",
        "paid_at" => "DATETIME NULL"
    ] as $column => $definition) {
        if (!phonepeColumnExists($conn, 'orders', $column)) {
            $conn->query("ALTER TABLE orders ADD COLUMN `$column` $definition");
        }
    }

    $conn->query("CREATE TABLE IF NOT EXISTS payments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        order_id INT NULL,
        user_id INT NULL,
        payment_gateway VARCHAR(50) DEFAULT 'PhonePe',
        merchant_order_id VARCHAR(255) NULL,
        gateway_order_id VARCHAR(255) NULL,
        gateway_payment_id VARCHAR(255) NULL,
        transaction_id VARCHAR(255) NULL,
        amount DECIMAL(10,2) DEFAULT 0,
        status VARCHAR(50) DEFAULT 'pending',
        response_json TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_payments_merchant_order_id (merchant_order_id),
        INDEX idx_payments_gateway_order_id (gateway_order_id),
        INDEX idx_payments_order_id (order_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    foreach ([
        "merchant_order_id" => "VARCHAR(255) NULL",
        "gateway_order_id" => "VARCHAR(255) NULL",
        "gateway_payment_id" => "VARCHAR(255) NULL",
        "transaction_id" => "VARCHAR(255) NULL",
        "payment_gateway" => "VARCHAR(50) DEFAULT 'PhonePe'",
        "response_json" => "TEXT NULL",
        "updated_at" => "TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP"
    ] as $column => $definition) {
        if (!phonepeColumnExists($conn, 'payments', $column)) {
            $conn->query("ALTER TABLE payments ADD COLUMN `$column` $definition");
        }
    }
}

function phonepeExtractStatus(array $data) {
    $payload = isset($data['data']) && is_array($data['data']) ? $data['data'] : $data;
    $state = strtoupper((string) ($payload['state'] ?? $payload['status'] ?? ''));
    if (in_array($state, ['COMPLETED', 'SUCCESS', 'SUCCESSFUL', 'PAID'], true)) {
        return 'paid';
    }
    if (in_array($state, ['FAILED', 'FAILURE', 'CANCELLED', 'CANCELED'], true)) {
        return 'failed';
    }
    return 'pending';
}

function phonepeVerifyAndUpdate($conn, $merchantOrderId) {
    $merchantOrderId = trim((string) $merchantOrderId);
    if ($merchantOrderId === '') {
        return ["success" => false, "status" => "failed", "message" => "Missing order id."];
    }

    ensurePhonePeSchema($conn);
    $token = phonepeAccessToken();
    if (!$token) {
        return ["success" => false, "status" => "failed", "message" => "Payment verification unavailable."];
    }

    $cfg = phonepeConfig();
    $url = rtrim($cfg["base_url"], "/") . "/checkout/v2/order/" . rawurlencode($merchantOrderId) . "/status";
    $res = phonepeJson($url, "GET", ["Content-Type: application/json", "Authorization: O-Bearer " . $token]);
    if (empty($res["ok"])) {
        phonepeLog("PhonePe verify failed", ["merchant_order_id" => $merchantOrderId, "http_code" => $res["code"] ?? 0]);
    }

    $data = $res["data"] ?? [];
    $payload = isset($data['data']) && is_array($data['data']) ? $data['data'] : $data;
    $status = phonepeExtractStatus($data);
    $gatewayOrderId = (string) ($payload["orderId"] ?? $payload["merchantOrderId"] ?? $merchantOrderId);
    $paymentDetails = $payload["paymentDetails"] ?? [];
    $firstPayment = is_array($paymentDetails) && isset($paymentDetails[0]) && is_array($paymentDetails[0]) ? $paymentDetails[0] : [];
    $gatewayPaymentId = (string) ($firstPayment["paymentId"] ?? $payload["paymentId"] ?? $gatewayOrderId);
    $transactionId = (string) ($firstPayment["transactionId"] ?? $payload["transactionId"] ?? "");
    $raw = json_encode($data, JSON_UNESCAPED_SLASHES);
    $paidSql = $status === "paid" ? ", paid_at = NOW()" : "";

    $stmt = $conn->prepare("UPDATE orders SET payment_status = ?, payment_method = 'PhonePe', payment_id = ?, transaction_id = ? $paidSql WHERE order_code = ? OR order_id = ?");
    $stmt->bind_param("sssss", $status, $gatewayPaymentId, $transactionId, $merchantOrderId, $merchantOrderId);
    $stmt->execute();

    $pay = $conn->prepare("UPDATE payments SET gateway_payment_id = ?, transaction_id = ?, status = ?, response_json = ?, updated_at = NOW() WHERE merchant_order_id = ? OR gateway_order_id = ?");
    $pay->bind_param("ssssss", $gatewayPaymentId, $transactionId, $status, $raw, $merchantOrderId, $merchantOrderId);
    $pay->execute();

    return ["success" => $status === "paid", "status" => $status, "response" => $data, "order_id" => $merchantOrderId];
}

function phonepeBuildOrderFromPayload($conn, array $payload, $userId, $customerEmail) {
    $items = $payload["items"] ?? [];
    $address = $payload["address"] ?? [];
    if (!$items || !$address) {
        return ["success" => false, "message" => "Invalid order data."];
    }

    $customerName = trim((string) ($address["full_name"] ?? "Customer"));
    $customerPhone = trim((string) ($address["mobile"] ?? ""));
    $deliveryAddress = implode(", ", array_filter([
        trim((string) ($address["full_address"] ?? "")),
        trim((string) ($address["landmark"] ?? "")),
        trim((string) ($address["city"] ?? "")),
        trim((string) ($address["state"] ?? "")),
        trim((string) ($address["pincode"] ?? ""))
    ]));

    if ($customerName === '' || $customerPhone === '' || $deliveryAddress === '') {
        return ["success" => false, "message" => "Please complete your delivery details."];
    }

    $subtotal = 0.0;
    $cleanItems = [];
    $productStmt = $conn->prepare("SELECT id, name, image, price, original_price, discount_price FROM products WHERE id = ? LIMIT 1");
    foreach ($items as $item) {
        $productId = (int) ($item["product_id"] ?? 0);
        $qty = max(1, min(20, (int) ($item["quantity"] ?? 1)));
        if ($productId <= 0) {
            continue;
        }
        $productStmt->bind_param("i", $productId);
        $productStmt->execute();
        $product = $productStmt->get_result()->fetch_assoc();
        if (!$product) {
            continue;
        }
        $price = (float) ($product["discount_price"] ?: $product["price"] ?: $product["original_price"]);
        if ($price <= 0) {
            continue;
        }
        $lineTotal = $price * $qty;
        $subtotal += $lineTotal;
        $cleanItems[] = [$productId, (string) $product["name"], (string) $product["image"], $qty, $price, $lineTotal];
    }

    if (!$cleanItems) {
        return ["success" => false, "message" => "No valid products found."];
    }

    $delivery = 499.0;
    $discount = round($subtotal * 0.08);
    $total = max(1.0, $subtotal + $delivery - $discount);
    $orderCode = "ZC" . date("ymdHis") . random_int(100, 999);
    $first = $cleanItems[0];
    $paymentMethod = "PhonePe";

    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare("INSERT INTO orders (order_id, order_code, user_id, product_id, customer_name, customer_email, customer_phone, customer_contact, delivery_address, payment_status, order_status, order_date, product_name, quantity, payment_method, total, total_amount) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', 'Placed', NOW(), ?, ?, ?, ?, ?)");
        $firstProductId = (int) $first[0];
        $firstProductName = (string) $first[1];
        $firstQuantity = (int) $first[3];
        $stmt->bind_param("ssiissssssisdd", $orderCode, $orderCode, $userId, $firstProductId, $customerName, $customerEmail, $customerPhone, $customerPhone, $deliveryAddress, $firstProductName, $firstQuantity, $paymentMethod, $total, $total);
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

        $emptyResponse = json_encode(["event" => "created_before_phonepe_redirect"], JSON_UNESCAPED_SLASHES);
        $paymentStatus = "pending";
        $pay = $conn->prepare("INSERT INTO payments (order_id, user_id, payment_gateway, merchant_order_id, gateway_order_id, amount, status, response_json) VALUES (?, ?, 'PhonePe', ?, ?, ?, ?, ?)");
        $pay->bind_param("iissdss", $orderDbId, $userId, $orderCode, $orderCode, $total, $paymentStatus, $emptyResponse);
        $pay->execute();

        $conn->commit();
    } catch (Throwable $e) {
        $conn->rollback();
        phonepeLog("Order creation failed before PhonePe redirect", ["error" => $e->getMessage()]);
        return ["success" => false, "message" => "Could not create order. Please try again."];
    }

    return ["success" => true, "order_id" => $orderCode, "db_id" => $orderDbId, "amount" => $total];
}

function phonepeMarkPaymentAttempt($conn, $merchantOrderId, $status, $response) {
    $raw = is_string($response) ? $response : json_encode($response, JSON_UNESCAPED_SLASHES);
    $stmt = $conn->prepare("UPDATE payments SET status = ?, response_json = ?, updated_at = NOW() WHERE merchant_order_id = ? OR gateway_order_id = ?");
    $stmt->bind_param("ssss", $status, $raw, $merchantOrderId, $merchantOrderId);
    $stmt->execute();

    $order = $conn->prepare("UPDATE orders SET payment_status = ? WHERE order_code = ? OR order_id = ?");
    $order->bind_param("sss", $status, $merchantOrderId, $merchantOrderId);
    $order->execute();
}

