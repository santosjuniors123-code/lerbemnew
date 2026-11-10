<?php
declare(strict_types=1);

/**
 * Layout, componentes visuais e identidade do LER BEM.
 */

function lb_icon(string $nome, string $classe = 'ico'): string
{
    $p = [
        'book'      => '<path d="M4 5.5A2.5 2.5 0 0 1 6.5 3H19v15H6.5A2.5 2.5 0 0 0 4 20.5V5.5Z"/><path d="M4 20.5A2.5 2.5 0 0 1 6.5 18H19v3H6.5A2.5 2.5 0 0 1 4 20.5Z"/><path d="M8 7h7M8 10.5h5"/>',
        'grid'      => '<rect x="3.5" y="3.5" width="7" height="7" rx="1.6"/><rect x="13.5" y="3.5" width="7" height="7" rx="1.6"/><rect x="3.5" y="13.5" width="7" height="7" rx="1.6"/><rect x="13.5" y="13.5" width="7" height="7" rx="1.6"/>',
        'users'     => '<circle cx="9" cy="8" r="3.2"/><path d="M2.8 20c0-3.3 2.8-5.4 6.2-5.4s6.2 2.1 6.2 5.4"/><path d="M16.5 5.2a3 3 0 0 1 0 5.6M17.5 14.8c2.2.5 3.7 2.2 3.7 4.4"/>',
        'class'     => '<path d="M3 8.5 12 4l9 4.5-9 4.5-9-4.5Z"/><path d="M7 10.7V16c0 1.5 2.2 2.6 5 2.6s5-1.1 5-2.6v-5.3"/>',
        'read'      => '<path d="M3 5.5c2.8-.9 5.4-.9 9 1.2 3.6-2.1 6.2-2.1 9-1.2v13c-2.8-.9-5.4-.9-9 1.2-3.6-2.1-6.2-2.1-9-1.2v-13Z"/><path d="M12 6.7v13"/>',
        'quiz'      => '<rect x="3.5" y="4" width="17" height="16" rx="2.4"/><path d="M8 9.5h8M8 13h5"/><circle cx="16.5" cy="15.5" r="2.5"/><path d="m15.4 15.5.8.8 1.5-1.6"/>',
        'report'    => '<path d="M5 20V9.5M10 20V4M15 20v-7M20 20v-4.5"/>',
        'settings'  => '<circle cx="12" cy="12" r="3.1"/><path d="M19.4 14.4a7.7 7.7 0 0 0 0-4.8l1.7-1.3-1.9-3.3-2 .8a7.6 7.6 0 0 0-4.1-2.4L12.7 1h-3.8l-.4 2.4a7.6 7.6 0 0 0-4.1 2.4l-2-.8L.5 8.3l1.7 1.3a7.7 7.7 0 0 0 0 4.8L.5 15.7l1.9 3.3 2-.8a7.6 7.6 0 0 0 4.1 2.4l.4 2.4h3.8l.4-2.4a7.6 7.6 0 0 0 4.1-2.4l2 .8 1.9-3.3-1.7-1.3Z"/>',
        'logout'    => '<path d="M14 4H6a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h8"/><path d="m17 8 4 4-4 4M21 12H9"/>',
        'plus'      => '<path d="M12 5v14M5 12h14"/>',
        'search'    => '<circle cx="10.8" cy="10.8" r="6.3"/><path d="m15.5 15.5 4.5 4.5"/>',
        'edit'      => '<path d="M4 20h4l10-10a2.5 2.5 0 0 0-3.5-3.5L4.5 16.5 4 20Z"/><path d="m13.5 7.5 3.5 3.5"/>',
        'trash'     => '<path d="M4 7h16M9 7V4.8h6V7M6.5 7l.8 12.2a1.8 1.8 0 0 0 1.8 1.7h5.8a1.8 1.8 0 0 0 1.8-1.7L17.5 7"/><path d="M10 11v6M14 11v6"/>',
        'check'     => '<path d="m4.5 12.5 5 5 10-11"/>',
        'x'         => '<path d="M6 6l12 12M18 6 6 18"/>',
        'download'  => '<path d="M12 3.5v11M7.5 10.5 12 15l4.5-4.5"/><path d="M4 19.5h16"/>',
        'sparkles'  => '<path d="M12 3l1.9 5.1L19 10l-5.1 1.9L12 17l-1.9-5.1L5 10l5.1-1.9L12 3Z"/><path d="M18.5 15.5l.8 2.2 2.2.8-2.2.8-.8 2.2-.8-2.2-2.2-.8 2.2-.8.8-2.2Z"/>',
        'arrow'     => '<path d="M5 12h14M13 6l6 6-6 6"/>',
        'back'      => '<path d="M19 12H5M11 6l-6 6 6 6"/>',
        'clock'     => '<circle cx="12" cy="12" r="8.5"/><path d="M12 7.5V12l3.2 2"/>',
        'school'    => '<path d="M3 20h18M5 20V9.5L12 4l7 5.5V20"/><path d="M9.5 20v-5h5v5"/><path d="M9 11h2M13 11h2"/>',
        'star'      => '<path d="m12 3.5 2.6 5.4 5.9.8-4.3 4.2 1 5.9-5.2-2.8-5.2 2.8 1-5.9L3.5 9.7l5.9-.8L12 3.5Z"/>',
        'warning'   => '<path d="M12 4 2.8 20h18.4L12 4Z"/><path d="M12 9.5v4.5M12 17.2h.01"/>',
        'mail'      => '<rect x="3" y="5.5" width="18" height="13" rx="2.2"/><path d="m3.8 7 8.2 6 8.2-6"/>',
        'lock'      => '<rect x="4.5" y="10.5" width="15" height="10" rx="2.2"/><path d="M8 10.5V8a4 4 0 0 1 8 0v2.5"/>',
        'chart'     => '<path d="M4 19.5h16"/><rect x="5.5" y="11" width="3.2" height="6"/><rect x="10.4" y="7" width="3.2" height="10"/><rect x="15.3" y="9.5" width="3.2" height="7.5"/>',
        'menu'      => '<path d="M4 7h16M4 12h16M4 17h16"/>',
        'eye'       => '<path d="M2.5 12S6 5.8 12 5.8 21.5 12 21.5 12 18 18.2 12 18.2 2.5 12 2.5 12Z"/><circle cx="12" cy="12" r="2.9"/>',
        'file'      => '<path d="M13.5 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8.5L13.5 3Z"/><path d="M13.5 3v5.5H19"/>',
        'save'      => '<path d="M5 4h11l3 3v13H5V4Z"/><path d="M8.5 4v5h7V4M8.5 20v-6h7v6"/>',
    ];
    $path = $p[$nome] ?? $p['book'];
    return '<svg class="' . e($classe) . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $path . '</svg>';
}

