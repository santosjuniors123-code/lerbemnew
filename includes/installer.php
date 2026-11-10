<?php
declare(strict_types=1);

/**
 * Instalador: criacao do esquema, dados iniciais e arquivo de configuracao.
 */

function lb_sql_statements(string $sql): array
{
    $sql = preg_replace('/^\s*--.*$/m', '', $sql) ?? $sql;
    $partes = preg_split('/;\s*(?:\r?\n|$)/', $sql) ?: [];
    return array_values(array_filter(array_map('trim', $partes), static fn ($p) => $p !== ''));
}

function lb_install_schema(PDO $pdo): int
{
    $arquivo = LB_ROOT . '/database/schema.sql';
    if (!is_file($arquivo)) {
        throw new RuntimeException('Arquivo database/schema.sql nao encontrado.');
    }
    $sql = (string) file_get_contents($arquivo);
    $total = 0;
    foreach (lb_sql_statements($sql) as $stmt) {
        $pdo->exec($stmt);
        $total++;
    }
    return $total;
}

function lb_pdo_connect(array $c, ?string $dbname = null): PDO
{
    $dsn = sprintf(
        'mysql:host=%s;port=%s%s;charset=utf8mb4',
        $c['host'],
        $c['port'],
        $dbname ? ';dbname=' . $dbname : ''
    );
    return new PDO($dsn, (string) $c['user'], (string) $c['pass'], [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
}

/**
 * Cria escola + administrador (+ professor opcional) e configuracoes iniciais.
 * Retorna dados dos registros criados.
 */
function lb_install_seed(PDO $pdo, array $d): array
{
    $pdo->beginTransaction();
    try {
        $slugBase = lb_slug((string) $d['escola_nome']);
        $slug = $slugBase;
        $i = 1;
        $st = $pdo->prepare('SELECT COUNT(*) FROM escolas WHERE slug = ?');
        while (true) {
            $st->execute([$slug]);
            if (!(int) $st->fetchColumn()) {
                break;
            }
            $slug = $slugBase . '-' . (++$i);
        }

        $st = $pdo->prepare('INSERT INTO escolas (nome, slug, cidade, uf, email, ativo) VALUES (?, ?, ?, ?, ?, 1)');
        $st->execute([
            $d['escola_nome'],
            $slug,
            $d['escola_cidade'] ?? null,
            $d['escola_uf'] ?? null,
            $d['escola_email'] ?? null,
        ]);
        $escolaId = (int) $pdo->lastInsertId();

        $st = $pdo->prepare('INSERT INTO usuarios (escola_id, nome, email, senha_hash, papel, ativo) VALUES (?, ?, ?, ?, ?, 1)');
        $st->execute([
            $escolaId,
            $d['admin_nome'],
            $d['admin_email'],
            password_hash((string) $d['admin_senha'], PASSWORD_DEFAULT),
            'admin',
        ]);
        $adminId = (int) $pdo->lastInsertId();

        $professorId = null;
        if (!empty($d['professor_nome']) && !empty($d['professor_email']) && !empty($d['professor_senha'])) {
            $st = $pdo->prepare('INSERT INTO usuarios (escola_id, nome, email, senha_hash, papel, ativo) VALUES (?, ?, ?, ?, ?, 1)');
            $st->execute([
                $escolaId,
                $d['professor_nome'],
                $d['professor_email'],
                password_hash((string) $d['professor_senha'], PASSWORD_DEFAULT),
                'professor',
            ]);
            $profUser = (int) $pdo->lastInsertId();
            $st = $pdo->prepare('INSERT INTO professores (escola_id, usuario_id, nome, email, disciplina, ativo) VALUES (?, ?, ?, ?, ?, 1)');
            $st->execute([$escolaId, $profUser, $d['professor_nome'], $d['professor_email'], $d['professor_disciplina'] ?? null]);
            $professorId = (int) $pdo->lastInsertId();
        }

        $cfg = [
            'nome_exibicao'         => (string) $d['escola_nome'],
            'meta_leitura_mensal'   => '4',
            'dias_para_acompanhar'  => '30',
            'permitir_autoidentificacao' => '1',
            'ia_habilitada'         => '1',
            'mensagem_aluno'        => 'Leia um livro, escreva com suas palavras o que entendeu e compartilhe com sua turma.',
        ];
        $st = $pdo->prepare('INSERT INTO configuracoes (escola_id, chave, valor) VALUES (?, ?, ?)');
        foreach ($cfg as $k => $v) {
            $st->execute([$escolaId, $k, $v]);
        }

        $pdo->commit();
        return [
            'escola_id'     => $escolaId,
            'admin_id'      => $adminId,
            'professor_id'  => $professorId,
            'slug'          => $slug,
            'turmas'        => 0,
        ];
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function lb_write_config(array $c): bool
{
    $conteudo = "<?php\n// Gerado pelo instalador do LER BEM em " . date('c') . "\n// Nao versionar este arquivo.\nreturn [\n";
    foreach ($c as $k => $v) {
        $conteudo .= '    ' . var_export((string) $k, true) . ' => ' . var_export((string) $v, true) . ",\n";
    }
    $conteudo .= "];\n";
    $destino = LB_ROOT . '/config/config.local.php';
    if (@file_put_contents($destino, $conteudo, LOCK_EX) === false) {
        return false;
    }
    @chmod($destino, 0640);
    return true;
}

function lb_turmas_padrao(): array
{
    return [
        ['6º ano A', 6, 'manha'],
        ['7º ano A', 7, 'manha'],
        ['8º ano A', 8, 'tarde'],
        ['9º ano A', 9, 'tarde'],
    ];
}

function lb_criar_turmas(PDO $pdo, int $escolaId, array $turmas): int
{
    $ano = (int) date('Y');
    $st = $pdo->prepare('INSERT IGNORE INTO turmas (escola_id, nome, ano_letivo, turno, serie, ativo) VALUES (?, ?, ?, ?, ?, 1)');
    $n = 0;
    foreach ($turmas as $t) {
        [$nome, $serie, $turno] = $t;
        $st->execute([$escolaId, $nome, $ano, $turno, (string) $serie]);
        $n += $st->rowCount();
    }
    return $n;
}
