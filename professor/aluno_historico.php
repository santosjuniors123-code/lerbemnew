<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

$u = require_professor();
$pdo = db();
$escolaId = (int) $u['escola_id'];
$id = int_get('id', 0);

$st = $pdo->prepare('SELECT a.*, t.nome AS turma_nome FROM alunos a LEFT JOIN turmas t ON t.id = a.turma_id
    WHERE a.id = ? AND a.escola_id = ?');
$st->execute([$id, $escolaId]);
$aluno = $st->fetch();
if (!$aluno) {
    flash('error', 'Aluno nao encontrado nesta escola.');
    redirect(lb_url('professor/alunos.php'));
}

$st = $pdo->prepare('SELECT COUNT(*) AS leituras, COALESCE(SUM(paginas),0) AS paginas, AVG(nota) AS media,
        SUM(CASE WHEN questionario_id IS NOT NULL THEN 1 ELSE 0 END) AS com_questionario,
        SUM(CASE WHEN status = "pendente_correcao" THEN 1 ELSE 0 END) AS pendentes
    FROM leituras WHERE aluno_id = ? AND escola_id = ?');
$st->execute([$id, $escolaId]);
$resumo = $st->fetch() ?: [];

$st = $pdo->prepare('SELECT l.*, q.titulo AS questionario_titulo FROM leituras l
    LEFT JOIN questionarios q ON q.id = l.questionario_id
    WHERE l.aluno_id = ? AND l.escola_id = ?
    ORDER BY l.data_leitura DESC, l.id DESC LIMIT 30');
$st->execute([$id, $escolaId]);
$leituras = $st->fetchAll();

$st = $pdo->prepare('SELECT q.titulo, COUNT(r.id) AS respostas,
        SUM(r.correta = 1) AS acertos, SUM(r.correta = 0) AS erros, SUM(r.correta IS NULL) AS pendentes
    FROM respostas r JOIN questionarios q ON q.id = r.questionario_id
    WHERE r.aluno_id = ? AND r.escola_id = ?
    GROUP BY q.id, q.titulo ORDER BY respostas DESC');
$st->execute([$id, $escolaId]);
$porQuestionario = $st->fetchAll();

lb_shell_start('Historico do aluno', [
    'contexto' => 'professor',
    'ativo'    => 'alunos',
    'titulo_pagina' => 'Historico individual',
    'subtitulo' => $aluno['nome'] . ' · ' . ($aluno['turma_nome'] ?? 'Sem turma'),
    'acoes' => '<a class="btn btn-ghost btn-sm" href="' . e(lb_url('professor/alunos.php')) . '">' . lb_icon('back') . 'Voltar</a>'
        . '<a class="btn btn-soft btn-sm" href="' . e(lb_url('professor/aluno_form.php?id=' . $id)) . '">' . lb_icon('edit') . 'Editar</a>',
]);
?>
<div class="card">
  <div class="card-head">
    <?= lb_avatar($aluno['nome']) ?>
    <div>
      <h2><?= e($aluno['nome']) ?></h2>
      <p><?= e($aluno['turma_nome'] ?? 'Sem turma') ?> · cadastrado em <?= e(lb_date($aluno['criado_em'])) ?> ·
        <?= $aluno['status'] === 'ativo' ? lb_badge('Ativo', 'success') : lb_badge('Inativo', 'neutral') ?></p>
    </div>
  </div>
  <div class="grid grid-4">
    <div class="stat"><div class="stat-top"><span class="stat-label">Leituras</span></div><strong class="stat-value"><?= (int) ($resumo['leituras'] ?? 0) ?></strong></div>
    <div class="stat"><div class="stat-top"><span class="stat-label">Paginas</span></div><strong class="stat-value"><?= number_format((int) ($resumo['paginas'] ?? 0), 0, ',', '.') ?></strong></div>
    <div class="stat"><div class="stat-top"><span class="stat-label">Media</span></div><strong class="stat-value"><?= isset($resumo['media']) && $resumo['media'] !== null ? lb_num((float) $resumo['media']) : '—' ?></strong></div>
    <div class="stat"><div class="stat-top"><span class="stat-label">Correcoes pendentes</span></div><strong class="stat-value"><?= (int) ($resumo['pendentes'] ?? 0) ?></strong></div>
  </div>
</div>

<div class="grid grid-2 mt-24">
  <div class="card">
    <div class="card-head"><?= lb_icon('read') ?><div><h2>Leituras registradas</h2><p>Ultimos 30 registros</p></div></div>
    <?php if (!$leituras): ?>
      <?= lb_empty('read', 'Nenhuma leitura', 'Este aluno ainda nao registrou leituras.') ?>
    <?php else: ?>
      <div class="table-wrap">
        <table class="table">
          <thead><tr><th>Livro</th><th>Data</th><th>Paginas</th><th>Nota</th><th></th></tr></thead>
          <tbody>
          <?php foreach ($leituras as $l): ?>
            <tr>
              <td><div class="cell-main"><strong><?= e($l['titulo']) ?></strong><small><?= e($l['autor'] ?: 'Autor nao informado') ?></small></div></td>
              <td class="nowrap"><?= e(lb_date($l['data_leitura'])) ?></td>
              <td><?= (int) $l['paginas'] ?></td>
              <td><?= $l['nota'] !== null ? lb_num((float) $l['nota']) : '—' ?></td>
              <td><a class="btn btn-ghost btn-sm" href="<?= e(lb_url('professor/leitura.php?id=' . (int) $l['id'])) ?>">Abrir</a></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>

  <div class="card">
    <div class="card-head"><?= lb_icon('quiz') ?><div><h2>Desempenho em questionarios</h2><p>Acertos, erros e pendencias</p></div></div>
    <?php if (!$porQuestionario): ?>
      <?= lb_empty('quiz', 'Nenhuma resposta', 'O aluno ainda nao respondeu questionarios.') ?>
    <?php else: ?>
      <div class="table-wrap">
        <table class="table">
          <thead><tr><th>Questionario</th><th>Respostas</th><th>Acertos</th><th>Erros</th><th>Pendentes</th></tr></thead>
          <tbody>
          <?php foreach ($porQuestionario as $q): ?>
            <tr>
              <td><?= e(lb_resumir($q['titulo'], 34)) ?></td>
              <td><?= (int) $q['respostas'] ?></td>
              <td><?= (int) $q['acertos'] ?></td>
              <td><?= (int) $q['erros'] ?></td>
              <td><?= (int) $q['pendentes'] ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <div class="btn-row mt-16">
        <a class="btn btn-soft btn-sm" href="<?= e(lb_url('professor/relatorios.php?aluno=' . $id)) ?>"><?= lb_icon('report') ?>Relatorio completo</a>
      </div>
    <?php endif; ?>
  </div>
</div>
<?php
lb_shell_end(['contexto' => 'professor']);
