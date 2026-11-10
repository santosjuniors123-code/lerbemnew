<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

$u = require_admin();
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $id = int_inp('id', 0) ?? 0;
    if (inp('acao') === 'alternar') {
        $st = $pdo->prepare('SELECT ativo FROM escolas WHERE id = ?');
        $st->execute([$id]);
        $atual = $st->fetchColumn();
        if ($atual !== false) {
            $pdo->prepare('UPDATE escolas SET ativo = ? WHERE id = ?')->execute([(int) $atual === 1 ? 0 : 1, $id]);
            flash('success', 'Situacao da escola atualizada.');
        }
    }
    redirect(lb_url('admin/escolas.php'));
}

$lista = $pdo->query('SELECT e.*,
        (SELECT COUNT(*) FROM alunos a WHERE a.escola_id = e.id) AS alunos,
        (SELECT COUNT(*) FROM professores p WHERE p.escola_id = e.id) AS professores,
        (SELECT COUNT(*) FROM leituras l WHERE l.escola_id = e.id) AS leituras
    FROM escolas e ORDER BY e.nome')->fetchAll();

lb_shell_start('Escolas', [
    'contexto' => 'admin',
    'ativo'    => 'escolas',
    'titulo_pagina' => 'Escolas',
    'subtitulo' => count($lista) . ' escola(s) na rede',
    'acoes' => '<a class="btn btn-sm" href="' . e(lb_url('admin/escola_form.php')) . '">' . lb_icon('plus') . 'Nova escola</a>',
]);
?>
<div class="card">
  <?php if (!$lista): ?>
    <?= lb_empty('school', 'Nenhuma escola cadastrada', 'Crie a primeira escola para liberar o acesso dos professores.', '<a class="btn btn-sm" href="' . e(lb_url('admin/escola_form.php')) . '">Cadastrar escola</a>') ?>
  <?php else: ?>
    <div class="table-wrap">
      <table class="table">
        <thead><tr><th>Escola</th><th>Cidade</th><th>Contato</th><th>Professores</th><th>Alunos</th><th>Leituras</th><th>Situacao</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($lista as $es): ?>
          <tr>
            <td><div class="cell-main"><strong><?= e($es['nome']) ?></strong><small><?= e(lb_slug($es['nome'])) ?></small></div></td>
            <td><?= e(($es['cidade'] ?: '—') . ($es['uf'] ? ' / ' . $es['uf'] : '')) ?></td>
            <td><?= e($es['email'] ?: '—') ?><br><small class="muted"><?= e($es['telefone'] ?: '') ?></small></td>
            <td><?= (int) $es['professores'] ?></td>
            <td><?= (int) $es['alunos'] ?></td>
            <td><?= (int) $es['leituras'] ?></td>
            <td><?= $es['ativo'] ? lb_badge('Ativa', 'success') : lb_badge('Inativa', 'neutral') ?></td>
            <td class="nowrap">
              <a class="btn btn-soft btn-sm" href="<?= e(lb_url('admin/escola_form.php?id=' . (int) $es['id'])) ?>"><?= lb_icon('edit') ?>Editar</a>
              <form method="post" style="display:inline">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= (int) $es['id'] ?>">
                <input type="hidden" name="acao" value="alternar">
                <button class="btn btn-ghost btn-sm" type="submit"><?= $es['ativo'] ? 'Desativar' : 'Ativar' ?></button>
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
lb_shell_end(['contexto' => 'admin']);
