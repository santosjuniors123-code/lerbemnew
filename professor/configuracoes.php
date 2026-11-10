<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

$u = require_professor();
$pdo = db();
$escolaId = (int) $u['escola_id'];

$meta = max(1, (int) (setting($escolaId, 'meta_leitura_mensal', '4') ?? 4));
$mensagem = setting($escolaId, 'mensagem_aluno', '') ?? '';
$erros = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $acao = inp('acao');

    if ($acao === 'escola') {
        $novaMeta = int_inp('meta_leitura_mensal', 0) ?? 0;
        $novaMsg = inp('mensagem_aluno');
        if ($novaMeta < 1 || $novaMeta > 200) {
            $erros[] = 'A meta mensal deve ficar entre 1 e 200 leituras.';
        }
        if (mb_strlen($novaMsg) > 300) {
            $erros[] = 'A mensagem para o aluno deve ter no maximo 300 caracteres.';
        }
        if (!$erros) {
            setting_set($escolaId, 'meta_leitura_mensal', (string) $novaMeta);
            setting_set($escolaId, 'mensagem_aluno', $novaMsg);
            flash('success', 'Configuracoes da escola salvas.');
            redirect(lb_url('professor/configuracoes.php'));
        }
    }

    if ($acao === 'perfil') {
        $nome = inp('nome');
        $email = mb_strtolower(inp('email'));
        if (mb_strlen($nome) < 3) {
            $erros[] = 'Informe seu nome completo.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $erros[] = 'Informe um e-mail valido.';
        }
        if (!$erros) {
            $st = $pdo->prepare('SELECT COUNT(*) FROM usuarios WHERE email = ? AND id <> ?');
            $st->execute([$email, (int) $u['id']]);
            if ((int) $st->fetchColumn() > 0) {
                $erros[] = 'Este e-mail ja esta em uso.';
            }
        }
        if (!$erros) {
            $pdo->prepare('UPDATE usuarios SET nome = ?, email = ? WHERE id = ?')->execute([$nome, $email, (int) $u['id']]);
            $pdo->prepare('UPDATE professores SET nome = ?, email = ? WHERE usuario_id = ? AND escola_id = ?')
                ->execute([$nome, $email, (int) $u['id'], $escolaId]);
            $_SESSION['usuario']['nome'] = $nome;
            $_SESSION['usuario']['email'] = $email;
            flash('success', 'Perfil atualizado.');
            redirect(lb_url('professor/configuracoes.php'));
        }
    }

    if ($acao === 'senha') {
        $atual = (string) ($_POST['senha_atual'] ?? '');
        $nova = (string) ($_POST['senha_nova'] ?? '');
        $confirma = (string) ($_POST['senha_confirma'] ?? '');

        $st = $pdo->prepare('SELECT senha_hash FROM usuarios WHERE id = ?');
        $st->execute([(int) $u['id']]);
        $hash = (string) $st->fetchColumn();

        if (!password_verify($atual, $hash)) {
            $erros[] = 'A senha atual nao confere.';
        }
        if (mb_strlen($nova) < 8) {
            $erros[] = 'A nova senha deve ter ao menos 8 caracteres.';
        }
        if ($nova !== $confirma) {
            $erros[] = 'A confirmacao da nova senha nao coincide.';
        }
        if (!$erros) {
            $pdo->prepare('UPDATE usuarios SET senha_hash = ? WHERE id = ?')
                ->execute([password_hash($nova, PASSWORD_DEFAULT), (int) $u['id']]);
            flash('success', 'Senha alterada com sucesso.');
            redirect(lb_url('professor/configuracoes.php'));
        }
    }
}

if ($erros) {
    flash('error', implode(' ', $erros));
}

$escola = lb_escola_atual();
$aiOk = trim((string) lb_config('ai.api_key', '')) !== '';

