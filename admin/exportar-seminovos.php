<?php
/**
 * admin/exportar-seminovos.php — Exportação Excel (.xlsx) da seção
 * "Concerto de Seminovos Marcio".
 */
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/seminovos_repo.php';
require_once __DIR__ . '/../includes/SimpleXlsxWriter.php';

exigir_login_admin();

$semi = obter_seminovos_order();
$itens = listar_itens_seminovos_para_impressao((int)$semi['id']);

$xlsx = new SimpleXlsxWriter();
$xlsx->setHeader(['Modelo/Nome', 'Quantidade Feita', 'Nossas Peças Usadas']);

foreach ($itens as $item) {
    $xlsx->addRow([
        $item['modelo_nome'],
        (int)$item['quantidade_feita'],
        $item['nossas_pecas_usadas'] ?? '',
    ]);
}

$xlsx->addRow(['', '', '']);
$xlsx->addRow(['Total geral', total_geral_seminovos($itens), ''], SimpleXlsxWriter::ESTILO_TOTAL);

$xlsx->download('concerto-seminovos-marcio.xlsx');
