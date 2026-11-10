<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

$u = require_professor();
$pdo = db();
$escolaId = (int) $u['escola_id'];
$id = int_get('id', 0);
$questionario = null;

if ($id) {
    $st = $pdo->prepare('SELECT * FROM questionarios WHERE id = ? AND escola_id = ?');
    $st->execute([$id, $escolaId]);
    $questionario = $st->fetch();
    if (!$questionario) {
        flash('error', 'Questionario nao encontrado nesta escola.');
        redirect(lb_url('professor/questionarios.php'));
    }
}

$niveis = ['facil' => 'Facil', 'medio' => 'Medio', 'dificil' => 'Dificil'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $titulo = inp('titulo');
    $livro = inp('livro_titulo');
    $descricao = inp('descricao');
    $nivel = inp('nivel', 'medio');
    $status = inp('status', 'rascunho');
    $erros = [];

    if (mb_strlen($titulo) < 3 || mb_strlen($titulo) > 180) {
        $erros[] = 'Informe o titulo do questionario (3 a 180 caracteres).';
    }
    if (mb_strlen($livro) > 200) {
        $erros[] = 'O titulo do livro deve ter no maximo 200 caracteres.';
    }
    if (!isset($niveis[$nivel])) {
        $nivel = 'medio';
    }
    if (!in_array($status, ['rascunho', 'publicado', 'arquivado'], true)) {
        $status = 'rascunho';
    }

    if ($status === 'publicado') {
        $st = $pdo->prepare('SELECT COUNT(*) FROM perguntas WHERE questionario_id = ?');
        $st->execute([$id ?: 0]);
        if ($id && (int) $st->fetchColumn() === 0) {
            $erros[] = 'Adicione ao menos uma pergunta antes de publicar.';
        }
    }

    if ($erros) {
        $_SESSION['_old'] = $_POST;
        flash('error', implode(' ', $erros));
        redirect(lb_url('professor/questionario_form.php' . ($id ? '?id=' . $id : '')));
    }

    if ($id) {
        $st = $pdo->prepare('UPDATE questionarios SET titulo = ?, livro_titulo = ?, descricao = ?, nivel = ?, status = ? WHERE id = ? AND escola_id = ?');
        $st->execute([$titulo, $livro !== '' ? $livro : null, $descricao !== '' ? $descricao : null, $nivel, $status, $id, $escolaId]);
        lb_limpar_old();
        flash('success', 'Questionario atualizado.');
        redirect(lb_url('professor/questionario_form.php?id=' . $id));
    }

    $st = $pdo->prepare('INSERT INTO questionarios (escola_id, professor_id, titulo, livro_titulo, descricao, nivel, origem, status) VALUES (?, ?, ?, ?, ?, ?, "manual", ?)');
    $st->execute([$escolaId, lb_professor_id($u), $titulo, $livro !== '' ? $livro : null, $descricao !== '' ? $descricao : null, $nivel, $status]);
    $novo = (int) $pdo->lastInsertId();
    lb_limpar_old();
    flash('success', 'Questionario criado. Agora adicione as perguntas.');
    redirect(lb_url('professor/questionario_form.php?id=' . $novo));
}

$perguntas = [];
if ($questionario) {
    $st = $pdo->prepare('SELECT p.*, (SELECT COUNT(*) FROM respostas r WHERE r.pergunta_id = p.id) AS respostas
        FROM perguntas p WHERE p.questionario_id = ? ORDER BY p.ordem, p.id');
    $st->execute([$id]);
    $perguntas = $st->fetchAll();
}

$v = static function (string $campo, string $padrao = '') use ($questionario): string {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        return lb_old($campo, $padrao);
    }
    return e((string) ($questionario[$campo] ?? $padrao));
};
$rotuloTipo = ['multipla' => 'Multipla escolha', 'vf' => 'Verdadeiro ou falso', 'aberta' => 'Resposta aberta'];

