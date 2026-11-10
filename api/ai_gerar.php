<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/ai.php';

$u = require_professor();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    lb_json(['ok' => false, 'erro' => 'Metodo nao permitido.'], 405);
}
csrf_verify();

$livro = inp('livro');
$conteudo = inp('conteudo');
$qtd = int_inp('quantidade', 5) ?? 5;
$nivel = inp('nivel', 'medio');

if (mb_strlen($conteudo) > 30000) {
    $conteudo = mb_substr($conteudo, 0, 30000);
}

$r = lb_ai_gerar($livro, $conteudo, $qtd, $nivel);
if (!$r['ok']) {
    lb_json(['ok' => false, 'erro' => $r['erro']], 422);
}
lb_json(['ok' => true, 'perguntas' => $r['perguntas'], 'modelo' => $r['modelo']]);
