<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

$u = require_professor();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(lb_url('professor/alunos.php'));
}
csrf_verify();

$pdo = db();
$escolaId = (int) $u['escola_id'];
$id = int_inp('id', 0) ?? 0;
$acao = inp('acao');

$st = $pdo->prepare('SELECT * FROM alunos WHERE id = ? AND escola_id = ?');
$st->execute([$id, $escolaId]);
$aluno = $st->fetch();
if (!$aluno) {
    flash('error', 'Aluno nao encontrado nesta escola.');
    redirect(lb_url('professor/alunos.php'));
}

if ($acao === 'status') {
    $novo = $aluno['status'] === 'ativo' ? 'inativo' : 'ativo';
    $pdo->prepare('UPDATE alunos SET status = ? WHERE id = ? AND escola_id = ?')->execute([$novo, $id, $escolaId]);
    flash('success', 'Aluno ' . ($novo === 'ativo' ? 'reativado' : 'inativado') . ' com sucesso.');
    redirect(lb_url('professor/alunos.php'));
}

if ($acao === 'excluir') {
    $st = $pdo->prepare('SELECT COUNT(*) FROM leituras WHERE aluno_id = ? AND escola_id = ?');
    $st->execute([$id, $escolaId]);
    $leituras = (int) $st->fetchColumn();

    if ($leituras > 0) {
        $pdo->prepare('UPDATE alunos SET status = "inativo" WHERE id = ? AND escola_id = ?')->execute([$id, $escolaId]);
        flash('warning', 'Este aluno possui ' . $leituras . ' leitura(s) no historico e nao pode ser excluido. Ele foi inativado.');
        redirect(lb_url('professor/alunos.php'));
    }

    $pdo->prepare('DELETE FROM alunos WHERE id = ? AND escola_id = ?')->execute([$id, $escolaId]);
    flash('success', 'Aluno excluido com sucesso.');
    redirect(lb_url('professor/alunos.php'));
}

flash('error', 'Acao invalida.');
redirect(lb_url('professor/alunos.php'));
