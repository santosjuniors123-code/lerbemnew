<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

$u = require_professor();
$pdo = db();
$escolaId = (int) $u['escola_id'];
$id = int_get('id', 0);
$turma = null;

if ($id) {
    $st = $pdo->prepare('SELECT * FROM turmas WHERE id = ? AND escola_id = ?');
    $st->execute([$id, $escolaId]);
    $turma = $st->fetch();
    if (!$turma) {
        flash('error', 'Turma nao encontrada nesta escola.');
        redirect(lb_url('professor/turmas.php'));
    }
}

$turnos = ['manha' => 'Manha', 'tarde' => 'Tarde', 'noite' => 'Noite', 'integral' => 'Integral'];
$anos = range((int) date('Y') + 1, (int) date('Y') - 6);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $nome = inp('nome');
    $ano = int_inp('ano_letivo', (int) date('Y')) ?? (int) date('Y');
    $turno = inp('turno', 'manha');
    $serie = inp('serie');
    $ativo = int_inp('ativo', 1) ? 1 : 0;
    $erros = [];

    if (mb_strlen($nome) < 2 || mb_strlen($nome) > 80) {
        $erros[] = 'Informe o nome da turma (2 a 80 caracteres).';
    }
    if ($ano < 2000 || $ano > (int) date('Y') + 2) {
        $erros[] = 'Informe um ano letivo valido.';
    }
    if (!isset($turnos[$turno])) {
        $erros[] = 'Selecione um turno valido.';
    }
    if (mb_strlen($serie) > 60) {
        $erros[] = 'A serie deve ter no maximo 60 caracteres.';
    }

    if (!$erros) {
        $st = $pdo->prepare('SELECT id FROM turmas WHERE escola_id = ? AND nome = ? AND ano_letivo = ? AND id <> ?');
        $st->execute([$escolaId, $nome, $ano, $id]);
        if ($st->fetch()) {
            $erros[] = 'Ja existe uma turma com este nome no ano letivo informado.';
        }
    }

    if ($erros) {
        $_SESSION['_old'] = $_POST;
        flash('error', implode(' ', $erros));
        redirect(lb_url('professor/turma_form.php' . ($id ? '?id=' . $id : '')));
    }

    if ($id) {
        $st = $pdo->prepare('UPDATE turmas SET nome = ?, ano_letivo = ?, turno = ?, serie = ?, ativo = ? WHERE id = ? AND escola_id = ?');
        $st->execute([$nome, $ano, $turno, $serie !== '' ? $serie : null, $ativo, $id, $escolaId]);
        lb_limpar_old();
        flash('success', 'Turma atualizada com sucesso.');
    } else {
        $st = $pdo->prepare('INSERT INTO turmas (escola_id, nome, ano_letivo, turno, serie, ativo) VALUES (?, ?, ?, ?, ?, ?)');
        $st->execute([$escolaId, $nome, $ano, $turno, $serie !== '' ? $serie : null, $ativo]);
        lb_limpar_old();
        flash('success', 'Turma criada com sucesso.');
    }
    redirect(lb_url('professor/turmas.php'));
}

$v = static function (string $campo, string $padrao = '') use ($turma): string {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        return lb_old($campo, $padrao);
    }
    return e((string) ($turma[$campo] ?? $padrao));
};

lb_shell_start($turma ? 'Editar turma' : 'Nova turma', [
    'contexto' => 'professor',
    'ativo'    => 'turmas',
    'titulo_pagina' => $turma ? 'Editar turma' : 'Nova turma',
    'subtitulo' => 'Organize alunos por ano letivo e turno',
    'acoes' => '<a class="btn btn-ghost btn-sm" href="' . e(lb_url('professor/turmas.php')) . '">' . lb_icon('back') . 'Voltar</a>',
]);
?>
<div class="card">
  <div class="card-head">
    <?= lb_icon('class', 'ico ico-lg') ?>
    <div><h2><?= $turma ? e($turma['nome']) : 'Dados da turma' ?></h2><p>Alunos sao associados a turma no cadastro do aluno.</p></div>
  </div>
  <form method="post" data-loading autocomplete="off">
    <?= csrf_field() ?>
    <div class="form-grid">
      <div class="field">
        <label for="nome">Nome da turma *</label>
        <input class="input" id="nome" name="nome" maxlength="80" required value="<?= $v('nome') ?>" placeholder="Ex.: 5º Ano A">
      </div>
      <div class="field">
        <label for="ano_letivo">Ano letivo *</label>
        <select class="input" id="ano_letivo" name="ano_letivo">
          <?php foreach ($anos as $ano): ?>
            <option value="<?= $ano ?>" <?= (int) $v('ano_letivo', (string) date('Y')) === $ano ? 'selected' : '' ?>><?= $ano ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label for="turno">Turno *</label>
        <select class="input" id="turno" name="turno">
          <?php foreach ($turnos as $k => $rotulo): ?>
            <option value="<?= e($k) ?>" <?= $v('turno', 'manha') === $k ? 'selected' : '' ?>><?= e($rotulo) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label for="serie">Serie / etapa</label>
        <input class="input" id="serie" name="serie" maxlength="60" value="<?= $v('serie') ?>" placeholder="Ex.: Ensino Fundamental I">
      </div>
      <div class="field">
        <label for="ativo">Situacao</label>
        <select class="input" id="ativo" name="ativo">
          <option value="1" <?= $v('ativo', '1') === '1' ? 'selected' : '' ?>>Ativa</option>
          <option value="0" <?= $v('ativo', '1') === '0' ? 'selected' : '' ?>>Inativa</option>
        </select>
      </div>
    </div>
    <div class="form-actions">
      <a class="btn btn-ghost" href="<?= e(lb_url('professor/turmas.php')) ?>">Cancelar</a>
      <button class="btn" type="submit"><?= lb_icon('save') ?><?= $turma ? 'Salvar alteracoes' : 'Criar turma' ?></button>
    </div>
  </form>
</div>
<?php
lb_shell_end(['contexto' => 'professor']);
