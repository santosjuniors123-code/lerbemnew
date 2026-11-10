<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/ai.php';

$u = require_professor();
$pdo = db();
$escolaId = (int) $u['escola_id'];
$preview = $_SESSION['_ia_preview'] ?? null;
$erro = null;

$st = $pdo->prepare('SELECT id, titulo, livro_titulo FROM questionarios WHERE escola_id = ? AND status <> "arquivado" ORDER BY criado_em DESC');
$st->execute([$escolaId]);
$questionarios = $st->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $acao = inp('acao');

    if ($acao === 'descartar') {
        unset($_SESSION['_ia_preview']);
        flash('info', 'Sugestoes descartadas.');
        redirect(lb_url('professor/ia.php'));
    }

    if ($acao === 'salvar') {
        $questionarioId = int_inp('questionario', 0) ?? 0;
        $st = $pdo->prepare('SELECT id FROM questionarios WHERE id = ? AND escola_id = ?');
        $st->execute([$questionarioId, $escolaId]);
        if (!$st->fetchColumn()) {
            flash('error', 'Selecione um questionario valido da sua escola.');
            redirect(lb_url('professor/ia.php'));
        }

        $itens = $_POST['perguntas'] ?? [];
        $itens = is_array($itens) ? $itens : [];
        $stOrdem = $pdo->prepare('SELECT COALESCE(MAX(ordem), 0) + 1 FROM perguntas WHERE questionario_id = ?');
        $stOrdem->execute([$questionarioId]);
        $ordem = (int) $stOrdem->fetchColumn();
        $insP = $pdo->prepare('INSERT INTO perguntas (questionario_id, enunciado, tipo, explicacao, ordem, pontos) VALUES (?, ?, ?, ?, ?, ?)');
        $insA = $pdo->prepare('INSERT INTO alternativas (pergunta_id, texto, correta, ordem) VALUES (?, ?, ?, ?)');
        $salvas = 0;

        $pdo->beginTransaction();
        try {
            foreach ($itens as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $enunciado = trim((string) ($item['enunciado'] ?? ''));
                $tipo = (string) ($item['tipo'] ?? 'multipla');
                $explicacao = trim((string) ($item['explicacao'] ?? ''));
                $pontos = (float) str_replace(',', '.', (string) ($item['pontos'] ?? '1'));
                if (mb_strlen($enunciado) < 5 || !in_array($tipo, ['multipla', 'vf', 'aberta'], true)) {
                    continue;
                }
                if ($pontos < 0.25 || $pontos > 10) {
                    $pontos = 1.0;
                }
                $textos = $item['alt'] ?? [];
                $textos = is_array($textos) ? array_values(array_filter(array_map(
                    static fn ($t) => is_string($t) ? trim($t) : '',
                    $textos
                ), static fn ($t) => $t !== '')) : [];
                $correta = isset($item['correta']) ? (int) $item['correta'] : -1;

                if ($tipo === 'multipla' && (count($textos) < 2 || !array_key_exists($correta, $textos))) {
                    continue;
                }

                $insP->execute([$questionarioId, mb_substr($enunciado, 0, 2000), $tipo, $explicacao !== '' ? mb_substr($explicacao, 0, 2000) : null, $ordem, $pontos]);
                $perguntaId = (int) $pdo->lastInsertId();
                $ordem++;

                if ($tipo === 'vf') {
                    $vf = (string) ($item['vf'] ?? 'V');
                    $insA->execute([$perguntaId, 'Verdadeiro', $vf === 'V' ? 1 : 0, 1]);
                    $insA->execute([$perguntaId, 'Falso', $vf === 'F' ? 1 : 0, 2]);
                } elseif ($tipo === 'multipla') {
                    $i = 1;
                    foreach ($textos as $idx => $texto) {
                        $insA->execute([$perguntaId, mb_substr($texto, 0, 255), $idx === $correta ? 1 : 0, $i++]);
                    }
                }
                $salvas++;
            }
            $pdo->commit();
        } catch (Throwable $ex) {
            $pdo->rollBack();
            lb_fatal('Nao foi possivel salvar as perguntas sugeridas.', $ex);
        }

        unset($_SESSION['_ia_preview']);
        flash($salvas > 0 ? 'success' : 'warning', $salvas > 0
            ? $salvas . ' pergunta(s) adicionada(s) ao questionario. Revise antes de publicar.'
            : 'Nenhuma pergunta valida foi enviada.');
        redirect(lb_url('professor/questionario_form.php?id=' . $questionarioId));
    }

    if ($acao === 'gerar') {
        $questionarioId = int_inp('questionario', 0) ?? 0;
        $st = $pdo->prepare('SELECT id FROM questionarios WHERE id = ? AND escola_id = ?');
        $st->execute([$questionarioId, $escolaId]);
        if (!$st->fetchColumn()) {
            flash('error', 'Selecione o questionario que recebera as perguntas.');
            redirect(lb_url('professor/ia.php'));
        }

        $livro = inp('livro');
        $conteudo = inp('conteudo');
        $qtd = int_inp('quantidade', 5) ?? 5;
        $nivel = inp('nivel', 'medio');

        $resultado = lb_ai_gerar($livro, $conteudo, $qtd, $nivel);
        if (!$resultado['ok'] || !$resultado['perguntas']) {
            $_SESSION['_old'] = $_POST;
            flash('error', $resultado['erro'] !== '' ? $resultado['erro'] : 'A IA nao retornou perguntas validas. Tente novamente.');
            redirect(lb_url('professor/ia.php?questionario=' . $questionarioId));
        }

        $_SESSION['_ia_preview'] = [
            'questionario_id' => $questionarioId,
            'livro'           => $livro,
            'perguntas'       => $resultado['perguntas'],
        ];
        flash('success', 'Sugestoes geradas. Revise, ajuste e aprove as perguntas antes de salvar.');
        redirect(lb_url('professor/ia.php?questionario=' . $questionarioId));
    }
}

