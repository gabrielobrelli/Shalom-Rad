<?php
/**
 * ============================================================================
 * SimplePdfWriter — Gerador mínimo de arquivos .pdf reais, sem depender de
 * bibliotecas externas (nem Composer/mPDF/Dompdf/TCPDF).
 * ============================================================================
 * Motivo de existir: hospedagens compartilhadas gratuitas como a InfinityFree
 * nem sempre permitem instalar dependências via Composer. Esta classe escreve
 * o arquivo PDF "na mão", seguindo o formato oficial do PDF (mesma ideia da
 * classe SimpleXlsxWriter para Excel), usando apenas as fontes padrão do PDF
 * (Helvetica), que todo leitor de PDF já sabe exibir sem precisar embutir
 * nenhum arquivo de fonte.
 *
 * Como usar (fluxo típico de uma página de exportação):
 *   $pdf = new SimplePdfWriter('Título do documento');
 *   $pdf->addInfo('Cliente', 'Auto Peças Bandeirantes');
 *   $pdf->addInfo('Status', 'Em andamento');
 *   $pdf->addTabela(
 *       ['Modelo/Nome', 'Quantidade', 'Situação'],   // cabeçalhos
 *       [[ 'Onix', '3', 'Urgente' ], [ 'HB20', '1', 'Normal' ]], // linhas
 *       [0.5, 0.25, 0.25]                              // largura de cada coluna (proporção)
 *   );
 *   $pdf->addTotais(['Total solicitado: 4 peças']);
 *   $pdf->download('pedido.pdf');
 *
 * Limitações conscientes (aceitáveis para o tamanho das listas deste
 * sistema): não quebra texto muito longo em várias linhas dentro da célula,
 * e gera sempre uma única página, cuja altura se ajusta automaticamente à
 * quantidade de conteúdo (em vez de paginar). Para as listas de pedidos e de
 * seminovos, isso é suficiente e evita uma lógica de paginação complexa.
 */
class SimplePdfWriter
{
    // Paleta usada no PDF (mesmos tons de azul/laranja do site), em RGB 0–1.
    private const COR_AZUL   = [0.05, 0.22, 0.42];
    private const COR_LARANJA = [0.86, 0.38, 0.04];
    private const COR_TEXTO  = [0.09, 0.13, 0.17];
    private const COR_VERDE  = [0.11, 0.48, 0.24];
    private const COR_CINZA  = [0.55, 0.6, 0.66];
    private const COR_LINHA  = [0.82, 0.85, 0.88];
    private const COR_BRANCO = [1, 1, 1];
    private const COR_FUNDO_ALT = [0.96, 0.97, 0.98];

    private float $largura = 595.0; // largura A4 em pontos
    private float $margem = 36.0;
    private float $cursorY = 0.0;   // distância do topo até o cursor atual (cresce para baixo)
    private array $instrucoes = []; // instruções de desenho acumuladas (ver métodos abaixo)

    public function __construct(string $titulo, string $subtitulo = '')
    {
        $this->cursorY = $this->margem;
        $this->texto($this->margem, 22, $titulo, 17, true, self::COR_AZUL);
        $this->cursorY += 24;
        if ($subtitulo !== '') {
            $this->texto($this->margem, 14, $subtitulo, 10, false, self::COR_CINZA);
            $this->cursorY += 16;
        }
        // Linha divisória laranja logo abaixo do título (assinatura visual do sistema).
        $this->linha($this->margem, 6, $this->largura - $this->margem, 6, self::COR_LARANJA, 2);
        $this->cursorY += 14;
    }

    /** Adiciona uma linha "Rótulo: valor" (ex.: Cliente, Status, Datas). */
    public function addInfo(string $rotulo, string $valor): void
    {
        $this->texto($this->margem, 13, $rotulo . ':', 10, true, self::COR_TEXTO);
        $this->texto($this->margem + 110, 13, $valor, 10, false, self::COR_TEXTO);
        $this->cursorY += 15;
    }

    /** Adiciona um espaço vertical em branco. */
    public function addEspaco(float $altura = 10): void
    {
        $this->cursorY += $altura;
    }

