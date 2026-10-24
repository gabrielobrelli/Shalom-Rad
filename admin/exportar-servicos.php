<?php
/**
 * admin/exportar-servicos.php — Exportação Excel (.xlsx) do histórico de
 * serviços de um dia.
 */
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/servicos_repo.php';
require_once __DIR__ . '/../includes/SimpleXlsxWriter.php';

exigir_login_admin();

$data = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($_GET['data'] ?? '')) ? $_GET['data'] : date('Y-m-d');
$servicos = listar_servicos_do_dia($data);

$xlsx = new SimpleXlsxWriter();
$xlsx->setHeader(['Carro/Cliente', 'Descrição', 'Observação (ADM)', 'Profissionais', 'Valor cobrado', 'Desconto', 'Líquido', 'Pagamentos']);

$totalLiquido = 0;
foreach ($servicos as $s) {
    $liquido = (float)$s['valor_cobrado'] - (float)$s['desconto'];
    $totalLiquido += $liquido;
    $pagamentosTexto = implode('; ', array_map(
        fn($p) => rotulo_forma_pagamento($p['forma']) . ': ' . formatar_dinheiro((float)$p['valor']) . ($p['parcelas'] ? ' (' . (int)$p['parcelas'] . 'x)' : '') . (!empty($p['conta']) ? ' [' . $p['conta'] . ']' : ''),
        $s['pagamentos']
    ));
    $xlsx->addRow([
        $s['carro_cliente'],
        $s['descricao'] ?? '',
        $s['observacao_adm'] ?? '',
        $s['profissionais_nomes'] ?? '',
        (float)$s['valor_cobrado'],
        (float)$s['desconto'],
        $liquido,
        $pagamentosTexto,
    ]);
}

$xlsx->addRow(array_fill(0, 8, ''));
$xlsx->addRow(['Total líquido do dia', '', '', '', '', '', $totalLiquido, ''], SimpleXlsxWriter::ESTILO_TOTAL);

$xlsx->download('servicos-' . $data . '.xlsx');
