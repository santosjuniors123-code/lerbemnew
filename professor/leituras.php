<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

$u = require_professor();
$pdo = db();
$escolaId = (int) $u['escola_id'];

$turmaId = int_get('turma', 0) ?: null;
$alunoId = int_get('aluno', 0) ?: null;
$de = gin('de');
$ate = gin('ate');
$status = gin('status');

$where = ['l.escola_id = ?'];
$params = [$escolaId];
if ($turmaId) {
    $where[] = 'l.turma_id = ?';
    $params[] = $turmaId;
}
if ($alunoId) {
    $where[] = 'l.aluno_id = ?';
    $params[] = $alunoId;
}
if ($de !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $de)) {
    $where[] = 'l.data_leitura >= ?';
    $params[] = $de;
}
if ($ate !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $ate)) {
    $where[] = 'l.data_leitura <= ?';
    $params[] = $ate;
}
if (in_array($status, ['registrada', 'pendente_correcao', 'validada'], true)) {
    $where[] = 'l.status = ?';
    $params[] = $status;
}
$whereSql = implode(' AND ', $where);

$st = $pdo->prepare("SELECT COUNT(*) FROM leituras l WHERE $whereSql");
$st->execute($params);
$total = (int) $st->fetchColumn();

$porPagina = 15;
$pg = lb_paginacao($total, $porPagina, max(1, (int) int_get('pagina', 1)), array_filter([
    'turma' => $turmaId, 'aluno' => $alunoId, 'de' => $de, 'ate' => $ate, 'status' => $status,
]));

$st = $pdo->prepare("SELECT l.*, a.nome AS aluno_nome, t.nome AS turma_nome, q.titulo AS questionario_titulo
    FROM leituras l
    JOIN alunos a ON a.id = l.aluno_id
    LEFT JOIN turmas t ON t.id = l.turma_id
    LEFT JOIN questionarios q ON q.id = l.questionario_id
    WHERE $whereSql ORDER BY l.data_leitura DESC, l.id DESC LIMIT $porPagina OFFSET " . (int) $pg['offset']);
$st->execute($params);
$leituras = $st->fetchAll();

$turmas = lb_turmas_escola($escolaId, false);
$alunos = lb_alunos_escola($escolaId);
$query = http_build_query(array_filter(['turma' => $turmaId, 'aluno' => $alunoId, 'de' => $de, 'ate' => $ate, 'status' => $status]));

lb_shell_start('Leituras', [
    'contexto' => 'professor',
    'ativo'    => 'leituras',
    'titulo_pagina' => 'Leituras',
    'subtitulo' => $total . ' leitura' . ($total === 1 ? '' : 's') . ' com os filtros atuais',
    'acoes' => '<a class="btn btn-ghost btn-sm" href="' . e(lb_url('professor/relatorios.php?' . $query)) . '">' . lb_icon('report') . 'Relatorio</a>',
]);
?>
<div class="card">
  <form class="filters" method="get" data-loading>
    <div class="field">
      <label for="turma">Turma</label>
      <select class="input" id="turma" name="turma">
        <option value="">Todas</option>
        <?php foreach ($turmas as $t): ?>
          <option value="<?= (int) $t['id'] ?>" <?= $turmaId === (int) $t['id'] ? 'selected' : '' ?>><?= e($t['nome']) ?> · <?= (int) $t['ano_letivo'] ?></option>
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
      <label for="de">De</label>
      <input class="input" type="date" id="de" name="de" value="<?= e($de) ?>">
    </div>
    <div class="field">
      <label for="ate">Ate</label>
      <input class="input" type="date" id="ate" name="ate" value="<?= e($ate) ?>">
    </div>
    <div class="field">
      <label for="status">Situacao</label>
      <select class="input" id="status" name="status">
        <option value="">Todas</option>
        <option value="registrada" <?= $status === 'registrada' ? 'selected' : '' ?>>Registradas</option>
        <option value="pendente_correcao" <?= $status === 'pendente_correcao' ? 'selected' : '' ?>>Aguardando correcao</option>
        <option value="validada" <?= $status === 'validada' ? 'selected' : '' ?>>Validadas</option>
      </select>
    </div>
    <div class="field">
      <label>&nbsp;</label>
      <div class="btn-row">
        <button class="btn btn-sm" type="submit"><?= lb_icon('search') ?>Filtrar</button>
        <a class="btn btn-ghost btn-sm" href="<?= e(lb_url('professor/leituras.php')) ?>">Limpar</a>
      </div>
    </div>
  </form>
</div>

<div class="card mt-24">
  <?php if (!$leituras): ?>
    <?= lb_empty('read', 'Nenhuma leitura encontrada', 'Ajuste os filtros ou aguarde novos registros dos alunos.') ?>
  <?php else: ?>
    <div class="table-wrap">
      <table class="table">
        <thead><tr><th>Aluno</th><th>Livro</th><th>Data</th><th>Paginas</th><th>Questionario</th><th>Nota</th><th>Situacao</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($leituras as $l): ?>
          <tr>
            <td><div class="cell-main"><strong><?= e($l['aluno_nome']) ?></strong><small><?= e($l['turma_nome'] ?? 'Sem turma') ?></small></div></td>
            <td><?= e(lb_resumir($l['titulo'], 30)) ?></td>
            <td class="nowrap"><?= e(lb_date($l['data_leitura'])) ?></td>
            <td><?= (int) $l['paginas'] ?></td>
            <td><?= $l['questionario_titulo'] ? e(lb_resumir($l['questionario_titulo'], 22)) : '—' ?></td>
            <td><?= $l['nota'] !== null ? lb_num((float) $l['nota']) : '—' ?></td>
            <td>
              <?php if ($l['status'] === 'validada'): ?><?= lb_badge('Validada', 'success') ?>
              <?php elseif ($l['status'] === 'pendente_correcao'): ?><?= lb_badge('Corrigir', 'warning') ?>
              <?php else: ?><?= lb_badge('Registrada', 'info') ?><?php endif; ?>
            </td>
            <td><a class="btn btn-soft btn-sm" href="<?= e(lb_url('professor/leitura.php?id=' . (int) $l['id'])) ?>">Abrir</a></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?= $pg['html'] ?>
  <?php endif; ?>
</div>
<?php
lb_shell_end(['contexto' => 'professor']);