    /** Adiciona um título de seção dentro do mesmo documento (ex.: "Serviços do dia"). */
    public function addSubtitulo(string $texto): void
    {
        $this->cursorY += 8;
        $this->texto($this->margem, 14, $texto, 12, true, self::COR_AZUL);
        $this->cursorY += 4;
        $this->linha($this->margem, 6, $this->margem + 60, 6, self::COR_LARANJA, 1.5);
        $this->cursorY += 12;
    }

    /** Adiciona um parágrafo de texto corrido, quebrando linhas automaticamente. */
    public function addParagrafo(string $texto, float $tamanho = 10): void
    {
        $larguraUtil = $this->largura - (2 * $this->margem);
        $maxCaracteres = max(20, (int)($larguraUtil / ($tamanho * 0.52)));
        foreach ($this->quebrarLinhas($texto, $maxCaracteres) as $linha) {
            $this->texto($this->margem, 13, $linha, $tamanho, false, self::COR_TEXTO);
            $this->cursorY += 14;
        }
        $this->cursorY += 4;
    }

    /** Quebra um texto longo em linhas de até $maxCaracteres, respeitando palavras inteiras. */
    private function quebrarLinhas(string $texto, int $maxCaracteres): array
    {
        $palavras = preg_split('/\s+/', trim($texto)) ?: [];
        $linhas = [];
        $atual = '';
        foreach ($palavras as $palavra) {
            $tentativa = $atual === '' ? $palavra : $atual . ' ' . $palavra;
            if (mb_strlen($tentativa) > $maxCaracteres && $atual !== '') {
                $linhas[] = $atual;
                $atual = $palavra;
            } else {
                $atual = $tentativa;
            }
        }
        if ($atual !== '') {
            $linhas[] = $atual;
        }
        return $linhas;
    }

    /**
     * Desenha uma tabela simples com cabeçalho colorido (fundo azul, texto
     * branco) e linhas de dados com fundo alternado, facilitando a leitura.
     *
     * @param string[] $cabecalhos     Títulos das colunas.
     * @param string[][] $linhas       Cada item é um array de células (strings) na mesma ordem dos cabeçalhos.
     * @param float[] $proporcoes      Proporção da largura de cada coluna (soma pode ser qualquer valor; é normalizada).
     */
    /**
     * Desenha uma tabela simples. $destaques (opcional) permite colorir
     * células específicas: cada item é ['linha' => i, 'coluna' => j, 'cor' => 'verde'|'laranja'],
     * usado por exemplo para destacar a coluna de status "Pronto" em verde.
     */
    public function addTabela(array $cabecalhos, array $linhas, array $proporcoes, array $destaques = []): void
    {
        $mapaDestaques = [];
        foreach ($destaques as $d) {
            $mapaDestaques[$d['linha'] . ':' . $d['coluna']] = $d['cor'];
        }
        $coresPorNome = ['verde' => self::COR_VERDE, 'laranja' => self::COR_LARANJA, 'cinza' => self::COR_CINZA];

        $larguraUtil = $this->largura - (2 * $this->margem);
        $somaProporcoes = array_sum($proporcoes) ?: 1;
        $largurasColunas = array_map(fn($p) => ($p / $somaProporcoes) * $larguraUtil, $proporcoes);

        $alturaLinha = 20;

        // --- Cabeçalho ---
        $this->retangulo($this->margem, $alturaLinha, $larguraUtil, $alturaLinha, self::COR_AZUL, null);
        $x = $this->margem;
        foreach ($cabecalhos as $i => $titulo) {
            $this->texto($x + 5, $alturaLinha - 6, $titulo, 9, true, self::COR_BRANCO);
            $x += $largurasColunas[$i];
        }
        $this->cursorY += $alturaLinha;

        // --- Linhas de dados ---
        foreach ($linhas as $indiceLinha => $celulas) {
            $corFundo = ($indiceLinha % 2 === 1) ? self::COR_FUNDO_ALT : null;
            if ($corFundo) {
                $this->retangulo($this->margem, $alturaLinha, $larguraUtil, $alturaLinha, $corFundo, null);
            }
            $x = $this->margem;
            foreach ($celulas as $i => $valor) {
                $chave = $indiceLinha . ':' . $i;
                $corCelula = isset($mapaDestaques[$chave]) ? ($coresPorNome[$mapaDestaques[$chave]] ?? self::COR_TEXTO) : self::COR_TEXTO;
                $negritoCelula = isset($mapaDestaques[$chave]);
                $this->texto($x + 5, $alturaLinha - 6, (string)$valor, 9, $negritoCelula, $corCelula);
                $x += $largurasColunas[$i] ?? 60;
            }
            $this->linha($this->margem, $alturaLinha, $this->largura - $this->margem, $alturaLinha, self::COR_LINHA, 0.5);
            $this->cursorY += $alturaLinha;
        }

        // Moldura externa da tabela.
        $alturaTotalTabela = $alturaLinha * (count($linhas) + 1);
        $this->retangulo($this->margem, $alturaTotalTabela, $larguraUtil, $alturaTotalTabela, null, self::COR_LINHA, true);
        $this->cursorY += 10;
    }

