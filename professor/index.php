<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

$u = require_professor();
$pdo = db();
$escolaId = (int) $u['escola_id'];

$alunosAtivos = (int) $pdo->query("SELECT COUNT(*) FROM alunos WHERE escola_id = $escolaId AND status = 'ativo'")->fetchColumn();
$turmasAtivas = (int) $pdo->query("SELECT COUNT(*) FROM turmas WHERE escola_id = $escolaId AND ativo = 1")->fetchColumn();
$st = $pdo->prepare('SELECT COUNT(*) AS leituras, COALESCE(SUM(paginas),0) AS paginas, AVG(nota) AS media FROM leituras WHERE escola_id = ?');
$st->execute([$escolaId]);
$tot = $st->fetch() ?: ['leituras' => 0, 'paginas' => 0, 'media' => null];

$st = $pdo->prepare("SELECT COUNT(*) FROM questionarios WHERE escola_id = ? AND status = 'publicado'");
$st->execute([$escolaId]);
$publicados = (int) $st->fetchColumn();

$st = $pdo->prepare('SELECT a.id, a.nome, t.nome AS turma, COUNT(l.id) AS qtd,
        COALESCE(SUM(l.paginas),0) AS paginas
    FROM alunos a
    LEFT JOIN turmas t ON t.id = a.turma_id
    LEFT JOIN leituras l ON l.aluno_id = a.id AND l.escola_id = a.escola_id
    WHERE a.escola_id = ? AND a.status = "ativo"
    GROUP BY a.id, a.nome, t.nome
    ORDER BY qtd DESC, paginas DESC, a.nome ASC LIMIT 5');
$st->execute([$escolaId]);
$top = $st->fetchAll();

$st = $pdo->prepare('SELECT a.id, a.nome, t.nome AS turma,
        MAX(l.data_leitura) AS ultima
    FROM alunos a
    LEFT JOIN turmas t ON t.id = a.turma_id
    LEFT JOIN leituras l ON l.aluno_id = a.id AND l.escola_id = a.escola_id
    WHERE a.escola_id = ? AND a.status = "ativo"
    GROUP BY a.id, a.nome, t.nome
    HAVING ultima IS NULL OR ultima < (CURDATE() - INTERVAL 30 DAY)
    ORDER BY ultima IS NULL DESC, ultima ASC LIMIT 6');
$st->execute([$escolaId]);
$acompanhar = $st->fetchAll();

$st = $pdo->prepare('SELECT l.*, a.nome AS aluno_nome, t.nome AS turma_nome
    FROM leituras l JOIN alunos a ON a.id = l.aluno_id
    LEFT JOIN turmas t ON t.id = l.turma_id
    WHERE l.escola_id = ? ORDER BY l.criado_em DESC, l.id DESC LIMIT 7');
$st->execute([$escolaId]);
$recentes = $st->fetchAll();

// Leituras por mes (ultimos 6 meses)
$meses = [];
$valores = [];
for ($i = 5; $i >= 0; $i--) {
    $ini = (new DateTimeImmutable('first day of this month'))->modify("-$i month");
    $fim = $ini->modify('last day of this month');
    $st = $pdo->prepare('SELECT COUNT(*) FROM leituras WHERE escola_id = ? AND data_leitura BETWEEN ? AND ?');
    $st->execute([$escolaId, $ini->format('Y-m-d'), $fim->format('Y-m-d')]);
    $meses[] = $ini->format('m/Y');
    $valores[] = (int) $st->fetchColumn();
}
$chart = json_encode([
    'type' => 'bar',
    'data' => [
        'labels' => $meses,
        'datasets' => [[
            'label' => 'Leituras',
            'data' => $valores,
            'backgroundColor' => '#0f766e',
            'borderRadius' => 8,
            'maxBarThickness' => 38,
        ]],
    ],
], JSON_UNESCAPED_UNICODE);

lb_shell_start('Dashboard', [
    'contexto'  => 'professor',
    'ativo'     => 'dashboard',
    'chartjs'   => true,
    'titulo_pagina' => 'Dashboard',
    'subtitulo' => 'Visao geral da leitura na sua escola',
    'acoes'     => '<a class="btn btn-sm" href="' . e(lb_url('aluno/leitura_nova.php')) . '">' . lb_icon('plus') . 'Nova leitura</a>',
]);
?>
<div class="welcome">
  <div>
    <h2>Ola, <?= e(explode(' ', $u['nome'])[0]) ?>!</h2>
    <p>Resumo do movimento de leitura da <?= e($u['escola_nome'] ?? 'sua escola') ?>. <?= $publicados ?> questionario<?= $publicados === 1 ? '' : 's' ?> publicado<?= $publicados === 1 ? '' : 's' ?>.</p>
  </div>
  <div class="btn-row">
    <a class="btn btn-soft btn-sm" href="<?= e(lb_url('professor/leituras.php')) ?>"><?= lb_icon('read') ?>Ver leituras</a>
    <a class="btn btn-sm" href="<?= e(lb_url('professor/questionario_form.php')) ?>"><?= lb_icon('quiz') ?>Novo questionario</a>
  </div>
</div>

<div class="grid grid-4">
  <article class="stat">
    <div class="stat-top"><span class="stat-ico"><?= lb_icon('users') ?></span><span class="stat-label">Alunos ativos</span></div>
    <strong class="stat-value"><?= $alunosAtivos ?></strong>
    <span class="stat-hint"><?= $turmasAtivas ?> turma<?= $turmasAtivas === 1 ? '' : 's' ?> ativa<?= $turmasAtivas === 1 ? '' : 's' ?></span>
  </article>
  <article class="stat">
    <div class="stat-top"><span class="stat-ico"><?= lb_icon('read') ?></span><span class="stat-label">Leituras</span></div>
    <strong class="stat-value"><?= (int) $tot['leituras'] ?></strong>
    <span class="stat-hint">Registros de toda a escola</span>
  </article>
  <article class="stat">
    <div class="stat-top"><span class="stat-ico"><?= lb_icon('file') ?></span><span class="stat-label">Paginas lidas</span></div>
    <strong class="stat-value"><?= number_format((int) $tot['paginas'], 0, ',', '.') ?></strong>
    <span class="stat-hint">Soma informada pelos alunos</span>
  </article>
  <article class="stat">
    <div class="stat-top"><span class="stat-ico"><?= lb_icon('star') ?></span><span class="stat-label">Media nos questionarios</span></div>
    <strong class="stat-value"><?= $tot['media'] !== null ? lb_num((float) $tot['media']) : '—' ?></strong>
    <span class="stat-hint">Escala de 0 a 10</span>
  </article>
</div>

<div class="grid grid-2 mt-24">
  <div class="card">
    <div class="card-head"><?= lb_icon('chart') ?><div><h2>Leituras por mes</h2><p>Ultimos 6 meses</p></div></div>
    <div class="chart-box"><canvas data-chart="<?= e($chart) ?>" height="240" aria-label="Grafico de leituras por mes" role="img"></canvas></div>
  </div>
  <div class="card">
    <div class="card-head"><?= lb_icon('star') ?><div><h2>Alunos que mais leem</h2><p>Ranking por quantidade de leituras</p></div></div>
    <?php if (!$top): ?>
      <?= lb_empty('users', 'Nenhuma leitura ainda', 'Cadastre alunos e incentive o primeiro registro de leitura.') ?>
    <?php else: ?>
      <div class="rank">
        <?php foreach ($top as $i => $t): ?>
          <div class="rank-body">
            <span class="rank-pos"><?= $i + 1 ?>º</span>
            <?= lb_avatar($t['nome']) ?>
            <div style="flex:1">
              <strong><?= e($t['nome']) ?></strong>
              <small class="muted"><?= e($t['turma'] ?? 'Sem turma') ?></small>
            </div>
            <span class="rank-value"><?= (int) $t['qtd'] ?> <small>leitura<?= (int) $t['qtd'] === 1 ? '' : 's' ?></small></span>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</div>

<div class="grid grid-2 mt-24">
  <div class="card">
    <div class="card-head"><?= lb_icon('alert') ?><div><h2>Precisam de acompanhamento</h2><p>Sem leitura nos ultimos 30 dias</p></div></div>
    <?php if (!$acompanhar): ?>
      <?= lb_empty('check', 'Todos em dia', 'Nenhum aluno ficou 30 dias sem registrar leitura.') ?>
    <?php else: ?>
      <div class="table-wrap">
        <table class="table">
          <thead><tr><th>Aluno</th><th>Turma</th><th>Ultima leitura</th><th></th></tr></thead>
          <tbody>
          <?php foreach ($acompanhar as $a): ?>
            <tr>
              <td><div class="cell-main"><?= lb_avatar($a['nome']) ?><strong><?= e($a['nome']) ?></strong></div></td>
              <td><?= e($a['turma'] ?? '—') ?></td>
              <td class="nowrap"><?= $a['ultima'] ? e(lb_date($a['ultima'])) : lb_badge('Nunca leu', 'danger') ?></td>
              <td><a class="btn btn-ghost btn-sm" href="<?= e(lb_url('professor/aluno_historico.php?id=' . (int) $a['id'])) ?>">Ver</a></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>

  <div class="card">
    <div class="card-head"><?= lb_icon('clock') ?><div><h2>Leituras recentes</h2><p>Ultimos registros enviados</p></div></div>
    <?php if (!$recentes): ?>
      <?= lb_empty('read', 'Nenhum registro', 'As leituras dos alunos aparecem aqui em tempo real.') ?>
    <?php else: ?>
      <div class="table-wrap">
        <table class="table">
          <thead><tr><th>Aluno</th><th>Livro</th><th>Data</th><th>Situacao</th><th></th></tr></thead>
          <tbody>
          <?php foreach ($recentes as $l): ?>
            <tr>
              <td><div class="cell-main"><strong><?= e($l['aluno_nome']) ?></strong><small><?= e($l['turma_nome'] ?? 'Sem turma') ?></small></div></td>
              <td><?= e(lb_resumir($l['titulo'], 26)) ?></td>
              <td class="nowrap"><?= e(lb_date($l['data_leitura'])) ?></td>
              <td>
                <?php if ($l['status'] === 'validada'): ?><?= lb_badge('Validada', 'success') ?>
                <?php elseif ($l['status'] === 'pendente_correcao'): ?><?= lb_badge('Corrigir', 'warning') ?>
                <?php else: ?><?= lb_badge('Registrada', 'info') ?><?php endif; ?>
              </td>
              <td><a class="btn btn-ghost btn-sm" href="<?= e(lb_url('professor/leitura.php?id=' . (int) $l['id'])) ?>">Abrir</a></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>
<?php
lb_shell_end(['contexto' => 'professor']);
