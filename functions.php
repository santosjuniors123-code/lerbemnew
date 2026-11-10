<?php
declare(strict_types=1);

/**
 * LER BEM - funcoes utilitarias compartilhadas.
 */

if (!defined('LB_ROOT')) {
    define('LB_ROOT', __DIR__);
}

function lb_config(?string $caminho = null, mixed $padrao = null): mixed
{
    static $cfg = null;
    if ($cfg === null) {
        $cfg = require LB_ROOT . '/config/config.php';
    }
    if ($caminho === null) {
        return $cfg;
    }
    $ref = $cfg;
    foreach (explode('.', $caminho) as $parte) {
        if (!is_array($ref) || !array_key_exists($parte, $ref)) {
            return $padrao;
        }
        $ref = $ref[$parte];
    }
    return $ref;
}

require_once LB_ROOT . '/config/database.php';

function db(): PDO
{
    $pdo = lb_db();
    if (!$pdo instanceof PDO) {
        lb_fatal('Nao foi possivel conectar ao banco de dados MySQL. Verifique config/config.local.php ou execute o instalador.');
    }
    return $pdo;
}

function lb_fatal(string $mensagem, ?Throwable $erro = null): never
{
    if ($erro !== null) {
        error_log('[LER BEM] ' . $erro->getMessage());
    }
    http_response_code(500);
    if (!headers_sent()) {
        header('Content-Type: text/html; charset=utf-8');
    }
    $detalhe = '';
    if ($erro !== null && lb_config('app.debug')) {
        $detalhe = '<pre class="detail">' . e($erro->getMessage()) . '</pre>';
    }
    echo '<!doctype html><html lang="pt-BR"><head><meta charset="utf-8">'
        . '<meta name="viewport" content="width=device-width, initial-scale=1">'
        . '<title>LER BEM</title><style>'
        . 'body{margin:0;min-height:100vh;display:grid;place-items:center;background:#eef4f4;font-family:system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;color:#10233f}'
        . '.box{background:#fff;border-radius:18px;padding:36px;max-width:620px;box-shadow:0 20px 50px rgba(15,45,50,.12)}'
        . 'h1{margin:0 0 10px;font-size:22px}p{line-height:1.6;color:#41556b}'
        . '.detail{background:#0b2b2b;color:#c8f5e8;padding:16px;border-radius:10px;overflow:auto;font-size:12px}'
        . 'a{display:inline-block;margin-top:18px;background:#0f766e;color:#fff;padding:12px 20px;border-radius:10px;text-decoration:none;font-weight:600}'
        . '</style></head><body><div class="box"><h1>Ops, algo saiu do previsto</h1>'
        . '<p>' . e($mensagem) . '</p>' . $detalhe
        . '<a href="' . e(lb_url('index.php')) . '">Voltar ao inicio</a></div></body></html>';
    exit;
}

function lb_installed(): bool
{
    static $resultado = null;
    if ($resultado !== null) {
        return $resultado;
    }
    $pdo = lb_db();
    if (!$pdo instanceof PDO) {
        return $resultado = false;
    }
    try {
        $pdo->query('SELECT 1 FROM usuarios LIMIT 1');
        return $resultado = true;
    } catch (Throwable $e) {
        return $resultado = false;
    }
}

