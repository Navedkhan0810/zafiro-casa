<?php
require_once(__DIR__ . '/../../config/app.php');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($conn) || !($conn instanceof mysqli)) {
    @include_once(__DIR__ . '/../config/db.php');
}

function ensureRememberColumns($conn) {
    foreach ([
        "status" => "VARCHAR(20) DEFAULT 'active'",
        "is_blocked" => "TINYINT(1) DEFAULT 0",
        "is_deleted" => "TINYINT(1) DEFAULT 0",
        "deleted_at" => "DATETIME NULL",
        "remember_token_hash" => "VARCHAR(255) NULL",
        "remember_token_expires" => "DATETIME NULL"
    ] as $column => $definition) {
        $stmt = $conn->prepare("SELECT COUNT(*) AS total FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'users' AND column_name = ?");
        $stmt->bind_param("s", $column);
        $stmt->execute();
        if ((int) ($stmt->get_result()->fetch_assoc()["total"] ?? 0) === 0) {
            $conn->query("ALTER TABLE users ADD COLUMN `$column` $definition");
        }
    }
}

function isUserLoginBlocked($user) {
    $status = strtolower(trim((string) ($user['status'] ?? 'active')));
    $blockedStatuses = ['inactive', 'disabled', 'blocked', 'banned', 'suspended', 'deleted'];

    return (int) ($user['is_blocked'] ?? 0) === 1
        || (int) ($user['is_deleted'] ?? 0) === 1
        || !empty($user['deleted_at'])
        || in_array($status, $blockedStatuses, true);
}

function setUserSession($user) {
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $user['id'];
    $_SESSION['full_name'] = $user['full_name'] ?? '';
    $_SESSION['username'] = $user['username'] ?? '';
    $_SESSION['email'] = $user['email'] ?? '';
}

function tryRememberLogin($conn) {
    if (isset($_SESSION['user_id']) || empty($_COOKIE['zafiro_remember'])) {
        return;
    }

    ensureRememberColumns($conn);
    $token = $_COOKIE['zafiro_remember'];
    $stmt = $conn->prepare("SELECT id, full_name, username, email, remember_token_hash, remember_token_expires, status, is_blocked, is_deleted, deleted_at FROM users WHERE remember_token_expires > NOW()");
    $stmt->execute();
    $result = $stmt->get_result();

    while ($user = $result->fetch_assoc()) {
        if (!empty($user['remember_token_hash']) && password_verify($token, $user['remember_token_hash'])) {
            if (isUserLoginBlocked($user)) {
                clearRememberCookie($conn);
                return;
            }
            setUserSession($user);
            return;
        }
    }
}

function createRememberCookie($conn, $userId, $days = 30) {
    ensureRememberColumns($conn);
    $token = bin2hex(random_bytes(32));
    $hash = password_hash($token, PASSWORD_DEFAULT);
    $stmt = $conn->prepare("UPDATE users SET remember_token_hash = ?, remember_token_expires = DATE_ADD(NOW(), INTERVAL ? DAY) WHERE id = ?");
    $stmt->bind_param("sii", $hash, $days, $userId);
    $stmt->execute();
    setcookie('zafiro_remember', $token, [
        'expires' => time() + ($days * 86400),
        'path' => zafiro_base_path(),
        'secure' => zafiro_is_https(),
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
}

function clearRememberCookie($conn = null) {
    if ($conn instanceof mysqli && isset($_SESSION['user_id'])) {
        $stmt = $conn->prepare("UPDATE users SET remember_token_hash = NULL, remember_token_expires = NULL WHERE id = ?");
        $stmt->bind_param("i", $_SESSION['user_id']);
        $stmt->execute();
    }
    setcookie('zafiro_remember', '', [
        'expires' => time() - 3600,
        'path' => zafiro_base_path(),
        'secure' => zafiro_is_https(),
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
}

if (isset($conn) && $conn instanceof mysqli) {
    tryRememberLogin($conn);
}
