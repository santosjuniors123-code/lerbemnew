<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

$aluno = require_aluno();
$pdo = db();
$escolaId = (int) $aluno['escola_id'];

$total = (int) $pdo->query('SELECT COUNT(*) FROM leituras WHERE aluno_id = ' . (int) $aluno['id'] . ' AND escola_id = ' . $escolaId)->fetchColumn();
$porPagina = 10;
$pag = max(1, (int) int_get('pagina', 1));
$pg = lb_paginacao($total, $porPagina, $pag);

$st = $pdo->prepare('SELECT l.*, q.titulo AS questionario_titulo FROM leituras l
    LEFT JOIN questionarios q ON q.id = l.questionario_id
    WHERE l.aluno_id = ? AND l.escola_id = ?
    ORDER BY l.data_leitura DESC, l.id DESC LIMIT ' . $porPagina . ' OFFSET ' . (int) $pg['offset']);
$st->execute([$aluno['id'], $escolaId]);
$leituras = $st->fetchAll();

lb_shell_start('Minhas leituras', ['contexto' => 'aluno', 'ativo' => 'historico']);
?>
<div class="card">
  <div class="card-head">
    <?= lb_icon('read', 'ico ico-lg') ?>
    <div><h2>Minhas leituras</h2><p><?= $total ?> leitura<?= $total === 1 ? '' : 's' ?> registrada<?= $total === 1 ? '' : 's' ?> no seu historico.</p></div>
  </div>

  <?php if (!$leituras): ?>
    <?= lb_empty('read', 'Nenhuma leitura registrada', 'Assim que voce registrar um livro ele aparece aqui com sua situacao e nota.', '<a class="btn btn-sm" href="' . e(lb_url('aluno/leitura_nova.php')) . '">Registrar leitura</a>') ?>
  <?php else: ?>
    <div class="table-wrap">
      <table class="table">
        <thead><tr><th>Livro</th><th>Data</th><th>Paginas</th><th>Nota</th><th>Situacao</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($leituras as $l): ?>
          <tr>
            <td>
              <div class="cell-main"><strong><?= e($l['titulo']) ?></strong>
                <small><?= e($l['autor'] ?: 'Autor nao informado') ?><?= $l['questionario_titulo'] ? ' · ' . e($l['questionario_titulo']) : '' ?></small></div>
            </td>
            <td class="nowrap"><?= e(lb_date($l['data_leitura'])) ?></td>
            <td><?= (int) $l['paginas'] ?></td>
            <td><?= $l['nota'] !== null ? lb_num((float) $l['nota']) : '—' ?></td>
            <td>
              <?php if ($l['status'] === 'validada'): ?>
                <?= lb_badge('Validada', 'success') ?>
              <?php elseif ($l['status'] === 'pendente_correcao'): ?>
                <?= lb_badge('Em correcao', 'warning') ?>
              <?php else: ?>
                <?= lb_badge('Registrada', 'info') ?>
              <?php endif; ?>
            </td>
            <td class="nowrap">
              <?php if ($l['questionario_id'] && (int) $l['total_objetivas'] === 0 && $l['status'] !== 'validada'): ?>
                <a class="btn btn-soft btn-sm" href="<?= e(lb_url('aluno/responder.php?leitura=' . (int) $l['id'])) ?>">Responder</a>
              <?php endif; ?>
              <a class="btn btn-ghost btn-sm" href="<?= e(lb_url('aluno/leitura.php?id=' . (int) $l['id'])) ?>">Detalhes</a>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?= $pg['html'] ?>
  <?php endif; ?>
</div>
<?php
lb_shell_end(['contexto' => 'aluno']);
