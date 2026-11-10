<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

$u = require_professor();
$pdo = db();
$escolaId = (int) $u['escola_id'];

$de = gin('de');
$ate = gin('ate');
$turmaId = int_get('turma', 0) ?: null;
$alunoId = int_get('aluno', 0) ?: null;

$where = ['l.escola_id = ?'];
$params = [$escolaId];
if ($de !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $de)) {
    $where[] = 'l.data_leitura >= ?';
    $params[] = $de;
}
if ($ate !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $ate)) {
    $where[] = 'l.data_leitura <= ?';
    $params[] = $ate;
}
if ($turmaId) {
    $where[] = 'l.turma_id = ?';
    $params[] = $turmaId;
}
if ($alunoId) {
    $where[] = 'l.aluno_id = ?';
    $params[] = $alunoId;
}
$whereSql = implode(' AND ', $where);

$st = $pdo->prepare("SELECT COUNT(*) AS leituras, COALESCE(SUM(l.paginas),0) AS paginas,
        COUNT(DISTINCT l.aluno_id) AS alunos, AVG(l.nota) AS media,
        SUM(CASE WHEN l.questionario_id IS NOT NULL THEN 1 ELSE 0 END) AS com_questionario,
        SUM(CASE WHEN l.status = 'pendente_correcao' THEN 1 ELSE 0 END) AS pendentes
    FROM leituras l WHERE $whereSql");
$st->execute($params);
$resumo = $st->fetch() ?: ['leituras' => 0, 'paginas' => 0, 'alunos' => 0, 'media' => null, 'com_questionario' => 0, 'pendentes' => 0];

$st = $pdo->prepare("SELECT t.id, t.nome, t.ano_letivo,
        COUNT(DISTINCT a.id) AS alunos,
        COUNT(l.id) AS leituras,
        COALESCE(SUM(l.paginas), 0) AS paginas,
        AVG(l.nota) AS media
    FROM turmas t
    LEFT JOIN alunos a ON a.turma_id = t.id AND a.escola_id = t.escola_id AND a.status = 'ativo'
    LEFT JOIN leituras l ON l.aluno_id = a.id AND l.escola_id = t.escola_id"
    . ($de !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $de) ? ' AND l.data_leitura >= ?' : '')
    . ($ate !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $ate) ? ' AND l.data_leitura <= ?' : '')
    . " WHERE t.escola_id = ? GROUP BY t.id, t.nome, t.ano_letivo ORDER BY leituras DESC, t.nome");
$pTurma = [];
if ($de !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $de)) {
    $pTurma[] = $de;
}
if ($ate !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $ate)) {
    $pTurma[] = $ate;
}
$pTurma[] = $escolaId;
$st->execute($pTurma);
$porTurma = $st->fetchAll();