$questionarioSel = int_get('questionario', 0) ?? (int) ($preview['questionario_id'] ?? 0);
$aiOk = lb_ai_disponivel();

lb_shell_start('Questionarios com IA', [
    'contexto' => 'professor',
    'ativo'    => 'questionarios',
    'titulo_pagina' => 'Gerar perguntas com IA',
    'subtitulo' => 'A IA usa apenas o conteudo que voce fornecer',
    'acoes' => '<a class="btn btn-ghost btn-sm" href="' . e(lb_url('professor/questionarios.php')) . '">' . lb_icon('back') . 'Voltar</a>',
]);
?>
<?php if (!$aiOk): ?>
  <div class="alert alert-info">
    <?= lb_icon('info') ?>
    <span><strong>Modulo de IA nao configurado.</strong> Defina a variavel de ambiente <code>AI_API_KEY</code> (e opcionalmente <code>AI_API_URL</code> e <code>AI_MODEL</code>) para habilitar a geracao automatica. Todo o sistema continua funcionando: crie as perguntas manualmente no questionario.</span>
  </div>
<?php endif; ?>

<div class="grid grid-2">
  <div class="card">
    <div class="card-head">
      <?= lb_icon('sparkles', 'ico ico-lg') ?>
      <div><h2>Conteudo de referencia</h2><p>Cole o trecho do livro. Sem conteudo, a IA nao gera perguntas.</p></div>
    </div>
    <form method="post" id="form-ia" data-loading autocomplete="off">
      <?= csrf_field() ?>
      <input type="hidden" name="acao" value="gerar">
      <div class="form-grid">
        <div class="field full">
          <label for="questionario">Questionario de destino *</label>
          <select class="input" id="questionario" name="questionario" required>
            <option value="">Selecione...</option>
            <?php foreach ($questionarios as $q): ?>
              <option value="<?= (int) $q['id'] ?>" <?= $questionarioSel === (int) $q['id'] ? 'selected' : '' ?>><?= e($q['titulo']) ?></option>
            <?php endforeach; ?>
          </select>
          <span class="hint">Crie um questionario antes, se ainda nao existir. <a href="<?= e(lb_url('professor/questionario_form.php')) ?>">Criar agora</a>.</span>
        </div>
        <div class="field full">
          <label for="livro">Titulo do livro *</label>
          <input class="input" id="livro" name="livro" maxlength="200" required value="<?= lb_old('livro') ?>" placeholder="Ex.: O Pequeno Principe">
        </div>
        <div class="field full">
          <label for="conteudo">Conteudo / trecho do livro *</label>
          <textarea class="input" id="conteudo" name="conteudo" rows="9" required placeholder="Cole aqui o texto, resumo oficial ou trecho fornecido pelo professor."><?= lb_old('conteudo') ?></textarea>
          <span class="hint">Minimo de 120 caracteres. A IA nao deve inventar fatos sobre a obra.</span>
        </div>
        <div class="field">
          <label for="quantidade">Quantidade de perguntas</label>
          <input class="input" id="quantidade" name="quantidade" type="number" min="1" max="20" value="<?= e(lb_old('quantidade', '5')) ?>">
        </div>
        <div class="field">
          <label for="nivel">Dificuldade</label>
          <select class="input" id="nivel" name="nivel">
            <option value="facil" <?= lb_old('nivel', 'medio') === 'facil' ? 'selected' : '' ?>>Facil</option>
            <option value="medio" <?= lb_old('nivel', 'medio') === 'medio' ? 'selected' : '' ?>>Medio</option>
            <option value="dificil" <?= lb_old('nivel', 'medio') === 'dificil' ? 'selected' : '' ?>>Dificil</option>
          </select>
        </div>
      </div>
      <div class="form-actions">
        <button class="btn" type="submit" <?= $aiOk ? '' : 'disabled' ?>><?= lb_icon('sparkles') ?>Gerar sugestoes</button>
      </div>
    </form>
  </div>

  <div class="card">
    <div class="card-head">
      <?= lb_icon('file', 'ico ico-lg') ?>
      <div><h2>Como funciona</h2><p>Aprovacao obrigatoria do professor</p></div>
    </div>
    <ol class="steps">
      <li>Informe o livro e cole o conteudo que serve de base.</li>
      <li>A IA sugere perguntas de interpretacao, compreensao, personagens, acontecimentos e reflexao.</li>
      <li>Voce revisa, edita, remove o que nao servir e aprova.</li>
      <li>As perguntas aprovadas entram no questionario e so aparecem aos alunos depois da publicacao.</li>
    </ol>
    <div class="divider"></div>
    <p class="muted small">Integracao configuravel por ambiente: <code>AI_API_KEY</code>, <code>AI_API_URL</code>, <code>AI_MODEL</code>, <code>AI_TIMEOUT</code>. Nenhuma chave fica gravada no banco.</p>
  </div>
