<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

$u = require_professor();
$pdo = db();
$escolaId = (int) $u['escola_id'];
$id = int_get('id', 0);

$st = $pdo->prepare('SELECT l.*, a.nome AS aluno_nome, a.turma_id AS aluno_turma, t.nome AS turma_nome,
        q.titulo AS questionario_titulo, q.nivel
    FROM leituras l
    JOIN alunos a ON a.id = l.aluno_id
    LEFT JOIN turmas t ON t.id = l.turma_id
    LEFT JOIN questionarios q ON q.id = l.questionario_id
    WHERE l.id = ? AND l.escola_id = ?');
$st->execute([$id, $escolaId]);
$leitura = $st->fetch();
if (!$leitura) {
    flash('error', 'Leitura nao encontrada nesta escola.');
    redirect(lb_url('professor/leituras.php'));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $acao = inp('acao');

    if ($acao === 'validar') {
        $pdo->prepare("UPDATE leituras SET status = 'validada' WHERE id = ? AND escola_id = ?")->execute([$id, $escolaId]);
        flash('success', 'Leitura validada.');
        redirect(lb_url('professor/leitura.php?id=' . $id));
    }

    if ($acao === 'corrigir') {
        $notas = $_POST['correcao'] ?? [];
        $notas = is_array($notas) ? $notas : [];

        $st = $pdo->prepare('SELECT r.id, r.correta, r.pontos_obtidos, p.tipo, p.pontos
            FROM respostas r JOIN perguntas p ON p.id = r.pergunta_id
            WHERE r.leitura_id = ? AND r.escola_id = ?');
        $st->execute([$id, $escolaId]);
        $respostas = $st->fetchAll();

        $totalPontos = 0.0;
        $obtidos = 0.0;
        $acertosObj = 0;
        $totalObj = 0;
        $upd = $pdo->prepare('UPDATE respostas SET correta = ?, pontos_obtidos = ?, feedback = ?, corrigida_em = NOW(), corrigida_por = ? WHERE id = ? AND leitura_id = ?');

        foreach ($respostas as $r) {
            $rid = (int) $r['id'];
            $pontos = (float) $r['pontos'];
            $totalPontos += $pontos;

            if ($r['tipo'] === 'aberta') {
                $info = is_array($notas[$rid] ?? null) ? $notas[$rid] : [];
                $correta = (int) ($info['correta'] ?? 0) === 1 ? 1 : 0;
                $feedback = isset($info['feedback']) && is_string($info['feedback']) ? trim($info['feedback']) : null;
                $pts = $correta === 1 ? $pontos : 0.0;
                $obtidos += $pts;
                $upd->execute([$correta, $pts, $feedback !== '' ? $feedback : null, (int) $u['id'], $rid, $id]);
                continue;
            }

            $totalObj++;
            if ((int) $r['correta'] === 1) {
                $acertosObj++;
                $obtidos += $pontos;
            }
        }

        $nota = $totalPontos > 0 ? round($obtidos / $totalPontos * 10, 2) : null;
        $pdo->prepare("UPDATE leituras SET status = 'validada', nota = ?, acertos = ?, total_objetivas = ? WHERE id = ? AND escola_id = ?")
            ->execute([$nota, $acertosObj, $totalObj, $id, $escolaId]);
        flash('success', 'Correcao salva e nota recalculada.');
        redirect(lb_url('professor/leitura.php?id=' . $id));
    }
}

$st = $pdo->prepare('SELECT r.*, p.enunciado, p.tipo, p.explicacao, p.ordem, p.pontos,
        a.texto AS alternativa_texto,
        (SELECT GROUP_CONCAT(alt.texto SEPARATOR " | ") FROM alternativas alt WHERE alt.pergunta_id = p.id AND alt.correta = 1) AS corretas
    FROM respostas r
    JOIN perguntas p ON p.id = r.pergunta_id
    LEFT JOIN alternativas a ON a.id = r.alternativa_id
    WHERE r.leitura_id = ? AND r.escola_id = ? ORDER BY p.ordem, p.id');
$st->execute([$id, $escolaId]);
$respostas = $st->fetchAll();

lb_shell_start('Leitura do aluno', [
    'contexto' => 'professor',
    'ativo'    => 'leituras',
    'titulo_pagina' => 'Leitura registrada',
    'subtitulo' => $leitura['aluno_nome'] . ' · ' . e($leitura['titulo']),
    'acoes' => '<a class="btn btn-ghost btn-sm" href="' . e(lb_url('professor/leituras.php')) . '">' . lb_icon('back') . 'Voltar</a>',
]);
?>
<div class="grid grid-2">
  <div class="card">
    <div class="card-head">
      <?= lb_icon('book', 'ico ico-lg') ?>
      <div><h2><?= e($leitura['titulo']) ?></h2><p><?= e($leitura['autor'] ?: 'Autor nao informado') ?></p></div>
    </div>
    <div class="detail-grid">
      <div class="detail"><small>Aluno</small><strong><?= e($leitura['aluno_nome']) ?></strong></div>
      <div class="detail"><small>Turma</small><strong><?= e($leitura['turma_nome'] ?? '—') ?></strong></div>
      <div class="detail"><small>Data da leitura</small><strong><?= e(lb_date($leitura['data_leitura'])) ?></strong></div>
      <div class="detail"><small>Paginas</small><strong><?= (int) $leitura['paginas'] ?></strong></div>
      <div class="detail"><small>Registrado em</small><strong><?= e(lb_datetime($leitura['criado_em'])) ?></strong></div>
      <div class="detail"><small>Nota</small><strong><?= $leitura['nota'] !== null ? lb_num((float) $leitura['nota']) . ' / 10' : '—' ?></strong></div>
    </div>
    <div class="divider"></div>
    <h3 class="mb-0">Resumo escrito pelo aluno</h3>
    <p class="muted" style="white-space:pre-line"><?= e($leitura['resumo']) ?></p>
    <div class="divider"></div>
    <div class="flex-between">
      <div>
        <small class="muted">Situacao</small><br>
        <?php if ($leitura['status'] === 'validada'): ?><?= lb_badge('Validada', 'success') ?>
        <?php elseif ($leitura['status'] === 'pendente_correcao'): ?><?= lb_badge('Aguardando correcao', 'warning') ?>
        <?php else: ?><?= lb_badge('Registrada', 'info') ?><?php endif; ?>
      </div>
      <?php if ($leitura['status'] !== 'validada'): ?>
        <form method="post" data-loading>
          <?= csrf_field() ?>
          <input type="hidden" name="acao" value="validar">
          <button class="btn btn-soft btn-sm" type="submit"><?= lb_icon('check') ?>Validar leitura</button>
        </form>
      <?php endif; ?>
    </div>
  </div>

  <div class="card">
    <div class="card-head">
      <?= lb_icon('quiz', 'ico ico-lg') ?>
      <div><h2>Respostas do questionario</h2><p><?= $leitura['questionario_titulo'] ? e($leitura['questionario_titulo']) : 'Nenhum questionario vinculado' ?></p></div>
    </div>

    <?php if (!$respostas): ?>
      <?= lb_empty('quiz', 'Sem respostas', 'Esta leitura nao possui questionario respondido.') ?>
    <?php else: ?>
      <form method="post" data-loading>
        <?= csrf_field() ?>
        <input type="hidden" name="acao" value="corrigir">
        <?php foreach ($respostas as $r): $rid = (int) $r['id']; ?>
          <div class="revisao-item <?= $r['correta'] === null ? 'pend' : ((int) $r['correta'] === 1 ? 'ok' : 'err') ?>">
            <strong><?= (int) $r['ordem'] ?>. <?= e($r['enunciado']) ?></strong>
            <p class="muted mt-16" style="margin-bottom:6px">
              <small>Resposta do aluno:</small><br>
              <?= e($r['resposta_texto'] !== null && $r['resposta_texto'] !== '' ? $r['resposta_texto'] : ($r['alternativa_texto'] ?? '—')) ?>
            </p>
            <?php if ($r['tipo'] !== 'aberta'): ?>
              <p class="muted" style="margin:0 0 6px"><small>Correta: <strong><?= e($r['corretas'] ?? '—') ?></strong></small></p>
            <?php endif; ?>
            <?php if ($r['tipo'] === 'aberta'): ?>
              <div class="form-grid mt-16">
                <div class="field">
                  <label for="c<?= $rid ?>">Correcao</label>
                  <select class="input" id="c<?= $rid ?>" name="correcao[<?= $rid ?>][correta]">
                    <option value="0" <?= (int) $r['correta'] === 0 ? 'selected' : '' ?>>Resposta incorreta</option>
                    <option value="1" <?= (int) $r['correta'] === 1 ? 'selected' : '' ?>>Resposta correta</option>
                  </select>
                </div>
                <div class="field full">
                  <label for="f<?= $rid ?>">Comentario para o aluno</label>
                  <textarea class="input" id="f<?= $rid ?>" name="correcao[<?= $rid ?>][feedback]" rows="2" maxlength="2000"><?= e($r['feedback'] ?? '') ?></textarea>
                </div>
              </div>
            <?php endif; ?>
            <?php if ($r['explicacao']): ?>
              <div class="explicacao"><strong>Explicacao:</strong> <?= e($r['explicacao']) ?></div>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
        <div class="form-actions">
          <button class="btn" type="submit"><?= lb_icon('save') ?>Salvar correcao e recalcular nota</button>
        </div>
      </form>
    <?php endif; ?>
  </div>
</div>
<?php
lb_shell_end(['contexto' => 'professor']);
