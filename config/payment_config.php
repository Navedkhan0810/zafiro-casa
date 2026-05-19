<?php
require_once(__DIR__ . "/app.php");

return [
    "mode" => trim((string) zafiro_env("PHONEPE_ENV", "sandbox")),
    "base_url" => rtrim((string) zafiro_env("PHONEPE_BASE_URL", "https://api-preprod.phonepe.com/apis/pg-sandbox"), "/"),
    "client_id" => trim((string) zafiro_env("PHONEPE_CLIENT_ID", "")),
    "client_secret" => trim((string) zafiro_env("PHONEPE_CLIENT_SECRET", "")),
    "client_version" => trim((string) zafiro_env("PHONEPE_CLIENT_VERSION", "1")),
    "redirect_url" => trim((string) zafiro_env("PHONEPE_REDIRECT_URL", "")),
    "callback_url" => trim((string) zafiro_env("PHONEPE_CALLBACK_URL", ""))
];
