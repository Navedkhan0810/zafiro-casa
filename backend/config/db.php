<?php
require_once(__DIR__ . "/../../config/app.php");
require_once(__DIR__ . "/../includes/security.php");

$dbHost = zafiro_env("DB_HOST", "localhost");
$dbUser = zafiro_env("DB_USER", "root");
$dbPass = zafiro_env("DB_PASS", "");
$dbName = zafiro_env("DB_NAME", "zafiro_casa_db");
$dbPort = (int) zafiro_env("DB_PORT", "3306");
$debugDb = zafiro_env("APP_ENV", "local") !== "production";

if ($dbHost === "" || $dbUser === "" || $dbName === "") {
    error_log("Database environment variables are missing.");
    http_response_code(500);
    exit($debugDb ? "Database configuration error: missing DB_HOST, DB_USER, or DB_NAME." : "Database configuration error. Please try again later.");
}

$conn = new mysqli($dbHost, $dbUser, $dbPass, $dbName, $dbPort);

if ($conn->connect_error) {
    error_log("Database connection failed: " . $conn->connect_error);
    http_response_code(500);
    exit($debugDb ? "Database connection error: " . htmlspecialchars($conn->connect_error, ENT_QUOTES, "UTF-8") : "Database connection error. Please try again later.");
}
$conn->set_charset("utf8mb4");
?>
