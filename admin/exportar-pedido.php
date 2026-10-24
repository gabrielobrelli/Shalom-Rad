<?php
/**
 * admin/exportar-pedido.php — Exportação Excel (.xlsx) do pedido, na
 * visão da ADMINISTRAÇÃO (inclui campos internos de produção).
 */
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/pedido_repo.php';
require_once __DIR__ . '/../includes/SimpleXlsxWriter.php';

exigir_login_admin();

$id = inteiro_positivo($_GET['id'] ?? 0);
$pedido = $id ? buscar_pedido_por_id($id) : null;
if (!$pedido) { die('Pedido não encontrado.'); }

$itens = listar_itens_pedido_para_impressao($id);

$xlsx = new SimpleXlsxWriter();
$xlsx->setHeader([
    'Cliente', 'Modelo/Nome', 'Nº de série', 'Quantidade', 'Situação',
    'Quantidade Feita', 'Feito', 'Profissionais', 'Observação (ADM)', 'Carcaça utilizada', 'Caixas utilizadas', 'Anéis',
]);

foreach ($itens as $item) {
    $xlsx->addRow([
        $pedido['cliente_nome'],
        $item['modelo_nome'],
        $item['numero_serie'] ?? '',
        (int)$item['quantidade'],
        rotulo_situacao($item['situacao']),
        (int)$item['quantidade_feita'],
        $item['feito'] ? 'Sim' : 'Não',
        $item['profissionais_nomes'] ?? '',
        $item['observacao_adm'] ?? '',
        $item['carcaca_utilizada'] ?? '',
        $item['caixas_utilizadas'] ?? '',
        $item['aneis'] ?? '',
    ]);
}

$xlsx->addRow(array_fill(0, 12, ''));
$xlsx->addRow(['Total solicitado', '', '', total_solicitado($itens), '', '', '', '', '', '', '', ''], SimpleXlsxWriter::ESTILO_TOTAL);
$xlsx->addRow(['Total feito', '', '', '', '', total_feito($itens), '', '', '', '', '', ''], SimpleXlsxWriter::ESTILO_TOTAL);

$nomeArquivo = 'admin-pedido-' . preg_replace('/[^a-z0-9]+/i', '-', $pedido['cliente_nome']) . '.xlsx';
$xlsx->download($nomeArquivo);