    /** Adiciona uma ou mais linhas de totais em destaque (negrito, cor laranja). */
    public function addTotais(array $linhasTotais): void
    {
        $this->cursorY += 6;
        foreach ($linhasTotais as $linha) {
            $this->texto($this->margem, 15, $linha, 12, true, self::COR_LARANJA);
            $this->cursorY += 17;
        }
    }

    /** Adiciona o texto de rodapé (nota final do documento). */
    public function addRodape(string $texto): void
    {
        $this->cursorY += 16;
        $this->linha($this->margem, 0, $this->largura - $this->margem, 0, self::COR_LINHA, 0.5);
        $this->cursorY += 12;
        $this->texto($this->margem, 10, $texto, 8, false, self::COR_CINZA);
    }

    /**
     * Desenha uma grade de "cartões" de resumo (número grande + rótulo
     * pequeno) — muito mais fácil de ler rapidamente do que uma lista de
     * frases. $cartoes é uma lista de ['rotulo' => ..., 'valor' => ...].
     * Até 4 cartões por linha.
     */
    public function addCartoesResumo(array $cartoes): void
    {
        $larguraUtil = $this->largura - (2 * $this->margem);
        $porLinha = min(4, max(1, count($cartoes)));
        $gap = 10;
        $larguraCartao = ($larguraUtil - ($gap * ($porLinha - 1))) / $porLinha;
        $alturaCartao = 48;

        foreach (array_chunk($cartoes, $porLinha) as $linhaCartoes) {
            foreach ($linhaCartoes as $i => $c) {
                $x = $this->margem + $i * ($larguraCartao + $gap);
                $this->retangulo($x, $alturaCartao, $larguraCartao, $alturaCartao, self::COR_FUNDO_ALT, self::COR_LINHA);
                $this->linha($x, 0, $x, $alturaCartao, self::COR_LARANJA, 3);
                $this->texto($x + 10, 16, mb_strtoupper($c['rotulo']), 7.5, false, self::COR_CINZA);
                $this->texto($x + 10, 36, (string)$c['valor'], 14, true, self::COR_AZUL);
            }
            $this->cursorY += $alturaCartao + $gap;
        }
        $this->cursorY += 2;
    }

