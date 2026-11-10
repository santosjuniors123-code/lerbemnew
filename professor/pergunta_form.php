<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

$u = require_professor();
$pdo = db();
$escolaId = (int) $u['escola_id'];

$questionarioId = int_get('questionario', 0) ?? int_inp('questionario', 0) ?? 0;
$st = $pdo->prepare('SELECT * FROM questionarios WHERE id = ? AND escola_id = ?');
$st->execute([$questionarioId, $escolaId]);
$questionario = $st->fetch();
if (!$questionario) {
    flash('error', 'Questionario nao encontrado nesta escola.');
    redirect(lb_url('professor/questionarios.php'));
}

$perguntaId = int_get('pergunta', 0);
$pergunta = null;
$alternativas = [];
if ($perguntaId) {
    $st = $pdo->prepare('SELECT * FROM perguntas WHERE id = ? AND questionario_id = ?');
    $st->execute([$perguntaId, $questionarioId]);
    $pergunta = $st->fetch();
    if (!$pergunta) {
        flash('error', 'Pergunta nao encontrada.');
        redirect(lb_url('professor/questionario_form.php?id=' . $questionarioId));
    }
    $st = $pdo->prepare('SELECT * FROM alternativas WHERE pergunta_id = ? ORDER BY ordem, id');
    $st->execute([$perguntaId]);
    $alternativas = $st->fetchAll();
}

$st = $pdo->prepare('SELECT COUNT(*) FROM respostas WHERE pergunta_id = ?');
$st->execute([$perguntaId ?: 0]);
$temRespostas = (int) $st->fetchColumn() > 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $enunciado = inp('enunciado');
    $tipo = inp('tipo', 'multipla');
    $explicacao = inp('explicacao');
    $pontos = (float) str_replace(',', '.', inp('pontos', '1'));
    $erros = [];

    if (mb_strlen($enunciado) < 5) {
        $erros[] = 'Escreva o enunciado da pergunta.';
    }
    if (!in_array($tipo, ['multipla', 'vf', 'aberta'], true)) {
        $erros[] = 'Selecione um tipo de pergunta valido.';
    }
    if ($pontos < 0.25 || $pontos > 10) {
        $erros[] = 'A pontuacao deve ficar entre 0,25 e 10.';
    }
    if ($pergunta && $temRespostas && $tipo !== $pergunta['tipo']) {
        $erros[] = 'Esta pergunta ja possui respostas; o tipo nao pode ser alterado.';
    }

    $novas = [];
    if ($tipo === 'multipla') {
        $textos = $_POST['alternativa'] ?? [];
        $textos = is_array($textos) ? $textos : [];
        $correta = int_inp('correta', -1) ?? -1;
        $limpos = [];
        foreach ($textos as $i => $t) {
            $t = is_string($t) ? trim($t) : '';
            if ($t !== '') {
                $limpos[(int) $i] = $t;
            }
        }
        if (count($limpos) < 2) {
            $erros[] = 'Informe ao menos duas alternativas.';
        }
        if (!array_key_exists($correta, $limpos)) {
            $erros[] = 'Marque qual alternativa e a correta.';
        }
        $ordem = 1;
        foreach ($limpos as $i => $t) {
            $novas[] = ['texto' => mb_substr($t, 0, 255), 'correta' => $i === $correta ? 1 : 0, 'ordem' => $ordem++];
        }
    } elseif ($tipo === 'vf') {
        $correta = inp('vf_correta', 'V');
        $novas = [
            ['texto' => 'Verdadeiro', 'correta' => $correta === 'V' ? 1 : 0, 'ordem' => 1],
            ['texto' => 'Falso', 'correta' => $correta === 'F' ? 1 : 0, 'ordem' => 2],
        ];
    }

    if ($erros) {
        $_SESSION['_old'] = $_POST;
        flash('error', implode(' ', $erros));
        redirect(lb_url('professor/pergunta_form.php?questionario=' . $questionarioId . ($perguntaId ? '&pergunta=' . $perguntaId : '')));
    }

    $pdo->beginTransaction();
    try {
        if ($perguntaId) {
            $pdo->prepare('UPDATE perguntas SET enunciado = ?, tipo = ?, explicacao = ?, pontos = ? WHERE id = ? AND questionario_id = ?')
                ->execute([$enunciado, $tipo, $explicacao !== '' ? $explicacao : null, $pontos, $perguntaId, $questionarioId]);
            if (!$temRespostas) {
                $pdo->prepare('DELETE FROM alternativas WHERE pergunta_id = ?')->execute([$perguntaId]);
                $ins = $pdo->prepare('INSERT INTO alternativas (pergunta_id, texto, correta, ordem) VALUES (?, ?, ?, ?)');
                foreach ($novas as $a) {
                    $ins->execute([$perguntaId, $a['texto'], $a['correta'], $a['ordem']]);
                }
            }
            flash('success', 'Pergunta atualizada.');
        } else {
            $st = $pdo->prepare('SELECT COALESCE(MAX(ordem), 0) + 1 FROM perguntas WHERE questionario_id = ?');
            $st->execute([$questionarioId]);
            $ordem = (int) $st->fetchColumn();
            $pdo->prepare('INSERT INTO perguntas (questionario_id, enunciado, tipo, explicacao, ordem, pontos) VALUES (?, ?, ?, ?, ?, ?)')
                ->execute([$questionarioId, $enunciado, $tipo, $explicacao !== '' ? $explicacao : null, $ordem, $pontos]);
            $novoId = (int) $pdo->lastInsertId();
            $ins = $pdo->prepare('INSERT INTO alternativas (pergunta_id, texto, correta, ordem) VALUES (?, ?, ?, ?)');
            foreach ($novas as $a) {
                $ins->execute([$novoId, $a['texto'], $a['correta'], $a['ordem']]);
            }
            flash('success', 'Pergunta adicionada ao questionario.');
        }
        $pdo->commit();
    } catch (Throwable $ex) {
        $pdo->rollBack();
        lb_fatal('Nao foi possivel salvar a pergunta.', $ex);
    }
    lb_limpar_old();
    redirect(lb_url('professor/questionario_form.php?id=' . $questionarioId));
}

