<?php
require_once(__DIR__ . "/app.php");

return [
    "mode" => zafiro_env("PHONEPE_ENV", "sandbox"),
    "base_url" => zafiro_env("PHONEPE_BASE_URL", "https://api-preprod.phonepe.com/apis/pg-sandbox"),
    "client_id" => zafiro_env("PHONEPE_CLIENT_ID", ""),
    "client_secret" => zafiro_env("PHONEPE_CLIENT_SECRET", ""),
    "client_version" => zafiro_env("PHONEPE_CLIENT_VERSION", "1"),
    "merchant_id" => zafiro_env("PHONEPE_MERCHANT_ID", ""),
    "salt_key" => zafiro_env("PHONEPE_SALT_KEY", ""),
    "salt_index" => zafiro_env("PHONEPE_SALT_INDEX", "1")
];
