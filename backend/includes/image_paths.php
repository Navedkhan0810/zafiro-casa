<?php
if (!function_exists('zafiroImageFallback')) {
    function zafiroImageFallback() {
        return "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='900' height='700' viewBox='0 0 900 700'%3E%3Crect width='900' height='700' fill='%23FAF7F0'/%3E%3Ctext x='450' y='350' text-anchor='middle' font-family='Arial' font-size='42' font-weight='700' fill='%236B7280'%3EZafiro Casa%3C/text%3E%3C/svg%3E";
    }
}

if (!function_exists('zafiroPublicImageUrl')) {
    function zafiroPublicImageUrl($path) {
        $path = trim((string) $path);
        if ($path === '') return zafiroImageFallback();
        if (preg_match('~^(https?:)?//|^data:image/~i', $path)) return $path;

        $clean = str_replace('\\', '/', $path);
        $clean = preg_replace('~^(\.\./)+~', '', $clean);
        $clean = ltrim($clean, '/');
        $clean = preg_replace('~^(my(?:project)|zafiro-casa)/~', '', $clean);

        $root = realpath(__DIR__ . '/../../');
        $file = $root ? realpath($root . '/' . $clean) : false;
        if (!$file || strpos($file, $root) !== 0 || !is_file($file)) return zafiroImageFallback();

        $basePath = function_exists('zafiro_base_path') ? zafiro_base_path() : '/zafiro-casa';
        if ($basePath === '/' && strpos($_SERVER['SCRIPT_NAME'] ?? '', '/zafiro-casa/') === 0) $basePath = '/zafiro-casa';
        return rtrim($basePath, '/') . '/' . $clean;
    }
}
?>
