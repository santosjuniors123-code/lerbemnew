<?php
declare(strict_types=1);

/**
 * Instalador via linha de comando (usado no container Docker e em hospedagens com SSH).
 * Uso: php database/install_cli.php
 * Variaveis: DB_HOST DB_PORT DB_NAME DB_USER DB_PASSWORD
 *            ESCOLA_NOME ADMIN_NOME ADMIN_EMAIL ADMIN_PASSWORD
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Execucao apenas via linha de comando.\n");
}

define('LB_NO_INSTALL_CHECK', true);
require_once dirname(__DIR__) . '/functions.php';
require_once dirname(__DIR__) . '/includes/installer.php';

$env = static function (string $k, string $padrao = '') {
    $v = getenv($k);
    return ($v === false || $v === '') ? $padrao : $v;
};

$db = [
    'host' => $env('DB_HOST', '127.0.0.1'),
    'port' => $env('DB_PORT', '3306'),
    'name' => $env('DB_NAME', 'ler_bem'),
    'user' => $env('DB_USER', 'root'),
    'pass' => $env('DB_PASSWORD', ''),
];

$escolaNome = $env('ESCOLA_NOME', 'Escola Modelo LER BEM');
$adminNome  = $env('ADMIN_NOME', 'Administrador LER BEM');
$adminEmail = $env('ADMIN_EMAIL', 'admin@lerbem.local');
$adminSenha = $env('ADMIN_PASSWORD', '');

if ($adminSenha === '') {
    $adminSenha = bin2hex(random_bytes(6)) . 'Aa1!';
    echo "[i] ADMIN_PASSWORD nao informado; senha aleatoria gerada: {$adminSenha}\n";
}

if (!preg_match('/^[A-Za-z0-9_\-]{1,64}$/', $db['name'])) {
    fwrite(STDERR, "[x] DB_NAME invalido.\n");
    exit(1);
}

try {
    $pdo = lb_pdo_connect($db);
    $pdo->exec('CREATE DATABASE IF NOT EXISTS `' . $db['name'] . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $pdo->exec('USE `' . $db['name'] . '`');
    $total = lb_install_schema($pdo);
    echo "[ok] Esquema aplicado ({$total} comandos).\n";

    $qtd = (int) $pdo->query('SELECT COUNT(*) FROM usuarios')->fetchColumn();
    if ($qtd > 0) {
        echo "[i] Sistema ja possui usuarios; dados iniciais nao foram recriados.\n";
    } else {
        $r = lb_install_seed($pdo, [
            'escola_nome'   => $escolaNome,
            'escola_cidade' => $env('ESCOLA_CIDADE', ''),
            'escola_uf'     => $env('ESCOLA_UF', ''),
            'escola_email'  => $env('ESCOLA_EMAIL', ''),
            'admin_nome'    => $adminNome,
            'admin_email'   => $adminEmail,
            'admin_senha'   => $adminSenha,
        ]);
        $turmas = lb_criar_turmas($pdo, (int) $r['escola_id'], lb_turmas_padrao());
        echo "[ok] Escola #{$r['escola_id']} criada com {$turmas} turmas.\n";
        echo "[ok] Administrador: {$adminEmail}\n";
    }

    lb_write_config([
        'DB_HOST'     => $db['host'],
        'DB_PORT'     => $db['port'],
        'DB_NAME'     => $db['name'],
        'DB_USER'     => $db['user'],
        'DB_PASSWORD' => $db['pass'],
        'APP_URL'     => $env('APP_URL', ''),
        'APP_DEBUG'   => $env('APP_DEBUG', '0'),
        'AI_API_KEY'  => $env('AI_API_KEY', ''),
        'AI_API_URL'  => $env('AI_API_URL', 'https://api.openai.com/v1/chat/completions'),
        'AI_MODEL'    => $env('AI_MODEL', 'gpt-4o-mini'),
    ]);
    echo "[ok] config/config.local.php atualizado.\n";
    echo "[ok] Instalacao finalizada.\n";
} catch (Throwable $e) {
    fwrite(STDERR, '[x] ' . $e->getMessage() . "\n");
    exit(1);
}