$v = static function (string $campo, string $padrao = '') use ($pergunta): string {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        return lb_old($campo, $padrao);
    }
    return e((string) ($pergunta[$campo] ?? $padrao));
};
$tipoAtual = $_SERVER['REQUEST_METHOD'] === 'POST' ? lb_old('tipo', 'multipla') : e((string) ($pergunta['tipo'] ?? 'multipla'));
$alts = [];
foreach ($alternativas as $a) {
    $alts[] = ['texto' => $a['texto'], 'correta' => (int) $a['correta'] === 1];
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $textos = $_POST['alternativa'] ?? [];
    $textos = is_array($textos) ? $textos : [];
    $corretaIdx = int_inp('correta', -1) ?? -1;
    $alts = [];
    foreach ($textos as $i => $t) {
        $alts[] = ['texto' => is_string($t) ? $t : '', 'correta' => (int) $i === $corretaIdx];
    }
}
$alts = array_pad($alts, 4, ['texto' => '', 'correta' => false]);
$vfCorreta = $_SERVER['REQUEST_METHOD'] === 'POST' ? lb_old('vf_correta', 'V') : 'V';

lb_shell_start($pergunta ? 'Editar pergunta' : 'Nova pergunta', [
    'contexto' => 'professor',
    'ativo'    => 'questionarios',
    'titulo_pagina' => $pergunta ? 'Editar pergunta' : 'Nova pergunta',
    'subtitulo' => e($questionario['titulo']),
    'acoes' => '<a class="btn btn-ghost btn-sm" href="' . e(lb_url('professor/questionario_form.php?id=' . $questionarioId)) . '">' . lb_icon('back') . 'Voltar ao questionario</a>',
]);
?>
<div class="card">
  <div class="card-head">
    <?= lb_icon('file', 'ico ico-lg') ?>
    <div><h2>Pergunta</h2><p>Questoes objetivas sao corrigidas automaticamente; abertas ficam para sua correcao.</p></div>
  </div>

  <?php if ($temRespostas): ?>
    <div class="alert alert-warning"><?= lb_icon('warning') ?><span>Esta pergunta ja possui respostas. As alternativas nao podem ser alteradas para preservar o historico.</span></div>
  <?php endif; ?>

  <form method="post" data-loading autocomplete="off">
    <?= csrf_field() ?>
    <input type="hidden" name="questionario" value="<?= $questionarioId ?>">
    <div class="form-grid">
      <div class="field full">
        <label for="enunciado">Enunciado *</label>
        <textarea class="input" id="enunciado" name="enunciado" rows="3" maxlength="2000" required placeholder="Escreva a pergunta com base no conteudo lido..."><?= $v('enunciado') ?></textarea>
      </div>
      <div class="field">
        <label for="tipo">Tipo *</label>
        <select class="input" id="tipo" name="tipo" <?= $temRespostas ? 'disabled' : '' ?>>
          <option value="multipla" <?= $tipoAtual === 'multipla' ? 'selected' : '' ?>>Multipla escolha</option>
          <option value="vf" <?= $tipoAtual === 'vf' ? 'selected' : '' ?>>Verdadeiro ou falso</option>
          <option value="aberta" <?= $tipoAtual === 'aberta' ? 'selected' : '' ?>>Resposta aberta</option>
        </select>
        <?php if ($temRespostas): ?><input type="hidden" name="tipo" value="<?= e($pergunta['tipo']) ?>"><?php endif; ?>
      </div>
      <div class="field">
        <label for="pontos">Pontuacao *</label>
        <input class="input" id="pontos" name="pontos" type="number" step="0.25" min="0.25" max="10" value="<?= $v('pontos', '1') ?>" required>
      </div>

      <div class="field full">
        <label>Alternativas (multipla escolha)</label>
        <?php if ($temRespostas): ?>
          <p class="muted">Alternativas atuais preservadas.</p>
        <?php else: ?>
          <div class="grid grid-2">
            <?php foreach ($alts as $i => $a): ?>
              <div class="field">
                <label class="alt" style="gap:10px">
                  <input type="radio" name="correta" value="<?= $i ?>" <?= $a['correta'] ? 'checked' : '' ?>>
                  <span>Correta</span>
                </label>
                <input class="input" name="alternativa[<?= $i ?>]" maxlength="255" value="<?= e($a['texto']) ?>" placeholder="Alternativa <?= $i + 1 ?>">
              </div>
            <?php endforeach; ?>
          </div>
          <span class="hint">Deixe em branco as alternativas que nao quiser usar e marque a correta.</span>
        <?php endif; ?>
      </div>

      <div class="field full">
        <label>Verdadeiro ou falso</label>
        <select class="input" name="vf_correta" <?= $temRespostas ? 'disabled' : '' ?>>
          <option value="V" <?= $vfCorreta === 'V' ? 'selected' : '' ?>>A afirmativa e verdadeira</option>
          <option value="F" <?= $vfCorreta === 'F' ? 'selected' : '' ?>>A afirmativa e falsa</option>
        </select>
        <span class="hint">Usado apenas quando o tipo for verdadeiro ou falso.</span>
      </div>

      <div class="field full">
        <label for="explicacao">Explicacao da resposta</label>
        <textarea class="input" id="explicacao" name="explicacao" rows="2" maxlength="2000" placeholder="Mostrada ao aluno apos a correcao."><?= $v('explicacao') ?></textarea>
      </div>
    </div>
    <div class="form-actions">
      <a class="btn btn-ghost" href="<?= e(lb_url('professor/questionario_form.php?id=' . $questionarioId)) ?>">Cancelar</a>
      <button class="btn" type="submit"><?= lb_icon('save') ?><?= $pergunta ? 'Salvar pergunta' : 'Adicionar pergunta' ?></button>
    </div>
  </form>
</div>
<?php
lb_shell_end(['contexto' => 'professor']);