function lb_logo(bool $compacto = false): string
{
    return '<a class="brand" href="' . e(lb_url('index.php')) . '">'
        . '<span class="brand-mark">' . lb_icon('book') . '</span>'
        . ($compacto ? '' : '<span class="brand-text"><strong>LER BEM</strong><small>leitura que transforma</small></span>')
        . '</a>';
}

function lb_flash_html(): string
{
    $html = '';
    foreach (lb_flashes() as $f) {
        $tipo = in_array($f['tipo'], ['success', 'error', 'info', 'warning'], true) ? $f['tipo'] : 'info';
        $icone = $tipo === 'success' ? 'check' : ($tipo === 'error' ? 'x' : ($tipo === 'warning' ? 'warning' : 'book'));
        $html .= '<div class="alert alert-' . $tipo . '" role="status">' . lb_icon($icone)
            . '<span>' . e($f['msg']) . '</span><button type="button" class="alert-close" data-dismiss aria-label="Fechar">'
            . lb_icon('x') . '</button></div>';
    }
    return $html;
}

function lb_empty(string $icone, string $titulo, string $texto, string $ctaHtml = ''): string
{
    return '<div class="empty">' . lb_icon($icone, 'ico ico-lg')
        . '<h3>' . e($titulo) . '</h3><p>' . e($texto) . '</p>' . $ctaHtml . '</div>';
}

