<?php
/**
 * admin/exportar-estoque-pdf.php — Exportação em PDF do estoque,
 * respeitando os filtros ativos na tela.
 */
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/estoque_repo.php';
require_once __DIR__ . '/../includes/SimplePdfWriter.php';

exigir_login_admin();

$busca = trim((string)($_GET['busca'] ?? ''));
$categoriaFiltro = inteiro_positivo($_GET['categoria_id'] ?? 0);
$marcaFiltro = trim((string)($_GET['marca'] ?? ''));
$valorMinFiltro = trim((string)($_GET['valor_min'] ?? '')) !== '' ? (float)str_replace(',', '.', $_GET['valor_min']) : null;
$valorMaxFiltro = trim((string)($_GET['valor_max'] ?? '')) !== '' ? (float)str_replace(',', '.', $_GET['valor_max']) : null;
$ordenarPor = array_key_exists($_GET['ordenar'] ?? '', ORDENACOES_ESTOQUE) ? $_GET['ordenar'] : 'nome_asc';
$itens = listar_itens_estoque($busca, $categoriaFiltro, $marcaFiltro, $valorMinFiltro, $valorMaxFiltro, $ordenarPor);

$pdf = new SimplePdfWriter('Shalom Radiadores', 'Estoque (' . count($itens) . ' itens)');

$pdf->addInfo('Valor total em estoque', formatar_dinheiro(valor_total_estoque()));
$pdf->addEspaco(6);

$linhas = array_map(fn($i) => [
    $i['numero_serie'] ?: '-', $i['nome'], $i['marca'] ?: '-', (string)$i['quantidade'],
    formatar_dinheiro((float)$i['valor_unitario']),
    formatar_dinheiro((float)$i['valor_unitario'] * (int)$i['quantidade']),
], $itens);

$pdf->addTabela(['Nº série', 'Nome', 'Marca', 'Qtd.', 'Valor unit.', 'Valor total'], $linhas, [0.16, 0.28, 0.16, 0.10, 0.15, 0.15]);
$pdf->addRodape('Shalom Radiadores — documento interno, gerado automaticamente pelo sistema.');

$pdf->download('estoque.pdf');
