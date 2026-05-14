<?php
if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
}

ini_set('display_errors', '0');
ini_set('log_errors', '1');

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/myproject',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
}

function zafiro_secure_upload(array $file, string $targetDir, string $webDir, array $allowedExt, array $allowedMime, int $maxBytes, string $prefix, ?string &$error): string {
    $error = '';
    if (empty($file['name']) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return '';
    }
    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'] ?? '')) {
        $error = 'Upload failed. Please try again.';
        return '';
    }
    if ((int) ($file['size'] ?? 0) <= 0 || (int) ($file['size'] ?? 0) > $maxBytes) {
        $error = 'Upload failed. File size is not allowed.';
        return '';
    }

    $ext = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExt, true)) {
        $error = 'Upload failed. File type is not allowed.';
        return '';
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    if (!in_array($mime, $allowedMime, true)) {
        $error = 'Upload failed. Invalid file content.';
        return '';
    }

    if (str_starts_with($mime, 'image/') && @getimagesize($file['tmp_name']) === false) {
        $error = 'Upload failed. Invalid image file.';
        return '';
    }

    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0755, true);
    }

    $fileName = $prefix . '_' . bin2hex(random_bytes(10)) . '.' . $ext;
    $target = rtrim($targetDir, '/\\') . DIRECTORY_SEPARATOR . $fileName;
    if (!move_uploaded_file($file['tmp_name'], $target)) {
        $error = 'Upload failed. Could not save file.';
        return '';
    }

    return rtrim($webDir, '/\\') . '/' . $fileName;
}
?>
