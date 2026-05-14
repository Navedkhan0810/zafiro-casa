<?php
if (!function_exists("zafiroLoadEnv")) {
    function zafiroLoadEnv($path) {
        if (!is_readable($path)) return;

        foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line);
            if ($line === "" || str_starts_with($line, "#") || !str_contains($line, "=")) continue;

            [$key, $value] = array_map("trim", explode("=", $line, 2));
            if ($key === "" || getenv($key) !== false) continue;

            $value = trim($value, "\"'");
            putenv($key . "=" . $value);
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
    }
}

zafiroLoadEnv(__DIR__ . "/../.env");

return [
    "mode" => getenv("PHONEPE_ENV") ?: "sandbox",
    "base_url" => getenv("PHONEPE_BASE_URL") ?: "https://api-preprod.phonepe.com/apis/pg-sandbox",
    "client_id" => getenv("PHONEPE_CLIENT_ID") ?: "",
    "client_secret" => getenv("PHONEPE_CLIENT_SECRET") ?: "",
    "client_version" => getenv("PHONEPE_CLIENT_VERSION") ?: "1",
    "merchant_id" => getenv("PHONEPE_MERCHANT_ID") ?: "",
    "salt_key" => getenv("PHONEPE_SALT_KEY") ?: "",
    "salt_index" => getenv("PHONEPE_SALT_INDEX") ?: "1"
];
