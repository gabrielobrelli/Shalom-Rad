<?php
/**
 * ============================================================================
 * SimpleZipWriter.php — Gera um arquivo .zip válido inteiramente em PHP
 * puro (sem a extensão ZipArchive), montando tudo em memória e devolvendo
 * os bytes prontos para serem enviados direto ao navegador.
 *
 * Por quê isso existe: em algumas hospedagens compartilhadas (como a
 * InfinityFree), o PHP não consegue abrir/escrever arquivos .zip no disco
 * — seja porque a extensão Zip não tem suporte a escrita nessa conta, seja
 * por alguma restrição de segurança do servidor. Como aqui a gente nunca
 * escreve o .zip no disco (só monta os bytes em memória e manda direto
 * pro download), esse tipo de bloqueio deixa de ser um problema.
 *
 * Formato gerado: ZIP padrão (compatível com Windows, macOS, WinRAR, 7-Zip
 * etc.), com compressão DEFLATE quando a extensão zlib está disponível
 * (praticamente sempre está — é uma extensão do PHP muito mais comum do
 * que a Zip), e sem compressão como último recurso.
 * ============================================================================
 */
class SimpleZipWriter
{
    /** @var array<int, array{nome: string, conteudo: string}> */
    private array $arquivos = [];

    /** Adiciona um arquivo já existente no disco, lendo o conteúdo dele. */
    public function addArquivo(string $caminhoNoZip, string $caminhoReal): void
    {
        $conteudo = @file_get_contents($caminhoReal);
        if ($conteudo === false) {
            return; // pula arquivos ilegíveis — não deve travar o backup inteiro
        }
        $this->arquivos[] = ['nome' => $caminhoNoZip, 'conteudo' => $conteudo];
    }

    /** Adiciona um conteúdo gerado na hora (ex.: o dump SQL, o LEIA-ME). */
    public function addString(string $caminhoNoZip, string $conteudo): void
    {
        $this->arquivos[] = ['nome' => $caminhoNoZip, 'conteudo' => $conteudo];
    }

    /**
     * Adiciona uma pasta inteira (recursivamente). $ignorarPastas é uma
     * lista de nomes de pasta (relativos à raiz) a pular.
     */
    public function addPastaRecursiva(string $pastaOrigem, string $prefixoZip, array $ignorarPastas = []): void
    {
        $itens = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($pastaOrigem, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($itens as $item) {
            if ($item->isDir()) {
                continue; // não precisamos de entradas de pasta vazias no zip
            }

            $caminhoReal = $item->getPathname();
            $caminhoRelativo = str_replace('\\', '/', substr($caminhoReal, strlen($pastaOrigem) + 1));

            foreach ($ignorarPastas as $pastaIgnorada) {
                if (strpos($caminhoRelativo, $pastaIgnorada . '/') === 0 || $caminhoRelativo === $pastaIgnorada) {
                    continue 2;
                }
            }

            $this->addArquivo($prefixoZip . $caminhoRelativo, $caminhoReal);
        }
    }

    /** Quantos arquivos já foram adicionados até agora. */
    public function contarArquivos(): int
    {
        return count($this->arquivos);
    }

    /**
     * Monta e devolve os bytes finais do arquivo .zip — pronto para ser
     * gravado em disco OU (o uso recomendado aqui) enviado direto como
     * resposta HTTP de download, sem nunca tocar o disco do servidor.
     */
    public function gerarBytes(): string
    {
        $usaDeflate = function_exists('gzdeflate');

        $corpo = '';
        $diretorioCentral = '';
        $offset = 0;
        $total = count($this->arquivos);

        [$dosHora, $dosData] = $this->dataHoraFormatoDos();

        foreach ($this->arquivos as $arquivo) {
            $nomeBin = $arquivo['nome'];
            $nomeLen = strlen($nomeBin);
            $dadosOriginais = $arquivo['conteudo'];
            $crc = crc32($dadosOriginais);
            $tamanhoOriginal = strlen($dadosOriginais);

            if ($usaDeflate) {
                $comprimido = gzdeflate($dadosOriginais, 6);
                $metodo = 8; // DEFLATE
            } else {
                $comprimido = $dadosOriginais;
                $metodo = 0; // sem compressão (STORED)
            }
            $tamanhoComprimido = strlen($comprimido);

            $cabecalhoLocal = pack('V', 0x04034b50)
                . pack('v', 20)
                . pack('v', 0)
                . pack('v', $metodo)
                . pack('v', $dosHora)
                . pack('v', $dosData)
                . pack('V', $crc)
                . pack('V', $tamanhoComprimido)
                . pack('V', $tamanhoOriginal)
                . pack('v', $nomeLen)
                . pack('v', 0)
                . $nomeBin;

            $corpo .= $cabecalhoLocal . $comprimido;

            $diretorioCentral .= pack('V', 0x02014b50)
                . pack('v', 20)
                . pack('v', 20)
                . pack('v', 0)
                . pack('v', $metodo)
                . pack('v', $dosHora)
                . pack('v', $dosData)
                . pack('V', $crc)
                . pack('V', $tamanhoComprimido)
                . pack('V', $tamanhoOriginal)
                . pack('v', $nomeLen)
                . pack('v', 0)
                . pack('v', 0)
                . pack('v', 0)
                . pack('v', 0)
                . pack('V', 0)
                . pack('V', $offset)
                . $nomeBin;

            $offset += strlen($cabecalhoLocal) + $tamanhoComprimido;
        }

        $offsetDiretorioCentral = $offset;
        $tamanhoDiretorioCentral = strlen($diretorioCentral);

        $fimDoDiretorioCentral = pack('V', 0x06054b50)
            . pack('v', 0)
            . pack('v', 0)
            . pack('v', $total)
            . pack('v', $total)
            . pack('V', $tamanhoDiretorioCentral)
            . pack('V', $offsetDiretorioCentral)
            . pack('v', 0);

        return $corpo . $diretorioCentral . $fimDoDiretorioCentral;
    }

    /** Envia os bytes do zip direto como download HTTP — nunca escreve nada em disco. */
    public function enviarComoDownload(string $nomeArquivo, string $tipoConteudo = 'application/zip'): void
    {
        $bytes = $this->gerarBytes();

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        header('Content-Type: ' . $tipoConteudo);
        header('Content-Disposition: attachment; filename="' . $nomeArquivo . '"');
        header('Content-Length: ' . strlen($bytes));
        header('Cache-Control: no-store, no-cache, must-revalidate');
        header('Pragma: no-cache');
        echo $bytes;
    }

    /** Converte o horário atual para o formato de data/hora usado pelo ZIP (DOS date/time). */
    private function dataHoraFormatoDos(): array
    {
        $hora = (int)date('H');
        $minuto = (int)date('i');
        $segundo = (int)date('s');
        $ano = (int)date('Y');
        $mes = (int)date('n');
        $dia = (int)date('j');

        $dosHora = ($hora << 11) | ($minuto << 5) | (int)($segundo / 2);
        $dosData = (($ano - 1980) << 9) | ($mes << 5) | $dia;

        return [$dosHora, $dosData];
    }
}
