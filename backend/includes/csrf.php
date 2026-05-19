<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field() {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
}

function csrf_validate($token = null) {
    $token = $token ?? ($_POST['csrf_token'] ?? '');
    return is_string($token)
        && isset($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $token);
}

function csrf_require() {
    if (!csrf_validate()) {
        http_response_code(403);
        exit('Invalid security token. Please go back and try again.');
    }
}

function csrf_inject_forms($html) {
    if (stripos($html, '<form') === false || !preg_match('/<form\b[^>]*method\s*=\s*["\']?post["\']?/i', $html)) {
        return $html;
    }

    return preg_replace_callback('/<form\b[^>]*>.*?<\/form>/is', function ($match) {
        $form = $match[0];
        if (!preg_match('/<form\b[^>]*method\s*=\s*["\']?post["\']?/i', $form) || preg_match('/name\s*=\s*["\']csrf_token["\']/i', $form)) {
            return $form;
        }
        return preg_replace('/<form\b[^>]*>/i', '$0' . csrf_field(), $form, 1);
    }, $html);
}

function csrf_start_form_injection() {
    if (empty($GLOBALS['csrf_output_started'])) {
        $GLOBALS['csrf_output_started'] = true;
        ob_start('csrf_inject_forms');
    }
}
?>

