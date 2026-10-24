<?php
/**
 * ============================================================================
 * SimpleXlsxWriter — Gerador de arquivos .xlsx reais e editáveis, sem
 * depender de Composer/PhpSpreadsheet (útil em hospedagens compartilhadas
 * como a InfinityFree). Usa apenas a extensão ZipArchive, já presente na
 * maioria das hospedagens PHP.
 * ============================================================================
 * Suporta um conjunto pequeno, mas suficiente, de estilos visuais:
 *   - ESTILO_CABECALHO → fundo azul, texto branco em negrito (linha de título)
 *   - ESTILO_TOTAL      → texto laranja em negrito (linhas de totais)
 *   - ESTILO_NORMAL      → texto padrão (linhas de dados comuns)
 *
 * Uso típico:
 *   $xlsx = new SimpleXlsxWriter();
 *   $xlsx->setHeader(['Coluna A', 'Coluna B']);
 *   $xlsx->addRow(['valor 1', 'valor 2']);
 *   $xlsx->addRow(['Total', 10], SimpleXlsxWriter::ESTILO_TOTAL);
 *   $xlsx->download('arquivo.xlsx');
 */
class SimpleXlsxWriter
{
    public const ESTILO_NORMAL    = 0;
    public const ESTILO_CABECALHO = 1;
    public const ESTILO_TOTAL     = 2;

    /** Cada item: ['valores' => array, 'estilo' => int] */
    private array $linhas = [];

    public function setHeader(array $colunas): void
    {
        $this->linhas[] = ['valores' => $colunas, 'estilo' => self::ESTILO_CABECALHO];
    }

    public function addRow(array $valores, int $estilo = self::ESTILO_NORMAL): void
    {
        $this->linhas[] = ['valores' => $valores, 'estilo' => $estilo];
    }

    private function colLetter(int $index): string
    {
        $letter = '';
        $index++;
        while ($index > 0) {
            $mod = ($index - 1) % 26;
            $letter = chr(65 + $mod) . $letter;
            $index = intdiv($index - $mod, 26);
        }
        return $letter;
    }

    private function escape(string $valor): string
    {
        return htmlspecialchars($valor, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    private function buildSheetXml(): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n";
        $xml .= '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">';

        // Larguras de coluna um pouco maiores que o padrão, para caber os
        // nomes de modelos e as anotações de peças usadas sem cortar texto.
        $maxColunas = max(array_map(fn($l) => count($l['valores']), $this->linhas ?: [['valores' => []]]));
        $xml .= '<cols>';
        for ($c = 0; $c < $maxColunas; $c++) {
            $xml .= '<col min="' . ($c + 1) . '" max="' . ($c + 1) . '" width="22" customWidth="1"/>';
        }
        $xml .= '</cols>';

        $xml .= '<sheetData>';

        foreach ($this->linhas as $rIndex => $linha) {
            $rowNum = $rIndex + 1;
            $xml .= '<row r="' . $rowNum . '">';
            foreach (array_values($linha['valores']) as $cIndex => $valor) {
                $cellRef = $this->colLetter($cIndex) . $rowNum;
                $s = $linha['estilo'];
                if (is_numeric($valor) && $valor !== '' && !preg_match('/^0[0-9]/', (string)$valor)) {
                    $xml .= '<c r="' . $cellRef . '" s="' . $s . '"><v>' . $this->escape((string)$valor) . '</v></c>';
                } else {
                    $xml .= '<c r="' . $cellRef . '" s="' . $s . '" t="inlineStr"><is><t xml:space="preserve">' . $this->escape((string)$valor) . '</t></is></c>';
                }
            }
            $xml .= '</row>';
        }

        $xml .= '</sheetData></worksheet>';
        return $xml;
    }

    /** Folha de estilos: define as 3 combinações de fonte/cor usadas nas planilhas. */
    private function buildStylesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
        '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">' .
        '<fonts count="3">' .
            '<font><sz val="11"/><name val="Calibri"/></font>' .                                    // 0: normal
            '<font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font>' .          // 1: cabeçalho (branco, negrito)
            '<font><b/><sz val="11"/><color rgb="FFB5480A"/><name val="Calibri"/></font>' .          // 2: total (laranja, negrito)
        '</fonts>' .
        '<fills count="3">' .
            '<fill><patternFill patternType="none"/></fill>' .
            '<fill><patternFill patternType="none"/></fill>' .
            '<fill><patternFill patternType="solid"><fgColor rgb="FF0F2C4F"/><bgColor indexed="64"/></patternFill></fill>' . // azul do cabeçalho
        '</fills>' .
        '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>' .
        '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>' .
        '<cellXfs count="3">' .
            '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>' .                       // s="0" normal
            '<xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"/>' . // s="1" cabeçalho
            '<xf numFmtId="0" fontId="2" fillId="0" borderId="0" xfId="0" applyFont="1"/>' .          // s="2" total
        '</cellXfs>' .
        '</styleSheet>';
    }

    /** Gera o arquivo .xlsx e envia como download imediato ao navegador. */
    public function download(string $nomeArquivo): void
    {
        require_once __DIR__ . '/SimpleZipWriter.php';

        $zip = new SimpleZipWriter();

        $zip->addString('[Content_Types].xml',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
            '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">' .
            '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>' .
            '<Default Extension="xml" ContentType="application/xml"/>' .
            '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>' .
            '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>' .
            '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>' .
            '</Types>'
        );

        $zip->addString('_rels/.rels',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
            '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' .
            '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>' .
            '</Relationships>'
        );

        $zip->addString('xl/_rels/workbook.xml.rels',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
            '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' .
            '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>' .
            '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>' .
            '</Relationships>'
        );

        $zip->addString('xl/workbook.xml',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
            '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" ' .
            'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">' .
            '<sheets><sheet name="Pedido" sheetId="1" r:id="rId1"/></sheets>' .
            '</workbook>'
        );

        $zip->addString('xl/styles.xml', $this->buildStylesXml());
        $zip->addString('xl/worksheets/sheet1.xml', $this->buildSheetXml());

        $zip->enviarComoDownload($nomeArquivo, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        exit;
    }
}
