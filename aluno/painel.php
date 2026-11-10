<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

$aluno = require_aluno();
$pdo = db();
$escolaId = (int) $aluno['escola_id'];

$st = $pdo->prepare('SELECT COUNT(*) AS leituras, COALESCE(SUM(paginas),0) AS paginas,
    SUM(CASE WHEN questionario_id IS NOT NULL THEN 1 ELSE 0 END) AS com_questionario,
    AVG(nota) AS media
    FROM leituras WHERE aluno_id = ? AND escola_id = ?');
$st->execute([$aluno['id'], $escolaId]);
$resumo = $st->fetch() ?: ['leituras' => 0, 'paginas' => 0, 'com_questionario' => 0, 'media' => null];

$st = $pdo->prepare('SELECT l.*, q.titulo AS questionario_titulo FROM leituras l
    LEFT JOIN questionarios q ON q.id = l.questionario_id
    WHERE l.aluno_id = ? AND l.escola_id = ? ORDER BY l.data_leitura DESC, l.id DESC LIMIT 4');
$st->execute([$aluno['id'], $escolaId]);
$recentes = $st->fetchAll();

$st = $pdo->prepare("SELECT COUNT(*) FROM questionarios WHERE escola_id = ? AND status = 'publicado'");
$st->execute([$escolaId]);
$disponiveis = (int) $st->fetchColumn();

$meta = max(1, (int) setting($escolaId, 'meta_leitura_mensal', '4'));
$st = $pdo->prepare('SELECT COUNT(*) FROM leituras WHERE aluno_id = ? AND escola_id = ? AND data_leitura >= DATE_FORMAT(CURDATE(), "%Y-%m-01")');
$st->execute([$aluno['id'], $escolaId]);
$noMes = (int) $st->fetchColumn();
$progresso = min(100, (int) round($noMes / $meta * 100));

$statusBadge = static function (string $status, ?float $nota): string {
    return match ($status) {
        'validada'           => lb_badge('Validada', 'success'),
        'pendente_correcao'  => lb_badge('Em correção', 'warning'),
        default              => lb_badge('Registrada', 'info'),
    };
};

lb_shell_start('Meu painel', ['contexto' => 'aluno', 'ativo' => 'painel']);
?>
<div class="welcome">
  <div>
    <h2>Ola, <?= e(explode(' ', $aluno['nome'])[0]) ?>! <?= lb_icon('star') ?></h2>
    <p><?= e(setting($escolaId, 'mensagem_aluno', 'Cada livro lido e uma nova descoberta. Registre sua leitura e compartilhe o que aprendeu.')) ?></p>
  </div>
  <a class="btn" href="<?= e(lb_url('aluno/leitura_nova.php')) ?>"><?= lb_icon('plus') ?>Registrar nova leitura</a>
</div>

<div class="grid grid-3">
  <article class="stat">
    <div class="stat-top"><span class="stat-ico"><?= lb_icon('read') ?></span><span class="stat-label">Leituras registradas</span></div>
    <strong class="stat-value"><?= (int) $resumo['leituras'] ?></strong>
    <span class="stat-hint">Total de livros registrados por voce</span>
  </article>
  <article class="stat">
    <div class="stat-top"><span class="stat-ico"><?= lb_icon('file') ?></span><span class="stat-label">Paginas lidas</span></div>
    <strong class="stat-value"><?= number_format((int) $resumo['paginas'], 0, ',', '.') ?></strong>
    <span class="stat-hint">Soma das paginas informadas</span>
  </article>
  <article class="stat">
    <div class="stat-top"><span class="stat-ico"><?= lb_icon('quiz') ?></span><span class="stat-label">Questionarios</span></div>
    <strong class="stat-value"><?= (int) $resumo['com_questionario'] ?></strong>
    <span class="stat-hint"><?= $resumo['media'] !== null ? 'Media de ' . lb_num((float) $resumo['media']) . ' pontos' : 'Responda para receber sua nota' ?></span>
  </article>
</div>

<div class="grid grid-2 mt-24">
  <div class="card">
    <div class="card-head"><?= lb_icon('clock') ?><div><h2>Minha meta do mes</h2><p>Leituras registradas no mes atual</p></div></div>
    <div class="progress"><span style="width: <?= $progresso ?>%"></span></div>
    <p class="muted mt-16"><?= $noMes ?> de <?= $meta ?> leituras neste mes. <?= $noMes >= $meta ? 'Meta alcancada, parabens!' : 'Faltam ' . ($meta - $noMes) . ' para bater sua meta.' ?></p>
    <div class="btn-row mt-16">
      <a class="btn btn-soft btn-sm" href="<?= e(lb_url('aluno/leitura_nova.php')) ?>"><?= lb_icon('plus') ?>Registrar leitura</a>
      <a class="btn btn-ghost btn-sm" href="<?= e(lb_url('aluno/historico.php')) ?>"><?= lb_icon('read') ?>Ver historico</a>
    </div>
  </div>

  <div class="card">
    <div class="card-head"><?= lb_icon('quiz') ?><div><h2>Questionarios disponiveis</h2><p>Escolha um livro e responda</p></div></div>
    <?php if ($disponiveis === 0): ?>
      <?= lb_empty('quiz', 'Nenhum questionario publicado', 'Seu professor ainda nao publicou questionarios. Registre sua leitura e volte depois.') ?>
    <?php else: ?>
      <p class="muted"><?= $disponiveis ?> questionario<?= $disponiveis === 1 ? '' : 's' ?> publicado<?= $disponiveis === 1 ? '' : 's' ?> pela sua escola. Ao registrar uma leitura voce pode escolher o questionario correspondente e responder na hora.</p>
      <a class="btn btn-soft btn-sm mt-16" href="<?= e(lb_url('aluno/leitura_nova.php')) ?>"><?= lb_icon('arrow') ?>Registrar leitura com questionario</a>
    <?php endif; ?>
  </div>
</div>

<div class="card mt-24">
  <div class="card-head"><?= lb_icon('read') ?><div><h2>Minhas leituras recentes</h2><p>Ultimos registros enviados</p></div></div>
  <?php if (!$recentes): ?>
    <?= lb_empty('read', 'Sua estante esta vazia', 'Comece agora: registre o livro que voce esta lendo e escreva um resumo com suas palavras.', '<a class="btn btn-sm" href="' . e(lb_url('aluno/leitura_nova.php')) . '">Registrar primeira leitura</a>') ?>
  <?php else: ?>
    <div class="table-wrap">
      <table class="table">
        <thead><tr><th>Livro</th><th>Data</th><th>Paginas</th><th>Questionario</th><th>Situacao</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($recentes as $l): ?>
          <tr>
            <td><div class="cell-main"><strong><?= e($l['titulo']) ?></strong><small><?= e($l['autor'] ?: 'Autor nao informado') ?></small></div></td>
            <td class="nowrap"><?= e(lb_date($l['data_leitura'])) ?></td>
            <td><?= (int) $l['paginas'] ?></td>
            <td><?= $l['questionario_titulo'] ? e(lb_resumir($l['questionario_titulo'], 28)) : '—' ?></td>
            <td><?= $statusBadge((string) $l['status'], $l['nota'] !== null ? (float) $l['nota'] : null) ?></td>
            <td><a class="btn btn-ghost btn-sm" href="<?= e(lb_url('aluno/leitura.php?id=' . (int) $l['id'])) ?>">Ver</a></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>
<?php
lb_shell_end(['contexto' => 'aluno']);