function lb_badge(string $texto, string $variante = 'neutral'): string
{
    return '<span class="badge badge-' . e($variante) . '">' . e($texto) . '</span>';
}

function lb_avatar(string $nome, string $variante = ''): string
{
    return '<span class="avatar ' . e($variante) . '">' . e(lb_iniciais($nome)) . '</span>';
}

function lb_nav_professor(): array
{
    return [
        ['dashboard', 'Dashboard', 'grid', 'professor/index.php'],
        ['alunos', 'Alunos', 'users', 'professor/alunos.php'],
        ['turmas', 'Turmas', 'class', 'professor/turmas.php'],
        ['leituras', 'Leituras', 'read', 'professor/leituras.php'],
        ['questionarios', 'Questionarios', 'quiz', 'professor/questionarios.php'],
        ['relatorios', 'Relatorios', 'report', 'professor/relatorios.php'],
        ['configuracoes', 'Configuracoes', 'settings', 'professor/configuracoes.php'],
    ];
}

function lb_nav_admin(): array
{
    return [
        ['dashboard', 'Visao geral', 'grid', 'admin/index.php'],
        ['escolas', 'Escolas', 'school', 'admin/escolas.php'],
        ['professores', 'Professores', 'users', 'admin/professores.php'],
        ['relatorios', 'Relatorios', 'report', 'professor/relatorios.php'],
    ];
}

function lb_shell_start(string $titulo, array $opcoes = []): void
{
    $u = lb_user();
    $aluno = lb_aluno();
    $contexto = $opcoes['contexto'] ?? ($u ? $u['papel'] : ($aluno ? 'aluno' : 'publico'));
    $ativo = $opcoes['ativo'] ?? basename($_SERVER['SCRIPT_NAME'] ?? '', '.php');
    $chart = !empty($opcoes['chartjs']);
    $corpoClasse = 'ctx-' . $contexto;
    ?>
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="theme-color" content="#0f766e">
<title><?= e($titulo) ?> · LER BEM</title>
<link rel="icon" href="<?= e(lb_url('assets/img/logo.svg')) ?>" type="image/svg+xml">
<link rel="stylesheet" href="<?= e(lb_url('assets/css/style.css')) ?>">
<?php if ($chart): ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js" defer></script>
<?php endif; ?>
<script src="<?= e(lb_url('assets/js/app.js')) ?>" defer></script>
</head>
<body class="<?= e($corpoClasse) ?>">
<?php if ($contexto === 'professor' || $contexto === 'admin'): ?>
<div class="app">
  <aside class="sidebar" id="sidebar">
    <div class="sidebar-top">
      <?= lb_logo() ?>
      <button class="sidebar-close" type="button" data-sidebar-close aria-label="Fechar menu"><?= lb_icon('x') ?></button>
    </div>
    <div class="school-chip">
      <?= lb_icon('school') ?>
      <div><strong><?= e($u['escola_nome'] ?? 'Administracao') ?></strong>
      <small><?= $contexto === 'admin' ? 'Administrador do sistema' : 'Painel do professor' ?></small></div>
    </div>
    <nav class="nav" aria-label="Menu principal">
      <?php foreach ($contexto === 'admin' ? lb_nav_admin() : lb_nav_professor() as [$id, $rotulo, $icone, $href]): ?>
        <a class="nav-link<?= $ativo === $id ? ' is-active' : '' ?>" href="<?= e(lb_url($href)) ?>">
          <?= lb_icon($icone) ?><span><?= e($rotulo) ?></span>
        </a>
      <?php endforeach; ?>
      <a class="nav-link nav-exit" href="<?= e(lb_url('logout.php')) ?>"><?= lb_icon('logout') ?><span>Sair</span></a>
    </nav>
    <div class="sidebar-foot">
      <?= lb_avatar($u['nome'] ?? 'LB') ?>
      <div class="who"><strong><?= e($u['nome'] ?? '') ?></strong><small><?= e($u['email'] ?? '') ?></small></div>
    </div>
  </aside>
  <div class="app-main">
    <header class="topbar">
      <button class="icon-btn only-mobile" type="button" data-sidebar-open aria-label="Abrir menu"><?= lb_icon('menu') ?></button>
      <div class="topbar-title"><h1><?= e($opcoes['titulo_pagina'] ?? $titulo) ?></h1>
        <?php if (!empty($opcoes['subtitulo'])): ?><p><?= e($opcoes['subtitulo']) ?></p><?php endif; ?>
      </div>
      <div class="topbar-actions"><?= $opcoes['acoes'] ?? '' ?></div>
    </header>
    <main class="content">
      <?= lb_flash_html() ?>
<?php elseif ($contexto === 'aluno'): ?>
<div class="aluno-app">
  <header class="aluno-header">
    <div class="aluno-header-inner">
      <?= lb_logo() ?>
      <div class="aluno-ident">
        <?= lb_avatar($aluno['nome'] ?? 'A') ?>
        <div><strong><?= e($aluno['nome'] ?? '') ?></strong>
          <small><?= e($aluno['turma_nome'] ?? 'Sem turma') ?> · <?= e($aluno['escola_nome'] ?? '') ?></small></div>
        <a class="btn btn-ghost btn-sm" href="<?= e(lb_url('aluno/sair.php')) ?>"><?= lb_icon('logout') ?>Sair</a>
      </div>
    </div>
    <nav class="aluno-nav">
      <?php foreach ([
          ['painel', 'Inicio', 'grid', 'aluno/painel.php'],
          ['leitura_nova', 'Nova leitura', 'plus', 'aluno/leitura_nova.php'],
          ['historico', 'Minhas leituras', 'read', 'aluno/historico.php'],
      ] as [$id, $rotulo, $icone, $href]): ?>
        <a class="aluno-nav-link<?= $ativo === $id ? ' is-active' : '' ?>" href="<?= e(lb_url($href)) ?>"><?= lb_icon($icone) ?><?= e($rotulo) ?></a>
      <?php endforeach; ?>
    </nav>
  </header>
  <main class="aluno-main">
    <?= lb_flash_html() ?>
<?php else: ?>
<main class="public-main">
  <?= lb_flash_html() ?>
<?php endif; ?>
<?php
}

