<?php
/**
 * ============================================================================
 * relatorio_repo.php — Relatório de FECHAMENTO (dia, semana, quinzena, mês,
 * trimestre, semestre, ano ou período personalizado), juntando dados de
 * serviços, peças de radiador concluídas, movimentações de estoque e
 * produção por profissional. Usado por admin/fechamento.php (tela),
 * admin/imprimir-fechamento.php (impressão) e
 * admin/exportar-fechamento-pdf.php (PDF completo para download).
 * ============================================================================
 */
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/servicos_repo.php';
require_once __DIR__ . '/pedido_repo.php';
require_once __DIR__ . '/estoque_repo.php';
require_once __DIR__ . '/profissionais_repo.php';

const TIPOS_PERIODO_FECHAMENTO = ['dia', 'semana', 'quinzena', 'mes', 'trimestre', 'semestre', 'ano', 'personalizado'];

/** Nome do mês por extenso + ano, ex.: "Agosto de 2026". */
function nome_mes_ano(string $data): string
{
    $nomesMes = ['', 'Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho',
                 'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'];
    return $nomesMes[(int)date('n', strtotime($data))] . ' de ' . date('Y', strtotime($data));
}

/**
 * Calcula o intervalo [início, fim] de um tipo de período a partir de uma
 * data de referência (qualquer dia dentro do período desejado — assim dá
 * pra pedir o fechamento de um dia/semana/mês/etc. já passado, não só o
 * atual). Para "personalizado", $dataInicioPersonalizada/$dataFimPersonalizada
 * são usadas diretamente.
 */
function calcular_periodo_fechamento(
    string $tipo,
    string $dataReferencia,
    ?string $dataInicioPersonalizada = null,
    ?string $dataFimPersonalizada = null
): array {
    $tipo = in_array($tipo, TIPOS_PERIODO_FECHAMENTO, true) ? $tipo : 'dia';
    $ref = preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataReferencia) ? $dataReferencia : date('Y-m-d');
    $ano = (int)date('Y', strtotime($ref));
    $mes = (int)date('n', strtotime($ref));

    switch ($tipo) {
        case 'semana':
            $diaSemana = (int)date('w', strtotime($ref));
            $inicio = date('Y-m-d', strtotime($ref . ' -' . $diaSemana . ' days'));
            $fim = date('Y-m-d', strtotime($inicio . ' +6 days'));
            $rotulo = 'Semana de ' . date('d/m', strtotime($inicio)) . ' a ' . date('d/m/Y', strtotime($fim));
            break;

        case 'quinzena':
            $dia = (int)date('d', strtotime($ref));
            if ($dia <= 15) {
                $inicio = sprintf('%04d-%02d-01', $ano, $mes);
                $fim = sprintf('%04d-%02d-15', $ano, $mes);
                $rotulo = '1ª quinzena de ' . nome_mes_ano($ref);
            } else {
                $inicio = sprintf('%04d-%02d-16', $ano, $mes);
                $fim = date('Y-m-t', strtotime($ref));
                $rotulo = '2ª quinzena de ' . nome_mes_ano($ref);
            }
            break;

        case 'mes':
            $inicio = sprintf('%04d-%02d-01', $ano, $mes);
            $fim = date('Y-m-t', strtotime($inicio));
            $rotulo = nome_mes_ano($ref);
            break;

        case 'trimestre':
            $trimestre = (int)ceil($mes / 3);
            $mesInicio = ($trimestre - 1) * 3 + 1;
            $inicio = sprintf('%04d-%02d-01', $ano, $mesInicio);
            $fim = date('Y-m-t', strtotime(sprintf('%04d-%02d-01', $ano, $mesInicio + 2)));
            $rotulo = $trimestre . 'º trimestre de ' . $ano;
            break;

        case 'semestre':
            $semestre = $mes <= 6 ? 1 : 2;
            $inicio = $semestre === 1 ? "{$ano}-01-01" : "{$ano}-07-01";
            $fim = $semestre === 1 ? "{$ano}-06-30" : "{$ano}-12-31";
            $rotulo = $semestre . 'º semestre de ' . $ano;
            break;

        case 'ano':
            $inicio = "{$ano}-01-01";
            $fim = "{$ano}-12-31";
            $rotulo = 'Ano de ' . $ano;
            break;

        case 'personalizado':
            $inicio = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$dataInicioPersonalizada) ? $dataInicioPersonalizada : $ref;
            $fim = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$dataFimPersonalizada) ? $dataFimPersonalizada : $ref;
            if (strtotime($fim) < strtotime($inicio)) {
                [$inicio, $fim] = [$fim, $inicio];
            }
            $rotulo = ($inicio === $fim)
                ? date('d/m/Y', strtotime($inicio))
                : 'De ' . date('d/m/Y', strtotime($inicio)) . ' a ' . date('d/m/Y', strtotime($fim));
            break;

        default: // dia
            $inicio = $fim = $ref;
            $rotulo = ($ref === date('Y-m-d')) ? 'Hoje, ' . date('d/m/Y', strtotime($ref)) : date('d/m/Y', strtotime($ref));
    }

    return ['tipo' => $tipo, 'inicio' => $inicio, 'fim' => $fim, 'rotulo' => $rotulo];
}

