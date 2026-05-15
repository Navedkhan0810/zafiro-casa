<?php
require_once(__DIR__ . "/../../config/app.php");
require_once(__DIR__ . "/../includes/security.php");

$dbHost = zafiro_env("DB_HOST", "");
$dbUser = zafiro_env("DB_USER", "");
$dbPass = zafiro_env("DB_PASS", "");
$dbName = zafiro_env("DB_NAME", "");
$dbPort = (int) zafiro_env("DB_PORT", "3306");

if ($dbHost === "" || $dbUser === "" || $dbName === "") {
    error_log("Database environment variables are missing.");
    http_response_code(500);
    exit("Database configuration error. Please try again later.");
}

$conn = new mysqli($dbHost, $dbUser, $dbPass, $dbName, $dbPort);

if ($conn->connect_error) {
    error_log("Database connection failed: " . $conn->connect_error);
    http_response_code(500);
    exit("Database connection error. Please try again later.");
}
$conn->set_charset("utf8mb4");
?>
