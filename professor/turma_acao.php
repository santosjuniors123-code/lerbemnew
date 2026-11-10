<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

$u = require_professor();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(lb_url('professor/turmas.php'));
}
csrf_verify();

$pdo = db();
$escolaId = (int) $u['escola_id'];
$id = int_inp('id', 0) ?? 0;
$acao = inp('acao');

$st = $pdo->prepare('SELECT * FROM turmas WHERE id = ? AND escola_id = ?');
$st->execute([$id, $escolaId]);
$turma = $st->fetch();
if (!$turma) {
    flash('error', 'Turma nao encontrada nesta escola.');
    redirect(lb_url('professor/turmas.php'));
}

if ($acao === 'excluir') {
    $st = $pdo->prepare('SELECT COUNT(*) FROM alunos WHERE turma_id = ? AND escola_id = ?');
    $st->execute([$id, $escolaId]);
    $alunos = (int) $st->fetchColumn();

    if ($alunos > 0) {
        $pdo->prepare('UPDATE turmas SET ativo = 0 WHERE id = ? AND escola_id = ?')->execute([$id, $escolaId]);
        flash('warning', 'A turma possui ' . $alunos . ' aluno(s) e foi inativada em vez de excluida. Transfira os alunos para outra turma para excluir.');
        redirect(lb_url('professor/turmas.php'));
    }

    $pdo->prepare('DELETE FROM turmas WHERE id = ? AND escola_id = ?')->execute([$id, $escolaId]);
    flash('success', 'Turma excluida com sucesso.');
    redirect(lb_url('professor/turmas.php'));
}

flash('error', 'Acao invalida.');
redirect(lb_url('professor/turmas.php'));
