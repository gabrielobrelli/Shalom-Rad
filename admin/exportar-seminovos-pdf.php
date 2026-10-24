<?php
/**
 * admin/exportar-seminovos-pdf.php — Exportação em PDF da seção
 * "Concerto de Seminovos Marcio".
 */
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/seminovos_repo.php';
require_once __DIR__ . '/../includes/SimplePdfWriter.php';

exigir_login_admin();

$semi = obter_seminovos_order();
$itens = listar_itens_seminovos_para_impressao((int)$semi['id']);

$pdf = new SimplePdfWriter('Shalom Radiadores', 'Concerto de Seminovos Marcio');

$linhas = array_map(fn($i) => [
    $i['modelo_nome'],
    (string)$i['quantidade_feita'],
    $i['nossas_pecas_usadas'] ?: '-',
], $itens);

$pdf->addTabela(['Modelo/Nome', 'Quantidade Feita', 'Nossas Peças Usadas'], $linhas, [0.3, 0.2, 0.5]);
$pdf->addTotais(['Total geral: ' . total_geral_seminovos($itens) . ' peças']);
$pdf->addRodape('Shalom Radiadores — documento interno, gerado automaticamente pelo sistema.');

$pdf->download('concerto-seminovos-marcio.pdf');
