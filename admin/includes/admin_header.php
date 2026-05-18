<?php
$currentAdminPage = basename($_SERVER['PHP_SELF']);
$adminBodyClasses = [];
$adminCsrfToken = function_exists('csrf_token') ? csrf_token() : '';
if (isset($conn) && $conn instanceof mysqli) {
    $settingsResult = $conn->query("SELECT setting_key, setting_value FROM admin_settings WHERE setting_key IN ('dark_mode', 'sidebar_compact_mode')");
    $headerSettings = [];
    if ($settingsResult) {
        while ($settingRow = $settingsResult->fetch_assoc()) {
            $headerSettings[$settingRow["setting_key"]] = $settingRow["setting_value"];
        }
    }
    if (($headerSettings["dark_mode"] ?? "0") === "1") {
        $adminBodyClasses[] = "admin-dark-mode";
    }
    if (($headerSettings["sidebar_compact_mode"] ?? "0") === "1") {
        $adminBodyClasses[] = "admin-sidebar-compact";
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo htmlspecialchars($adminCsrfToken); ?>">
    <title>Zafiro Casa Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/admin.css?v=53">
    <link rel="stylesheet" href="../assets/css/zafiro-popup.css?v=1">
</head>
<body class="admin-shell <?php echo htmlspecialchars(implode(' ', $adminBodyClasses)); ?>" data-csrf="<?php echo htmlspecialchars($adminCsrfToken); ?>">
<div class="admin-layout">
<button class="admin-sidebar-toggle" type="button" aria-label="Open admin menu" aria-expanded="false">
    <i class="fa-solid fa-bars"></i>
</button>
<div class="admin-sidebar-backdrop" data-admin-sidebar-close></div>