function lb_session_start(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    ini_set('session.use_strict_mode', '1');
    ini_set('session.cookie_httponly', '1');
    session_name('LERBEMSESSID');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => $https,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function lb_base(): string
{
    static $base = null;
    if ($base !== null) {
        return $base;
    }
    $docRoot = isset($_SERVER['DOCUMENT_ROOT']) ? realpath((string) $_SERVER['DOCUMENT_ROOT']) : false;
    $appRoot = realpath(LB_ROOT);
    $base = '';
    if ($docRoot && $appRoot && str_starts_with($appRoot, $docRoot)) {
        $base = str_replace('\\', '/', substr($appRoot, strlen($docRoot)));
    }
    return $base = rtrim($base, '/');
}

function lb_url(string $caminho = ''): string
{
    return lb_base() . '/' . ltrim($caminho, '/');
}

function e(mixed $valor): string
{
    return htmlspecialchars((string) $valor, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function redirect(string $url, int $codigo = 302): never
{
    if (!headers_sent()) {
        header('Location: ' . $url, true, $codigo);
    }
    exit;
}

function flash(string $tipo, string $mensagem): void
{
    lb_session_start();
    $_SESSION['_flash'][] = ['tipo' => $tipo, 'msg' => $mensagem];
}

function lb_flashes(): array
{
    $lista = $_SESSION['_flash'] ?? [];
    unset($_SESSION['_flash']);
    return is_array($lista) ? $lista : [];
}

function csrf_token(): string
{
    lb_session_start();
    if (empty($_SESSION['_csrf']) || !is_string($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function csrf_verify(): void
{
    $token = $_POST['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!is_string($token) || $token === '' || !hash_equals(csrf_token(), $token)) {
        http_response_code(419);
        if (lb_is_ajax()) {
            lb_json(['ok' => false, 'erro' => 'Sessao expirada. Recarregue a pagina e tente novamente.'], 419);
        }
        lb_fatal('Sua sessao expirou ou a requisicao e invalida (protecao CSRF). Recarregue a pagina e tente novamente.');
    }
}

function lb_is_ajax(): bool
{
    return strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest'
        || str_contains(strtolower($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json');
}

function lb_json(mixed $dados, int $codigo = 200): never
{
    http_response_code($codigo);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function inp(string $chave, string $padrao = ''): string
{
    $v = $_POST[$chave] ?? $padrao;
    return is_string($v) ? trim($v) : $padrao;
}

function gin(string $chave, string $padrao = ''): string
{
    $v = $_GET[$chave] ?? $padrao;
    return is_string($v) ? trim($v) : $padrao;
}

function int_inp(string $chave, ?int $padrao = null): ?int
{
    $v = $_POST[$chave] ?? null;
    if ($v === null || $v === '' || !is_scalar($v)) {
        return $padrao;
    }
    return (int) $v;
}

function int_get(string $chave, ?int $padrao = null): ?int
{
    $v = $_GET[$chave] ?? null;
    if ($v === null || $v === '' || !is_scalar($v)) {
        return $padrao;
    }
    return (int) $v;
}

function lb_date(?string $data, string $formato = 'd/m/Y'): string
{
    if (!$data || $data === '0000-00-00' || str_starts_with($data, '0000-00-00')) {
        return '—';
    }
    $ts = strtotime($data);
    return $ts ? date($formato, $ts) : '—';
}

function lb_datetime(?string $data): string
{
    return lb_date($data, 'd/m/Y H:i');
}

function lb_num(float|int|null $valor, int $decimais = 1): string
{
    if ($valor === null) {
        return '—';
    }
    return number_format((float) $valor, $decimais, ',', '.');
}

function lb_slug(string $texto): string
{
    $t = @iconv('UTF-8', 'ASCII//TRANSLIT', $texto);
    if ($t === false) {
        $t = $texto;
    }
    $t = strtolower((string) preg_replace('/[^A-Za-z0-9]+/', '-', $t));
    $t = trim($t, '-');
    return $t !== '' ? $t : 'escola';
}

function lb_nome_busca(string $nome): string
{
    $n = trim((string) preg_replace('/\s+/u', ' ', $nome));
    return mb_strtolower($n, 'UTF-8');
}

function lb_iniciais(string $nome): string
{
    $partes = preg_split('/\s+/', trim($nome)) ?: [];
    $partes = array_values(array_filter($partes, static fn ($p) => $p !== ''));
    if (!$partes) {
        return '?';
    }
    $ini = mb_substr($partes[0], 0, 1, 'UTF-8');
    if (count($partes) > 1) {
        $ini .= mb_substr($partes[count($partes) - 1], 0, 1, 'UTF-8');
    }
    return mb_strtoupper($ini, 'UTF-8');
}

function lb_resumir(string $texto, int $limite = 120): string
{
    $texto = trim($texto);
    if (mb_strlen($texto, 'UTF-8') <= $limite) {
        return $texto;
    }
    return rtrim(mb_substr($texto, 0, $limite - 1, 'UTF-8')) . '…';
}

function lb_user(): ?array
{
    $u = $_SESSION['usuario'] ?? null;
    return is_array($u) ? $u : null;
}

function lb_aluno(): ?array
{
    $a = $_SESSION['aluno'] ?? null;
    return is_array($a) ? $a : null;
}

function lb_escola_atual(): ?array
{
    static $cache = [];
    $u = lb_user();
    $a = lb_aluno();
    $id = $u['escola_id'] ?? ($a['escola_id'] ?? null);
    if (!$id) {
        return null;
    }
    $id = (int) $id;
    if (array_key_exists($id, $cache)) {
        return $cache[$id];
    }
    $st = db()->prepare('SELECT * FROM escolas WHERE id = ?');
    $st->execute([$id]);
    $row = $st->fetch();
    return $cache[$id] = ($row ?: null);
}

function &lb_setting_store(): array
{
    static $cache = [];
    return $cache;
}

function setting(int $escolaId, string $chave, ?string $padrao = null): ?string
{
    $cache = &lb_setting_store();
    if (!isset($cache[$escolaId])) {
        $st = db()->prepare('SELECT chave, valor FROM configuracoes WHERE escola_id = ?');
        $st->execute([$escolaId]);
        $cache[$escolaId] = $st->fetchAll(PDO::FETCH_KEY_PAIR);
    }
    return array_key_exists($chave, $cache[$escolaId]) ? (string) $cache[$escolaId][$chave] : $padrao;
}

function setting_set(int $escolaId, string $chave, string $valor): void
{
    $st = db()->prepare('INSERT INTO configuracoes (escola_id, chave, valor) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE valor = VALUES(valor)');
    $st->execute([$escolaId, $chave, $valor]);
    $cache = &lb_setting_store();
    $cache[$escolaId][$chave] = $valor;
}

function lb_paginacao(int $total, int $porPagina, int $pagina, array $params = []): array
{
    $paginas = max(1, (int) ceil($total / max(1, $porPagina)));
    $pagina = max(1, min($pagina, $paginas));
    $offset = ($pagina - 1) * $porPagina;
    $html = '';
    if ($paginas > 1) {
        $html .= '<nav class="pagination" aria-label="Paginacao">';
        for ($i = 1; $i <= $paginas; $i++) {
            if ($paginas > 9 && $i > 2 && $i < $paginas - 1 && abs($i - $pagina) > 1) {
                if ($i === 3) {
                    $html .= '<span class="page-gap">…</span>';
                }
                continue;
            }
            $q = http_build_query(array_merge($params, ['pagina' => $i]));
            $html .= '<a class="page' . ($i === $pagina ? ' is-active' : '') . '" href="?' . e($q) . '">' . $i . '</a>';
        }
        $html .= '</nav>';
    }
    return ['offset' => $offset, 'pagina' => $pagina, 'paginas' => $paginas, 'html' => $html];
}

function lb_old(string $chave, string $padrao = ''): string
{
    $v = $_SESSION['_old'][$chave] ?? $padrao;
    return is_string($v) ? e($v) : $padrao;
}

function lb_limpar_old(): void
{
    unset($_SESSION['_old'], $_SESSION['_erros']);
}
