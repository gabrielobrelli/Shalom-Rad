<?php
/**
 * admin/imprimir-relatorio.php — Versão para impressão do relatório
 * financeiro de um período.
 */
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/servicos_repo.php';

exigir_login_admin();

$periodo = in_array($_GET['periodo'] ?? '', ['dia','semana','mes','semestre','ano'], true) ? $_GET['periodo'] : 'mes';
$data = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($_GET['data'] ?? '')) ? $_GET['data'] : date('Y-m-d');
$ano = (int)date('Y', strtotime($data));
$mes = (int)date('n', strtotime($data));

switch ($periodo) {
    case 'dia': $inicio = $fim = $data; $rotuloPeriodo = 'Dia ' . date('d/m/Y', strtotime($data)); break;
    case 'semana':
        $diaSemana = (int)date('w', strtotime($data));
        $inicio = date('Y-m-d', strtotime($data . ' -' . $diaSemana . ' days'));
        $fim = date('Y-m-d', strtotime($inicio . ' +6 days'));
        $rotuloPeriodo = 'Semana de ' . date('d/m', strtotime($inicio)) . ' a ' . date('d/m/Y', strtotime($fim));
        break;
    case 'semestre':
        $inicioMes = $mes <= 6 ? 1 : 7; $fimMes = $mes <= 6 ? 6 : 12;
        $inicio = sprintf('%04d-%02d-01', $ano, $inicioMes);
        $fim = date('Y-m-t', strtotime(sprintf('%04d-%02d-01', $ano, $fimMes)));
        $rotuloPeriodo = ($mes <= 6 ? '1º semestre' : '2º semestre') . ' de ' . $ano;
        break;
    case 'ano':
        $inicio = sprintf('%04d-01-01', $ano); $fim = sprintf('%04d-12-31', $ano);
        $rotuloPeriodo = 'Ano de ' . $ano;
        break;
    default:
        $inicio = sprintf('%04d-%02d-01', $ano, $mes); $fim = date('Y-m-t', strtotime($inicio));
        $nomesMes = ['', 'Janeiro','Fevereiro','Março','Abril','Maio','Junho','Julho','Agosto','Setembro','Outubro','Novembro','Dezembro'];
        $rotuloPeriodo = $nomesMes[$mes] . ' de ' . $ano;
}

$totais = totais_do_periodo($inicio, $fim);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<title>Relatório financeiro — <?= h($rotuloPeriodo) ?></title>
<link rel="icon" type="image/png" href="../assets/img/oficina.png">
<link rel="stylesheet" href="../assets/css/print.css">
</head>
<body>

<div class="imp-acoes-tela"><button onclick="window.print()">🖨️ Imprimir esta página</button></div>

<div class="imp-cabecalho">
    <div class="imp-marca">Shalom Radiadores — Relatório Financeiro</div>
    <div class="imp-meta"><?= h($rotuloPeriodo) ?><br>Impresso em <?= h(date('d/m/Y H:i')) ?></div>
</div>
<div class="imp-faixa"></div>

<div class="imp-totais">
    <div>Serviços: <?= $totais['quantidade'] ?></div>
    <div>Bruto: <?= h(formatar_dinheiro($totais['bruto'])) ?></div>
    <div>Descontos: <?= h(formatar_dinheiro($totais['descontos'])) ?></div>
    <div>Líquido: <?= h(formatar_dinheiro($totais['liquido'])) ?></div>
</div>

<h3 style="margin-top:20px;">Por forma de pagamento</h3>
<table class="imp-tabela">
    <thead><tr><th>Forma</th><th class="num">Total recebido</th></tr></thead>
    <tbody>
        <?php foreach ($totais['por_forma'] as $forma => $valor): ?>
            <tr><td><?= h(rotulo_forma_pagamento($forma)) ?></td><td class="num"><?= h(formatar_dinheiro($valor)) ?></td></tr>
        <?php endforeach; ?>
        <?php if (empty($totais['por_forma'])): ?><tr><td colspan="2">Nenhum pagamento neste período.</td></tr><?php endif; ?>
    </tbody>
</table>

<div class="imp-rodape">Shalom Radiadores — documento interno, gerado automaticamente pelo sistema.</div>

</body>
</html>
