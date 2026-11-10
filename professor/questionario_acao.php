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
$acao = inp('acao');

$st = $pdo->prepare('SELECT * FROM questionarios WHERE id = ? AND escola_id = ?');
$st->execute([$id, $escolaId]);
$q = $st->fetch();
if (!$q) {
    flash('error', 'Questionario nao encontrado nesta escola.');
    redirect(lb_url('professor/questionarios.php'));
}

if ($acao === 'publicar' || $acao === 'arquivar') {
    if ($acao === 'publicar') {
        $st = $pdo->prepare('SELECT COUNT(*) FROM perguntas WHERE questionario_id = ?');
        $st->execute([$id]);
        if ((int) $st->fetchColumn() === 0) {
            flash('error', 'Adicione ao menos uma pergunta antes de publicar.');
            redirect(lb_url('professor/questionarios.php'));
        }
    }
    $novo = $acao === 'publicar' ? 'publicado' : 'arquivado';
    $pdo->prepare('UPDATE questionarios SET status = ? WHERE id = ? AND escola_id = ?')->execute([$novo, $id, $escolaId]);
    flash('success', 'Questionario ' . ($novo === 'publicado' ? 'publicado' : 'arquivado') . ' com sucesso.');
    redirect(lb_url('professor/questionarios.php'));
}

if ($acao === 'excluir') {
    $st = $pdo->prepare('SELECT COUNT(*) FROM respostas WHERE questionario_id = ? AND escola_id = ?');
    $st->execute([$id, $escolaId]);
    if ((int) $st->fetchColumn() > 0) {
        $pdo->prepare('UPDATE questionarios SET status = "arquivado" WHERE id = ? AND escola_id = ?')->execute([$id, $escolaId]);
        flash('warning', 'Este questionario ja possui respostas e foi arquivado em vez de excluido.');
        redirect(lb_url('professor/questionarios.php'));
    }

    $pdo->beginTransaction();
    try {
        $pdo->prepare('DELETE a FROM alternativas a JOIN perguntas p ON p.id = a.pergunta_id WHERE p.questionario_id = ?')->execute([$id]);
        $pdo->prepare('DELETE FROM perguntas WHERE questionario_id = ?')->execute([$id]);
        $pdo->prepare('UPDATE leituras SET questionario_id = NULL WHERE questionario_id = ? AND escola_id = ?')->execute([$id, $escolaId]);
        $pdo->prepare('DELETE FROM questionarios WHERE id = ? AND escola_id = ?')->execute([$id, $escolaId]);
        $pdo->commit();
    } catch (Throwable $ex) {
        $pdo->rollBack();
        lb_fatal('Nao foi possivel excluir o questionario.', $ex);
    }
    flash('success', 'Questionario excluido.');
    redirect(lb_url('professor/questionarios.php'));
}

flash('error', 'Acao invalida.');
redirect(lb_url('professor/questionarios.php'));
