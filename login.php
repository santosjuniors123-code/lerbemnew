<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

$u = lb_user();
if ($u) {
    redirect($u['papel'] === 'admin' ? lb_url('admin/index.php') : lb_url('professor/index.php'));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $email = mb_strtolower(inp('email'));
    $senha = (string) ($_POST['senha'] ?? '');
    $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $senha === '') {
        flash('error', 'Informe e-mail e senha validos.');
        redirect(lb_url('login.php'));
    }
    if (lb_login_bloqueado($email, $ip)) {
        flash('error', 'Muitas tentativas sem sucesso. Aguarde 15 minutos e tente novamente.');
        redirect(lb_url('login.php'));
    }

    $st = db()->prepare(
        'SELECT u.*, e.nome AS escola_nome, e.ativo AS escola_ativa, p.id AS professor_id
         FROM usuarios u
         LEFT JOIN escolas e ON e.id = u.escola_id
         LEFT JOIN professores p ON p.usuario_id = u.id
         WHERE u.email = ? LIMIT 1'
    );
    $st->execute([$email]);
    $row = $st->fetch();

    $ok = $row && password_verify($senha, (string) $row['senha_hash']);
    lb_registrar_tentativa($email, $ip, (bool) $ok);

    if (!$ok) {
        flash('error', 'E-mail ou senha incorretos.');
        redirect(lb_url('login.php'));
    }
    if (!(int) $row['ativo']) {
        flash('error', 'Sua conta esta inativa. Procure o administrador.');
        redirect(lb_url('login.php'));
    }
    if ($row['papel'] === 'professor' && (!(int) ($row['escola_ativa'] ?? 0) || !$row['professor_id'])) {
        flash('error', 'Sua escola esta inativa ou seu perfil de professor esta incompleto.');
        redirect(lb_url('login.php'));
    }
    if (password_needs_rehash((string) $row['senha_hash'], PASSWORD_DEFAULT)) {
        db()->prepare('UPDATE usuarios SET senha_hash = ? WHERE id = ?')
            ->execute([password_hash($senha, PASSWORD_DEFAULT), $row['id']]);
    }
    db()->prepare('UPDATE usuarios SET ultimo_acesso = NOW() WHERE id = ?')->execute([$row['id']]);

    lb_login_user($row);
    flash('success', 'Bem-vindo(a), ' . $row['nome'] . '!');
    redirect($row['papel'] === 'admin' ? lb_url('admin/index.php') : lb_url('professor/index.php'));
}

lb_shell_start('Entrar', ['contexto' => 'publico', 'ativo' => 'login']);
?>
<div class="login-wrap">
  <aside class="login-side">
    <?= lb_logo() ?>
    <div>
      <h2>Acompanhe cada leitura da sua escola.</h2>
      <p>Um painel completo para registrar, avaliar e celebrar o progresso dos seus alunos.</p>
      <ul>
        <li><?= lb_icon('check') ?>Dashboard com indicadores da escola</li>
        <li><?= lb_icon('check') ?>Alunos, turmas e historico individual</li>
        <li><?= lb_icon('check') ?>Questionarios com correcao automatica</li>
        <li><?= lb_icon('check') ?>Relatorios em CSV e PDF</li>
      </ul>
    </div>
    <p style="font-size:12px;color:#9fd0c8">LER BEM · dados isolados por escola</p>
  </aside>

  <div class="login-panel">
    <div class="login-card">
      <div class="mb-24"><?= lb_logo() ?></div>
      <h1>Area do professor</h1>
      <p>Acesse com a conta criada pelo administrador da sua escola.</p>

      <form method="post" data-loading autocomplete="on">
        <?= csrf_field() ?>
        <div class="field">
          <label for="email">E-mail</label>
          <input class="input" type="email" id="email" name="email" value="<?= lb_old('email') ?>" required autocomplete="username">
        </div>
        <div class="field">
          <label for="senha">Senha</label>
          <input class="input" type="password" id="senha" name="senha" required autocomplete="current-password">
        </div>
        <button class="btn btn-lg" type="submit"><?= lb_icon('lock') ?>Entrar no painel</button>
      </form>

      <p class="login-alt">E aluno? <a href="<?= e(lb_url('index.php')) ?>">Registre sua leitura sem senha</a></p>
      <p class="login-alt">Primeiro acesso do sistema? <a href="<?= e(lb_url('install.php')) ?>">Executar instalacao</a></p>
    </div>
  </div>
</div>
<?php
lb_shell_end(['contexto' => 'publico']);
