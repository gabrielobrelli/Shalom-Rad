<?php
/**
 * admin/exportar-grafico-pdf.php — Exportação em PDF do gráfico comparativo.
 */
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/servicos_repo.php';
require_once __DIR__ . '/../includes/SimplePdfWriter.php';

exigir_login_admin();

$tipo = in_array($_GET['tipo'] ?? '', ['semana', 'mes', 'personalizado'], true) ? $_GET['tipo'] : 'mes';
$quantidade = max(2, min(24, inteiro_positivo($_GET['quantidade'] ?? 6) ?: 6));
$dataInicio = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($_GET['data_inicio'] ?? '')) ? $_GET['data_inicio'] : date('Y-m-d', strtotime('-30 days'));
$dataFim = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($_GET['data_fim'] ?? '')) ? $_GET['data_fim'] : date('Y-m-d');

$periodos = construir_periodos_comparacao($tipo, $quantidade, $dataInicio, $dataFim);
$totalGeral = array_sum(array_column($periodos, 'total'));
$rotuloTipo = ['semana' => 'Semanas', 'mes' => 'Meses', 'personalizado' => 'Período personalizado'][$tipo];

$pdf = new SimplePdfWriter('Shalom Radiadores', 'Gráfico comparativo — ' . $rotuloTipo);
$pdf->addGraficoBarras($periodos);
$pdf->addTotais(['Total do período: ' . formatar_dinheiro($totalGeral)]);
$pdf->addRodape('Shalom Radiadores — documento interno, gerado automaticamente pelo sistema.');

$pdf->download('grafico-comparativo.pdf');
