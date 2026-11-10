<?php
declare(strict_types=1);

/**
 * Gerador de PDF simples (sem dependencias externas).
 * Suporta titulo, subtitulo, paragrafos, tabelas e multiplas paginas.
 */
final class LB_Pdf
{
    private const L = 40.0;
    private const R = 555.0;
    private const TOP = 800.0;
    private const BOTTOM = 60.0;

    private array $paginas = [];
    private string $atual = '';
    private float $y = self::TOP;
    private string $titulo;
    private string $subtitulo;

    public function __construct(string $titulo = 'LER BEM', string $subtitulo = '')
    {
        $this->titulo = $titulo;
        $this->subtitulo = $subtitulo;
        $this->novaPagina();
    }

    private function enc(string $s): string
    {
        $t = @iconv('UTF-8', 'Windows-1252//TRANSLIT', $s);
        if ($t === false) {
            $t = preg_replace('/[^\x20-\x7E]/', '?', $s) ?? $s;
        }
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $t);
    }

    private function texto(float $x, float $y, string $txt, float $tamanho = 9.0, bool $negrito = false): void
    {
        $this->atual .= sprintf(
            "BT /%s %.2f Tf %.2f %.2f Td (%s) Tj ET\n",
            $negrito ? 'F2' : 'F1',
            $tamanho,
            $x,
            $y,
            $this->enc($txt)
        );
    }

    private function linha(float $x1, float $y1, float $x2, float $y2, float $cinza = 0.82): void
    {
        $this->atual .= sprintf("%.2f G %.2f w %.2f %.2f m %.2f %.2f l S\n", $cinza, 0.6, $x1, $y1, $x2, $y2);
    }

    public function novaPagina(): void
    {
        if ($this->atual !== '') {
            $this->paginas[] = $this->atual;
        }
        $this->atual = "0.06 0.35 0.33 RG\n";
        $this->texto(self::L, self::TOP + 14, 'LER BEM', 8, true);
        $this->texto(self::L + 48, self::TOP + 14, 'Gestao de leitura escolar', 8);
        $this->texto(self::R - 120, self::TOP + 14, date('d/m/Y H:i'), 8);
        $this->linha(self::L, self::TOP + 6, self::R, self::TOP + 6);
        $this->y = self::TOP - 14;
        if (count($this->paginas) === 0) {
            $this->texto(self::L, $this->y, $this->titulo, 17, true);
            $this->y -= 18;
            if ($this->subtitulo !== '') {
                $this->texto(self::L, $this->y, $this->subtitulo, 10);
                $this->y -= 16;
            }
        }
    }

    private function garantir(float $altura): void
    {
        if ($this->y - $altura < self::BOTTOM) {
            $this->novaPagina();
        }
    }

    public function h2(string $texto): void
    {
        $this->garantir(30);
        $this->y -= 8;
        $this->texto(self::L, $this->y, $texto, 11.5, true);
        $this->y -= 6;
        $this->linha(self::L, $this->y, self::R, $this->y);
        $this->y -= 12;
    }

    public function p(string $texto): void
    {
        foreach ($this->quebrar($texto, 100) as $linha) {
            $this->garantir(14);
            $this->texto(self::L, $this->y, $linha, 9.5);
            $this->y -= 12;
        }
    }

    /**
     * @param array<int,string> $cabecalho
     * @param array<int,array<int,string>> $linhas
     * @param array<int,float>|null $larguras
     */
    public function tabela(array $cabecalho, array $linhas, ?array $larguras = null, string $vazio = 'Sem registros para os filtros informados.'): void
    {
        $colunas = count($cabecalho);
        $larguras ??= array_fill(0, $colunas, (self::R - self::L) / max(1, $colunas));

        $this->garantir(26);
        $x = self::L;
        foreach ($cabecalho as $i => $col) {
            $this->texto($x + 2, $this->y, mb_strtoupper($col), 8, true);
            $x += $larguras[$i];
        }
        $this->y -= 5;
        $this->linha(self::L, $this->y, self::R, $this->y, 0.6);
        $this->y -= 12;

        if (!$linhas) {
            $this->texto(self::L, $this->y, $vazio, 9);
            $this->y -= 14;
            return;
        }

        foreach ($linhas as $linha) {
            $this->garantir(15);
            $x = self::L;
            foreach (array_values($linha) as $i => $valor) {
                $larg = $larguras[$i] ?? 80;
                $txt = $this->quebrar((string) $valor, max(8, (int) ($larg / 4.4)))[0] ?? '';
                $this->texto($x + 2, $this->y, $txt, 8.6);
                $x += $larg;
            }
            $this->y -= 12;
            $this->linha(self::L, $this->y + 3, self::R, $this->y + 3, 0.9);
        }
    }

    private function quebrar(string $texto, int $limite): array
    {
        $palavras = preg_split('/\s+/', trim($texto)) ?: [];
        $linhas = [];
        $linha = '';
        foreach ($palavras as $p) {
            if ($linha === '') {
                $linha = $p;
            } elseif (mb_strlen($linha . ' ' . $p) <= $limite) {
                $linha .= ' ' . $p;
            } else {
                $linhas[] = $linha;
                $linha = $p;
            }
        }
        if ($linha !== '') {
            $linhas[] = $linha;
        }
        return $linhas ?: [''];
    }

    public function output(string $nomeArquivo = 'relatorio.pdf'): never
    {
        $this->paginas[] = $this->atual;

        $objetos = [];
        $n = count($this->paginas);
        $fontes = 3;
        $primeiraPagina = $fontes + 2;
        $kids = [];
        for ($i = 0; $i < $n; $i++) {
            $kids[] = ($primeiraPagina + $i * 2) . ' 0 R';
        }

        $objetos[1] = "<< /Type /Catalog /Pages 2 0 R >>";
        $objetos[2] = '<< /Type /Pages /Count ' . $n . ' /Kids [' . implode(' ', $kids) . '] >>';
        $objetos[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
        $objetos[4] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>';

        foreach ($this->paginas as $i => $conteudo) {
            $paginaObj = $primeiraPagina + $i * 2;
            $conteudoObj = $paginaObj + 1;
            $objetos[$paginaObj] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents ' . $conteudoObj . ' 0 R >>';
            $objetos[$conteudoObj] = "<< /Length " . strlen($conteudo) . " >>\nstream\n" . $conteudo . "endstream";
        }

        ksort($objetos);
        $pdf = "%PDF-1.4\n";
        $offsets = [];
        foreach ($objetos as $id => $corpo) {
            $offsets[$id] = strlen($pdf);
            $pdf .= $id . " 0 obj\n" . $corpo . "\nendobj\n";
        }
        $xref = strlen($pdf);
        $max = max(array_keys($objetos)) + 1;
        $pdf .= "xref\n0 " . $max . "\n0000000000 65535 f \n";
        for ($i = 1; $i < $max; $i++) {
            $pdf .= isset($offsets[$i]) ? sprintf("%010d 00000 n \n", $offsets[$i]) : "0000000000 65535 f \n";
        }
        $pdf .= "trailer\n<< /Size " . $max . " /Root 1 0 R >>\nstartxref\n" . $xref . "\n%%EOF";

        if (!headers_sent()) {
            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="' . str_replace('"', '', $nomeArquivo) . '"');
            header('Content-Length: ' . strlen($pdf));
            header('Cache-Control: private, max-age=0, must-revalidate');
            header('X-Content-Type-Options: nosniff');
        }
        echo $pdf;
        exit;
    }
}

function lb_csv_download(string $nomeArquivo, array $cabecalho, array $linhas): never
{
    if (!headers_sent()) {
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . str_replace('"', '', $nomeArquivo) . '"');
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, max-age=0, must-revalidate');
    }
    $out = fopen('php://output', 'wb');
    if ($out === false) {
        exit;
    }
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, $cabecalho, ';');
    foreach ($linhas as $linha) {
        fputcsv($out, array_values($linha), ';');
    }
    fclose($out);
    exit;
}
