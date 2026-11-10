<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

if (lb_aluno()) {
    redirect(lb_url('aluno/painel.php'));
}
$u = lb_user();
if ($u) {
    redirect($u['papel'] === 'admin' ? lb_url('admin/index.php') : lb_url('professor/index.php'));
}

$escolas = db()->query('SELECT id, nome, cidade, uf FROM escolas WHERE ativo = 1 ORDER BY nome')->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $escolaId = int_inp('escola_id', 0);
    $turmaId = int_inp('turma_id', 0);
    $nome = inp('nome');

    $erros = [];
    if (!$escolaId) {
        $erros[] = 'Selecione sua escola.';
    }
    if (!$turmaId) {
        $erros[] = 'Selecione sua turma.';
    }
    if (mb_strlen($nome) < 3) {
        $erros[] = 'Digite seu nome completo.';
    }

    if (!$erros) {
        $st = db()->prepare(
            'SELECT a.id, a.nome, a.escola_id, a.turma_id, t.nome AS turma_nome, e.nome AS escola_nome
             FROM alunos a
             JOIN escolas e ON e.id = a.escola_id AND e.ativo = 1
             JOIN turmas t ON t.id = a.turma_id AND t.ativo = 1
             WHERE a.escola_id = ? AND a.turma_id = ? AND a.nome_busca = ? AND a.status = "ativo"
             LIMIT 1'
        );
        $st->execute([$escolaId, $turmaId, lb_nome_busca($nome)]);
        $aluno = $st->fetch();

        if (!$aluno) {
            $_SESSION['_old'] = ['nome' => $nome, 'escola_id' => $escolaId, 'turma_id' => $turmaId];
            flash('error', 'Nao encontramos esse nome nesta turma. Confira a grafia ou procure seu professor para confirmar o cadastro.');
            redirect(lb_url('index.php'));
        }

        session_regenerate_id(true);
        $_SESSION['aluno'] = [
            'id'          => (int) $aluno['id'],
            'nome'        => (string) $aluno['nome'],
            'escola_id'   => (int) $aluno['escola_id'],
            'escola_nome' => (string) $aluno['escola_nome'],
            'turma_id'    => (int) $aluno['turma_id'],
            'turma_nome'  => (string) $aluno['turma_nome'],
        ];
        lb_limpar_old();
        flash('success', 'Ola, ' . $aluno['nome'] . '! Bom te ver por aqui.');
        redirect(lb_url('aluno/painel.php'));
    }

    $_SESSION['_old'] = $_POST;
    flash('error', implode(' ', $erros));
    redirect(lb_url('index.php'));
}

