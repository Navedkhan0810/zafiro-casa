<?php
include("../config/db.php");

$name = trim($_POST['name'] ?? '');
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
