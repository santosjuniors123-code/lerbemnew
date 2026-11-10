<?php
declare(strict_types=1);

/**
 * Modulo de IA para geracao de perguntas.
 * Configuravel por variaveis de ambiente: AI_API_KEY, AI_API_URL, AI_MODEL, AI_TIMEOUT.
 * Sem chave configurada o sistema continua funcionando (criacao manual de perguntas).
 */

function lb_ai_disponivel(): bool
{
    return trim((string) lb_config('ai.api_key', '')) !== '' && function_exists('curl_init');
}

function lb_ai_info(): array
{
    return [
        'disponivel' => lb_ai_disponivel(),
        'modelo'     => (string) lb_config('ai.model', ''),
        'endpoint'   => (string) lb_config('ai.api_url', ''),
    ];
}

function lb_ai_prompt(string $livro, string $conteudo, int $qtd, string $nivel): array
{
    $niveis = ['facil' => 'facil (compreensao literal)', 'medio' => 'medio (interpretacao)', 'dificil' => 'dificil (reflexao e inferencia)'];
    $nivelTxt = $niveis[$nivel] ?? $niveis['medio'];

    $sistema = 'Voce e um professor de lingua portuguesa especialista em avaliacao de leitura. '
        . 'Gere perguntas usando EXCLUSIVAMENTE as informacoes do conteudo fornecido pelo professor. '
        . 'Nunca invente personagens, fatos, capitulos ou detalhes que nao estejam no texto. '
        . 'Se o conteudo for insuficiente para gerar a quantidade pedida, gere menos perguntas. '
        . 'Responda somente com JSON valido, sem comentarios e sem markdown.';

    $usuario = "Livro: {$livro}\n"
        . "Nivel de dificuldade desejado: {$nivelTxt}\n"
        . "Quantidade de perguntas: {$qtd}\n\n"
        . "Conteudo de referencia (unica fonte de verdade):\n\"\"\"\n{$conteudo}\n\"\"\"\n\n"
        . "Formato de resposta obrigatorio:\n"
        . '{"perguntas":[{"enunciado":"texto da pergunta","tipo":"multipla|vf|aberta",'
        . '"alternativas":[{"texto":"alternativa","correta":true}],'
        . '"explicacao":"justificativa baseada no conteudo"}]}' . "\n"
        . 'Regras: multipla tem 4 alternativas com exatamente uma correta; '
        . 'vf tem exatamente 2 alternativas ("Verdadeiro" e "Falso"); '
        . 'aberta nao tem alternativas. Misture interpretacao, compreensao, personagens, acontecimentos e reflexao. '
        . 'Escreva em portugues do Brasil.';

    return [
        ['role' => 'system', 'content' => $sistema],
        ['role' => 'user', 'content' => $usuario],
    ];
}

/**
 * @return array{ok:bool, perguntas:array, erro:string, modelo:string, bruto:string}
 */
