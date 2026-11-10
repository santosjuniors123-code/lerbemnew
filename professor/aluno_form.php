<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

$u = require_professor();
$pdo = db();
$escolaId = (int) $u['escola_id'];
$id = int_get('id', 0);
$aluno = null;

if ($id) {
    $st = $pdo->prepare('SELECT * FROM alunos WHERE id = ? AND escola_id = ?');
    $st->execute([$id, $escolaId]);
    $aluno = $st->fetch();
    if (!$aluno) {
        flash('error', 'Aluno nao encontrado nesta escola.');
        redirect(lb_url('professor/alunos.php'));
    }
}

$turmas = lb_turmas_escola($escolaId);
$erros = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $nome = inp('nome');
    $turmaId = int_inp('turma_id', 0) ?: null;
    $nascimento = inp('data_nascimento');
    $status = inp('status', 'ativo');
    $erros = [];

    if (mb_strlen($nome) < 3 || mb_strlen($nome) > 150) {
        $erros[] = 'Informe o nome completo do aluno (3 a 150 caracteres).';
    }
    if ($turmaId !== null) {
        $st = $pdo->prepare('SELECT COUNT(*) FROM turmas WHERE id = ? AND escola_id = ?');
        $st->execute([$turmaId, $escolaId]);
        if (!(int) $st->fetchColumn()) {
            $erros[] = 'A turma selecionada nao pertence a sua escola.';
        }
    }
    if ($nascimento !== '') {
        $dt = DateTimeImmutable::createFromFormat('!Y-m-d', $nascimento);
        if (!$dt || $dt->format('Y-m-d') !== $nascimento || $dt > new DateTimeImmutable('today')) {
            $erros[] = 'Informe uma data de nascimento valida.';
        }
    }
    if (!in_array($status, ['ativo', 'inativo'], true)) {
        $status = 'ativo';
    }

    if (!$erros) {
        $busca = lb_nome_busca($nome);
        $st = $pdo->prepare('SELECT id FROM alunos WHERE escola_id = ? AND nome_busca = ? AND id <> ?');
        $st->execute([$escolaId, $busca, $id]);
        if ($st->fetch()) {
            $erros[] = 'Ja existe um aluno com este nome nesta escola.';
        }
    }

    if ($erros) {
        $_SESSION['_old'] = $_POST;
        flash('error', implode(' ', $erros));
        redirect(lb_url('professor/aluno_form.php' . ($id ? '?id=' . $id : '')));
    }

    if ($id) {
        $st = $pdo->prepare('UPDATE alunos SET nome = ?, nome_busca = ?, turma_id = ?, data_nascimento = ?, status = ? WHERE id = ? AND escola_id = ?');
        $st->execute([$nome, lb_nome_busca($nome), $turmaId, $nascimento !== '' ? $nascimento : null, $status, $id, $escolaId]);
        $pdo->prepare('UPDATE leituras SET turma_id = ? WHERE aluno_id = ? AND escola_id = ?')->execute([$turmaId, $id, $escolaId]);
        lb_limpar_old();
        flash('success', 'Dados do aluno atualizados com sucesso.');
        redirect(lb_url('professor/aluno_historico.php?id=' . $id));
    }

    $st = $pdo->prepare('INSERT INTO alunos (escola_id, turma_id, nome, nome_busca, data_nascimento, status) VALUES (?, ?, ?, ?, ?, ?)');
    $st->execute([$escolaId, $turmaId, $nome, lb_nome_busca($nome), $nascimento !== '' ? $nascimento : null, $status]);
    lb_limpar_old();
    flash('success', 'Aluno cadastrado com sucesso. Ele ja pode registrar leituras na pagina inicial.');
    redirect(lb_url('professor/alunos.php'));
}

$valor = static function (string $campo, string $padrao = '') use ($aluno): string {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        return lb_old($campo, $padrao);
    }
    return e((string) ($aluno[$campo] ?? $padrao));
};

lb_shell_start($aluno ? 'Editar aluno' : 'Novo aluno', [
    'contexto' => 'professor',
    'ativo'    => 'alunos',
    'titulo_pagina' => $aluno ? 'Editar aluno' : 'Novo aluno',
    'subtitulo' => $aluno ? 'Atualize os dados cadastrais' : 'Cadastre um aluno para registrar leituras',
    'acoes' => '<a class="btn btn-ghost btn-sm" href="' . e(lb_url('professor/alunos.php')) . '">' . lb_icon('back') . 'Voltar</a>',
]);
?>
<div class="card">
  <div class="card-head">
    <?= lb_icon('users', 'ico ico-lg') ?>
    <div><h2><?= $aluno ? 'Dados do aluno' : 'Cadastro de aluno' ?></h2><p>Somente o nome e obrigatorio. A turma ajuda nos relatorios.</p></div>
  </div>
  <form method="post" data-loading autocomplete="off">
    <?= csrf_field() ?>
    <div class="form-grid">
      <div class="field full">
        <label for="nome">Nome completo *</label>
        <input class="input" id="nome" name="nome" maxlength="150" required value="<?= $valor('nome') ?>" placeholder="Ex.: Maria Eduarda Silva">
      </div>
      <div class="field">
        <label for="turma_id">Turma</label>
        <?= lb_select_turmas($turmas, $_SERVER['REQUEST_METHOD'] === 'POST' ? int_inp('turma_id', 0) : (int) ($aluno['turma_id'] ?? 0)) ?>
      </div>
      <div class="field">
        <label for="data_nascimento">Data de nascimento</label>
        <input class="input" type="date" id="data_nascimento" name="data_nascimento" max="<?= date('Y-m-d') ?>" value="<?= $valor('data_nascimento') ?>">
      </div>
      <div class="field">
        <label for="status">Situacao</label>
        <select class="input" id="status" name="status">
          <option value="ativo" <?= $valor('status', 'ativo') === 'ativo' ? 'selected' : '' ?>>Ativo</option>
          <option value="inativo" <?= $valor('status', 'ativo') === 'inativo' ? 'selected' : '' ?>>Inativo</option>
        </select>
        <span class="hint">Alunos inativos nao podem registrar novas leituras.</span>
      </div>
    </div>
    <div class="form-actions">
      <a class="btn btn-ghost" href="<?= e(lb_url('professor/alunos.php')) ?>">Cancelar</a>
      <button class="btn" type="submit"><?= lb_icon('save') ?><?= $aluno ? 'Salvar alteracoes' : 'Cadastrar aluno' ?></button>
    </div>
  </form>
</div>
<?php
lb_shell_end(['contexto' => 'professor']);