    /**
     * Desenha um gráfico de barras simples a partir de uma lista de
     * períodos no formato usado por construir_periodos_comparacao()
     * (cada item com 'label' e 'total'). Usado na exportação em PDF do
     * gráfico comparativo — mesma lógica visual da versão em SVG da tela,
     * só que desenhada diretamente com os primitivos do PDF.
     */
    public function addGraficoBarras(array $periodos, float $altura = 200): void
    {
        if (empty($periodos)) return;

        $larguraUtil = $this->largura - (2 * $this->margem);
        $maior = max(array_column($periodos, 'total')) ?: 1;
        $qtd = count($periodos);
        $espaco = $larguraUtil / $qtd;
        $larguraBarra = max(4, min(40, $espaco * 0.6));

        $topoBloco = $this->cursorY; // topo da área reservada pro gráfico

        // Linha de base do gráfico.
        $this->linha($this->margem, $altura, $this->largura - $this->margem, $altura, self::COR_LINHA, 0.75);

        foreach ($periodos as $i => $p) {
            $alturaBarra = $maior > 0 ? ($p['total'] / $maior) * ($altura - 24) : 0;
            $x = $this->margem + $i * $espaco + ($espaco - $larguraBarra) / 2;
            $corBarra = $p['total'] > 0 ? self::COR_LARANJA : self::COR_LINHA;

            // retangulo() sempre desenha a partir do cursor atual — por
            // isso movemos o cursor pro topo exato desta barra antes de
            // chamar, e devolvemos ao topo do bloco logo em seguida.
            $this->cursorY = $topoBloco + ($altura - $alturaBarra);
            $this->retangulo($x, 0, $larguraBarra, $alturaBarra, $corBarra, null);

            $this->cursorY = $topoBloco;
            if ($p['total'] > 0) {
                $this->texto($x - 4, $altura - $alturaBarra - 4, number_format($p['total'], 0, ',', '.'), 7, true, self::COR_AZUL);
            }
            $this->texto($x - 4, $altura + 12, (string)$p['label'], 7, false, self::COR_CINZA);
        }

        $this->cursorY = $topoBloco + $altura + 24;
    }

    // ------------------------------------------------------------------
    // Métodos internos de baixo nível: apenas acumulam instruções de
    // desenho com posição relativa ao TOPO da página (mais simples de
    // raciocinar). A conversão para o sistema de coordenadas do PDF
    // (origem no canto inferior esquerdo) só acontece em montarConteudo(),
    // quando já sabemos a altura total da página.
    // ------------------------------------------------------------------

    private function texto(float $x, float $yLinhaAtual, string $conteudo, float $tamanho, bool $negrito, array $cor): void
    {
        $this->instrucoes[] = [
            'tipo' => 'texto',
            'x' => $x,
            'y' => $this->cursorY + $yLinhaAtual,
            'texto' => $conteudo,
            'tamanho' => $tamanho,
            'negrito' => $negrito,
            'cor' => $cor,
        ];
    }

    private function linha(float $x1, float $yOffset, float $x2, float $yOffset2, array $cor, float $espessura): void
    {
        $this->instrucoes[] = [
            'tipo' => 'linha',
            'x1' => $x1, 'y1' => $this->cursorY + $yOffset,
            'x2' => $x2, 'y2' => $this->cursorY + $yOffset2,
            'cor' => $cor, 'espessura' => $espessura,
        ];
    }

    private function retangulo(float $x, float $yInferiorOffset, float $largura, float $alturaAcima, ?array $corFundo, ?array $corBorda, bool $apenasContorno = false): void
    {
        $this->instrucoes[] = [
            'tipo' => 'retangulo',
            'x' => $x,
            'yTopo' => $this->cursorY, // topo do retângulo = cursor antes de avançar
            'largura' => $largura,
            'altura' => $alturaAcima,
            'corFundo' => $apenasContorno ? null : $corFundo,
            'corBorda' => $corBorda,
        ];
    }