$oldEscola = (int) ($_SESSION['_old']['escola_id'] ?? 0);
$oldTurma = (int) ($_SESSION['_old']['turma_id'] ?? 0);
$turmas = [];
if ($oldEscola) {
    $st = db()->prepare('SELECT id, nome, ano_letivo FROM turmas WHERE escola_id = ? AND ativo = 1 ORDER BY nome');
    $st->execute([$oldEscola]);
    $turmas = $st->fetchAll();
}
lb_shell_start('Leitura que transforma', ['contexto' => 'publico', 'ativo' => 'home']);
?>
<div class="public-shell">
  <div class="public-top">
    <?= lb_logo() ?>
    <div class="btn-row">
      <a class="btn btn-ghost btn-sm" href="<?= e(lb_url('login.php')) ?>"><?= lb_icon('lock') ?>Sou professor</a>
      <a class="btn btn-sm" href="#identificacao"><?= lb_icon('read') ?>Registrar leitura</a>
    </div>
  </div>

  <section class="hero">
    <div class="hero-copy">
      <span class="hero-eyebrow"><?= lb_icon('star') ?>Programa de leitura escolar</span>
      <h1>Ler bem abre <span>mundos inteiros.</span></h1>
      <p class="lead">O LER BEM conecta alunos, professores e escolas em torno da leitura. O aluno registra o livro, escreve seu resumo e responde ao questionário. O professor acompanha o progresso de cada turma com relatorios claros.</p>
      <div class="hero-points">
        <span class="hero-point"><?= lb_icon('check') ?>Sem cadastro para o aluno</span>
        <span class="hero-point"><?= lb_icon('check') ?>Correcao automatica</span>
        <span class="hero-point"><?= lb_icon('check') ?>Relatorios por turma</span>
        <span class="hero-point"><?= lb_icon('check') ?>Dados isolados por escola</span>
      </div>
      <div class="trust-row">
        <span class="trust-item"><?= lb_icon('users') ?><?= count($escolas) ?> escola<?= count($escolas) === 1 ? '' : 's' ?> participante<?= count($escolas) === 1 ? '' : 's' ?></span>
        <span class="trust-item"><?= lb_icon('school') ?>Multiescolas</span>
        <span class="trust-item"><?= lb_icon('lock') ?>Acesso seguro do professor</span>
      </div>
    </div>

    <div class="hero-card" id="identificacao">
      <h2>Vamos comecar sua leitura</h2>
      <p>Escolha sua escola e turma e escreva seu nome como esta no cadastro.</p>

      <?php if (!$escolas): ?>
        <?= lb_empty('school', 'Nenhuma escola ativa', 'O sistema ainda nao possui escolas cadastradas. Procure a secretaria da sua escola.', '<a class="btn btn-sm" href="' . e(lb_url('install.php')) . '">Configurar sistema</a>') ?>
      <?php else: ?>
        <form method="post" action="<?= e(lb_url('index.php')) ?>" data-loading autocomplete="off">
          <?= csrf_field() ?>
          <div class="field mb-16">
            <label for="escola_id">Sua escola</label>
            <select class="input" name="escola_id" id="escola_id" required>
              <option value="">Selecione sua escola</option>
              <?php foreach ($escolas as $es): ?>
                <option value="<?= (int) $es['id'] ?>" <?= $oldEscola === (int) $es['id'] ? 'selected' : '' ?>>
                  <?= e($es['nome']) ?><?= $es['cidade'] ? ' · ' . e($es['cidade']) : '' ?><?= $es['uf'] ? '/' . e($es['uf']) : '' ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="field mb-16">
            <label for="turma_id">Sua turma</label>
            <select class="input" name="turma_id" id="turma_id" data-placeholder="Selecione a turma" required>
              <option value="">Selecione a turma</option>
              <?php foreach ($turmas as $t): ?>
                <option value="<?= (int) $t['id'] ?>" <?= $oldTurma === (int) $t['id'] ? 'selected' : '' ?>><?= e($t['nome']) ?> · <?= (int) $t['ano_letivo'] ?></option>
              <?php endforeach; ?>
            </select>
            <span class="hint">Escolha a escola para carregar as turmas.</span>
          </div>
          <div class="field mb-16">
            <label for="nome">Seu nome completo</label>
            <input class="input" id="nome" name="nome" value="<?= lb_old('nome') ?>" placeholder="Ex.: Maria Clara Souza" required minlength="3">
            <span class="hint">Use o mesmo nome do cadastro feito pelo professor.</span>
          </div>
          <button class="btn btn-lg" type="submit" style="width:100%"><?= lb_icon('arrow') ?>Comecar a leitura</button>
        </form>
        <p class="muted mt-16" style="font-size:12px;text-align:center">Nao encontrou seu nome? Peça ao professor para conferir seu cadastro.</p>
      <?php endif; ?>
    </div>
  </section>

  <h2 class="section-title">Feito para a rotina da escola</h2>
  <p class="section-sub">Cada etapa do programa de leitura tem uma ferramenta simples e direta.</p>
  <div class="grid grid-4">
    <article class="feature"><?= lb_icon('read', 'ico ico-lg') ?><h3>Registro de leitura</h3><p>Titulo, autor, data, paginas e resumo em um formulario curto e amigavel.</p></article>
    <article class="feature"><?= lb_icon('quiz', 'ico ico-lg') ?><h3>Questionarios</h3><p>Perguntas de multipla escolha, verdadeiro ou falso e resposta aberta.</p></article>
    <article class="feature"><?= lb_icon('sparkles', 'ico ico-lg') ?><h3>Apoio de IA</h3><p>Geracao de perguntas a partir do texto do livro, sempre revisadas pelo professor.</p></article>
    <article class="feature"><?= lb_icon('report', 'ico ico-lg') ?><h3>Relatorios</h3><p>Leituras por aluno, turma e periodo, com exportacao em CSV e PDF.</p></article>
  </div>
</div>
<footer class="public-footer">
  <span>LER BEM · Leitura que transforma</span>
  <span><a href="<?= e(lb_url('login.php')) ?>">Area do professor</a> · <a href="<?= e(lb_url('install.php')) ?>">Instalacao</a></span>
</footer>
<?php
lb_shell_end(['contexto' => 'publico']);
