<?php
/**
 * admin/exportar-servicos-pdf.php — Exportação em PDF do histórico de
 * serviços de um dia (extrato).
 */
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/servicos_repo.php';
require_once __DIR__ . '/../includes/SimplePdfWriter.php';

exigir_login_admin();

$data = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($_GET['data'] ?? '')) ? $_GET['data'] : date('Y-m-d');
$servicos = listar_servicos_do_dia($data);
$totais = totais_do_periodo($data, $data);

$pdf = new SimplePdfWriter('Shalom Radiadores', 'Extrato de serviços — ' . date('d/m/Y', strtotime($data)));

$linhas = array_map(function ($s) {
    $pagamentosTexto = implode('; ', array_map(
        fn($p) => rotulo_forma_pagamento($p['forma']) . ': ' . formatar_dinheiro((float)$p['valor']) . (!empty($p['conta']) ? ' [' . $p['conta'] . ']' : ''),
        $s['pagamentos']
    ));
    return [
        $s['carro_cliente'],
        $s['profissionais_nomes'] ?? '-',
        $s['observacao_adm'] ?: '-',
        formatar_dinheiro((float)$s['valor_cobrado']),
        formatar_dinheiro((float)$s['desconto']),
        $pagamentosTexto ?: '-',
    ];
}, $servicos);

$pdf->addTabela(['Carro/Cliente', 'Profissionais', 'Observação (ADM)', 'Cobrado', 'Desconto', 'Pagamentos'], $linhas, [0.2, 0.16, 0.18, 0.14, 0.12, 0.2]);
$pdf->addTotais([
    'Serviços: ' . $totais['quantidade'],
    'Total líquido: ' . formatar_dinheiro($totais['liquido']),
]);
$pdf->addRodape('Shalom Radiadores — documento interno, não é nota fiscal.');

$pdf->download('servicos-' . $data . '.pdf');
