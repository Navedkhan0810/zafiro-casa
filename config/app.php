<?php
if (!function_exists('zafiro_project_root')) {
    function zafiro_project_root(): string {
        return dirname(__DIR__);
    }
}

if (!function_exists('zafiro_load_env')) {
    function zafiro_load_env(?string $path = null): void {
        $path = $path ?: zafiro_project_root() . DIRECTORY_SEPARATOR . '.env';
        if (!is_readable($path)) return;

        foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) continue;

            [$key, $value] = array_map('trim', explode('=', $line, 2));
            if ($key === '' || getenv($key) !== false) continue;

            $value = trim($value, "\"'");
            putenv($key . '=' . $value);
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
    }
}

if (!function_exists('zafiro_env')) {
    function zafiro_env(string $key, $default = '') {
        zafiro_load_env();
        $value = getenv($key);
        return $value === false ? $default : $value;
    }
}

if (!function_exists('zafiro_is_https')) {
    function zafiro_is_https(): bool {
        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    }
}

if (!function_exists('zafiro_app_url')) {
    function zafiro_app_url(): string {
        $configured = zafiro_env('APP_URL', zafiro_env('RENDER_EXTERNAL_URL', ''));
        if ($configured !== '') return rtrim($configured, '/');

        $host = $_SERVER['HTTP_HOST'] ?? '';
        if ($host === '') return '';

        $scheme = zafiro_is_https() ? 'https' : 'http';
        return $scheme . '://' . $host;
    }
}

if (!function_exists('zafiro_url')) {
    function zafiro_url(string $path = ''): string {
        $base = zafiro_app_url();
        return ($base === '' ? '' : $base) . '/' . ltrim($path, '/');
    }
}

if (!function_exists('zafiro_base_path')) {
    function zafiro_base_path(): string {
        $path = zafiro_env('APP_BASE_PATH', '/');
        $path = '/' . trim($path, '/');
        return $path === '//' ? '/' : $path;
    }
}
?>