function lb_ai_gerar(string $livro, string $conteudo, int $qtd, string $nivel): array
{
    $base = ['ok' => false, 'perguntas' => [], 'erro' => '', 'modelo' => (string) lb_config('ai.model', ''), 'bruto' => ''];

    if (!lb_ai_disponivel()) {
        $base['erro'] = 'Modulo de IA nao configurado. Defina a variavel de ambiente AI_API_KEY para habilitar a geracao automatica.';
        return $base;
    }
    $livro = trim($livro);
    $conteudo = trim($conteudo);
    if (mb_strlen($livro) < 2) {
        $base['erro'] = 'Informe o titulo do livro.';
        return $base;
    }
    if (mb_strlen($conteudo) < 120) {
        $base['erro'] = 'Cole um trecho maior do conteudo (minimo 120 caracteres) para que as perguntas tenham relacao com o texto.';
        return $base;
    }
    $qtd = max(1, min(20, $qtd));
    if (!in_array($nivel, ['facil', 'medio', 'dificil'], true)) {
        $nivel = 'medio';
    }

    $payload = [
        'model'       => (string) lb_config('ai.model', 'gpt-4o-mini'),
        'messages'    => lb_ai_prompt($livro, $conteudo, $qtd, $nivel),
        'temperature' => 0.4,
    ];

    $ch = curl_init((string) lb_config('ai.api_url'));
    if ($ch === false) {
        $base['erro'] = 'Nao foi possivel iniciar a chamada HTTP do modulo de IA.';
        return $base;
    }
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => (int) lb_config('ai.timeout', 45),
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . (string) lb_config('ai.api_key'),
        ],
        CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
    ]);
    $resposta = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $erroCurl = curl_error($ch);
    curl_close($ch);

    if ($resposta === false) {
        $base['erro'] = 'Falha de rede ao consultar a IA: ' . $erroCurl;
        return $base;
    }
    if ($status < 200 || $status >= 300) {
        $base['erro'] = 'A API de IA respondeu com status ' . $status . '. Confira AI_API_KEY, AI_MODEL e AI_API_URL.';
        return $base;
    }

    $json = json_decode((string) $resposta, true);
    $texto = $json['choices'][0]['message']['content'] ?? '';
    if (!is_string($texto) || trim($texto) === '') {
        $base['erro'] = 'A IA retornou uma resposta vazia. Tente novamente.';
        return $base;
    }
    $base['bruto'] = $texto;

    $inicio = strpos($texto, '{');
    $fim = strrpos($texto, '}');
    if ($inicio === false || $fim === false || $fim <= $inicio) {
        $base['erro'] = 'A resposta da IA nao esta em JSON valido. Tente novamente.';
        return $base;
    }
    $dados = json_decode(substr($texto, $inicio, $fim - $inicio + 1), true);
    if (!is_array($dados) || !isset($dados['perguntas']) || !is_array($dados['perguntas'])) {
        $base['erro'] = 'Formato inesperado na resposta da IA. Tente novamente.';
        return $base;
    }

    $perguntas = [];
    foreach ($dados['perguntas'] as $item) {
        $norm = lb_ai_normalizar_pergunta($item);
        if ($norm !== null) {
            $perguntas[] = $norm;
        }
        if (count($perguntas) >= $qtd) {
            break;
        }
    }
    if (!$perguntas) {
        $base['erro'] = 'Nenhuma pergunta valida foi gerada. Ajuste o conteudo e tente novamente.';
        return $base;
    }
    $base['ok'] = true;
    $base['perguntas'] = $perguntas;
    return $base;
}

function lb_ai_normalizar_pergunta(mixed $item): ?array
{
    if (!is_array($item)) {
        return null;
    }
    $enunciado = trim((string) ($item['enunciado'] ?? ''));
    if (mb_strlen($enunciado) < 10) {
        return null;
    }
    $tipo = in_array($item['tipo'] ?? '', ['multipla', 'vf', 'aberta'], true) ? $item['tipo'] : 'multipla';
    $explicacao = trim((string) ($item['explicacao'] ?? ''));
    $alternativas = [];

    if ($tipo === 'aberta') {
        return ['enunciado' => $enunciado, 'tipo' => 'aberta', 'explicacao' => $explicacao, 'alternativas' => []];
    }

    if ($tipo === 'vf') {
        $valor = $item['alternativas'][0]['correta'] ?? null;
        if (is_array($item['alternativas'] ?? null)) {
            foreach ($item['alternativas'] as $a) {
                if (is_array($a) && !empty($a['correta'])) {
                    $valor = $a['correta'];
                    break;
                }
            }
        }
        $textoCorreto = (string) ($item['resposta'] ?? '');
        $corretoEhVerdadeiro = $valor === true || $valor === 1 || $valor === 'true' || stripos($textoCorreto, 'verdade') !== false;
        return [
            'enunciado'    => $enunciado,
            'tipo'         => 'vf',
            'explicacao'   => $explicacao,
            'alternativas' => [
                ['texto' => 'Verdadeiro', 'correta' => $corretoEhVerdadeiro],
                ['texto' => 'Falso', 'correta' => !$corretoEhVerdadeiro],
            ],
        ];
    }

    $lista = is_array($item['alternativas'] ?? null) ? $item['alternativas'] : [];
    foreach ($lista as $a) {
        if (!is_array($a)) {
            continue;
        }
        $texto = trim((string) ($a['texto'] ?? ''));
        if ($texto === '') {
            continue;
        }
        $alternativas[] = ['texto' => mb_substr($texto, 0, 250), 'correta' => !empty($a['correta'])];
    }
    if (count($alternativas) < 2) {
        return null;
    }
    $alternativas = array_slice($alternativas, 0, 5);
    if (!array_filter($alternativas, static fn ($a) => $a['correta'])) {
        $alternativas[0]['correta'] = true;
    }
    $primeira = null;
    foreach ($alternativas as $i => $a) {
        if ($a['correta']) {
            if ($primeira !== null) {
                $alternativas[$i]['correta'] = false;
            } else {
                $primeira = $i;
            }
        }
    }
    return ['enunciado' => $enunciado, 'tipo' => 'multipla', 'explicacao' => $explicacao, 'alternativas' => $alternativas];
}