lb_shell_start('Configuracoes', [
    'contexto' => 'professor',
    'ativo'    => 'configuracoes',
    'titulo_pagina' => 'Configuracoes',
    'subtitulo' => 'Escola, perfil e seguranca da conta',
]);
?>
<div class="grid grid-2">
  <div class="card">
    <div class="card-head">
      <?= lb_icon('school', 'ico ico-lg') ?>
      <div><h2>Escola</h2><p><?= e($escola['nome'] ?? 'Escola') ?></p></div>
    </div>
    <div class="detail-grid">
      <div class="detail"><small>Cidade</small><strong><?= e(($escola['cidade'] ?? '') ?: '—') ?><?= !empty($escola['uf']) ? ' / ' . e($escola['uf']) : '' ?></strong></div>
      <div class="detail"><small>Telefone</small><strong><?= e(($escola['telefone'] ?? '') ?: '—') ?></strong></div>
      <div class="detail"><small>E-mail</small><strong><?= e(($escola['email'] ?? '') ?: '—') ?></strong></div>
    </div>
    <div class="divider"></div>
    <form method="post" data-loading>
      <?= csrf_field() ?>
      <input type="hidden" name="acao" value="escola">
      <div class="form-grid">
        <div class="field">
          <label for="meta_leitura_mensal">Meta de leituras por aluno/mes</label>
          <input class="input" id="meta_leitura_mensal" name="meta_leitura_mensal" type="number" min="1" max="200" value="<?= (int) $meta ?>">
        </div>
        <div class="field full">
          <label for="mensagem_aluno">Mensagem exibida ao aluno</label>
          <textarea class="input" id="mensagem_aluno" name="mensagem_aluno" rows="3" maxlength="300" placeholder="Ex.: Leia todos os dias e registre suas leituras!"><?= e($mensagem) ?></textarea>
        </div>
      </div>
      <div class="form-actions"><button class="btn" type="submit"><?= lb_icon('save') ?>Salvar escola</button></div>
    </form>
  </div>

  <div class="card">
    <div class="card-head">
      <?= lb_icon('users', 'ico ico-lg') ?>
      <div><h2>Meu perfil</h2><p>Dados de acesso</p></div>
    </div>
    <form method="post" data-loading autocomplete="off">
      <?= csrf_field() ?>
      <input type="hidden" name="acao" value="perfil">
      <div class="form-grid">
        <div class="field full">
          <label for="nome">Nome completo</label>
          <input class="input" id="nome" name="nome" maxlength="150" required value="<?= e($u['nome']) ?>">
        </div>
        <div class="field full">
          <label for="email">E-mail</label>
          <input class="input" id="email" name="email" type="email" maxlength="150" required value="<?= e($u['email']) ?>">
        </div>
      </div>
      <div class="form-actions"><button class="btn btn-soft" type="submit"><?= lb_icon('save') ?>Atualizar perfil</button></div>
    </form>

    <div class="divider"></div>

    <h3>Alterar senha</h3>
    <form method="post" data-loading autocomplete="off">
      <?= csrf_field() ?>
      <input type="hidden" name="acao" value="senha">
      <div class="form-grid">
        <div class="field full">
          <label for="senha_atual">Senha atual</label>
          <input class="input" id="senha_atual" name="senha_atual" type="password" required autocomplete="current-password">
        </div>
        <div class="field">
          <label for="senha_nova">Nova senha</label>
          <input class="input" id="senha_nova" name="senha_nova" type="password" minlength="8" required autocomplete="new-password">
        </div>
        <div class="field">
          <label for="senha_confirma">Confirmar</label>
          <input class="input" id="senha_confirma" name="senha_confirma" type="password" minlength="8" required autocomplete="new-password">
        </div>
      </div>
      <div class="form-actions"><button class="btn btn-soft" type="submit"><?= lb_icon('lock') ?>Alterar senha</button></div>
    </form>
  </div>
</div>

<div class="card mt-24">
  <div class="card-head">
    <?= lb_icon('sparkles', 'ico ico-lg') ?>
    <div><h2>Modulo de inteligencia artificial</h2><p><?= $aiOk ? 'Integracao configurada' : 'Integracao desativada' ?></p></div>
    <div><?= $aiOk ? lb_badge('Ativo', 'success') : lb_badge('Sem chave', 'neutral') ?></div>
  </div>
  <p class="muted">A geracao automatica de perguntas usa uma API externa configurada por variaveis de ambiente: <code>AI_API_KEY</code>, <code>AI_API_URL</code>, <code>AI_MODEL</code> e <code>AI_TIMEOUT</code>. Nenhuma chave e armazenada no banco de dados e, sem a chave, todo o restante do sistema continua funcionando normalmente.</p>
</div>
<?php
lb_shell_end(['contexto' => 'professor']);
