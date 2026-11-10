<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

$u = require_professor();
$pdo = db();
$escolaId = (int) $u['escola_id'];

$st = $pdo->prepare('SELECT q.*,
        (SELECT COUNT(*) FROM perguntas p WHERE p.questionario_id = q.id) AS perguntas,
        (SELECT COUNT(*) FROM respostas r WHERE r.questionario_id = q.id) AS respostas
    FROM questionarios q WHERE q.escola_id = ? ORDER BY q.criado_em DESC');
$st->execute([$escolaId]);
$questionarios = $st->fetchAll();

$rotuloNivel = ['facil' => 'Facil', 'medio' => 'Medio', 'dificil' => 'Dificil'];

lb_shell_start('Questionarios', [
    'contexto' => 'professor',
    'ativo'    => 'questionarios',
    'titulo_pagina' => 'Questionarios',
    'subtitulo' => count($questionarios) . ' questionario' . (count($questionarios) === 1 ? '' : 's') . ' na escola',
    'acoes' => '<a class="btn btn-sm" href="' . e(lb_url('professor/questionario_form.php')) . '">' . lb_icon('plus') . 'Novo questionario</a>',
]);
?>
<div class="card">
  <?php if (!$questionarios): ?>
    <?= lb_empty('quiz', 'Nenhum questionario criado', 'Crie um questionario manualmente ou use o modulo de IA para gerar perguntas a partir do conteudo do livro.', '<a class="btn btn-sm" href="' . e(lb_url('professor/questionario_form.php')) . '">Criar questionario</a>') ?>
  <?php else: ?>
    <div class="table-wrap">
      <table class="table">
        <thead><tr><th>Questionario</th><th>Livro</th><th>Nivel</th><th>Perguntas</th><th>Respostas</th><th>Origem</th><th>Situacao</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($questionarios as $q): ?>
          <tr>
            <td><div class="cell-main"><strong><?= e($q['titulo']) ?></strong><small>Criado em <?= e(lb_date($q['criado_em'])) ?></small></div></td>
            <td><?= e($q['livro_titulo'] ?: '—') ?></td>
            <td><?= e($rotuloNivel[$q['nivel']] ?? $q['nivel']) ?></td>
            <td><?= (int) $q['perguntas'] ?></td>
            <td><?= (int) $q['respostas'] ?></td>
            <td><?= $q['origem'] === 'ia' ? lb_badge('IA', 'teal') : lb_badge('Manual', 'neutral') ?></td>
            <td>
              <?php if ($q['status'] === 'publicado'): ?><?= lb_badge('Publicado', 'success') ?>
              <?php elseif ($q['status'] === 'arquivado'): ?><?= lb_badge('Arquivado', 'neutral') ?>
              <?php else: ?><?= lb_badge('Rascunho', 'warning') ?><?php endif; ?>
            </td>
            <td class="nowrap">
              <a class="btn btn-soft btn-sm" href="<?= e(lb_url('professor/questionario_form.php?id=' . (int) $q['id'])) ?>"><?= lb_icon('edit') ?>Abrir</a>
              <form method="post" action="<?= e(lb_url('professor/questionario_acao.php')) ?>" style="display:inline">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= (int) $q['id'] ?>">
                <input type="hidden" name="acao" value="<?= $q['status'] === 'publicado' ? 'arquivar' : 'publicar' ?>">
                <button class="btn btn-ghost btn-sm" type="submit"><?= $q['status'] === 'publicado' ? 'Arquivar' : 'Publicar' ?></button>
              </form>
              <form method="post" action="<?= e(lb_url('professor/questionario_acao.php')) ?>" data-confirm="Excluir o questionario <?= e($q['titulo']) ?> e suas perguntas?" style="display:inline">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= (int) $q['id'] ?>">
                <input type="hidden" name="acao" value="excluir">
                <button class="btn btn-danger btn-sm" type="submit"><?= lb_icon('trash') ?></button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>
<?php
lb_shell_end(['contexto' => 'professor']);