function lb_shell_end(array $opcoes = []): void
{
    $contexto = $opcoes['contexto'] ?? (lb_user() ? (lb_user()['papel'] ?? 'professor') : (lb_aluno() ? 'aluno' : 'publico'));
    if ($contexto === 'professor' || $contexto === 'admin') {
        echo '</main></div></div><div class="sidebar-scrim" data-sidebar-close></div>';
    } elseif ($contexto === 'aluno') {
        echo '</main><footer class="aluno-footer">LER BEM · Leitura que transforma · '
            . e(lb_escola_atual()['nome'] ?? '') . '</footer></div>';
    } else {
        echo '</main>';
    }
    if (!empty($opcoes['scripts'])) {
        echo $opcoes['scripts'];
    }
    echo '</body></html>';
}

function lb_page(string $titulo, array $opcoes = []): void
{
    lb_shell_start($titulo, $opcoes);
}

function lb_select_turmas(array $turmas, ?int $selecionado = null, string $nome = 'turma_id', bool $vazio = true): string
{
    $html = '<select class="input" name="' . e($nome) . '" id="' . e($nome) . '"' . ($vazio ? '' : ' required') . '>';
    $html .= '<option value="">' . ($vazio ? 'Selecione a turma' : 'Sem turma') . '</option>';
    foreach ($turmas as $t) {
        $sel = $selecionado !== null && (int) $t['id'] === $selecionado ? ' selected' : '';
        $html .= '<option value="' . (int) $t['id'] . '"' . $sel . '>' . e($t['nome']) . ' · ' . (int) $t['ano_letivo'] . '</option>';
    }
    return $html . '</select>';
}
