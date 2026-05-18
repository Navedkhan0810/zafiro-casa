<?php
function ensureAdminReportsTable(mysqli $conn): void {
    $conn->query("CREATE TABLE IF NOT EXISTS admin_reports (
        id INT AUTO_INCREMENT PRIMARY KEY,
        admin_id INT NULL,
        admin_name VARCHAR(120) NULL,
        admin_email VARCHAR(160) NULL,
        action_type VARCHAR(80) NOT NULL,
        description TEXT NOT NULL,
        item_type VARCHAR(80) NULL,
        item_id VARCHAR(120) NULL,
        item_name VARCHAR(180) NULL,
        ip_address VARCHAR(60) NULL,
        user_agent VARCHAR(255) NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_action_type (action_type),
        INDEX idx_created_at (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function adminReportLog(mysqli $conn, string $actionType, string $description, string $itemType = "", $itemId = null, string $itemName = ""): void {
    ensureAdminReportsTable($conn);
    $adminId = isset($_SESSION["admin_id"]) ? (int) $_SESSION["admin_id"] : null;
    $adminName = $_SESSION["admin_name"] ?? "";
    $adminEmail = $_SESSION["admin_email"] ?? "";
    $itemIdValue = $itemId === null ? "" : (string) $itemId;
    $ip = $_SERVER["REMOTE_ADDR"] ?? "";
    $agent = substr($_SERVER["HTTP_USER_AGENT"] ?? "", 0, 255);
    $stmt = $conn->prepare("INSERT INTO admin_reports (admin_id, admin_name, admin_email, action_type, description, item_type, item_id, item_name, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    if (!$stmt) return;
    $stmt->bind_param("isssssssss", $adminId, $adminName, $adminEmail, $actionType, $description, $itemType, $itemIdValue, $itemName, $ip, $agent);
    $stmt->execute();
}
?>
