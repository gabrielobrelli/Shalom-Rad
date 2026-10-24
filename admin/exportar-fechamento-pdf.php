<?php
/**
 * admin/exportar-fechamento-pdf.php — Gera o PDF completo de fechamento
 * de um período: resumo escrito, cartões de indicadores, gráfico de
 * evolução, formas de pagamento, serviços, peças de radiador concluídas
 * (com quem fez), movimentações de estoque e produção por profissional.
 *
 * IMPORTANTE: o gerador de PDF usa uma fonte padrão (Helvetica) que só
 * entende texto em Windows-1252 — por isso NENHUM emoji é usado aqui
 * (eles seriam descartados silenciosamente e deixariam o layout com
 * espaços estranhos). Os emojis continuam normalmente nas telas e na
 * impressão em HTML, que suportam Unicode.
 */
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/relatorio_repo.php';
require_once __DIR__ . '/../includes/SimplePdfWriter.php';

exigir_login_admin();

$tipo = in_array($_GET['tipo'] ?? '', TIPOS_PERIODO_FECHAMENTO, true) ? $_GET['tipo'] : 'dia';
$data = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($_GET['data'] ?? '')) ? $_GET['data'] : date('Y-m-d');
$dataInicioPersonalizada = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($_GET['data_inicio'] ?? '')) ? $_GET['data_inicio'] : date('Y-m-d', strtotime('-7 days'));
$dataFimPersonalizada = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($_GET['data_fim'] ?? '')) ? $_GET['data_fim'] : date('Y-m-d');

$periodo = calcular_periodo_fechamento($tipo, $data, $dataInicioPersonalizada, $dataFimPersonalizada);

try {
    $dados = compilar_dados_fechamento($periodo['inicio'], $periodo['fim']);
} catch (Throwable $e) {
    header('Content-Type: text/html; charset=UTF-8');
    echo '<h2>Não foi possível gerar o PDF do fechamento</h2><p>' . h($e->getMessage()) . '</p>';
    echo '<p>Isso costuma acontecer quando alguma migração de banco ainda não foi rodada no phpMyAdmin.</p>';
    echo '<p><a href="fechamento.php">&larr; Voltar</a></p>';
    exit;
}

$pdf = new SimplePdfWriter('Shalom Radiadores - Fechamento', $periodo['rotulo']);

// --- Resumo escrito + cartões de indicadores ---------------------------------
$pdf->addParagrafo($dados['resumo_textual']);
$pdf->addEspaco(6);

$pdf->addCartoesResumo([
    ['rotulo' => 'Servicos lancados', 'valor' => (string)$dados['totais_servicos']['quantidade']],
    ['rotulo' => 'Faturamento liquido', 'valor' => formatar_dinheiro($dados['totais_servicos']['liquido'])],
    ['rotulo' => 'Pecas concluidas', 'valor' => (string)count($dados['pecas'])],
    ['rotulo' => 'Mov. de estoque', 'valor' => (string)count($dados['movimentacoes'])],
]);

// --- Gráfico de evolução no período -----------------------------------------
if (count($dados['evolucao_diaria']) > 1) {
    $pdf->addSubtitulo('Evolucao no periodo');
    $pdf->addGraficoBarras($dados['evolucao_diaria']);
}

// --- Financeiro: por forma de pagamento --------------------------------------
$pdf->addSubtitulo('Financeiro - por forma de pagamento');
if (empty($dados['totais_servicos']['por_forma'])) {
    $pdf->addParagrafo('Nenhum pagamento registrado neste período.');
} else {
    $linhasPagamento = [];
    foreach ($dados['totais_servicos']['por_forma'] as $forma => $valor) {
        $linhasPagamento[] = [rotulo_forma_pagamento($forma), formatar_dinheiro($valor)];
    }
    $pdf->addTabela(['Forma', 'Total recebido'], $linhasPagamento, [0.6, 0.4]);
}

// --- Serviços do período -------------------------------------------------------
$pdf->addSubtitulo('Servicos do periodo');
if (empty($dados['servicos'])) {
    $pdf->addParagrafo('Nenhum serviço lançado neste período.');
} else {
    $linhasServicos = array_map(fn($s) => [
        date('d/m/Y', strtotime($s['data_servico'])),
        $s['carro_cliente'],
        $s['profissionais_nomes'] ?: '-',
        $s['observacao_adm'] ?: '-',
        formatar_dinheiro((float)$s['valor_cobrado'] - (float)$s['desconto']),
    ], $dados['servicos']);
    $pdf->addTabela(['Data', 'Carro/Cliente', 'Profissionais', 'Observacao', 'Liquido'], $linhasServicos, [0.12, 0.26, 0.2, 0.27, 0.15]);
}

// --- Radiadores concluídos dos pedidos ------------------------------------------
$pdf->addSubtitulo('Radiadores concluidos (pedidos de clientes)');
if (empty($dados['pecas'])) {
    $pdf->addParagrafo('Nenhuma peça concluída neste período.');
} else {
    $linhasPecas = array_map(fn($p) => [
        formatar_data($p['feito_em']),
        $p['cliente_nome'],
        $p['modelo_nome'],
        (string)(int)$p['quantidade_feita'],
        $p['profissionais_nomes'] ?: '-',
    ], $dados['pecas']);
    $pdf->addTabela(['Data', 'Cliente', 'Peca/Modelo', 'Qtd.', 'Quem fez'], $linhasPecas, [0.16, 0.24, 0.24, 0.09, 0.27]);
}

// --- Movimentações de estoque ----------------------------------------------------
$pdf->addSubtitulo('Movimentacoes de estoque');
if (empty($dados['movimentacoes'])) {
    $pdf->addParagrafo('Nenhuma movimentação de estoque neste período.');
} else {
    $rotulosTipo = ['entrada' => 'Entrada', 'saida' => 'Saída', 'cadastro' => 'Cadastro'];
    $linhasMovimentacoes = array_map(fn($m) => [
        formatar_data($m['criado_em']),
        $m['item_nome'] . (!empty($m['marca']) ? ' (' . $m['marca'] . ')' : ''),
        $rotulosTipo[$m['tipo']] ?? $m['tipo'],
        (string)(int)$m['quantidade'],
    ], $dados['movimentacoes']);
    $pdf->addTabela(['Data', 'Item', 'Tipo', 'Qtd.'], $linhasMovimentacoes, [0.18, 0.44, 0.20, 0.18]);
}

// --- Produção por profissional ----------------------------------------------------
$pdf->addSubtitulo('Producao por profissional no periodo');
if (empty($dados['ranking'])) {
    $pdf->addParagrafo('Nenhuma produção registrada por profissional neste período.');
} else {
    $linhasRanking = array_map(fn($r) => [
        $r['nome'],
        (string)(int)$r['total_pecas_feitas'],
        (string)(int)$r['total_unidades_feitas'],
        (string)(int)$r['total_servicos'],
    ], $dados['ranking']);
    $pdf->addTabela(['Profissional', 'Pecas', 'Unidades', 'Servicos'], $linhasRanking, [0.4, 0.2, 0.2, 0.2]);
}

$pdf->addRodape('Shalom Radiadores - documento interno, gerado automaticamente pelo sistema. Nao e nota fiscal.');

$nomeArquivo = 'fechamento-' . $periodo['tipo'] . '-' . $periodo['inicio'] . '-a-' . $periodo['fim'] . '.pdf';
$pdf->download($nomeArquivo);
