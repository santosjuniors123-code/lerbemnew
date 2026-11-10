<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

$u = require_admin();
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $id = int_inp('id', 0) ?? 0;
    if (inp('acao') === 'alternar') {
        $st = $pdo->prepare('SELECT usuario_id FROM professores WHERE id = ?');
        $st->execute([$id]);
        $usuarioId = (int) ($st->fetchColumn() ?: 0);
        if ($usuarioId) {
            $pdo->beginTransaction();
            try {
                $pdo->prepare('UPDATE professores SET ativo = 1 - ativo WHERE id = ?')->execute([$id]);
                $pdo->prepare('UPDATE usuarios SET ativo = (SELECT ativo FROM professores WHERE id = ?) WHERE id = ?')->execute([$id, $usuarioId]);
                $pdo->commit();
                flash('success', 'Acesso do professor atualizado.');
            } catch (Throwable $ex) {
                $pdo->rollBack();
                lb_fatal('Nao foi possivel atualizar o professor.', $ex);
            }
        }
    }
    redirect(lb_url('admin/professores.php'));
}

$lista = $pdo->query('SELECT p.*, e.nome AS escola_nome, u.ativo AS usuario_ativo, u.ultimo_acesso
    FROM professores p
    JOIN escolas e ON e.id = p.escola_id
    LEFT JOIN usuarios u ON u.id = p.usuario_id
    ORDER BY e.nome, p.nome')->fetchAll();

lb_shell_start('Professores', [
    'contexto' => 'admin',
    'ativo'    => 'professores',
    'titulo_pagina' => 'Professores',
    'subtitulo' => count($lista) . ' professor(es) cadastrado(s)',
    'acoes' => '<a class="btn btn-sm" href="' . e(lb_url('admin/professor_form.php')) . '">' . lb_icon('plus') . 'Novo professor</a>',
]);
?>
<div class="card">
  <?php if (!$lista): ?>
    <?= lb_empty('users', 'Nenhum professor cadastrado', 'Cadastre um professor para que ele possa acessar o painel da escola.') ?>
  <?php else: ?>
    <div class="table-wrap">
      <table class="table">
        <thead><tr><th>Professor</th><th>Escola</th><th>Disciplina</th><th>Ultimo acesso</th><th>Situacao</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($lista as $p): ?>
          <tr>
            <td><div class="cell-main"><strong><?= e($p['nome']) ?></strong><small><?= e($p['email']) ?></small></div></td>
            <td><?= e($p['escola_nome']) ?></td>
            <td><?= e($p['disciplina'] ?: '—') ?></td>
            <td class="nowrap"><?= $p['ultimo_acesso'] ? e(lb_datetime($p['ultimo_acesso'])) : '—' ?></td>
            <td><?= $p['ativo'] ? lb_badge('Ativo', 'success') : lb_badge('Inativo', 'neutral') ?></td>
            <td class="nowrap">
              <a class="btn btn-soft btn-sm" href="<?= e(lb_url('admin/professor_form.php?id=' . (int) $p['id'])) ?>"><?= lb_icon('edit') ?>Editar</a>
              <form method="post" style="display:inline">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                <input type="hidden" name="acao" value="alternar">
                <button class="btn btn-ghost btn-sm" type="submit"><?= $p['ativo'] ? 'Desativar' : 'Ativar' ?></button>
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
