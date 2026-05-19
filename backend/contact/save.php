<?php
include("../config/db.php");
include_once("../includes/csrf.php");

if ($_SERVER["REQUEST_METHOD"] !== "POST" || !csrf_validate()) {
    header("Location: ../../frontend/index.php?contact=invalid");
    exit;
}

$name = trim($_POST['name'] ?? '');
if ($name === '') {
    $name = trim(($_POST['first_name'] ?? '') . ' ' . ($_POST['last_name'] ?? ''));
}
$email = trim($_POST['email'] ?? '');
$message = trim($_POST['message'] ?? '');

if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    header("Location: ../../frontend/index.php?contact=invalid");
    exit;
}

$stmt = $conn->prepare("INSERT INTO contact (name, email, message) VALUES (?, ?, ?)");
$stmt->bind_param("sss", $name, $email, $message);

if ($stmt->execute()) {
    header("Location: ../../frontend/index.php?contact=sent");
    exit;
} else {
    header("Location: ../../frontend/index.php?contact=error");
    exit;
}

$conn->close();
?>

