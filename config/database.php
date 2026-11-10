<?php
declare(strict_types=1);

/**
 * Conexao PDO (MySQL/MariaDB) com prepared statements reais.
 */
function lb_db(): ?PDO
{
    static $pdo = null;
    static $falhou = false;

    if ($pdo instanceof PDO) {
        return $pdo;
    }
    if ($falhou) {
        return null;
    }

    $c = lb_config('db');
    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=%s',
        $c['host'],
        $c['port'],
        $c['name'],
        $c['charset']
    );

    try {
        $pdo = new PDO($dsn, (string) $c['user'], (string) $c['pass'], [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_STRINGIFY_FETCHES  => false,
        ]);
    } catch (PDOException $e) {
        $falhou = true;
        $GLOBALS['lb_db_error'] = $e->getMessage();
        return null;
    }

    return $pdo;
}
