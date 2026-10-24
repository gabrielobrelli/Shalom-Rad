<?php
/**
 * admin/imprimir-grafico.php — Versão para impressão do gráfico comparativo.
 */
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/servicos_repo.php';

exigir_login_admin();

$tipo = in_array($_GET['tipo'] ?? '', ['semana', 'mes', 'personalizado'], true) ? $_GET['tipo'] : 'mes';
$quantidade = max(2, min(24, inteiro_positivo($_GET['quantidade'] ?? 6) ?: 6));
$dataInicio = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($_GET['data_inicio'] ?? '')) ? $_GET['data_inicio'] : date('Y-m-d', strtotime('-30 days'));
$dataFim = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($_GET['data_fim'] ?? '')) ? $_GET['data_fim'] : date('Y-m-d');

$periodos = construir_periodos_comparacao($tipo, $quantidade, $dataInicio, $dataFim);
$totalGeral = array_sum(array_column($periodos, 'total'));
$rotuloTipo = ['semana' => 'Semanas', 'mes' => 'Meses', 'personalizado' => 'Período personalizado'][$tipo];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<title>Gráfico comparativo — Shalom Radiadores</title>
<link rel="icon" type="image/png" href="../assets/img/oficina.png">
<link rel="stylesheet" href="../assets/css/print.css">
</head>
<body>

<div class="imp-acoes-tela"><button onclick="window.print()">🖨️ Imprimir esta página</button></div>

<div class="imp-cabecalho">
    <div class="imp-marca">Shalom Radiadores — Gráfico Comparativo</div>
    <div class="imp-meta"><?= h($rotuloTipo) ?><br>Impresso em <?= h(date('d/m/Y H:i')) ?></div>
</div>
<div class="imp-faixa"></div>

<?= renderizar_grafico_svg($periodos, 720, 300) ?>

<div class="imp-totais">
    <div>Total do período: <?= h(formatar_dinheiro($totalGeral)) ?></div>
</div>

<div class="imp-rodape">Shalom Radiadores — documento interno, gerado automaticamente pelo sistema.</div>

</body>
</html>
