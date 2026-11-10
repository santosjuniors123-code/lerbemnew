<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

$u = require_professor();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(lb_url('professor/questionarios.php'));
}
csrf_verify();

$pdo = db();
$escolaId = (int) $u['escola_id'];
$id = int_inp('id', 0) ?? 0;
$questionarioId = int_inp('questionario', 0) ?? 0;

$st = $pdo->prepare('SELECT p.* FROM perguntas p JOIN questionarios q ON q.id = p.questionario_id
    WHERE p.id = ? AND q.escola_id = ?');
$st->execute([$id, $escolaId]);
$pergunta = $st->fetch();
if (!$pergunta) {
    flash('error', 'Pergunta nao encontrada nesta escola.');
    redirect(lb_url('professor/questionarios.php'));
}

if (inp('acao') === 'excluir') {
    $st = $pdo->prepare('SELECT COUNT(*) FROM respostas WHERE pergunta_id = ?');
    $st->execute([$id]);
    if ((int) $st->fetchColumn() > 0) {
        flash('warning', 'Esta pergunta ja possui respostas e nao pode ser excluida.');
        redirect(lb_url('professor/questionario_form.php?id=' . (int) $pergunta['questionario_id']));
    }

    $pdo->beginTransaction();
    try {
        $pdo->prepare('DELETE FROM alternativas WHERE pergunta_id = ?')->execute([$id]);
        $pdo->prepare('DELETE FROM perguntas WHERE id = ?')->execute([$id]);
        $pdo->commit();
    } catch (Throwable $ex) {
        $pdo->rollBack();
        lb_fatal('Nao foi possivel excluir a pergunta.', $ex);
    }
    flash('success', 'Pergunta excluida.');
    redirect(lb_url('professor/questionario_form.php?id=' . (int) $pergunta['questionario_id']));
}

flash('error', 'Acao invalida.');
redirect(lb_url('professor/questionario_form.php?id=' . (int) ($questionarioId ?: $pergunta['questionario_id'])));
