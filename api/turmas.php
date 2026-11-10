<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    lb_json(['ok' => false, 'erro' => 'Metodo nao permitido.'], 405);
}

$escolaId = int_get('escola_id', 0);
if (!$escolaId) {
    lb_json(['ok' => false, 'erro' => 'Informe a escola.', 'turmas' => []], 422);
}

$st = db()->prepare(
    'SELECT t.id, t.nome, t.ano_letivo, t.turno, t.serie
     FROM turmas t JOIN escolas e ON e.id = t.escola_id AND e.ativo = 1
     WHERE t.escola_id = ? AND t.ativo = 1
     ORDER BY t.nome'
);
$st->execute([$escolaId]);
$turmas = array_map(static fn ($t) => [
    'id'         => (int) $t['id'],
    'nome'       => (string) $t['nome'],
    'ano_letivo' => (int) $t['ano_letivo'],
    'turno'      => (string) $t['turno'],
    'serie'      => (string) ($t['serie'] ?? ''),
], $st->fetchAll());

lb_json(['ok' => true, 'turmas' => $turmas]);
