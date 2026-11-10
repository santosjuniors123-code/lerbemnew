<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/pdf.php';

$u = require_professor();
$pdo = db();
$escolaId = (int) $u['escola_id'];

$de = gin('de');
$ate = gin('ate');
$turmaId = int_get('turma', 0) ?: null;
$alunoId = int_get('aluno', 0) ?: null;
$formato = gin('formato', 'csv') === 'pdf' ? 'pdf' : 'csv';

$where = ['l.escola_id = ?'];
$params = [$escolaId];
if ($de !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $de)) {
    $where[] = 'l.data_leitura >= ?';
    $params[] = $de;
}
if ($ate !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $ate)) {
    $where[] = 'l.data_leitura <= ?';
    $params[] = $ate;
}
if ($turmaId) {
    $where[] = 'l.turma_id = ?';
    $params[] = $turmaId;
}
if ($alunoId) {
    $where[] = 'l.aluno_id = ?';
    $params[] = $alunoId;
}
$whereSql = implode(' AND ', $where);

$st = $pdo->prepare("SELECT l.data_leitura, a.nome AS aluno, t.nome AS turma, l.titulo, l.autor, l.paginas,
        l.status, l.nota, q.titulo AS questionario
    FROM leituras l
    JOIN alunos a ON a.id = l.aluno_id
    LEFT JOIN turmas t ON t.id = l.turma_id
    LEFT JOIN questionarios q ON q.id = l.questionario_id
    WHERE $whereSql
    ORDER BY l.data_leitura ASC, a.nome ASC");
$st->execute($params);
$linhas = $st->fetchAll();

$st = $pdo->prepare("SELECT COUNT(*) AS leituras, COALESCE(SUM(l.paginas),0) AS paginas,
        COUNT(DISTINCT l.aluno_id) AS alunos, AVG(l.nota) AS media
    FROM leituras l WHERE $whereSql");
$st->execute($params);
$resumo = $st->fetch() ?: ['leituras' => 0, 'paginas' => 0, 'alunos' => 0, 'media' => null];

$escola = lb_escola_atual();
$rotulo = ['registrada' => 'Registrada', 'pendente_correcao' => 'Aguardando correcao', 'validada' => 'Validada'];
$periodo = ($de !== '' ? lb_date($de) : 'inicio') . ' a ' . ($ate !== '' ? lb_date($ate) : 'hoje');
$nomeBase = 'ler-bem-relatorio-' . date('Y-m-d');

if ($formato === 'csv') {
    $cabecalho = ['Data', 'Aluno', 'Turma', 'Livro', 'Autor', 'Paginas', 'Questionario', 'Nota', 'Situacao'];
    $dados = [];
    foreach ($linhas as $l) {
        $dados[] = [
            lb_date($l['data_leitura']),
            $l['aluno'],
            $l['turma'] ?? '',
            $l['titulo'],
            $l['autor'] ?? '',
            (int) $l['paginas'],
            $l['questionario'] ?? '',
            $l['nota'] !== null ? number_format((float) $l['nota'], 1, ',', '') : '',
            $rotulo[$l['status']] ?? $l['status'],
        ];
    }
    lb_csv_download($nomeBase . '.csv', $cabecalho, $dados);
}

$pdf = new LB_Pdf('LER BEM', 'Relatorio de leituras');
$pdf->h2('Resumo do periodo');
$pdf->p('Escola: ' . ($escola['nome'] ?? '—'));
$pdf->p('Periodo: ' . $periodo);
$pdf->p('Leituras: ' . (int) $resumo['leituras'] . '  |  Paginas: ' . (int) $resumo['paginas']
    . '  |  Alunos participantes: ' . (int) $resumo['alunos']
    . '  |  Media: ' . ($resumo['media'] !== null ? number_format((float) $resumo['media'], 1, ',', '') : '—'));
$pdf->h2('Leituras registradas');
$pdf->tabela(
    ['Data', 'Aluno', 'Turma', 'Livro', 'Pag.', 'Nota', 'Situacao'],
    array_map(static function (array $l) use ($rotulo): array {
        return [
            lb_date($l['data_leitura']),
            $l['aluno'],
            $l['turma'] ?? '—',
            $l['titulo'],
            (string) (int) $l['paginas'],
            $l['nota'] !== null ? number_format((float) $l['nota'], 1, ',', '') : '—',
            $rotulo[$l['status']] ?? $l['status'],
        ];
    }, $linhas),
    [16, 30, 16, 34, 8, 10, 22]
);
$pdf->output($nomeBase . '.pdf');
