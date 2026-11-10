<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

$u = require_admin();
$pdo = db();
$id = int_get('id', 0);
$professor = null;

$escolas = $pdo->query('SELECT id, nome, ativo FROM escolas ORDER BY nome')->fetchAll();

if ($id) {
    $st = $pdo->prepare('SELECT p.*, u.email AS usuario_email, u.ativo AS usuario_ativo
        FROM professores p LEFT JOIN usuarios u ON u.id = p.usuario_id WHERE p.id = ?');
    $st->execute([$id]);
    $professor = $st->fetch();
    if (!$professor) {
        flash('error', 'Professor nao encontrado.');
        redirect(lb_url('admin/professores.php'));
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $escolaId = int_inp('escola_id', 0) ?? 0;
    $nome = inp('nome');
    $email = mb_strtolower(inp('email'));
    $disciplina = inp('disciplina');
    $telefone = inp('telefone');
    $senha = (string) ($_POST['senha'] ?? '');
    $ativo = isset($_POST['ativo']) ? 1 : 0;
    $erros = [];

    $st = $pdo->prepare('SELECT id FROM escolas WHERE id = ?');
    $st->execute([$escolaId]);
    if (!$st->fetchColumn()) {
        $erros[] = 'Selecione a escola do professor.';
    }
    if (mb_strlen($nome) < 3 || mb_strlen($nome) > 150) {
        $erros[] = 'Informe o nome completo do professor.';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erros[] = 'Informe um e-mail valido.';
    }
    if (!$id && mb_strlen($senha) < 8) {
        $erros[] = 'A senha inicial deve ter ao menos 8 caracteres.';
    }
    if ($id && $senha !== '' && mb_strlen($senha) < 8) {
        $erros[] = 'A nova senha deve ter ao menos 8 caracteres.';
    }
    if (!$erros) {
        $st = $pdo->prepare('SELECT COUNT(*) FROM usuarios WHERE email = ? AND id <> ?');
        $st->execute([$email, (int) ($professor['usuario_id'] ?? 0)]);
        if ((int) $st->fetchColumn() > 0) {
            $erros[] = 'Este e-mail ja esta cadastrado.';
        }
    }

    if ($erros) {
        $_SESSION['_old'] = $_POST;
        flash('error', implode(' ', $erros));
        redirect(lb_url('admin/professor_form.php' . ($id ? '?id=' . $id : '')));
    }

    $pdo->beginTransaction();
    try {
        if ($id) {
            $usuarioId = (int) ($professor['usuario_id'] ?? 0);
            if ($usuarioId) {
                $pdo->prepare('UPDATE usuarios SET escola_id = ?, nome = ?, email = ?, papel = "professor", ativo = ? WHERE id = ?')
                    ->execute([$escolaId, $nome, $email, $ativo, $usuarioId]);
                if ($senha !== '') {
                    $pdo->prepare('UPDATE usuarios SET senha_hash = ? WHERE id = ?')
                        ->execute([password_hash($senha, PASSWORD_DEFAULT), $usuarioId]);
                }
            } else {
                $pdo->prepare('INSERT INTO usuarios (escola_id, nome, email, senha_hash, papel, ativo) VALUES (?, ?, ?, ?, "professor", ?)')
                    ->execute([$escolaId, $nome, $email, password_hash($senha !== '' ? $senha : bin2hex(random_bytes(16)), PASSWORD_DEFAULT), $ativo]);
                $usuarioId = (int) $pdo->lastInsertId();
            }
            $pdo->prepare('UPDATE professores SET escola_id = ?, usuario_id = ?, nome = ?, email = ?, disciplina = ?, telefone = ?, ativo = ? WHERE id = ?')
                ->execute([$escolaId, $usuarioId, $nome, $email, $disciplina !== '' ? $disciplina : null, $telefone !== '' ? $telefone : null, $ativo, $id]);
            flash('success', 'Professor atualizado.');
        } else {
            $pdo->prepare('INSERT INTO usuarios (escola_id, nome, email, senha_hash, papel, ativo) VALUES (?, ?, ?, ?, "professor", ?)')
                ->execute([$escolaId, $nome, $email, password_hash($senha, PASSWORD_DEFAULT), $ativo]);
            $usuarioId = (int) $pdo->lastInsertId();
            $pdo->prepare('INSERT INTO professores (escola_id, usuario_id, nome, email, disciplina, telefone, ativo) VALUES (?, ?, ?, ?, ?, ?, ?)')
                ->execute([$escolaId, $usuarioId, $nome, $email, $disciplina !== '' ? $disciplina : null, $telefone !== '' ? $telefone : null, $ativo]);
            flash('success', 'Professor cadastrado com acesso ao painel da escola.');
        }
        $pdo->commit();
    } catch (Throwable $ex) {
        $pdo->rollBack();
        lb_fatal('Nao foi possivel salvar o professor.', $ex);
    }
    lb_limpar_old();
    redirect(lb_url('admin/professores.php'));
}

$escolaPadrao = int_get('escola', 0) ?? 0;
$v = static function (string $campo, string $padrao = '') use ($professor): string {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        return lb_old($campo, $padrao);
    }
    return e((string) ($professor[$campo] ?? $padrao));
};
$escolaSel = $_SERVER['REQUEST_METHOD'] === 'POST' ? (int_inp('escola_id', 0) ?? 0) : (int) ($professor['escola_id'] ?? $escolaPadrao);
$ativoAtual = $_SERVER['REQUEST_METHOD'] === 'POST' ? isset($_POST['ativo']) : (bool) ($professor['ativo'] ?? true);

lb_shell_start($professor ? 'Editar professor' : 'Novo professor', [
    'contexto' => 'admin',
    'ativo'    => 'professores',
    'titulo_pagina' => $professor ? 'Editar professor' : 'Novo professor',
    'subtitulo' => $professor ? e($professor['nome']) : 'Crie o acesso ao painel da escola',
    'acoes' => '<a class="btn btn-ghost btn-sm" href="' . e(lb_url('admin/professores.php')) . '">' . lb_icon('back') . 'Voltar</a>',
]);
?>
<div class="card">
  <div class="card-head">
    <?= lb_icon('users', 'ico ico-lg') ?>
    <div><h2>Dados do professor</h2><p>O professor acessa somente os dados da escola vinculada.</p></div>
  </div>
  <form method="post" data-loading autocomplete="off">
    <?= csrf_field() ?>
    <div class="form-grid">
      <div class="field">
        <label for="escola_id">Escola *</label>
        <select class="input" id="escola_id" name="escola_id" required>
          <option value="">Selecione...</option>
          <?php foreach ($escolas as $es): ?>
            <option value="<?= (int) $es['id'] ?>" <?= $escolaSel === (int) $es['id'] ? 'selected' : '' ?>><?= e($es['nome']) ?><?= $es['ativo'] ? '' : ' (inativa)' ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label for="nome">Nome completo *</label>
        <input class="input" id="nome" name="nome" maxlength="150" required value="<?= $v('nome') ?>">
      </div>
      <div class="field">
        <label for="email">E-mail de acesso *</label>
        <input class="input" id="email" name="email" type="email" maxlength="150" required value="<?= $v('email') ?>">
      </div>
      <div class="field">
        <label for="disciplina">Disciplina</label>
        <input class="input" id="disciplina" name="disciplina" maxlength="80" value="<?= $v('disciplina') ?>">
      </div>
      <div class="field">
        <label for="telefone">Telefone</label>
        <input class="input" id="telefone" name="telefone" maxlength="30" value="<?= $v('telefone') ?>">
      </div>
      <div class="field">
        <label for="senha"><?= $professor ? 'Nova senha (opcional)' : 'Senha inicial *' ?></label>
        <input class="input" id="senha" name="senha" type="password" minlength="8" <?= $professor ? '' : 'required' ?> autocomplete="new-password">
        <span class="hint">Minimo de 8 caracteres. A senha e gravada com hash.</span>
      </div>
      <div class="field full">
        <label class="alt" style="gap:10px">
          <input type="checkbox" name="ativo" value="1" <?= $ativoAtual ? 'checked' : '' ?>>
          <span>Acesso ativo</span>
        </label>
      </div>
    </div>
    <div class="form-actions">
      <a class="btn btn-ghost" href="<?= e(lb_url('admin/professores.php')) ?>">Cancelar</a>
      <button class="btn" type="submit"><?= lb_icon('save') ?><?= $professor ? 'Salvar professor' : 'Cadastrar professor' ?></button>
    </div>
  </form>
</div>
<?php
lb_shell_end(['contexto' => 'admin']);