/** Monta um parágrafo-resumo em texto corrido a partir dos números já calculados. */
function montar_resumo_textual_fechamento(array $totaisServicos, array $pecas, array $movimentacoes, array $ranking): string
{
    $totalUnidadesPecas = array_sum(array_column($pecas, 'quantidade_feita'));
    $entradas = count(array_filter($movimentacoes, fn($m) => $m['tipo'] !== 'saida'));
    $saidas = count(array_filter($movimentacoes, fn($m) => $m['tipo'] === 'saida'));

    $partes = [];
    $partes[] = $totaisServicos['quantidade'] > 0
        ? "Foram lançados {$totaisServicos['quantidade']} serviço(s) no período, somando " . formatar_dinheiro($totaisServicos['liquido']) . ' em faturamento líquido.'
        : 'Nenhum serviço foi lançado neste período.';

    $partes[] = count($pecas) > 0
        ? count($pecas) . ' peça(s) de radiador foram concluídas nos pedidos de clientes, totalizando ' . $totalUnidadesPecas . ' unidade(s).'
        : 'Nenhuma peça de radiador foi concluída neste período.';

    $partes[] = ($entradas + $saidas) > 0
        ? "O estoque teve {$entradas} entrada(s)/cadastro(s) e {$saidas} saída(s) registradas."
        : 'Não houve movimentação de estoque neste período.';

    if (!empty($ranking)) {
        $partes[] = count($ranking) . ' profissional(is) tiveram produção registrada no período.';
    }

    return implode(' ', $partes);
}

/**
 * Junta TUDO que o relatório de fechamento precisa mostrar, num único
 * array — usado igualmente pela tela, pela impressão e pelo PDF, pra não
 * repetir a lógica de busca em três lugares.
 */
function compilar_dados_fechamento(string $inicio, string $fim): array
{
    $totaisServicos = totais_do_periodo($inicio, $fim);
    $servicos = listar_servicos_periodo($inicio, $fim);
    $pecas = listar_pecas_feitas_periodo($inicio, $fim);
    $movimentacoes = listar_movimentacoes_periodo($inicio, $fim);
    $ranking = ranking_profissionais_periodo($inicio, $fim);
    $evolucaoDiaria = construir_periodos_comparacao('personalizado', 0, $inicio, $fim);

    return [
        'totais_servicos' => $totaisServicos,
        'servicos' => $servicos,
        'pecas' => $pecas,
        'movimentacoes' => $movimentacoes,
        'ranking' => $ranking,
        'evolucao_diaria' => $evolucaoDiaria,
        'resumo_textual' => montar_resumo_textual_fechamento($totaisServicos, $pecas, $movimentacoes, $ranking),
    ];
}
