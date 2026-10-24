<?php
/**
 * exportar-pedido.php — Exportação Excel (.xlsx) do pedido, na visão do
 * CLIENTE (sem os campos internos da administração).
 */
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/pedido_repo.php';
require_once __DIR__ . '/includes/SimpleXlsxWriter.php';

$token = trim((string)($_GET['token'] ?? ''));
if ($token === '') { die('Link inválido.'); }

$pedido = buscar_pedido_por_token($token);
if (!$pedido) { die('Pedido não encontrado.'); }

$itens = listar_itens_pedido_para_impressao((int)$pedido['id']);

$xlsx = new SimpleXlsxWriter();
$xlsx->setHeader(['Cliente', 'Modelo/Nome', 'Nº de série', 'Quantidade', 'Situação', 'Status']);

foreach ($itens as $item) {
    $xlsx->addRow([
        $pedido['cliente_nome'],
        $item['modelo_nome'],
        $item['numero_serie'] ?? '',
        (int)$item['quantidade'],
        rotulo_situacao($item['situacao']),
        $item['feito'] ? '✅ Pronto' : '⏳ Pendente',
    ]);
}

$xlsx->addRow(['', '', '', '', '', '']);
$xlsx->addRow(['Total solicitado', '', '', total_solicitado($itens), '', ''], SimpleXlsxWriter::ESTILO_TOTAL);

$nomeArquivo = 'pedido-' . preg_replace('/[^a-z0-9]+/i', '-', $pedido['cliente_nome']) . '.xlsx';
$xlsx->download($nomeArquivo);
