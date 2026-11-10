<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

$u = require_admin();
$pdo = db();
$id = int_get('id', 0);
$escola = null;

if ($id) {
    $st = $pdo->prepare('SELECT * FROM escolas WHERE id = ?');
    $st->execute([$id]);
    $escola = $st->fetch();
    if (!$escola) {
        flash('error', 'Escola nao encontrada.');
        redirect(lb_url('admin/escolas.php'));
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $nome = inp('nome');
    $cidade = inp('cidade');
    $uf = mb_strtoupper(inp('uf'));
    $telefone = inp('telefone');
    $email = mb_strtolower(inp('email'));
    $ativo = isset($_POST['ativo']) ? 1 : 0;
    $erros = [];

    if (mb_strlen($nome) < 3 || mb_strlen($nome) > 150) {
        $erros[] = 'Informe o nome da escola (3 a 150 caracteres).';
    }
    if ($uf !== '' && !preg_match('/^[A-Z]{2}$/', $uf)) {
        $erros[] = 'A UF deve ter duas letras.';
    }
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erros[] = 'Informe um e-mail valido.';
    }

    $slug = lb_slug($nome);
    if (!$erros) {
        $base = $slug !== '' ? $slug : 'escola';
        $slug = $base;
        $i = 2;
        while (true) {
            $st = $pdo->prepare('SELECT COUNT(*) FROM escolas WHERE slug = ? AND id <> ?');
            $st->execute([$slug, $id]);
            if ((int) $st->fetchColumn() === 0) {
                break;
            }
            $slug = $base . '-' . $i++;
        }
    }

    if ($erros) {
        $_SESSION['_old'] = $_POST;
        flash('error', implode(' ', $erros));
        redirect(lb_url('admin/escola_form.php' . ($id ? '?id=' . $id : '')));
    }

    if ($id) {
        $pdo->prepare('UPDATE escolas SET nome = ?, slug = ?, cidade = ?, uf = ?, telefone = ?, email = ?, ativo = ? WHERE id = ?')
            ->execute([$nome, $slug, $cidade !== '' ? $cidade : null, $uf !== '' ? $uf : null, $telefone !== '' ? $telefone : null, $email !== '' ? $email : null, $ativo, $id]);
        lb_limpar_old();
        flash('success', 'Escola atualizada.');
        redirect(lb_url('admin/escolas.php'));
    }

    $pdo->prepare('INSERT INTO escolas (nome, slug, cidade, uf, telefone, email, ativo) VALUES (?, ?, ?, ?, ?, ?, ?)')
        ->execute([$nome, $slug, $cidade !== '' ? $cidade : null, $uf !== '' ? $uf : null, $telefone !== '' ? $telefone : null, $email !== '' ? $email : null, $ativo]);
    lb_limpar_old();
    flash('success', 'Escola criada. Agora cadastre os professores dela.');
    redirect(lb_url('admin/professor_form.php?escola=' . (int) $pdo->lastInsertId()));
}

$v = static function (string $campo, string $padrao = '') use ($escola): string {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        return lb_old($campo, $padrao);
    }
    return e((string) ($escola[$campo] ?? $padrao));
};
$ativoAtual = $_SERVER['REQUEST_METHOD'] === 'POST' ? isset($_POST['ativo']) : (bool) ($escola['ativo'] ?? true);

lb_shell_start($escola ? 'Editar escola' : 'Nova escola', [
    'contexto' => 'admin',
    'ativo'    => 'escolas',
    'titulo_pagina' => $escola ? 'Editar escola' : 'Nova escola',
    'subtitulo' => $escola ? e($escola['nome']) : 'Cadastro de escola da rede',
    'acoes' => '<a class="btn btn-ghost btn-sm" href="' . e(lb_url('admin/escolas.php')) . '">' . lb_icon('back') . 'Voltar</a>',
]);
?>
<div class="card">
  <div class="card-head">
    <?= lb_icon('school', 'ico ico-lg') ?>
    <div><h2>Dados da escola</h2><p>Cada escola possui alunos, turmas e leituras isolados.</p></div>
  </div>
  <form method="post" data-loading autocomplete="off">
    <?= csrf_field() ?>
    <div class="form-grid">
      <div class="field full">
        <label for="nome">Nome da escola *</label>
        <input class="input" id="nome" name="nome" maxlength="150" required value="<?= $v('nome') ?>" placeholder="Ex.: Escola Municipal Monteiro Lobato">
      </div>
      <div class="field">
        <label for="cidade">Cidade</label>
        <input class="input" id="cidade" name="cidade" maxlength="120" value="<?= $v('cidade') ?>">
      </div>
      <div class="field">
        <label for="uf">UF</label>
        <input class="input" id="uf" name="uf" maxlength="2" value="<?= $v('uf') ?>" placeholder="SP">
      </div>
      <div class="field">
        <label for="telefone">Telefone</label>
        <input class="input" id="telefone" name="telefone" maxlength="30" value="<?= $v('telefone') ?>">
      </div>
      <div class="field">
        <label for="email">E-mail</label>
        <input class="input" id="email" name="email" type="email" maxlength="150" value="<?= $v('email') ?>">
      </div>
      <div class="field full">
        <label class="alt" style="gap:10px">
          <input type="checkbox" name="ativo" value="1" <?= $ativoAtual ? 'checked' : '' ?>>
          <span>Escola ativa (aparece na pagina inicial do aluno)</span>
        </label>
      </div>
    </div>
    <div class="form-actions">
      <a class="btn btn-ghost" href="<?= e(lb_url('admin/escolas.php')) ?>">Cancelar</a>
      <button class="btn" type="submit"><?= lb_icon('save') ?><?= $escola ? 'Salvar escola' : 'Criar escola' ?></button>
    </div>
  </form>
</div>
<?php
lb_shell_end(['contexto' => 'admin']);