$st = $pdo->prepare("SELECT a.id, a.nome, t.nome AS turma_nome, COUNT(l.id) AS leituras,
        COALESCE(SUM(l.paginas),0) AS paginas, AVG(l.nota) AS media
    FROM leituras l
    JOIN alunos a ON a.id = l.aluno_id
    LEFT JOIN turmas t ON t.id = a.turma_id
    WHERE $whereSql
    GROUP BY a.id, a.nome, t.nome
    ORDER BY leituras DESC, paginas DESC LIMIT 10");
$st->execute($params);
$topAlunos = $st->fetchAll();

$st = $pdo->prepare("SELECT DATE_FORMAT(l.data_leitura, '%Y-%m') AS mes, COUNT(*) AS leituras, COALESCE(SUM(l.paginas),0) AS paginas
    FROM leituras l WHERE $whereSql GROUP BY mes ORDER BY mes");
$st->execute($params);
$porMes = $st->fetchAll();

$meses = ['01' => 'Jan', '02' => 'Fev', '03' => 'Mar', '04' => 'Abr', '05' => 'Mai', '06' => 'Jun',
          '07' => 'Jul', '08' => 'Ago', '09' => 'Set', '10' => 'Out', '11' => 'Nov', '12' => 'Dez'];
$labels = [];
$valores = [];
foreach ($porMes as $m) {
    [$ano, $mes] = array_pad(explode('-', (string) $m['mes']), 2, '');
    $labels[] = ($meses[$mes] ?? $mes) . '/' . substr($ano, 2);
    $valores[] = (int) $m['leituras'];
}

$chart = [
    'type' => 'bar',
    'data' => [
        'labels' => $labels,
        'datasets' => [[
            'label' => 'Leituras por mes',
            'data' => $valores,
            'backgroundColor' => '#0f766e',
            'borderRadius' => 6,
            'maxBarThickness' => 42,
        ]],
    ],
];

$turmas = lb_turmas_escola($escolaId, false);
$alunos = lb_alunos_escola($escolaId);
$query = http_build_query(array_filter(['de' => $de, 'ate' => $ate, 'turma' => $turmaId, 'aluno' => $alunoId]));

lb_shell_start('Relatorios', [
    'contexto' => 'professor',
    'ativo'    => 'relatorios',
    'titulo_pagina' => 'Relatorios',
    'subtitulo' => 'Leituras, participacao e aproveitamento',
    'chartjs'  => true,
    'acoes' => '<a class="btn btn-ghost btn-sm" href="' . e(lb_url('professor/relatorios_export.php?formato=csv&' . $query)) . '">' . lb_icon('download') . 'CSV</a>'
        . '<a class="btn btn-sm" href="' . e(lb_url('professor/relatorios_export.php?formato=pdf&' . $query)) . '">' . lb_icon('file') . 'PDF</a>',
]);
?>
<div class="card">
  <form class="filters" method="get" data-loading>
    <div class="field">
      <label for="de">De</label>
      <input class="input" type="date" id="de" name="de" value="<?= e($de) ?>">
    </div>
    <div class="field">
      <label for="ate">Ate</label>
      <input class="input" type="date" id="ate" name="ate" value="<?= e($ate) ?>">
    </div>
    <div class="field">
      <label for="turma">Turma</label>
      <select class="input" id="turma" name="turma">
        <option value="">Todas</option>
        <?php foreach ($turmas as $t): ?>
          <option value="<?= (int) $t['id'] ?>" <?= $turmaId === (int) $t['id'] ? 'selected' : '' ?>><?= e($t['nome']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="field">
      <label for="aluno">Aluno</label>
      <select class="input" id="aluno" name="aluno">
        <option value="">Todos</option>
        <?php foreach ($alunos as $a): ?>
          <option value="<?= (int) $a['id'] ?>" <?= $alunoId === (int) $a['id'] ? 'selected' : '' ?>><?= e($a['nome']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="field">
      <label>&nbsp;</label>
      <div class="btn-row">
        <button class="btn btn-sm" type="submit"><?= lb_icon('search') ?>Aplicar</button>
        <a class="btn btn-ghost btn-sm" href="<?= e(lb_url('professor/relatorios.php')) ?>">Limpar</a>
      </div>
    </div>
  </form>
</div>

<div class="grid grid-4 mt-24">
  <div class="stat"><div class="stat-icon"><?= lb_icon('read') ?></div><div><small>Leituras</small><strong><?= (int) $resumo['leituras'] ?></strong></div></div>
  <div class="stat"><div class="stat-icon"><?= lb_icon('book') ?></div><div><small>Paginas lidas</small><strong><?= (int) $resumo['paginas'] ?></strong></div></div>
  <div class="stat"><div class="stat-icon"><?= lb_icon('users') ?></div><div><small>Alunos participantes</small><strong><?= (int) $resumo['alunos'] ?></strong></div></div>
  <div class="stat"><div class="stat-icon"><?= lb_icon('star') ?></div><div><small>Media nas questoes</small><strong><?= $resumo['media'] !== null ? lb_num((float) $resumo['media']) : '—' ?></strong></div></div>
</div>

<div class="card mt-24">
  <div class="card-head">
    <?= lb_icon('chart', 'ico ico-lg') ?>
    <div><h2>Leituras por periodo</h2><p><?= $labels ? 'Agrupadas por mes' : 'Sem dados no periodo' ?></p></div>
  </div>
  <div class="chart-box" style="height:280px">
    <canvas data-chart="<?= e(json_encode($chart, JSON_UNESCAPED_UNICODE)) ?>"></canvas>
  </div>
</div>

<div class="grid grid-2 mt-24">
  <div class="card">
    <div class="card-head">
      <?= lb_icon('class', 'ico ico-lg') ?>
      <div><h2>Desempenho por turma</h2><p>Leituras e paginas no periodo</p></div>
    </div>
    <?php if (!$porTurma): ?>
      <?= lb_empty('class', 'Nenhuma turma cadastrada', 'Cadastre turmas para acompanhar o desempenho coletivo.') ?>
    <?php else: ?>
      <div class="table-wrap">
        <table class="table">
          <thead><tr><th>Turma</th><th>Alunos</th><th>Leituras</th><th>Paginas</th><th>Media</th></tr></thead>
          <tbody>
          <?php foreach ($porTurma as $t): ?>
            <tr>
              <td><?= e($t['nome']) ?> <small class="muted"><?= (int) $t['ano_letivo'] ?></small></td>
              <td><?= (int) $t['alunos'] ?></td>
              <td><?= (int) $t['leituras'] ?></td>
              <td><?= (int) $t['paginas'] ?></td>
              <td><?= $t['media'] !== null ? lb_num((float) $t['media']) : '—' ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>

  <div class="card">
    <div class="card-head">
      <?= lb_icon('star', 'ico ico-lg') ?>
      <div><h2>Alunos mais participativos</h2><p>Top 10 no periodo</p></div>
    </div>
    <?php if (!$topAlunos): ?>
      <?= lb_empty('read', 'Sem leituras no periodo', 'Ajuste as datas ou aguarde novos registros.') ?>
    <?php else: ?>
      <div class="table-wrap">
        <table class="table">
          <thead><tr><th>Aluno</th><th>Turma</th><th>Leituras</th><th>Paginas</th><th>Media</th></tr></thead>
          <tbody>
          <?php foreach ($topAlunos as $a): ?>
            <tr>
              <td><a href="<?= e(lb_url('professor/aluno_historico.php?id=' . (int) $a['id'])) ?>"><?= e($a['nome']) ?></a></td>
              <td><?= e($a['turma_nome'] ?? '—') ?></td>
              <td><?= (int) $a['leituras'] ?></td>
              <td><?= (int) $a['paginas'] ?></td>
              <td><?= $a['media'] !== null ? lb_num((float) $a['media']) : '—' ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>

<div class="card mt-24">
  <div class="card-head">
    <?= lb_icon('quiz', 'ico ico-lg') ?>
    <div><h2>Questionarios</h2><p>Aproveitamento e pendencias de correcao</p></div>
  </div>
  <div class="grid grid-3">
    <div class="detail"><small>Leituras com questionario</small><strong><?= (int) $resumo['com_questionario'] ?></strong></div>
    <div class="detail"><small>Aguardando correcao</small><strong><?= (int) $resumo['pendentes'] ?></strong></div>
    <div class="detail"><small>Media geral</small><strong><?= $resumo['media'] !== null ? lb_num((float) $resumo['media']) . ' / 10' : '—' ?></strong></div>
  </div>
  <p class="muted small mt-16">Exporte o relatorio em CSV (planilhas) ou PDF (impressao e arquivo) usando os botoes no topo da pagina; os filtros aplicados sao mantidos na exportacao.</p>
</div>
<?php
lb_shell_end(['contexto' => 'professor']);
