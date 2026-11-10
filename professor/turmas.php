<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

$u = require_professor();
$pdo = db();
$escolaId = (int) $u['escola_id'];

$st = $pdo->prepare('SELECT t.*,
        (SELECT COUNT(*) FROM alunos a WHERE a.turma_id = t.id AND a.escola_id = t.escola_id AND a.status = "ativo") AS alunos,
        (SELECT COUNT(*) FROM leituras l WHERE l.turma_id = t.id AND l.escola_id = t.escola_id) AS leituras,
        (SELECT COALESCE(SUM(l.paginas),0) FROM leituras l WHERE l.turma_id = t.id AND l.escola_id = t.escola_id) AS paginas
    FROM turmas t WHERE t.escola_id = ? ORDER BY t.ano_letivo DESC, t.nome ASC');
$st->execute([$escolaId]);
$turmas = $st->fetchAll();

$turnos = ['manha' => 'Manha', 'tarde' => 'Tarde', 'noite' => 'Noite', 'integral' => 'Integral'];

lb_shell_start('Turmas', [
    'contexto' => 'professor',
    'ativo'    => 'turmas',
    'titulo_pagina' => 'Turmas',
    'subtitulo' => count($turmas) . ' turma' . (count($turmas) === 1 ? '' : 's') . ' cadastrada' . (count($turmas) === 1 ? '' : 's'),
    'acoes' => '<a class="btn btn-sm" href="' . e(lb_url('professor/turma_form.php')) . '">' . lb_icon('plus') . 'Nova turma</a>',
]);
?>
<div class="card">
  <?php if (!$turmas): ?>
    <?= lb_empty('class', 'Nenhuma turma cadastrada', 'Crie a primeira turma para organizar seus alunos e gerar relatorios por classe.', '<a class="btn btn-sm" href="' . e(lb_url('professor/turma_form.php')) . '">Criar turma</a>') ?>
  <?php else: ?>
    <div class="table-wrap">
      <table class="table">
        <thead><tr><th>Turma</th><th>Ano letivo</th><th>Turno</th><th>Serie</th><th>Alunos</th><th>Leituras</th><th>Paginas</th><th>Situacao</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($turmas as $t): ?>
          <tr>
            <td><div class="cell-main"><strong><?= e($t['nome']) ?></strong><small>Media de <?= (int) $t['alunos'] > 0 ? lb_num((int) $t['leituras'] / (int) $t['alunos'], 1) : '0,0' ?> leituras por aluno</small></div></td>
            <td><?= (int) $t['ano_letivo'] ?></td>
            <td><?= e($turnos[$t['turno']] ?? $t['turno']) ?></td>
            <td><?= e($t['serie'] ?: '—') ?></td>
            <td><?= (int) $t['alunos'] ?></td>
            <td><?= (int) $t['leituras'] ?></td>
            <td><?= number_format((int) $t['paginas'], 0, ',', '.') ?></td>
            <td><?= (int) $t['ativo'] ? lb_badge('Ativa', 'success') : lb_badge('Inativa', 'neutral') ?></td>
            <td class="nowrap">
              <a class="btn btn-ghost btn-sm" href="<?= e(lb_url('professor/alunos.php?turma=' . (int) $t['id'])) ?>">Alunos</a>
              <a class="btn btn-soft btn-sm" href="<?= e(lb_url('professor/turma_form.php?id=' . (int) $t['id'])) ?>"><?= lb_icon('edit') ?>Editar</a>
              <form method="post" action="<?= e(lb_url('professor/turma_acao.php')) ?>" data-confirm="Excluir a turma <?= e($t['nome']) ?>?" style="display:inline">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= (int) $t['id'] ?>">
                <input type="hidden" name="acao" value="excluir">
                <button class="btn btn-danger btn-sm" type="submit"><?= lb_icon('trash') ?>Excluir</button>
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
