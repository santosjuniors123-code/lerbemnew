<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

$aluno = require_aluno();
$pdo = db();
$escolaId = (int) $aluno['escola_id'];

$st = $pdo->prepare("SELECT id, titulo, livro_titulo, nivel FROM questionarios
    WHERE escola_id = ? AND status = 'publicado' ORDER BY titulo");
$st->execute([$escolaId]);
$questionarios = $st->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $titulo = inp('titulo');
    $autor = inp('autor');
    $data = inp('data_leitura');
    $paginas = int_inp('paginas', 0) ?? 0;
    $resumo = inp('resumo');
    $questionarioId = int_inp('questionario_id', 0) ?: null;

    $erros = [];
    if (mb_strlen($titulo) < 3 || mb_strlen($titulo) > 200) {
        $erros[] = 'Informe o titulo do livro (3 a 200 caracteres).';
    }
    if (mb_strlen($autor) > 150) {
        $erros[] = 'O nome do autor deve ter no maximo 150 caracteres.';
    }
    $dt = DateTimeImmutable::createFromFormat('Y-m-d', $data);
    $hoje = new DateTimeImmutable('today');
    if (!$dt || $dt->format('Y-m-d') !== $data) {
        $erros[] = 'Informe uma data de leitura valida.';
    } elseif ($dt > $hoje) {
        $erros[] = 'A data da leitura nao pode estar no futuro.';
    } elseif ($dt < $hoje->modify('-5 years')) {
        $erros[] = 'A data da leitura deve ser dos ultimos 5 anos.';
    }
    if ($paginas < 1 || $paginas > 5000) {
        $erros[] = 'Informe a quantidade de paginas lidas (1 a 5000).';
    }
    if (mb_strlen($resumo) < 30) {
        $erros[] = 'Escreva um resumo com pelo menos 30 caracteres sobre o que voce leu.';
    }
    if (mb_strlen($resumo) > 5000) {
        $erros[] = 'O resumo deve ter no maximo 5000 caracteres.';
    }

    $questionario = null;
    if ($questionarioId) {
        $st = $pdo->prepare("SELECT id, titulo FROM questionarios WHERE id = ? AND escola_id = ? AND status = 'publicado'");
        $st->execute([$questionarioId, $escolaId]);
        $questionario = $st->fetch();
        if (!$questionario) {
            $erros[] = 'O questionario selecionado nao esta disponivel.';
        }
    }

    if ($erros) {
        $_SESSION['_old'] = $_POST;
        flash('error', implode(' ', $erros));
        redirect(lb_url('aluno/leitura_nova.php'));
    }

    $st = $pdo->prepare('INSERT INTO leituras (escola_id, aluno_id, turma_id, questionario_id, titulo, autor, data_leitura, paginas, resumo, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, "registrada")');
    $st->execute([
        $escolaId,
        $aluno['id'],
        $aluno['turma_id'],
        $questionario ? (int) $questionario['id'] : null,
        $titulo,
        $autor !== '' ? $autor : null,
        $data,
        $paginas,
        $resumo,
    ]);
    $leituraId = (int) $pdo->lastInsertId();
    lb_limpar_old();

    if ($questionario) {
        flash('success', 'Leitura registrada! Agora responda o questionario para concluir.');
        redirect(lb_url('aluno/responder.php?leitura=' . $leituraId));
    }
    flash('success', 'Leitura registrada com sucesso! Continue assim.');
    redirect(lb_url('aluno/leitura.php?id=' . $leituraId));
}

lb_shell_start('Nova leitura', ['contexto' => 'aluno', 'ativo' => 'leitura_nova']);
?>
<div class="card">
  <div class="card-head">
    <?= lb_icon('plus', 'ico ico-lg') ?>
    <div><h2>Registrar uma nova leitura</h2><p>Conte para sua escola o que voce leu. Leva menos de 3 minutos.</p></div>
  </div>

  <form method="post" data-loading autocomplete="off">
    <?= csrf_field() ?>
    <div class="form-grid">
      <div class="field full">
        <label for="titulo">Titulo do livro *</label>
        <input class="input" id="titulo" name="titulo" maxlength="200" value="<?= lb_old('titulo') ?>" required placeholder="Ex.: O Pequeno Principe">
      </div>
      <div class="field">
        <label for="autor">Autor</label>
        <input class="input" id="autor" name="autor" maxlength="150" value="<?= lb_old('autor') ?>" placeholder="Ex.: Antoine de Saint-Exupery">
      </div>
      <div class="field">
        <label for="data_leitura">Data da leitura *</label>
        <input class="input" type="date" id="data_leitura" name="data_leitura" max="<?= date('Y-m-d') ?>" value="<?= lb_old('data_leitura', date('Y-m-d')) ?>" required>
      </div>
      <div class="field">
        <label for="paginas">Paginas lidas *</label>
        <input class="input" type="number" id="paginas" name="paginas" min="1" max="5000" value="<?= lb_old('paginas') ?>" required>
      </div>
      <div class="field">
        <label for="questionario_id">Questionario (opcional)</label>
        <select class="input" id="questionario_id" name="questionario_id">
          <option value="">Responder depois</option>
          <?php foreach ($questionarios as $q): ?>
            <option value="<?= (int) $q['id'] ?>" <?= (int) ($_SESSION['_old']['questionario_id'] ?? 0) === (int) $q['id'] ? 'selected' : '' ?>>
              <?= e($q['titulo']) ?><?= $q['livro_titulo'] ? ' · ' . e(lb_resumir($q['livro_titulo'], 30)) : '' ?>
            </option>
          <?php endforeach; ?>
        </select>
        <span class="hint">Se escolher, voce responde em seguida e recebe a correcao automatica.</span>
      </div>
      <div class="field full">
        <label for="resumo">Resumo da leitura *</label>
        <textarea class="input" id="resumo" name="resumo" rows="7" maxlength="5000" required data-counter="contador-resumo" placeholder="Escreva com suas palavras o que aconteceu na historia, quem sao os personagens e o que voce aprendeu..."><?= lb_old('resumo') ?></textarea>
        <span class="hint" id="contador-resumo">0 caracteres</span>
      </div>
    </div>
    <div class="form-actions">
      <a class="btn btn-ghost" href="<?= e(lb_url('aluno/painel.php')) ?>">Cancelar</a>
      <button class="btn" type="submit"><?= lb_icon('save') ?>Registrar leitura</button>
    </div>
  </form>
</div>
<?php
lb_shell_end(['contexto' => 'aluno']);
