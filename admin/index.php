<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

$u = require_admin();
$pdo = db();

$escolas = (int) $pdo->query('SELECT COUNT(*) FROM escolas')->fetchColumn();
$professores = (int) $pdo->query('SELECT COUNT(*) FROM professores WHERE ativo = 1')->fetchColumn();
$alunos = (int) $pdo->query("SELECT COUNT(*) FROM alunos WHERE status = 'ativo'")->fetchColumn();
$leituras = (int) $pdo->query('SELECT COUNT(*) FROM leituras')->fetchColumn();

$lista = $pdo->query('SELECT e.*,
        (SELECT COUNT(*) FROM alunos a WHERE a.escola_id = e.id) AS alunos,
        (SELECT COUNT(*) FROM professores p WHERE p.escola_id = e.id) AS professores,
        (SELECT COUNT(*) FROM leituras l WHERE l.escola_id = e.id) AS leituras
    FROM escolas e ORDER BY e.nome')->fetchAll();

$recentes = $pdo->query('SELECT l.titulo, l.data_leitura, a.nome AS aluno_nome, e.nome AS escola_nome
    FROM leituras l JOIN alunos a ON a.id = l.aluno_id JOIN escolas e ON e.id = l.escola_id
    ORDER BY l.id DESC LIMIT 8')->fetchAll();

lb_shell_start('Visao geral', [
    'contexto' => 'admin',
    'ativo'    => 'dashboard',
    'titulo_pagina' => 'Visao geral',
    'subtitulo' => 'Todas as escolas da rede',
    'acoes' => '<a class="btn btn-sm" href="' . e(lb_url('admin/escola_form.php')) . '">' . lb_icon('plus') . 'Nova escola</a>',
]);
?>
<div class="grid grid-4">
  <div class="stat"><div class="stat-icon"><?= lb_icon('school') ?></div><div><small>Escolas</small><strong><?= $escolas ?></strong></div></div>
  <div class="stat"><div class="stat-icon"><?= lb_icon('users') ?></div><div><small>Professores ativos</small><strong><?= $professores ?></strong></div></div>
  <div class="stat"><div class="stat-icon"><?= lb_icon('class') ?></div><div><small>Alunos ativos</small><strong><?= $alunos ?></strong></div></div>
  <div class="stat"><div class="stat-icon"><?= lb_icon('read') ?></div><div><small>Leituras registradas</small><strong><?= $leituras ?></strong></div></div>
</div>

<div class="card mt-24">
  <div class="card-head">
    <?= lb_icon('school', 'ico ico-lg') ?>
    <div><h2>Escolas</h2><p>Dados isolados por escola</p></div>
  </div>
  <?php if (!$lista): ?>
    <?= lb_empty('school', 'Nenhuma escola cadastrada', 'Cadastre a primeira escola para comecar a usar o LER BEM.', '<a class="btn btn-sm" href="' . e(lb_url('admin/escola_form.php')) . '">Cadastrar escola</a>') ?>
  <?php else: ?>
    <div class="table-wrap">
      <table class="table">
        <thead><tr><th>Escola</th><th>Cidade</th><th>Professores</th><th>Alunos</th><th>Leituras</th><th>Situacao</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($lista as $es): ?>
          <tr>
            <td><div class="cell-main"><strong><?= e($es['nome']) ?></strong><small><?= e($es['email'] ?: 'Sem e-mail') ?></small></div></td>
            <td><?= e(($es['cidade'] ?: '—') . ($es['uf'] ? ' / ' . $es['uf'] : '')) ?></td>
            <td><?= (int) $es['professores'] ?></td>
            <td><?= (int) $es['alunos'] ?></td>
            <td><?= (int) $es['leituras'] ?></td>
            <td><?= $es['ativo'] ? lb_badge('Ativa', 'success') : lb_badge('Inativa', 'neutral') ?></td>
            <td><a class="btn btn-soft btn-sm" href="<?= e(lb_url('admin/escola_form.php?id=' . (int) $es['id'])) ?>"><?= lb_icon('edit') ?>Editar</a></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<div class="card mt-24">
  <div class="card-head">
    <?= lb_icon('clock', 'ico ico-lg') ?>
    <div><h2>Leituras recentes na rede</h2><p>Ultimos registros de todas as escolas</p></div>
  </div>
  <?php if (!$recentes): ?>
    <?= lb_empty('read', 'Nenhuma leitura registrada', 'Os registros aparecem aqui quando os alunos comecarem a usar o sistema.') ?>
  <?php else: ?>
    <div class="table-wrap">
      <table class="table">
        <thead><tr><th>Aluno</th><th>Escola</th><th>Livro</th><th>Data</th></tr></thead>
        <tbody>
        <?php foreach ($recentes as $r): ?>
          <tr><td><?= e($r['aluno_nome']) ?></td><td><?= e($r['escola_nome']) ?></td><td><?= e(lb_resumir($r['titulo'], 40)) ?></td><td class="nowrap"><?= e(lb_date($r['data_leitura'])) ?></td></tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>
<?php
lb_shell_end(['contexto' => 'admin']);
