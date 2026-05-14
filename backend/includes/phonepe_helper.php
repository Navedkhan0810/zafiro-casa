<?php
function phonepeConfig() {
    return require(__DIR__ . "/../../config/payment_config.php");
}

function phonepeMissingCredentials() {
    $cfg = phonepeConfig();
    $required = ["merchant_id", "client_id", "client_secret", "salt_key", "salt_index", "base_url"];
    $missing = [];
    foreach ($required as $key) {
        if (trim((string) ($cfg[$key] ?? "")) === "") {
            $missing[] = $key;
        }
    }
    if ($missing) {
        error_log("PhonePe config missing: " . implode(", ", $missing));
    }
    return $missing;
}

function phonepeUnavailableResponse() {
    return ["success" => false, "message" => "Payment service is temporarily unavailable."];
}

function phonepeJson($url, $method = "GET", $headers = [], $body = null) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => 30
    ]);
    if ($body !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    $raw = curl_exec($ch);
    $err = curl_error($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($err) return ["ok" => false, "error" => $err, "code" => $code, "raw" => $raw];
    $json = json_decode((string) $raw, true);
    return ["ok" => $code >= 200 && $code < 300, "data" => $json, "code" => $code, "raw" => $raw];
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
        error_log("PhonePe token request failed: HTTP " . ($res["code"] ?? 0) . " " . substr((string) ($res["raw"] ?? $res["error"] ?? ""), 0, 500));
    }
    return $res["data"]["access_token"] ?? $res["data"]["data"]["access_token"] ?? null;
}

function ensurePhonePeSchema($conn) {
    foreach ([
        "payment_id" => "VARCHAR(255) NULL",
        "transaction_id" => "VARCHAR(255) NULL",
        "paid_at" => "DATETIME NULL"
    ] as $column => $definition) {
        $stmt = $conn->prepare("SELECT COUNT(*) AS total FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'orders' AND column_name = ?");
        $stmt->bind_param("s", $column);
        $stmt->execute();
        if ((int) ($stmt->get_result()->fetch_assoc()["total"] ?? 0) === 0) {
            $conn->query("ALTER TABLE orders ADD COLUMN `$column` $definition");
        }
    }
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

function phonepeVerifyAndUpdate($conn, $merchantOrderId) {
    $token = phonepeAccessToken();
    if (!$token) return ["success" => false, "status" => "failed"];
    $cfg = phonepeConfig();
    $url = rtrim($cfg["base_url"], "/") . "/checkout/v2/order/" . rawurlencode($merchantOrderId) . "/status";
    $res = phonepeJson($url, "GET", ["Content-Type: application/json", "Authorization: O-Bearer " . $token]);
    if (empty($res["ok"])) {
        error_log("PhonePe verify failed for {$merchantOrderId}: HTTP " . ($res["code"] ?? 0));
    }
    $data = $res["data"] ?? [];
    $state = strtoupper($data["state"] ?? $data["data"]["state"] ?? "");
    $paymentId = $data["orderId"] ?? $data["data"]["orderId"] ?? "";
    $transactionId = $data["paymentDetails"][0]["transactionId"] ?? $data["data"]["paymentDetails"][0]["transactionId"] ?? "";
    $status = $state === "COMPLETED" ? "paid" : ($state === "FAILED" ? "failed" : "pending");
    $paidSql = $status === "paid" ? ", paid_at = NOW()" : "";
    $stmt = $conn->prepare("UPDATE orders SET payment_status = ?, payment_method = 'PhonePe', payment_id = ?, transaction_id = ? $paidSql WHERE order_code = ? OR order_id = ?");
    $stmt->bind_param("sssss", $status, $paymentId, $transactionId, $merchantOrderId, $merchantOrderId);
    $stmt->execute();
    $pay = $conn->prepare("UPDATE payments SET gateway_payment_id = ?, transaction_id = ?, status = ?, response_json = ? WHERE gateway_order_id = ?");
    $raw = json_encode($data);
    $pay->bind_param("sssss", $paymentId, $transactionId, $status, $raw, $merchantOrderId);
    $pay->execute();
    return ["success" => $status === "paid", "status" => $status, "response" => $data];
}