</div>

<?php if ($preview && !empty($preview['perguntas'])): ?>
<div class="card mt-24">
  <div class="card-head">
    <?= lb_icon('check', 'ico ico-lg') ?>
    <div><h2>Revisar sugestoes</h2><p><?= count($preview['perguntas']) ?> pergunta(s) para "<?= e($preview['livro']) ?>"</p></div>
  </div>
  <form method="post" data-loading>
    <?= csrf_field() ?>
    <input type="hidden" name="acao" value="salvar">
    <input type="hidden" name="questionario" value="<?= (int) $preview['questionario_id'] ?>">
    <?php foreach ($preview['perguntas'] as $i => $p): $tipo = (string) ($p['tipo'] ?? 'multipla'); ?>
      <div class="pergunta">
        <div class="pergunta-head">
          <span class="pergunta-num"><?= $i + 1 ?></span>
          <div style="flex:1"><div class="pergunta-tipo"><?= e(['multipla' => 'Multipla escolha', 'vf' => 'Verdadeiro ou falso', 'aberta' => 'Resposta aberta'][$tipo] ?? $tipo) ?></div></div>
        </div>
        <input type="hidden" name="perguntas[<?= $i ?>][tipo]" value="<?= e($tipo) ?>">
        <div class="form-grid">
          <div class="field full">
            <label>Enunciado</label>
            <textarea class="input" name="perguntas[<?= $i ?>][enunciado]" rows="2" required><?= e((string) $p['enunciado']) ?></textarea>
          </div>
          <div class="field">
            <label>Pontuacao</label>
            <input class="input" type="number" step="0.25" min="0.25" max="10" name="perguntas[<?= $i ?>][pontos]" value="1">
          </div>
          <div class="field full">
            <label>Explicacao</label>
            <input class="input" name="perguntas[<?= $i ?>][explicacao]" maxlength="2000" value="<?= e((string) ($p['explicacao'] ?? '')) ?>">
          </div>

          <?php if ($tipo === 'multipla'): ?>
            <?php foreach (($p['alternativas'] ?? []) as $j => $alt): ?>
              <div class="field">
                <label class="alt" style="gap:10px">
                  <input type="radio" name="perguntas[<?= $i ?>][correta]" value="<?= (int) $j ?>" <?= !empty($alt['correta']) ? 'checked' : '' ?>>
                  <span>Correta</span>
                </label>
                <input class="input" name="perguntas[<?= $i ?>][alt][<?= (int) $j ?>]" maxlength="255" value="<?= e((string) $alt['texto']) ?>">
              </div>
            <?php endforeach; ?>
          <?php elseif ($tipo === 'vf'): ?>
            <div class="field full">
              <label>Resposta correta</label>
              <select class="input" name="perguntas[<?= $i ?>][vf]">
                <option value="V" <?= !empty($p['vf']) && $p['vf'] === 'V' ? 'selected' : '' ?>>Verdadeiro</option>
                <option value="F" <?= !empty($p['vf']) && $p['vf'] === 'F' ? 'selected' : '' ?>>Falso</option>
              </select>
            </div>
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>
    <div class="form-actions">
      <button class="btn btn-ghost" type="submit" name="acao" value="descartar" formnovalidate>Descartar</button>
      <button class="btn" type="submit"><?= lb_icon('check') ?>Aprovar e adicionar ao questionario</button>
    </div>
  </form>
</div>
<?php endif; ?>
<?php
lb_shell_end(['contexto' => 'professor']);
