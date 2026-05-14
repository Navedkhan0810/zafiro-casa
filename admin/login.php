<?php
session_start();
include("../backend/config/db.php");
include_once("../backend/includes/csrf.php");

$error = "";

$conn->query("CREATE TABLE IF NOT EXISTS admins (
    admin_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    username VARCHAR(80) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

foreach ([
    "phone" => "VARCHAR(30) NULL",
    "profile_image" => "VARCHAR(255) NULL",
    "last_login" => "DATETIME NULL",
    "reset_otp" => "VARCHAR(10) NULL",
    "reset_otp_expiry" => "DATETIME NULL"
] as $column => $definition) {
    $exists = $conn->query("SELECT COUNT(*) AS total FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'admins' AND column_name = '$column'");
    if ((int) ($exists->fetch_assoc()["total"] ?? 0) === 0) {
        $conn->query("ALTER TABLE admins ADD COLUMN `$column` $definition");
    }
}

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

$legacyAdmin = $conn->prepare("SELECT admin_id, password FROM admins WHERE username = 'admin' OR email = 'zafirocasaadmin@gmail.com' LIMIT 1");
$legacyAdmin->execute();
$legacyRow = $legacyAdmin->get_result()->fetch_assoc();
if ($legacyRow && password_verify("admin123", $legacyRow["password"])) {
    $lockedHash = password_hash(bin2hex(random_bytes(24)), PASSWORD_DEFAULT);
    $lockStmt = $conn->prepare("UPDATE admins SET password = ? WHERE admin_id = ?");
    $lockStmt->bind_param("si", $lockedHash, $legacyRow["admin_id"]);
    $lockStmt->execute();
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!csrf_validate()) {
        $error = "Invalid security token. Please refresh and try again.";
    } else {
    $login = trim($_POST["login"] ?? "");
    $password = $_POST["password"] ?? "";

    $stmt = $conn->prepare("SELECT * FROM admins WHERE username = ? OR email = ? LIMIT 1");
    $stmt->bind_param("ss", $login, $login);
    $stmt->execute();
    $admin = $stmt->get_result()->fetch_assoc();

    if ($admin && password_verify($password, $admin["password"])) {
        $_SESSION["admin_id"] = $admin["admin_id"];
        $_SESSION["admin_name"] = $admin["name"];
        $_SESSION["admin_email"] = $admin["email"];
        $_SESSION["admin_username"] = $admin["username"];
        $sessionId = session_id();
        $ip = $_SERVER["REMOTE_ADDR"] ?? "";
        $agent = substr($_SERVER["HTTP_USER_AGENT"] ?? "", 0, 255);
        $stmt = $conn->prepare("UPDATE admins SET last_login = NOW() WHERE admin_id = ?");
        $stmt->bind_param("i", $admin["admin_id"]);
        $stmt->execute();
        $stmt = $conn->prepare("INSERT INTO admin_login_activity (admin_id, session_id, ip_address, user_agent) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("isss", $admin["admin_id"], $sessionId, $ip, $agent);
        $stmt->execute();
        header("Location: dashboard.php");
        exit;
    }

    $error = "Invalid username/email or password.";
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login | Zafiro Casa</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body class="admin-login-body">
    <main class="admin-login-page">
        <section class="admin-login-card">
            <span>Zafiro Casa</span>
            <h1>Admin Login</h1>
            <p>Secure access for website management.</p>

            <?php if ($error): ?>
                <div class="admin-alert"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <form method="POST" action="login.php" class="admin-login-form">
                <?php echo csrf_field(); ?>
                <input type="text" name="login" placeholder="Username or Email" required>
                <div class="admin-password-wrap">
                    <input type="password" name="password" id="adminPassword" placeholder="Password" required>
                    <button type="button" id="toggleAdminPassword">Show</button>
                </div>
                <button type="submit" class="admin-btn">Login</button>
                <a href="forgot-password.php" class="admin-auth-link">Forgot Password?</a>
            </form>
        </section>
    </main>
    <script src="../assets/js/admin.js"></script>
</body>
</html>
