<?php
declare(strict_types=1);

/**
 * LER BEM - configuracao central.
 * Prioridade: variaveis de ambiente (Docker/hospedagem) > config/config.local.php (instalador).
 */
$localFile = __DIR__ . '/config.local.php';
$local = is_file($localFile) ? (require $localFile) : [];
if (!is_array($local)) {
    $local = [];
}

$env = static function (string $chave, mixed $padrao = null) use ($local): mixed {
    $v = getenv($chave);
    if ($v !== false && $v !== '') {
        return $v;
    }
    return $local[$chave] ?? $padrao;
};

return [
    'app' => [
        'name'  => 'LER BEM',
        'debug' => filter_var($env('APP_DEBUG', '0'), FILTER_VALIDATE_BOOLEAN),
        'url'   => rtrim((string) $env('APP_URL', ''), '/'),
    ],
    'db' => [
        'host'    => (string) $env('DB_HOST', '127.0.0.1'),
        'port'    => (string) $env('DB_PORT', '3306'),
        'name'    => (string) $env('DB_NAME', 'ler_bem'),
        'user'    => (string) $env('DB_USER', 'root'),
        'pass'    => (string) $env('DB_PASSWORD', ''),
        'charset' => 'utf8mb4',
    ],
    'ai' => [
        'api_key' => (string) $env('AI_API_KEY', ''),
        'api_url' => (string) $env('AI_API_URL', 'https://api.openai.com/v1/chat/completions'),
        'model'   => (string) $env('AI_MODEL', 'gpt-4o-mini'),
        'timeout' => (int) $env('AI_TIMEOUT', 45),
    ],
];
