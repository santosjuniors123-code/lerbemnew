<?php
declare(strict_types=1);

function lb_login_user(array $row): void
{
    lb_session_start();
    session_regenerate_id(true);
    $_SESSION['usuario'] = [
        'id'           => (int) $row['id'],
        'nome'         => (string) $row['nome'],
        'email'        => (string) $row['email'],
        'papel'        => (string) $row['papel'],
        'escola_id'    => isset($row['escola_id']) && $row['escola_id'] !== null ? (int) $row['escola_id'] : null,
        'escola_nome'  => $row['escola_nome'] ?? null,
        'professor_id' => isset($row['professor_id']) && $row['professor_id'] !== null ? (int) $row['professor_id'] : null,
    ];
    $_SESSION['login_em'] = time();
    unset($_SESSION['_csrf']);
}

function lb_logout_user(): void
{
    lb_session_start();
    unset($_SESSION['usuario']);
    session_regenerate_id(true);
    $_SESSION['_csrf'] = bin2hex(random_bytes(32));
}

function lb_login_bloqueado(string $email, string $ip): bool
{
    $st = db()->prepare(
        'SELECT COUNT(*) FROM tentativas_login
         WHERE sucesso = 0 AND criado_em > (NOW() - INTERVAL 15 MINUTE) AND (email = ? OR ip = ?)'
    );
    $st->execute([$email, $ip]);
    return (int) $st->fetchColumn() >= 8;
}

function lb_registrar_tentativa(string $email, string $ip, bool $sucesso): void
{
    $st = db()->prepare('INSERT INTO tentativas_login (email, ip, sucesso) VALUES (?, ?, ?)');
    $st->execute([$email, $ip, $sucesso ? 1 : 0]);
    if ($sucesso) {
        db()->prepare('DELETE FROM tentativas_login WHERE email = ? AND sucesso = 0')->execute([$email]);
    }
}

function require_professor(): array
{
    $u = lb_user();
    if (!$u || ($u['papel'] ?? '') !== 'professor' || empty($u['escola_id'])) {
        flash('error', 'Entre com sua conta de professor para acessar o painel.');
        redirect(lb_url('login.php'));
    }
    $st = db()->prepare(
        'SELECT u.ativo AS usuario_ativo, e.ativo AS escola_ativa
         FROM usuarios u JOIN escolas e ON e.id = u.escola_id
         WHERE u.id = ? AND u.escola_id = ?'
    );
    $st->execute([$u['id'], $u['escola_id']]);
    $row = $st->fetch();
    if (!$row || !(int) $row['usuario_ativo'] || !(int) $row['escola_ativa']) {
        lb_logout_user();
        flash('error', 'Sua conta ou escola esta inativa. Procure o administrador.');
        redirect(lb_url('login.php'));
    }
    return $u;
}

function require_admin(): array
{
    $u = lb_user();
    if (!$u || ($u['papel'] ?? '') !== 'admin') {
        flash('error', 'Acesso restrito ao administrador do sistema.');
        redirect(lb_url('login.php'));
    }
    $st = db()->prepare('SELECT ativo FROM usuarios WHERE id = ?');
    $st->execute([$u['id']]);
    if (!(int) $st->fetchColumn()) {
        lb_logout_user();
        flash('error', 'Sua conta esta inativa.');
        redirect(lb_url('login.php'));
    }
    return $u;
}

function require_aluno(): array
{
    $a = lb_aluno();
    if (!$a) {
        flash('error', 'Identifique-se com seu nome e turma para continuar.');
        redirect(lb_url('index.php'));
    }
    $st = db()->prepare(
        'SELECT a.id, a.nome, a.escola_id, a.turma_id, a.status, t.nome AS turma_nome, e.nome AS escola_nome
         FROM alunos a
         JOIN escolas e ON e.id = a.escola_id
         LEFT JOIN turmas t ON t.id = a.turma_id
         WHERE a.id = ? AND a.escola_id = ?'
    );
    $st->execute([$a['id'], $a['escola_id']]);
    $row = $st->fetch();
    if (!$row || $row['status'] !== 'ativo') {
        unset($_SESSION['aluno']);
        flash('error', 'Seu cadastro nao esta ativo. Procure seu professor.');
        redirect(lb_url('index.php'));
    }
    $_SESSION['aluno'] = [
        'id'          => (int) $row['id'],
        'nome'        => (string) $row['nome'],
        'escola_id'   => (int) $row['escola_id'],
        'escola_nome' => (string) $row['escola_nome'],
        'turma_id'    => $row['turma_id'] !== null ? (int) $row['turma_id'] : null,
        'turma_nome'  => $row['turma_nome'],
    ];
    return $_SESSION['aluno'];
}
