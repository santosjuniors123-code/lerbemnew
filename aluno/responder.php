<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

$aluno = require_aluno();
$pdo = db();
$escolaId = (int) $aluno['escola_id'];
$leituraId = int_get('leitura', 0);

if (!$leituraId) {
    flash('error', 'Selecione a leitura que deseja responder.');
    redirect(lb_url('aluno/historico.php'));
}

$st = $pdo->prepare('SELECT l.*, q.titulo AS questionario_titulo, q.descricao AS questionario_descricao, q.nivel
    FROM leituras l JOIN questionarios q ON q.id = l.questionario_id
    WHERE l.id = ? AND l.aluno_id = ? AND l.escola_id = ?');
$st->execute([$leituraId, $aluno['id'], $escolaId]);
$leitura = $st->fetch();

if (!$leitura) {
    flash('error', 'Leitura nao encontrada ou sem questionario vinculado.');
    redirect(lb_url('aluno/historico.php'));
}

$st = $pdo->prepare('SELECT COUNT(*) FROM respostas WHERE leitura_id = ?');
$st->execute([$leituraId]);
if ((int) $st->fetchColumn() > 0) {
    flash('info', 'Voce ja respondeu este questionario.');
    redirect(lb_url('aluno/leitura.php?id=' . $leituraId));
}

$st = $pdo->prepare('SELECT * FROM perguntas WHERE questionario_id = ? ORDER BY ordem, id');
$st->execute([(int) $leitura['questionario_id']]);
$perguntas = $st->fetchAll();

$alternativas = [];
if ($perguntas) {
    $ids = array_map(static fn ($p) => (int) $p['id'], $perguntas);
    $in = implode(',', array_fill(0, count($ids), '?'));
    $st = $pdo->prepare("SELECT * FROM alternativas WHERE pergunta_id IN ($in) ORDER BY ordem, id");
    $st->execute($ids);
    foreach ($st->fetchAll() as $a) {
        $alternativas[(int) $a['pergunta_id']][] = $a;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    if (!$perguntas) {
        flash('error', 'Este questionario ainda nao possui perguntas.');
        redirect(lb_url('aluno/leitura.php?id=' . $leituraId));
    }

    $respostas = $_POST['respostas'] ?? [];
    $respostas = is_array($respostas) ? $respostas : [];
    $erros = [];
    $acertos = 0;
    $totalObjetivas = 0;
    $temAberta = false;
    $dados = [];

    foreach ($perguntas as $p) {
        $pid = (int) $p['id'];
        $tipo = (string) $p['tipo'];
        $valor = $respostas[$pid] ?? null;

        if ($tipo === 'aberta') {
            $temAberta = true;
            $texto = is_string($valor) ? trim($valor) : '';
            if (mb_strlen($texto) < 3) {
                $erros[] = 'Responda a pergunta ' . (int) $p['ordem'] . '.';
            }
            $dados[] = ['pergunta' => $p, 'alternativa_id' => null, 'texto' => $texto, 'correta' => null, 'pontos' => null];
            continue;
        }

        $totalObjetivas++;
        $altId = is_scalar($valor) ? (int) $valor : 0;
        $escolhida = null;
        foreach ($alternativas[$pid] ?? [] as $a) {
            if ((int) $a['id'] === $altId) {
                $escolhida = $a;
                break;
            }
        }
        if (!$escolhida) {
            $erros[] = 'Escolha uma alternativa na pergunta ' . (int) $p['ordem'] . '.';
            $dados[] = ['pergunta' => $p, 'alternativa_id' => null, 'texto' => null, 'correta' => 0, 'pontos' => 0];
            continue;
        }
        $correta = (int) $escolhida['correta'] === 1;
        if ($correta) {
            $acertos++;
        }
        $dados[] = [
            'pergunta'       => $p,
            'alternativa_id' => (int) $escolhida['id'],
            'texto'          => null,
            'correta'        => $correta ? 1 : 0,
            'pontos'         => $correta ? (float) $p['pontos'] : 0.0,
        ];
    }

    if ($erros) {
        flash('error', implode(' ', array_unique($erros)));
        redirect(lb_url('aluno/responder.php?leitura=' . $leituraId));
    }

    $nota = $totalObjetivas > 0 ? round($acertos / $totalObjetivas * 10, 2) : null;
    $status = $temAberta ? 'pendente_correcao' : 'registrada';

    $pdo->beginTransaction();
    try {
        $st = $pdo->prepare('INSERT INTO respostas (escola_id, leitura_id, questionario_id, aluno_id, pergunta_id, alternativa_id, resposta_texto, correta, pontos_obtidos)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
        foreach ($dados as $d) {
            $st->execute([
                $escolaId,
                $leituraId,
                (int) $leitura['questionario_id'],
                (int) $aluno['id'],
                (int) $d['pergunta']['id'],
                $d['alternativa_id'],
                $d['texto'] !== '' ? $d['texto'] : null,
                $d['correta'],
                $d['pontos'],
            ]);
        }
        $pdo->prepare('UPDATE leituras SET acertos = ?, total_objetivas = ?, nota = ?, status = ? WHERE id = ?')
            ->execute([$acertos, $totalObjetivas, $nota, $status, $leituraId]);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        lb_fatal('Nao foi possivel salvar suas respostas. Tente novamente.', $e);
    }

    flash('success', $temAberta
        ? 'Respostas enviadas! As questoes abertas serao corrigidas pelo seu professor.'
        : 'Respostas enviadas! Sua nota foi calculada automaticamente.');
    redirect(lb_url('aluno/leitura.php?id=' . $leituraId));
}

lb_shell_start('Responder questionario', ['contexto' => 'aluno', 'ativo' => 'historico']);
?>
<div class="card">
  <div class="card-head">
    <?= lb_icon('quiz', 'ico ico-lg') ?>
    <div>
      <h2><?= e($leitura['questionario_titulo']) ?></h2>
      <p>Livro: <strong><?= e($leitura['titulo']) ?></strong> · <?= count($perguntas) ?> pergunta<?= count($perguntas) === 1 ? '' : 's' ?> · nivel <?= e($leitura['nivel']) ?></p>
    </div>
  </div>
  <?php if ($leitura['questionario_descricao']): ?>
    <div class="note mb-24"><?= e($leitura['questionario_descricao']) ?></div>
  <?php endif; ?>

  <?php if (!$perguntas): ?>
    <?= lb_empty('quiz', 'Questionario sem perguntas', 'Seu professor ainda esta preparando as perguntas deste questionario.') ?>
  <?php else: ?>
  <form method="post" data-loading autocomplete="off">
    <?= csrf_field() ?>
    <?php foreach ($perguntas as $i => $p): $pid = (int) $p['id']; ?>
      <div class="pergunta">
        <div class="pergunta-head">
          <span class="pergunta-num"><?= $i + 1 ?></span>
          <div style="flex:1">
            <div class="pergunta-texto"><?= e($p['enunciado']) ?></div>
            <div class="pergunta-tipo">
              <?= $p['tipo'] === 'vf' ? 'Verdadeiro ou falso' : ($p['tipo'] === 'aberta' ? 'Resposta aberta' : 'Multipla escolha') ?>
              · <?= lb_num((float) $p['pontos']) ?> ponto<?= (float) $p['pontos'] == 1 ? '' : 's' ?>
            </div>
          </div>
        </div>

        <?php if ($p['tipo'] === 'aberta'): ?>
          <div class="field">
            <label class="sr-only" for="p<?= $pid ?>">Sua resposta</label>
            <textarea class="input" id="p<?= $pid ?>" name="respostas[<?= $pid ?>]" rows="4" maxlength="3000" required placeholder="Escreva sua resposta com suas palavras..."></textarea>
          </div>
        <?php else: ?>
          <?php foreach ($alternativas[$pid] ?? [] as $a): ?>
            <label class="alt">
              <input type="radio" name="respostas[<?= $pid ?>]" value="<?= (int) $a['id'] ?>" required>
              <span><?= e($a['texto']) ?></span>
            </label>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
    <div class="form-actions">
      <a class="btn btn-ghost" href="<?= e(lb_url('aluno/historico.php')) ?>">Responder depois</a>
      <button class="btn" type="submit"><?= lb_icon('check') ?>Enviar respostas</button>
    </div>
  </form>
  <?php endif; ?>
</div>
<?php
lb_shell_end(['contexto' => 'aluno']);