    /** Converte texto UTF-8 para a codificação Windows-1252 usada pelas fontes padrão do PDF. */
    private function paraWinAnsi(string $texto): string
    {
        $convertido = @iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $texto);
        return $convertido !== false ? $convertido : $texto;
    }

    /** Escapa parênteses e barras invertidas exigidos pela sintaxe de strings do PDF. */
    private function escaparPdf(string $texto): string
    {
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $texto);
    }

    private function corPdf(array $rgb): string
    {
        return sprintf('%.3F %.3F %.3F', $rgb[0], $rgb[1], $rgb[2]);
    }

    /** Monta o stream de conteúdo (operadores PDF) a partir das instruções acumuladas. */
    private function montarConteudo(float $alturaPagina): string
    {
        $ops = [];
        foreach ($this->instrucoes as $inst) {
            if ($inst['tipo'] === 'texto') {
                $yPdf = $alturaPagina - $inst['y'];
                $fonte = $inst['negrito'] ? '/F2' : '/F1';
                $texto = $this->escaparPdf($this->paraWinAnsi($inst['texto']));
                $ops[] = 'BT';
                $ops[] = $this->corPdf($inst['cor']) . ' rg';
                $ops[] = sprintf('%s %.1F Tf', $fonte, $inst['tamanho']);
                $ops[] = sprintf('%.2F %.2F Td', $inst['x'], $yPdf);
                $ops[] = '(' . $texto . ') Tj';
                $ops[] = 'ET';
            } elseif ($inst['tipo'] === 'linha') {
                $y1 = $alturaPagina - $inst['y1'];
                $y2 = $alturaPagina - $inst['y2'];
                $ops[] = $this->corPdf($inst['cor']) . ' RG';
                $ops[] = sprintf('%.2F w', $inst['espessura']);
                $ops[] = sprintf('%.2F %.2F m', $inst['x1'], $y1);
                $ops[] = sprintf('%.2F %.2F l', $inst['x2'], $y2);
                $ops[] = 'S';
            } elseif ($inst['tipo'] === 'retangulo') {
                $yTopoPdf = $alturaPagina - $inst['yTopo'];
                $yBaixoPdf = $yTopoPdf - $inst['altura'];
                if ($inst['corFundo']) {
                    $ops[] = $this->corPdf($inst['corFundo']) . ' rg';
                    $ops[] = sprintf('%.2F %.2F %.2F %.2F re', $inst['x'], $yBaixoPdf, $inst['largura'], $inst['altura']);
                    $ops[] = 'f';
                }
                if ($inst['corBorda']) {
                    $ops[] = $this->corPdf($inst['corBorda']) . ' RG';
                    $ops[] = '0.75 w';
                    $ops[] = sprintf('%.2F %.2F %.2F %.2F re', $inst['x'], $yBaixoPdf, $inst['largura'], $inst['altura']);
                    $ops[] = 'S';
                }
            }
        }
        return implode("\n", $ops);
    }

    /** Gera os bytes do arquivo PDF completo (cabeçalho, objetos, xref e trailer). */
    private function montarPdf(): string
    {
        $alturaPagina = $this->cursorY + $this->margem + 20;
        $conteudo = $this->montarConteudo($alturaPagina);

        $objetos = [];
        $objetos[1] = "<< /Type /Catalog /Pages 2 0 R >>";
        $objetos[2] = "<< /Type /Pages /Kids [3 0 R] /Count 1 >>";
        $objetos[3] = sprintf(
            "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %.2F %.2F] /Resources << /Font << /F1 5 0 R /F2 6 0 R >> >> /Contents 4 0 R >>",
            $this->largura, $alturaPagina
        );
        $objetos[4] = "<< /Length " . strlen($conteudo) . " >>\nstream\n" . $conteudo . "\nendstream";
        $objetos[5] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>";
        $objetos[6] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>";

        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [];
        foreach ($objetos as $numero => $corpo) {
            $offsets[$numero] = strlen($pdf);
            $pdf .= $numero . " 0 obj\n" . $corpo . "\nendobj\n";
        }

        $offsetXref = strlen($pdf);
        $totalObjetos = count($objetos) + 1;
        $pdf .= "xref\n0 " . $totalObjetos . "\n";
        $pdf .= "0000000000 65535 f \n";
        for ($i = 1; $i < $totalObjetos; $i++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
        }
        $pdf .= "trailer\n<< /Size " . $totalObjetos . " /Root 1 0 R >>\nstartxref\n" . $offsetXref . "\n%%EOF";

        return $pdf;
    }

    /** Gera o PDF e envia como download imediato ao navegador. */
    public function download(string $nomeArquivo): void
    {
        $pdf = $this->montarPdf();
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $nomeArquivo . '"');
        header('Content-Length: ' . strlen($pdf));
        header('Cache-Control: max-age=0');
        echo $pdf;
        exit;
    }
}
