<?php
include("auth.php");
include("../backend/config/db.php");
include_once("../backend/includes/admin_reports.php");

$settingsCsrfToken = csrf_token();
$message = "";
$messageType = "";

function settingsColumnExists($conn, $table, $column) {
    $stmt = $conn->prepare("SELECT COUNT(*) AS total FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?");
    $stmt->bind_param("ss", $table, $column);
    $stmt->execute();
    return (int) ($stmt->get_result()->fetch_assoc()["total"] ?? 0) > 0;
}

function ensureSettingsSchema($conn) {
    foreach ([
        "phone" => "VARCHAR(30) NULL",
        "profile_image" => "VARCHAR(255) NULL",
        "last_login" => "DATETIME NULL"
    ] as $column => $definition) {
        if (!settingsColumnExists($conn, "admins", $column)) {
            $conn->query("ALTER TABLE admins ADD COLUMN `$column` $definition");
        }
    }

    $conn->query("CREATE TABLE IF NOT EXISTS admin_settings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        setting_key VARCHAR(120) NOT NULL UNIQUE,
        setting_value TEXT NULL,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $conn->query("CREATE TABLE IF NOT EXISTS admin_login_activity (
        id INT AUTO_INCREMENT PRIMARY KEY,
        admin_id INT NOT NULL,
        session_id VARCHAR(128) NOT NULL,
        ip_address VARCHAR(60) NULL,
        user_agent VARCHAR(255) NULL,
        login_time DATETIME DEFAULT CURRENT_TIMESTAMP,
        logout_time DATETIME NULL,
        is_active TINYINT(1) DEFAULT 1
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function defaultAdminSettings() {
    return [
        "website_name" => "Zafiro Casa Luxury Living",
        "website_logo" => "",
        "website_description" => "Premium furniture and luxury living products for modern homes.",
        "contact_email" => "support@zafirocasa.com",
        "contact_phone" => "+91 91716 17974",
        "website_address" => "Zafiro Casa Luxury Living showroom address",
        "dark_mode" => "0",
        "light_mode" => "1",
        "theme_color" => "#c8a96b",
        "sidebar_compact_mode" => "0",
        "order_notifications" => "1",
        "review_notifications" => "1",
        "email_notifications" => "0",
        "user_registration_alerts" => "1",
        "low_stock_alerts" => "1",
        "two_factor" => "0"
    ];
}

function getAdminSettings($conn) {
    $settings = defaultAdminSettings();
    $result = $conn->query("SELECT setting_key, setting_value FROM admin_settings");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $settings[$row["setting_key"]] = $row["setting_value"];
        }
    }
    if (isset($settings["compact_sidebar"])) $settings["sidebar_compact_mode"] = $settings["compact_sidebar"];
    if (isset($settings["user_alerts"])) $settings["user_registration_alerts"] = $settings["user_alerts"];
    if (isset($settings["stock_alerts"])) $settings["low_stock_alerts"] = $settings["stock_alerts"];
    return $settings;
}

function saveSetting($conn, $key, $value) {
    $stmt = $conn->prepare("INSERT INTO admin_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
    $stmt->bind_param("ss", $key, $value);
    return $stmt->execute();
}

function uploadSettingsImage($field, $folder) {
    $error = "";
    $path = zafiro_secure_upload($_FILES[$field] ?? [], "../uploads/" . $folder, "../uploads/" . $folder, ["jpg", "jpeg", "png", "webp"], ["image/jpeg", "image/png", "image/webp"], 2 * 1024 * 1024, $field, $error);
    if ($error !== "") throw new RuntimeException($error);
    return $path;
}

function buildSqlBackup($conn) {
    $sql = "-- Zafiro Casa database backup\n-- Generated: " . date("Y-m-d H:i:s") . "\n\nSET FOREIGN_KEY_CHECKS=0;\n\n";
    $tables = $conn->query("SHOW TABLES");
    while ($tableRow = $tables->fetch_array()) {
        $table = $tableRow[0];
        $create = $conn->query("SHOW CREATE TABLE `$table`")->fetch_assoc();
        $sql .= "DROP TABLE IF EXISTS `$table`;\n" . $create["Create Table"] . ";\n\n";
        $rows = $conn->query("SELECT * FROM `$table`");
        while ($row = $rows->fetch_assoc()) {
            $columns = array_map(fn($column) => "`" . $conn->real_escape_string($column) . "`", array_keys($row));
            $values = array_map(function ($value) use ($conn) {
                return $value === null ? "NULL" : "'" . $conn->real_escape_string($value) . "'";
            }, array_values($row));
            $sql .= "INSERT INTO `$table` (" . implode(", ", $columns) . ") VALUES (" . implode(", ", $values) . ");\n";
        }
        $sql .= "\n";
    }
    return $sql . "SET FOREIGN_KEY_CHECKS=1;\n";
}

function downloadTextFile($fileName, $mimeType, $content) {
    header("Content-Type: " . $mimeType);
    header("Content-Disposition: attachment; filename=\"" . $fileName . "\"");
    header("Content-Length: " . strlen($content));
    echo $content;
    exit;
}

function clearAdminCache() {
    $cacheDir = realpath(__DIR__ . "/../uploads/cache");
    if ($cacheDir === false) {
        mkdir(__DIR__ . "/../uploads/cache", 0755, true);
        return 0;
    }
    $allowedRoot = realpath(__DIR__ . "/../uploads");
    if (!$allowedRoot || strpos($cacheDir, $allowedRoot) !== 0) {
        throw new RuntimeException("Cache path is not safe.");
    }
    $deleted = 0;
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($cacheDir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $item) {
        if ($item->isFile()) {
            unlink($item->getPathname());
            $deleted++;
        }
    }
    return $deleted;
}

function adminSettingsDebug($message, $adminId, $stmt = null) {
    $debug = " admin_id=" . $adminId;
    if ($stmt instanceof mysqli_stmt) {
        $debug .= " sql_error=" . $stmt->error . " affected_rows=" . $stmt->affected_rows;
    }
    return $message . $debug;
}

ensureSettingsSchema($conn);
$settings = getAdminSettings($conn);
$adminId = (int) ($_SESSION["admin_id"] ?? 0);

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = $_POST["action"] ?? "save_settings";

    try {
        if ($action === "change_password") {
            $currentPassword = $_POST["current_password"] ?? "";
            $newPassword = $_POST["new_password"] ?? "";
            $confirmPassword = $_POST["confirm_password"] ?? "";

            $stmt = $conn->prepare("SELECT password FROM admins WHERE admin_id = ? LIMIT 1");
            $stmt->bind_param("i", $adminId);
            $stmt->execute();
            $adminPassword = $stmt->get_result()->fetch_assoc()["password"] ?? "";

            if (!$adminPassword || !password_verify($currentPassword, $adminPassword)) {
                throw new RuntimeException("Current password is incorrect.");
            }
            if (strlen($newPassword) < 6) {
                throw new RuntimeException("New password must be at least 6 characters.");
            }
            if ($newPassword !== $confirmPassword) {
                throw new RuntimeException("New password and confirm password do not match.");
            }

            $hash = password_hash($newPassword, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE admins SET password = ? WHERE admin_id = ?");
            $stmt->bind_param("si", $hash, $adminId);
            if (!$stmt->execute()) {
                throw new RuntimeException("Password could not be updated.");
            }
            adminReportLog($conn, "update_settings", "Changed admin password.", "admin", $adminId, $_SESSION["admin_username"] ?? "");
            $message = "Password changed successfully.";
            $messageType = "success";
        } elseif ($action === "reset_defaults") {
            foreach (defaultAdminSettings() as $key => $value) {
                saveSetting($conn, $key, $value);
            }
            $settings = getAdminSettings($conn);
            adminReportLog($conn, "update_settings", "Restored default website settings.", "settings");
            $message = "Default website settings restored.";
            $messageType = "success";
        } elseif ($action === "backup_database") {
            downloadTextFile("zafiro_casa_backup_" . date("Ymd_His") . ".sql", "application/sql", buildSqlBackup($conn));
        } elseif ($action === "export_data") {
            $export = [];
            foreach (["admins", "admin_settings", "users", "products", "categories", "orders", "order_items", "product_reviews"] as $table) {
                $exists = $conn->query("SELECT COUNT(*) AS total FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = '$table'");
                if ((int) ($exists->fetch_assoc()["total"] ?? 0) > 0) {
                    $export[$table] = [];
                    $rows = $conn->query("SELECT * FROM `$table`");
                    while ($row = $rows->fetch_assoc()) {
                        if (isset($row["password"])) $row["password"] = "[hidden]";
                        $export[$table][] = $row;
                    }
                }
            }
            downloadTextFile("zafiro_casa_export_" . date("Ymd_His") . ".json", "application/json", json_encode($export, JSON_PRETTY_PRINT));
        } elseif ($action === "clear_cache") {
            $deleted = clearAdminCache();
            adminReportLog($conn, "update_settings", "Cleared admin cache. Files removed: " . $deleted . ".", "settings");
            $message = "Cache cleared successfully. Files removed: " . $deleted . ".";
            $messageType = "success";
        } else {
            if ($adminId <= 0) {
                throw new RuntimeException("Missing admin session admin_id.");
            }

            $adminName = trim($_POST["admin_name"] ?? "");
            $adminUsername = trim($_POST["admin_username"] ?? "");
            $adminEmail = trim($_POST["admin_email"] ?? "");
            $adminPhone = trim($_POST["admin_phone"] ?? "");

            if ($adminName === "" || $adminUsername === "" || !filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
                throw new RuntimeException("Admin name, username, and valid email are required.");
            }

            $duplicate = $conn->prepare("SELECT admin_id FROM admins WHERE (email = ? OR username = ?) AND admin_id != ? LIMIT 1");
            $duplicate->bind_param("ssi", $adminEmail, $adminUsername, $adminId);
            $duplicate->execute();
            if ($duplicate->get_result()->num_rows > 0) {
                throw new RuntimeException("Email or username already belongs to another admin.");
            }

            $profileImage = uploadSettingsImage("admin_profile_image", "admin");
            if ($profileImage !== "") {
                $stmt = $conn->prepare("UPDATE admins SET name = ?, username = ?, email = ?, phone = ?, profile_image = ? WHERE admin_id = ?");
                $stmt->bind_param("sssssi", $adminName, $adminUsername, $adminEmail, $adminPhone, $profileImage, $adminId);
            } else {
                $stmt = $conn->prepare("UPDATE admins SET name = ?, username = ?, email = ?, phone = ? WHERE admin_id = ?");
                $stmt->bind_param("ssssi", $adminName, $adminUsername, $adminEmail, $adminPhone, $adminId);
            }
            if (!$stmt->execute()) {
                throw new RuntimeException(adminSettingsDebug("Admin profile could not be saved.", $adminId, $stmt));
            }

            $fresh = $conn->prepare("SELECT name, username, email, phone, profile_image FROM admins WHERE admin_id = ? LIMIT 1");
            $fresh->bind_param("i", $adminId);
            if (!$fresh->execute()) {
                throw new RuntimeException(adminSettingsDebug("Admin profile saved but fresh fetch failed.", $adminId, $fresh));
            }
            $freshAdmin = $fresh->get_result()->fetch_assoc();
            if (!$freshAdmin) {
                throw new RuntimeException(adminSettingsDebug("Admin profile update failed because admin row was not found.", $adminId, $stmt));
            }
            if ($freshAdmin["name"] !== $adminName || $freshAdmin["username"] !== $adminUsername || $freshAdmin["email"] !== $adminEmail || (string) ($freshAdmin["phone"] ?? "") !== $adminPhone) {
                throw new RuntimeException(adminSettingsDebug("Admin profile update did not persist.", $adminId, $stmt));
            }

            $_SESSION["admin_name"] = $freshAdmin["name"];
            $_SESSION["admin_username"] = $freshAdmin["username"];
            $_SESSION["admin_email"] = $freshAdmin["email"];

            $logoPath = uploadSettingsImage("website_logo", "site");
            $saveMap = [
                "website_name" => trim($_POST["website_name"] ?? ""),
                "website_description" => trim($_POST["website_description"] ?? ""),
                "contact_email" => trim($_POST["contact_email"] ?? ""),
                "contact_phone" => trim($_POST["contact_phone"] ?? ""),
                "website_address" => trim($_POST["website_address"] ?? ""),
                "dark_mode" => isset($_POST["dark_mode"]) ? "1" : "0",
                "light_mode" => isset($_POST["light_mode"]) ? "1" : "0",
                "theme_color" => trim($_POST["theme_color"] ?? "#c8a96b"),
                "sidebar_compact_mode" => isset($_POST["sidebar_compact_mode"]) ? "1" : "0",
                "order_notifications" => isset($_POST["order_notifications"]) ? "1" : "0",
                "review_notifications" => isset($_POST["review_notifications"]) ? "1" : "0",
                "email_notifications" => isset($_POST["email_notifications"]) ? "1" : "0",
                "user_registration_alerts" => isset($_POST["user_registration_alerts"]) ? "1" : "0",
                "low_stock_alerts" => isset($_POST["low_stock_alerts"]) ? "1" : "0",
                "two_factor" => isset($_POST["two_factor"]) ? "1" : "0"
            ];
            if ($saveMap["dark_mode"] === "1") $saveMap["light_mode"] = "0";
            if ($saveMap["light_mode"] === "1") $saveMap["dark_mode"] = "0";
            if ($logoPath !== "") {
                $saveMap["website_logo"] = $logoPath;
            }

            foreach ($saveMap as $key => $value) {
                if (!saveSetting($conn, $key, $value)) {
                    throw new RuntimeException("Settings could not be saved.");
                }
            }
            $settings = getAdminSettings($conn);
            adminReportLog($conn, "update_settings", "Updated admin profile and website settings.", "settings", $adminId, $adminName);
            $message = "Admin profile updated successfully.";
            $messageType = "success";
        }
    } catch (Throwable $error) {
        $message = $error->getMessage();
        $messageType = "error";
    }
}

$admin = [
    "name" => $_SESSION["admin_name"] ?? "Zafiro Admin",
    "username" => $_SESSION["admin_username"] ?? "admin",
    "email" => $_SESSION["admin_email"] ?? "admin@zafirocasa.com",
    "phone" => "",
    "profile_image" => ""
];

$stmt = $conn->prepare("SELECT name, username, email, phone, profile_image, last_login FROM admins WHERE admin_id = ? LIMIT 1");
$stmt->bind_param("i", $adminId);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
if ($row) {
    $admin = array_merge($admin, $row);
}

$loginHistory = [];
$stmt = $conn->prepare("SELECT login_time, logout_time, ip_address, is_active FROM admin_login_activity WHERE admin_id = ? ORDER BY login_time DESC LIMIT 5");
$stmt->bind_param("i", $adminId);
$stmt->execute();
$historyResult = $stmt->get_result();
while ($historyRow = $historyResult->fetch_assoc()) {
    $loginHistory[] = $historyRow;
}

$activeSessions = [];
$stmt = $conn->prepare("SELECT login_time, ip_address, user_agent FROM admin_login_activity WHERE admin_id = ? AND is_active = 1 ORDER BY login_time DESC");
$stmt->bind_param("i", $adminId);
$stmt->execute();
$sessionResult = $stmt->get_result();
while ($sessionRow = $sessionResult->fetch_assoc()) {
    $activeSessions[] = $sessionRow;
}

$systemStatus = $conn->ping() ? "Online" : "Offline";
$adminVersion = "Admin Panel v1.0.0";

include("includes/admin_header.php");
include("includes/admin_sidebar.php");
?>
<main class="admin-main">
    <header class="admin-topbar admin-dark-topbar">
        <div>
            <span>Zafiro Casa Luxury Living</span>
            <h1>Settings</h1>
            <p>Manage website preferences, admin settings, and system configuration.</p>
        </div>
    </header>

    <?php if ($message): ?>
        <div class="admin-popup <?php echo $messageType === 'success' ? 'success' : 'error'; ?>"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>

    <form method="POST" action="settings.php" enctype="multipart/form-data" class="admin-settings-page">
        <input type="hidden" name="action" value="save_settings">
        <section class="admin-settings-card admin-settings-profile">
            <div class="admin-settings-card-head">
                <div>
                    <span>Account</span>
                    <h2>Admin Profile Settings</h2>
                </div>
            </div>
            <div class="admin-settings-profile-layout">
                <div class="admin-settings-avatar">
                    <div class="admin-settings-avatar-circle has-preview">
                        <?php if (!empty($admin["profile_image"])): ?>
                            <img id="adminProfilePreview" src="<?php echo htmlspecialchars($admin["profile_image"]); ?>" alt="Admin profile">
                            <i class="fa-regular fa-circle-user admin-preview-placeholder"></i>
                        <?php else: ?>
                            <img id="adminProfilePreview" src="" alt="Admin profile" class="is-hidden">
                            <i class="fa-regular fa-circle-user admin-preview-placeholder"></i>
                        <?php endif; ?>
                    </div>
                    <label class="admin-small-upload">Upload Image<input type="file" name="admin_profile_image" id="adminProfileImageInput" accept=".jpg,.jpeg,.png,.webp"></label>
                </div>
                <div class="admin-form-grid">
                    <label>Full Name<input type="text" name="admin_name" value="<?php echo htmlspecialchars($admin["name"]); ?>" required></label>
                    <label>Username<input type="text" name="admin_username" value="<?php echo htmlspecialchars($admin["username"]); ?>" required></label>
                    <label>Email Address<input type="email" name="admin_email" value="<?php echo htmlspecialchars($admin["email"]); ?>" required></label>
                    <label>Phone Number<input type="text" name="admin_phone" value="<?php echo htmlspecialchars($admin["phone"] ?? ""); ?>"></label>
                </div>
            </div>
            <div class="admin-settings-actions">
                <button type="button" class="admin-btn admin-btn-light" id="openPasswordModal">Change Password</button>
                <button type="submit" class="admin-btn">Save Changes</button>
            </div>
        </section>

        <section class="admin-settings-card">
            <div class="admin-settings-card-head">
                <div>
                    <span>Website</span>
                    <h2>Website Settings</h2>
                </div>
            </div>
            <div class="admin-form-grid">
                <label>Website Name<input type="text" name="website_name" value="<?php echo htmlspecialchars($settings["website_name"]); ?>"></label>
                <label>Website Logo<input type="file" name="website_logo" accept=".jpg,.jpeg,.png,.webp,.svg"></label>
                <label class="admin-form-wide">Website Description<textarea name="website_description"><?php echo htmlspecialchars($settings["website_description"]); ?></textarea></label>
                <label>Contact Email<input type="email" name="contact_email" value="<?php echo htmlspecialchars($settings["contact_email"]); ?>"></label>
                <label>Contact Phone<input type="text" name="contact_phone" value="<?php echo htmlspecialchars($settings["contact_phone"]); ?>"></label>
                <label class="admin-form-wide">Website Address<textarea name="website_address"><?php echo htmlspecialchars($settings["website_address"]); ?></textarea></label>
            </div>
        </section>

        <section class="admin-settings-grid">
            <article class="admin-settings-card">
                <div class="admin-settings-card-head">
                    <div>
                        <span>Display</span>
                        <h2>Theme & Appearance</h2>
                    </div>
                </div>
                <div class="admin-settings-list">
                    <label class="admin-switch-row">Dark Mode <input type="checkbox" name="dark_mode" data-admin-toggle="dark_mode" <?php echo $settings["dark_mode"] === "1" ? "checked" : ""; ?>><span></span></label>
                    <label class="admin-switch-row">Light Mode <input type="checkbox" name="light_mode" data-admin-toggle="light_mode" <?php echo $settings["light_mode"] === "1" ? "checked" : ""; ?>><span></span></label>
                    <label>Primary Theme Color<input type="color" name="theme_color" value="<?php echo htmlspecialchars($settings["theme_color"]); ?>"></label>
                    <label class="admin-switch-row">Sidebar Compact Mode <input type="checkbox" name="sidebar_compact_mode" data-admin-toggle="sidebar_compact_mode" <?php echo $settings["sidebar_compact_mode"] === "1" ? "checked" : ""; ?>><span></span></label>
                </div>
            </article>

            <article class="admin-settings-card">
                <div class="admin-settings-card-head">
                    <div>
                        <span>Alerts</span>
                        <h2>Notification Settings</h2>
                    </div>
                </div>
                <div class="admin-settings-list">
                    <label class="admin-switch-row">Order notifications <input type="checkbox" name="order_notifications" data-admin-toggle="order_notifications" <?php echo $settings["order_notifications"] === "1" ? "checked" : ""; ?>><span></span></label>
                    <label class="admin-switch-row">Review notifications <input type="checkbox" name="review_notifications" data-admin-toggle="review_notifications" <?php echo $settings["review_notifications"] === "1" ? "checked" : ""; ?>><span></span></label>
                    <label class="admin-switch-row">Email notifications <input type="checkbox" name="email_notifications" data-admin-toggle="email_notifications" <?php echo $settings["email_notifications"] === "1" ? "checked" : ""; ?>><span></span></label>
                    <label class="admin-switch-row">User registration alerts <input type="checkbox" name="user_registration_alerts" data-admin-toggle="user_registration_alerts" <?php echo $settings["user_registration_alerts"] === "1" ? "checked" : ""; ?>><span></span></label>
                    <label class="admin-switch-row">Low stock alerts <input type="checkbox" name="low_stock_alerts" data-admin-toggle="low_stock_alerts" <?php echo $settings["low_stock_alerts"] === "1" ? "checked" : ""; ?>><span></span></label>
                </div>
            </article>
        </section>

        <section class="admin-settings-grid">
            <article class="admin-settings-card">
                <div class="admin-settings-card-head">
                    <div>
                        <span>Protection</span>
                        <h2>Security Settings</h2>
                    </div>
                </div>
                <div class="admin-settings-list">
                    <button type="button" class="admin-btn admin-btn-light" id="openPasswordModalSecondary">Change Admin Password</button>
                    <label class="admin-switch-row">Two-factor authentication <input type="checkbox" name="two_factor" data-admin-toggle="two_factor" <?php echo $settings["two_factor"] === "1" ? "checked" : ""; ?>><span></span></label>
                    <div class="admin-system-row"><strong>Last login</strong><p><?php echo htmlspecialchars($admin["last_login"] ?: "Current admin session"); ?></p></div>
                    <div class="admin-system-row"><strong>Active sessions</strong><p><?php echo count($activeSessions); ?> active session(s)</p></div>
                    <div class="admin-system-row"><strong>Login history</strong>
                        <?php if ($loginHistory): ?>
                            <?php foreach ($loginHistory as $item): ?>
                                <p><?php echo htmlspecialchars($item["login_time"]); ?> | <?php echo htmlspecialchars($item["ip_address"] ?: "Localhost"); ?> | <?php echo $item["is_active"] ? "Active" : "Closed"; ?></p>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p>No login history recorded yet.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </article>

            <article class="admin-settings-card">
                <div class="admin-settings-card-head">
                    <div>
                        <span>System</span>
                        <h2>Database / System</h2>
                    </div>
                </div>
                <div class="admin-settings-tools">
                    <button type="submit" name="action" value="backup_database" class="admin-action-link edit">Backup Database</button>
                    <button type="submit" name="action" value="export_data" class="admin-action-link">Export Data</button>
                    <button type="submit" name="action" value="clear_cache" class="admin-action-link">Clear Cache</button>
                    <div class="admin-system-row"><strong>System Status</strong><p><span class="status-pill"><?php echo htmlspecialchars($systemStatus); ?></span></p></div>
                    <div class="admin-system-row"><strong>Current Version</strong><p><?php echo htmlspecialchars($adminVersion); ?></p></div>
                </div>
            </article>
        </section>

        <section class="admin-settings-savebar">
            <button type="submit" class="admin-btn">Save Settings</button>
            <button type="submit" name="action" value="reset_defaults" class="admin-btn admin-btn-light">Reset Default</button>
        </section>
    </form>

    <div class="admin-modal" id="passwordSettingsModal">
        <form method="POST" action="settings.php" class="admin-modal-card">
            <button type="button" class="admin-search-close" id="closePasswordModal">&times;</button>
            <h2>Change Admin Password</h2>
            <input type="hidden" name="action" value="change_password">
            <div class="admin-form-grid">
                <label class="admin-form-wide">Current Password<input type="password" name="current_password" required></label>
                <label>New Password<input type="password" name="new_password" required minlength="6"></label>
                <label>Confirm Password<input type="password" name="confirm_password" required minlength="6"></label>
            </div>
            <div class="modal-actions">
                <button type="submit" class="admin-btn">Update Password</button>
                <button type="button" class="admin-btn admin-btn-light" id="cancelPasswordModal">Cancel</button>
            </div>
        </form>
    </div>
<?php include("includes/admin_footer.php"); ?>

