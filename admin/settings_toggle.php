<?php
ob_start();
session_start();
include_once("../backend/includes/csrf.php");
header("Content-Type: application/json");

function jsonToggleResponse($payload, $code = 200) {
    if (ob_get_length()) ob_clean();
    http_response_code($code);
    echo json_encode($payload);
    exit;
}

if (!isset($_SESSION["admin_id"])) {
    jsonToggleResponse(["success" => false, "message" => "Admin login required."], 401);
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    jsonToggleResponse(["success" => false, "message" => "Invalid request method."], 405);
}

if (!csrf_validate($_POST["csrf_token"] ?? "")) {
    csrf_token();
    jsonToggleResponse(["success" => false, "message" => "Invalid security token. Please refresh the Settings page and try again."], 403);
}

include("../backend/config/db.php");
include_once("../backend/includes/admin_reports.php");

function toggleSaveSetting($conn, $key, $value) {
    $stmt = $conn->prepare("INSERT INTO admin_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
    $stmt->bind_param("ss", $key, $value);
    return $stmt->execute();
}

$conn->query("CREATE TABLE IF NOT EXISTS admin_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(120) NOT NULL UNIQUE,
    setting_value TEXT NULL,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$key = trim($_POST["key"] ?? "");
$value = ($_POST["value"] ?? "0") === "1" ? "1" : "0";

$allowed = [
    "dark_mode",
    "light_mode",
    "sidebar_compact_mode",
    "order_notifications",
    "review_notifications",
    "email_notifications",
    "user_registration_alerts",
    "low_stock_alerts",
    "two_factor"
];

if (!in_array($key, $allowed, true)) {
    jsonToggleResponse(["success" => false, "message" => "Invalid setting key."], 400);
}

try {
    if ($key === "dark_mode" && $value === "1") {
        toggleSaveSetting($conn, "light_mode", "0");
    }
    if ($key === "light_mode" && $value === "1") {
        toggleSaveSetting($conn, "dark_mode", "0");
    }
    if ($key === "dark_mode" && $value === "0") {
        toggleSaveSetting($conn, "light_mode", "1");
    }
    if ($key === "light_mode" && $value === "0") {
        toggleSaveSetting($conn, "dark_mode", "1");
    }

    if (!toggleSaveSetting($conn, $key, $value)) {
        throw new RuntimeException("Setting could not be saved.");
    }
    adminReportLog($conn, "update_settings", "Updated setting " . $key . " to " . $value . ".", "settings", $key, $key);

    $state = [];
    $result = $conn->query("SELECT setting_key, setting_value FROM admin_settings");
    while ($row = $result->fetch_assoc()) {
        $state[$row["setting_key"]] = $row["setting_value"];
    }

    jsonToggleResponse(["success" => true, "message" => "Setting updated successfully", "settings" => $state]);
} catch (Throwable $error) {
    jsonToggleResponse(["success" => false, "message" => "Setting could not be saved."], 500);
}
