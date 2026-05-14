<?php
session_start();
include_once(__DIR__ . "/../backend/includes/csrf.php");

if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    csrf_require();
}

csrf_start_form_injection();
?>
