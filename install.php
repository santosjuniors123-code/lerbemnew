<?php
declare(strict_types=1);

define('LB_NO_INSTALL_CHECK', true);
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/installer.php';

if (lb_installed()) {
    flash('info', 'O sistema ja esta instalado. Use o login para acessar.');
    redirect(lb_url('login.php'));
}

$erros = [];
$etapa = 'form';
$resultado = null;
$post = $_POST;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $db = [
        'host' => inp('db_host', '127.0.0.1') ?: '127.0.0.1',
        'port' => inp('db_port', '3306') ?: '3306',
        'name' => inp('db_name', 'ler_bem') ?: 'ler_bem',
        'user' => inp('db_user'),
        'pass' => (string) ($_POST['db_pass'] ?? ''),
    ];
    $escolaNome = inp('escola_nome');
    $adminNome = inp('admin_nome');
    $adminEmail = inp('admin_email');
    $adminSenha = (string) ($_POST['admin_senha'] ?? '');
    $adminSenha2 = (string) ($_POST['admin_senha2'] ?? '');
    $criarTurmas = isset($_POST['criar_turmas']);
    $profNome = inp('professor_nome');
    $profEmail = inp('professor_email');
    $profSenha = (string) ($_POST['professor_senha'] ?? '');

    if ($db['user'] === '') {
        $erros[] = 'Informe o usuario do MySQL.';
    }
    if (!preg_match('/^[A-Za-z0-9_\-]{1,64}$/', $db['name'])) {
        $erros[] = 'O nome do banco deve conter apenas letras, numeros, _ ou -.';
    }
    if (mb_strlen($escolaNome) < 3) {
        $erros[] = 'Informe o nome da escola (minimo 3 caracteres).';
    }
    if (mb_strlen($adminNome) < 3) {
        $erros[] = 'Informe o nome do administrador.';
    }
    if (!filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
        $erros[] = 'Informe um e-mail valido para o administrador.';
    }
    if (strlen($adminSenha) < 8) {
        $erros[] = 'A senha do administrador deve ter no minimo 8 caracteres.';
    }
    if ($adminSenha !== $adminSenha2) {
        $erros[] = 'A confirmacao da senha do administrador nao confere.';
    }
    if ($profEmail !== '' && !filter_var($profEmail, FILTER_VALIDATE_EMAIL)) {
        $erros[] = 'O e-mail do professor e invalido.';
    }
    if ($profEmail !== '' && $profEmail === $adminEmail) {
        $erros[] = 'O e-mail do professor deve ser diferente do administrador.';
    }
    if ($profSenha !== '' && strlen($profSenha) < 8) {
        $erros[] = 'A senha do professor deve ter no minimo 8 caracteres.';
    }

    if (!$erros) {
        try {
            $pdo = lb_pdo_connect($db);
            $pdo->exec('CREATE DATABASE IF NOT EXISTS `' . $db['name'] . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
            $pdo->exec('USE `' . $db['name'] . '`');
            lb_install_schema($pdo);

            $dados = [
                'escola_nome'          => $escolaNome,
                'escola_cidade'        => inp('escola_cidade'),
                'escola_uf'            => strtoupper(inp('escola_uf')),
                'escola_email'         => inp('escola_email'),
                'admin_nome'           => $adminNome,
                'admin_email'          => $adminEmail,
                'admin_senha'          => $adminSenha,
                'professor_nome'       => $profNome,
                'professor_email'      => $profEmail,
                'professor_senha'      => $profSenha,
                'professor_disciplina' => inp('professor_disciplina'),
            ];
            $resultado = lb_install_seed($pdo, $dados);
            if ($criarTurmas) {
                $resultado['turmas'] = lb_criar_turmas($pdo, (int) $resultado['escola_id'], lb_turmas_padrao());
            }

            $gravouConfig = lb_write_config([
                'DB_HOST'     => $db['host'],
                'DB_PORT'     => $db['port'],
                'DB_NAME'     => $db['name'],
                'DB_USER'     => $db['user'],
                'DB_PASSWORD' => $db['pass'],
                'APP_URL'     => '',
                'APP_DEBUG'   => '0',
                'AI_API_KEY'  => '',
                'AI_API_URL'  => 'https://api.openai.com/v1/chat/completions',
                'AI_MODEL'    => 'gpt-4o-mini',
            ]);
            if (!$gravouConfig) {
                flash('warning', 'Instalacao concluida, mas nao foi possivel gravar config/config.local.php. Crie o arquivo manualmente com as credenciais.');
            }

            $etapa = 'sucesso';
        } catch (PDOException $e) {
            $msg = $e->getMessage();
            if (str_contains($msg, 'Access denied')) {
                $erros[] = 'Acesso negado pelo MySQL. Confira usuario e senha.';
            } elseif (str_contains($msg, 'Unknown database')) {
                $erros[] = 'Banco de dados inexistente e o usuario sem permissao para cria-lo.';
            } else {
                $erros[] = 'Falha no banco de dados: ' . $msg;
            }
        } catch (Throwable $e) {
            $erros[] = 'Falha na instalacao: ' . $e->getMessage();
        }
    }
}
?>
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Instalacao · LER BEM</title>
<link rel="icon" href="<?= e(lb_url('assets/img/logo.svg')) ?>" type="image/svg+xml">
<link rel="stylesheet" href="<?= e(lb_url('assets/css/style.css')) ?>">
</head>
<body class="ctx-publico">
<main class="install-shell">
  <div class="flex-between mb-24">
    <?= lb_logo() ?>
    <a class="btn btn-ghost btn-sm" href="<?= e(lb_url('index.php')) ?>">Ir para o site</a>
  </div>

  <div class="steps">
    <span class="step is-active"><span class="step-num">1</span>Banco de dados</span>
    <span class="step"><span class="step-num">2</span>Escola</span>
    <span class="step"><span class="step-num">3</span>Administrador</span>
    <span class="step"><span class="step-num">4</span>Concluido</span>
  </div>

  <?php foreach (lb_flashes() as $f): ?>
    <div class="alert alert-<?= e($f['tipo']) ?>"><?= lb_icon($f['tipo'] === 'error' ? 'x' : 'check') ?><span><?= e($f['msg']) ?></span></div>
  <?php endforeach; ?>

  <?php if ($etapa === 'sucesso' && $resultado): ?>
    <div class="card">
      <div class="card-head">
        <?= lb_icon('check', 'ico ico-lg') ?>
        <div><h2>Instalacao concluida com sucesso</h2><p>O banco de dados, a escola e o administrador foram criados.</p></div>
      </div>
      <div class="detail-grid">
        <div class="detail"><small>Escola</small><strong><?= e($escolaNome) ?></strong></div>
        <div class="detail"><small>Administrador</small><strong><?= e($adminEmail) ?></strong></div>
        <div class="detail"><small>Turmas criadas</small><strong><?= (int) ($resultado['turmas'] ?? 0) ?></strong></div>
      </div>
      <div class="note mt-16">Por seguranca, o instalador fica bloqueado apos a instalacao. Guarde suas credenciais em local seguro.</div>
      <div class="btn-row mt-16">
        <a class="btn" href="<?= e(lb_url('login.php')) ?>"><?= lb_icon('lock') ?>Entrar no sistema</a>
        <a class="btn btn-ghost" href="<?= e(lb_url('index.php')) ?>">Area do aluno</a>
      </div>
    </div>
  <?php else: ?>
    <?php if ($erros): ?>
      <div class="alert alert-error"><?= lb_icon('warning') ?><span><?= e(implode(' ', $erros)) ?></span></div>
    <?php endif; ?>

    <form method="post" class="card" data-loading autocomplete="off">
      <?= csrf_field() ?>

      <div class="card-head">
        <?= lb_icon('settings', 'ico ico-lg') ?>
        <div><h2>Configuracao do banco de dados</h2><p>Use os dados fornecidos pela sua hospedagem (cPanel, Plesk, etc.).</p></div>
      </div>
      <div class="form-grid">
        <div class="field"><label for="db_host">Servidor MySQL</label>
          <input class="input" id="db_host" name="db_host" value="<?= e($post['db_host'] ?? 'localhost') ?>" required></div>
        <div class="field"><label for="db_port">Porta</label>
          <input class="input" id="db_port" name="db_port" value="<?= e($post['db_port'] ?? '3306') ?>" required></div>
        <div class="field"><label for="db_name">Nome do banco</label>
          <input class="input" id="db_name" name="db_name" value="<?= e($post['db_name'] ?? 'ler_bem') ?>" required></div>
        <div class="field"><label for="db_user">Usuario do banco</label>
          <input class="input" id="db_user" name="db_user" value="<?= e($post['db_user'] ?? '') ?>" required></div>
        <div class="field full"><label for="db_pass">Senha do banco</label>
          <input class="input" type="password" id="db_pass" name="db_pass" value="">
          <span class="hint">O instalador cria o banco se o usuario tiver permissao.</span></div>
      </div>

      <div class="divider"></div>

      <div class="card-head">
        <?= lb_icon('school', 'ico ico-lg') ?>
        <div><h2>Dados da escola</h2><p>Cada escola possui alunos, turmas e relatorios isolados.</p></div>
      </div>
      <div class="form-grid">
        <div class="field full"><label for="escola_nome">Nome da escola</label>
          <input class="input" id="escola_nome" name="escola_nome" value="<?= e($post['escola_nome'] ?? '') ?>" required></div>
        <div class="field"><label for="escola_cidade">Cidade</label>
          <input class="input" id="escola_cidade" name="escola_cidade" value="<?= e($post['escola_cidade'] ?? '') ?>"></div>
        <div class="field"><label for="escola_uf">UF</label>
          <input class="input" id="escola_uf" name="escola_uf" maxlength="2" value="<?= e($post['escola_uf'] ?? '') ?>"></div>
        <div class="field full"><label for="escola_email">E-mail da escola (opcional)</label>
          <input class="input" type="email" id="escola_email" name="escola_email" value="<?= e($post['escola_email'] ?? '') ?>"></div>
        <div class="field full">
          <label class="check"><input type="checkbox" name="criar_turmas" value="1" <?= !isset($_POST['criar_turmas']) || $_POST['criar_turmas'] ? 'checked' : '' ?>>
            Criar turmas iniciais (6º ao 9º ano) para comecar a testar</label>
        </div>
      </div>

      <div class="divider"></div>

      <div class="card-head">
        <?= lb_icon('lock', 'ico ico-lg') ?>
        <div><h2>Administrador do sistema</h2><p>Gerencia escolas, professores e configuracoes globais.</p></div>
      </div>
      <div class="form-grid">
        <div class="field"><label for="admin_nome">Nome completo</label>
          <input class="input" id="admin_nome" name="admin_nome" value="<?= e($post['admin_nome'] ?? '') ?>" required></div>
        <div class="field"><label for="admin_email">E-mail de acesso</label>
          <input class="input" type="email" id="admin_email" name="admin_email" value="<?= e($post['admin_email'] ?? '') ?>" required></div>
        <div class="field"><label for="admin_senha">Senha (min. 8 caracteres)</label>
          <input class="input" type="password" id="admin_senha" name="admin_senha" minlength="8" required></div>
        <div class="field"><label for="admin_senha2">Confirmar senha</label>
          <input class="input" type="password" id="admin_senha2" name="admin_senha2" minlength="8" required></div>
      </div>

      <div class="divider"></div>

      <div class="card-head">
        <?= lb_icon('users', 'ico ico-lg') ?>
        <div><h2>Primeiro professor (opcional)</h2><p>Pode ser criado depois pelo painel administrativo.</p></div>
      </div>
      <div class="form-grid">
        <div class="field"><label for="professor_nome">Nome</label>
          <input class="input" id="professor_nome" name="professor_nome" value="<?= e($post['professor_nome'] ?? '') ?>"></div>
        <div class="field"><label for="professor_email">E-mail</label>
          <input class="input" type="email" id="professor_email" name="professor_email" value="<?= e($post['professor_email'] ?? '') ?>"></div>
        <div class="field"><label for="professor_disciplina">Disciplina</label>
          <input class="input" id="professor_disciplina" name="professor_disciplina" value="<?= e($post['professor_disciplina'] ?? '') ?>"></div>
        <div class="field"><label for="professor_senha">Senha (min. 8)</label>
          <input class="input" type="password" id="professor_senha" name="professor_senha" minlength="8"></div>
      </div>

      <div class="form-actions">
        <a class="btn btn-ghost" href="<?= e(lb_url('index.php')) ?>">Cancelar</a>
        <button class="btn" type="submit"><?= lb_icon('save') ?>Instalar sistema</button>
      </div>
    </form>
  <?php endif; ?>

  <p class="muted text-center mt-24" style="font-size:12.5px">LER BEM · PHP <?= e(PHP_VERSION) ?> · instalador seguro</p>
</main>
</body>
</html>
