<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

$u = require_professor();
$pdo = db();
$escolaId = (int) $u['escola_id'];

$busca = gin('q');
$turmaId = int_get('turma', 0) ?: null;
$status = gin('status');

$where = ['a.escola_id = ?'];
$params = [$escolaId];
if ($busca !== '') {
    $where[] = 'a.nome_busca LIKE ?';
    $params[] = '%' . lb_nome_busca($busca) . '%';
}
if ($turmaId) {
    $where[] = 'a.turma_id = ?';
    $params[] = $turmaId;
}
if (in_array($status, ['ativo', 'inativo'], true)) {
    $where[] = 'a.status = ?';
    $params[] = $status;
}
$whereSql = implode(' AND ', $where);

$st = $pdo->prepare("SELECT COUNT(*) FROM alunos a WHERE $whereSql");
$st->execute($params);
$total = (int) $st->fetchColumn();

$porPagina = 12;
$pg = lb_paginacao($total, $porPagina, max(1, (int) int_get('pagina', 1)), array_filter([
    'q' => $busca, 'turma' => $turmaId, 'status' => $status,
]));

$st = $pdo->prepare("SELECT a.*, t.nome AS turma_nome,
        (SELECT COUNT(*) FROM leituras l WHERE l.aluno_id = a.id AND l.escola_id = a.escola_id) AS qtd_leituras,
        (SELECT COALESCE(SUM(l.paginas),0) FROM leituras l WHERE l.aluno_id = a.id AND l.escola_id = a.escola_id) AS paginas,
        (SELECT AVG(l.nota) FROM leituras l WHERE l.aluno_id = a.id AND l.escola_id = a.escola_id) AS media
    FROM alunos a LEFT JOIN turmas t ON t.id = a.turma_id
    WHERE $whereSql ORDER BY a.nome ASC LIMIT $porPagina OFFSET " . (int) $pg['offset']);
$st->execute($params);
$alunos = $st->fetchAll();

$turmas = lb_turmas_escola($escolaId);

lb_shell_start('Alunos', [
    'contexto' => 'professor',
    'ativo'    => 'alunos',
    'titulo_pagina' => 'Alunos',
    'subtitulo' => $total . ' aluno' . ($total === 1 ? '' : 's') . ' encontrado' . ($total === 1 ? '' : 's'),
    'acoes' => '<a class="btn btn-sm" href="' . e(lb_url('professor/aluno_form.php')) . '">' . lb_icon('plus') . 'Novo aluno</a>',
]);
?>
<div class="card">
  <form class="filters" method="get" data-loading>
    <div class="field">
      <label for="q">Buscar por nome</label>
      <input class="input" id="q" name="q" value="<?= e($busca) ?>" placeholder="Digite parte do nome">
    </div>
    <div class="field">
      <label for="turma">Turma</label>
      <select class="input" id="turma" name="turma">
        <option value="">Todas as turmas</option>
        <?php foreach ($turmas as $t): ?>
          <option value="<?= (int) $t['id'] ?>" <?= $turmaId === (int) $t['id'] ? 'selected' : '' ?>><?= e($t['nome']) ?> · <?= (int) $t['ano_letivo'] ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="field">
      <label for="status">Situacao</label>
      <select class="input" id="status" name="status">
        <option value="">Todas</option>
        <option value="ativo" <?= $status === 'ativo' ? 'selected' : '' ?>>Ativos</option>
        <option value="inativo" <?= $status === 'inativo' ? 'selected' : '' ?>>Inativos</option>
      </select>
    </div>
    <div class="field">
      <label>&nbsp;</label>
      <div class="btn-row">
        <button class="btn btn-sm" type="submit"><?= lb_icon('search') ?>Filtrar</button>
        <a class="btn btn-ghost btn-sm" href="<?= e(lb_url('professor/alunos.php')) ?>">Limpar</a>
      </div>
    </div>
  </form>
</div>

<div class="card mt-24">
  <?php if (!$alunos): ?>
    <?= lb_empty('users', 'Nenhum aluno encontrado', 'Ajuste os filtros ou cadastre um novo aluno para comecar.', '<a class="btn btn-sm" href="' . e(lb_url('professor/aluno_form.php')) . '">Cadastrar aluno</a>') ?>
  <?php else: ?>
    <div class="table-wrap">
      <table class="table">
        <thead>
          <tr><th>Aluno</th><th>Turma</th><th>Leituras</th><th>Paginas</th><th>Media</th><th>Cadastro</th><th>Situacao</th><th></th></tr>
        </thead>
        <tbody>
        <?php foreach ($alunos as $a): ?>
          <tr>
            <td><div class="cell-main"><?= lb_avatar($a['nome']) ?><strong><?= e($a['nome']) ?></strong></div></td>
            <td><?= e($a['turma_nome'] ?? '—') ?></td>
            <td><strong><?= (int) $a['qtd_leituras'] ?></strong></td>
            <td><?= number_format((int) $a['paginas'], 0, ',', '.') ?></td>
            <td><?= $a['media'] !== null ? lb_num((float) $a['media']) : '—' ?></td>
            <td class="nowrap"><?= e(lb_date($a['criado_em'])) ?></td>
            <td><?= $a['status'] === 'ativo' ? lb_badge('Ativo', 'success') : lb_badge('Inativo', 'neutral') ?></td>
            <td class="nowrap">
              <a class="btn btn-ghost btn-sm" href="<?= e(lb_url('professor/aluno_historico.php?id=' . (int) $a['id'])) ?>">Historico</a>
              <a class="btn btn-soft btn-sm" href="<?= e(lb_url('professor/aluno_form.php?id=' . (int) $a['id'])) ?>"><?= lb_icon('edit') ?>Editar</a>
              <form method="post" action="<?= e(lb_url('professor/aluno_acao.php')) ?>" data-confirm="Excluir o aluno <?= e($a['nome']) ?>? Esta acao nao pode ser desfeita." style="display:inline">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= (int) $a['id'] ?>">
                <input type="hidden" name="acao" value="excluir">
                <button class="btn btn-danger btn-sm" type="submit"><?= lb_icon('trash') ?>Excluir</button>
              </form>
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
lb_shell_end(['contexto' => 'professor']);
