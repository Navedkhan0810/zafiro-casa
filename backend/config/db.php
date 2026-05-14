<?php
require_once(__DIR__ . "/../includes/security.php");

$dbHost = getenv("ZAFIRO_DB_HOST") ?: "localhost";
$dbUser = getenv("ZAFIRO_DB_USER") ?: "root";
$dbPass = getenv("ZAFIRO_DB_PASS") ?: "";
$dbName = getenv("ZAFIRO_DB_NAME") ?: "zafiro_casa_db";

$conn = new mysqli($dbHost, $dbUser, $dbPass, $dbName);

if ($conn->connect_error) {
    error_log("Database connection failed: " . $conn->connect_error);
    http_response_code(500);
    exit("Database connection error. Please try again later.");
}
?>
