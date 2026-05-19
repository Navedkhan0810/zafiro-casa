<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header("Content-Type: application/json");

require_once(__DIR__ . "/../backend/config/db.php");
require_once(__DIR__ . "/../backend/includes/user_auth.php");
require_once(__DIR__ . "/../backend/includes/csrf.php");
require_once(__DIR__ . "/../backend/includes/phonepe_helper.php");

if (empty($_SESSION["user_id"])) {
    http_response_code(401);
    echo json_encode(["success" => false, "message" => "Please login first."]);
    exit;
}

$payload = json_decode(file_get_contents("php://input"), true) ?: [];
if (!csrf_validate($payload["csrf_token"] ?? ($_SERVER["HTTP_X_CSRF_TOKEN"] ?? ""))) {
    http_response_code(403);
    echo json_encode(["success" => false, "message" => "Invalid security token. Please refresh and try again."]);
    exit;
}

ensurePhonePeSchema($conn);
if (phonepeMissingCredentials()) {
    echo json_encode(phonepeUnavailableResponse());
    exit;
}

$userId = (int) $_SESSION["user_id"];
$customerEmail = (string) ($_SESSION["email"] ?? "");
$order = phonepeBuildOrderFromPayload($conn, $payload, $userId, $customerEmail);
if (empty($order["success"])) {
    echo json_encode($order);
    exit;
}

$merchantOrderId = (string) $order["order_id"];
$totalAmount = (float) $order["amount"];
$token = phonepeAccessToken();
if (!$token) {
    phonepeMarkPaymentAttempt($conn, $merchantOrderId, "failed", ["error" => "missing_access_token"]);
    echo json_encode(phonepeUnavailableResponse());
    exit;
}

$redirectUrl = phonepeConfiguredUrl("redirect_url", "payment/phonepe_callback.php", ["merchantOrderId" => $merchantOrderId]);
$body = json_encode([
    "merchantOrderId" => $merchantOrderId,
    "amount" => (int) round($totalAmount * 100),
    "expireAfter" => 1200,
    "paymentFlow" => [
        "type" => "PG_CHECKOUT",
        "message" => "Zafiro Casa order " . $merchantOrderId,
        "merchantUrls" => ["redirectUrl" => $redirectUrl]
    ]
], JSON_UNESCAPED_SLASHES);

$cfg = phonepeConfig();
$res = phonepeJson(rtrim($cfg["base_url"], "/") . "/checkout/v2/pay", "POST", [
    "Content-Type: application/json",
    "Authorization: O-Bearer " . $token
], $body);

$responseData = $res["data"] ?? [];
$redirect = $responseData["redirectUrl"] ?? $responseData["data"]["redirectUrl"] ?? "";
if ($redirect === "" && isset($responseData["redirectInfo"]["url"])) {
    $redirect = (string) $responseData["redirectInfo"]["url"];
}

phonepeMarkPaymentAttempt($conn, $merchantOrderId, $redirect ? "pending" : "failed", $res["raw"] ?? $responseData);

if ($redirect === "") {
    phonepeLog("PhonePe checkout redirect missing", ["merchant_order_id" => $merchantOrderId, "http_code" => $res["code"] ?? 0]);
}

echo json_encode([
    "success" => (bool) $redirect,
    "redirect_url" => $redirect,
    "order_id" => $merchantOrderId,
    "message" => $redirect ? "Redirecting to PhonePe." : "Payment service is temporarily unavailable."
]);
