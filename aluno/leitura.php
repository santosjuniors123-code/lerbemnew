<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

$aluno = require_aluno();
$pdo = db();
$escolaId = (int) $aluno['escola_id'];
$id = int_get('id', 0);

$st = $pdo->prepare('SELECT l.*, q.titulo AS questionario_titulo, q.nivel, t.nome AS turma_nome
    FROM leituras l
    LEFT JOIN questionarios q ON q.id = l.questionario_id
    LEFT JOIN turmas t ON t.id = l.turma_id
    WHERE l.id = ? AND l.aluno_id = ? AND l.escola_id = ?');
$st->execute([$id, $aluno['id'], $escolaId]);
$leitura = $st->fetch();

if (!$leitura) {
    flash('error', 'Leitura nao encontrada.');
    redirect(lb_url('aluno/historico.php'));
}

$st = $pdo->prepare('SELECT r.*, p.enunciado, p.tipo, p.explicacao, p.ordem, a.texto AS alternativa_texto
    FROM respostas r
    JOIN perguntas p ON p.id = r.pergunta_id
    LEFT JOIN alternativas a ON a.id = r.alternativa_id
    WHERE r.leitura_id = ? AND r.aluno_id = ? ORDER BY p.ordem, p.id');
$st->execute([$id, $aluno['id']]);
$respostas = $st->fetchAll();

lb_shell_start('Detalhe da leitura', ['contexto' => 'aluno', 'ativo' => 'historico']);
?>
<div class="btn-row mb-16">
  <a class="btn btn-ghost btn-sm" href="<?= e(lb_url('aluno/historico.php')) ?>"><?= lb_icon('back') ?>Voltar ao historico</a>
</div>

<div class="grid grid-2">
  <div class="card">
    <div class="card-head">
      <?= lb_icon('book', 'ico ico-lg') ?>
      <div><h2><?= e($leitura['titulo']) ?></h2><p><?= e($leitura['autor'] ?: 'Autor nao informado') ?></p></div>
    </div>
    <div class="detail-grid">
      <div class="detail"><small>Data da leitura</small><strong><?= e(lb_date($leitura['data_leitura'])) ?></strong></div>
      <div class="detail"><small>Paginas</small><strong><?= (int) $leitura['paginas'] ?></strong></div>
      <div class="detail"><small>Turma</small><strong><?= e($leitura['turma_nome'] ?? '—') ?></strong></div>
      <div class="detail"><small>Registrado em</small><strong><?= e(lb_datetime($leitura['criado_em'])) ?></strong></div>
      <div class="detail"><small>Situacao</small><strong>
        <?php if ($leitura['status'] === 'validada'): ?><?= lb_badge('Validada pelo professor', 'success') ?>
        <?php elseif ($leitura['status'] === 'pendente_correcao'): ?><?= lb_badge('Aguardando correcao', 'warning') ?>
        <?php else: ?><?= lb_badge('Registrada', 'info') ?><?php endif; ?>
      </strong></div>
      <div class="detail"><small>Nota</small><strong><?= $leitura['nota'] !== null ? lb_num((float) $leitura['nota']) . ' / 10' : '—' ?></strong></div>
    </div>
    <div class="divider"></div>
    <h3 class="mb-0">Meu resumo</h3>
    <p class="muted" style="white-space:pre-line"><?= e($leitura['resumo']) ?></p>
  </div>

  <div class="card">
    <div class="card-head">
      <?= lb_icon('quiz', 'ico ico-lg') ?>
      <div><h2>Questionario</h2><p><?= $leitura['questionario_titulo'] ? e($leitura['questionario_titulo']) : 'Nenhum questionario vinculado' ?></p></div>
    </div>

    <?php if (!$leitura['questionario_id']): ?>
      <?= lb_empty('quiz', 'Sem questionario', 'Esta leitura foi registrada apenas com o resumo.') ?>
    <?php elseif (!$respostas): ?>
      <?= lb_empty('quiz', 'Questionario nao respondido', 'Voce ainda pode responder e receber a correcao automatica.', '<a class="btn btn-sm" href="' . e(lb_url('aluno/responder.php?leitura=' . (int) $leitura['id'])) . '">Responder agora</a>') ?>
    <?php else: ?>
      <div class="detail-grid">
        <div class="detail"><small>Acertos objetivos</small><strong><?= (int) $leitura['acertos'] ?> de <?= (int) $leitura['total_objetivas'] ?></strong></div>
        <div class="detail"><small>Aproveitamento</small><strong><?= $leitura['nota'] !== null ? lb_num((float) $leitura['nota'] * 10) . '%' : '—' ?></strong></div>
      </div>
      <div class="divider"></div>
      <?php foreach ($respostas as $r): ?>
        <?php
        $classe = $r['correta'] === null ? 'pend' : ((int) $r['correta'] === 1 ? 'ok' : 'err');
        $icone = $r['correta'] === null ? 'clock' : ((int) $r['correta'] === 1 ? 'check' : 'x');
        ?>
        <div class="revisao-item <?= $classe ?>">
          <div class="flex-between">
            <strong><?= (int) $r['ordem'] ?>. <?= e($r['enunciado']) ?></strong>
            <?= lb_icon($icone) ?>
          </div>
          <p class="muted mt-16" style="margin-bottom:4px">
            <small>Sua resposta:</small><br>
            <?= e($r['resposta_texto'] !== null && $r['resposta_texto'] !== '' ? $r['resposta_texto'] : ($r['alternativa_texto'] ?? '—')) ?>
          </p>
          <?php if ($r['correta'] === null): ?>
            <p class="muted"><small>Aguardando correcao do professor.</small></p>
          <?php endif; ?>
          <?php if ($r['explicacao']): ?>
            <div class="explicacao"><strong>Explicacao:</strong> <?= e($r['explicacao']) ?></div>
          <?php endif; ?>
          <?php if ($r['feedback']): ?>
            <div class="explicacao"><strong>Comentario do professor:</strong> <?= e($r['feedback']) ?></div>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>
<?php
lb_shell_end(['contexto' => 'aluno']);