lb_shell_start($questionario ? 'Editar questionario' : 'Novo questionario', [
    'contexto' => 'professor',
    'ativo'    => 'questionarios',
    'titulo_pagina' => $questionario ? 'Editar questionario' : 'Novo questionario',
    'subtitulo' => $questionario ? e($questionario['titulo']) : 'Defina os dados e adicione as perguntas',
    'acoes' => '<a class="btn btn-ghost btn-sm" href="' . e(lb_url('professor/questionarios.php')) . '">' . lb_icon('back') . 'Voltar</a>',
]);
?>
<div class="card">
  <div class="card-head">
    <?= lb_icon('quiz', 'ico ico-lg') ?>
    <div><h2>Dados do questionario</h2><p>Depois de salvar voce adiciona as perguntas.</p></div>
  </div>
  <form method="post" data-loading autocomplete="off">
    <?= csrf_field() ?>
    <div class="form-grid">
      <div class="field">
        <label for="titulo">Titulo *</label>
        <input class="input" id="titulo" name="titulo" maxlength="180" required value="<?= $v('titulo') ?>" placeholder="Ex.: Interpretacao - O Pequeno Principe">
      </div>
      <div class="field">
        <label for="livro_titulo">Livro</label>
        <input class="input" id="livro_titulo" name="livro_titulo" maxlength="200" value="<?= $v('livro_titulo') ?>" placeholder="Titulo da obra">
      </div>
      <div class="field">
        <label for="nivel">Nivel</label>
        <select class="input" id="nivel" name="nivel">
          <?php foreach ($niveis as $k => $rotulo): ?>
            <option value="<?= e($k) ?>" <?= $v('nivel', 'medio') === $k ? 'selected' : '' ?>><?= e($rotulo) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label for="status">Situacao</label>
        <select class="input" id="status" name="status">
          <option value="rascunho" <?= $v('status', 'rascunho') === 'rascunho' ? 'selected' : '' ?>>Rascunho</option>
          <option value="publicado" <?= $v('status', 'rascunho') === 'publicado' ? 'selected' : '' ?>>Publicado</option>
          <option value="arquivado" <?= $v('status', 'rascunho') === 'arquivado' ? 'selected' : '' ?>>Arquivado</option>
        </select>
        <span class="hint">Somente questionarios publicados aparecem para os alunos.</span>
      </div>
      <div class="field full">
        <label for="descricao">Orientacoes ao aluno</label>
        <textarea class="input" id="descricao" name="descricao" rows="3" maxlength="2000" placeholder="Ex.: Leia com atencao e responda com base no texto lido."><?= $v('descricao') ?></textarea>
      </div>
    </div>
    <div class="form-actions">
      <a class="btn btn-ghost" href="<?= e(lb_url('professor/questionarios.php')) ?>">Cancelar</a>
      <button class="btn" type="submit"><?= lb_icon('save') ?><?= $questionario ? 'Salvar dados' : 'Criar questionario' ?></button>
    </div>
  </form>
</div>

<?php if ($questionario): ?>
<div class="card mt-24">
  <div class="card-head">
    <?= lb_icon('file', 'ico ico-lg') ?>
    <div><h2>Perguntas</h2><p><?= count($perguntas) ?> pergunta<?= count($perguntas) === 1 ? '' : 's' ?> cadastrada<?= count($perguntas) === 1 ? '' : 's' ?></p></div>
    <div class="topbar-actions">
      <a class="btn btn-soft btn-sm" href="<?= e(lb_url('professor/ia.php?questionario=' . $id)) ?>"><?= lb_icon('sparkles') ?>Gerar com IA</a>
      <a class="btn btn-sm" href="<?= e(lb_url('professor/pergunta_form.php?questionario=' . $id)) ?>"><?= lb_icon('plus') ?>Nova pergunta</a>
    </div>
  </div>

  <?php if (!$perguntas): ?>
    <?= lb_empty('file', 'Nenhuma pergunta ainda', 'Adicione perguntas manualmente ou gere sugestoes com o modulo de IA e aprove as que fizerem sentido.') ?>
  <?php else: ?>
    <?php foreach ($perguntas as $i => $p): ?>
      <div class="pergunta">
        <div class="pergunta-head">
          <span class="pergunta-num"><?= $i + 1 ?></span>
          <div style="flex:1">
            <div class="pergunta-texto"><?= e($p['enunciado']) ?></div>
            <div class="pergunta-tipo"><?= e($rotuloTipo[$p['tipo']] ?? $p['tipo']) ?> · <?= lb_num((float) $p['pontos']) ?> ponto(s) · <?= (int) $p['respostas'] ?> resposta(s)</div>
          </div>
          <a class="btn btn-ghost btn-sm" href="<?= e(lb_url('professor/pergunta_form.php?questionario=' . $id . '&pergunta=' . (int) $p['id'])) ?>"><?= lb_icon('edit') ?>Editar</a>
          <form method="post" action="<?= e(lb_url('professor/pergunta_acao.php')) ?>" data-confirm="Excluir esta pergunta?" style="display:inline">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
            <input type="hidden" name="questionario" value="<?= $id ?>">
            <input type="hidden" name="acao" value="excluir">
            <button class="btn btn-danger btn-sm" type="submit"><?= lb_icon('trash') ?></button>
          </form>
        </div>
      </div>
    <?php endforeach; ?>
    <?php if ($questionario['status'] !== 'publicado'): ?>
      <div class="btn-row mt-16">
        <form method="post">
          <?= csrf_field() ?>
          <input type="hidden" name="titulo" value="<?= e($questionario['titulo']) ?>">
          <input type="hidden" name="livro_titulo" value="<?= e($questionario['livro_titulo']) ?>">
          <input type="hidden" name="descricao" value="<?= e($questionario['descricao']) ?>">
          <input type="hidden" name="nivel" value="<?= e($questionario['nivel']) ?>">
          <input type="hidden" name="status" value="publicado">
          <button class="btn" type="submit"><?= lb_icon('check') ?>Publicar para os alunos</button>
        </form>
      </div>
    <?php endif; ?>
  <?php endif; ?>
</div>
<?php endif; ?>
<?php
lb_shell_end(['contexto' => 'professor']);
