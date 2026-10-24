<?php
/**
 * admin/exportar-estoque.php — Exportação Excel (.xlsx) do estoque,
 * respeitando os filtros ativos na tela.
 */
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/estoque_repo.php';
require_once __DIR__ . '/../includes/SimpleXlsxWriter.php';

exigir_login_admin();

$busca = trim((string)($_GET['busca'] ?? ''));
$categoriaFiltro = inteiro_positivo($_GET['categoria_id'] ?? 0);
$marcaFiltro = trim((string)($_GET['marca'] ?? ''));
$valorMinFiltro = trim((string)($_GET['valor_min'] ?? '')) !== '' ? (float)str_replace(',', '.', $_GET['valor_min']) : null;
$valorMaxFiltro = trim((string)($_GET['valor_max'] ?? '')) !== '' ? (float)str_replace(',', '.', $_GET['valor_max']) : null;
$ordenarPor = array_key_exists($_GET['ordenar'] ?? '', ORDENACOES_ESTOQUE) ? $_GET['ordenar'] : 'nome_asc';
$itens = listar_itens_estoque($busca, $categoriaFiltro, $marcaFiltro, $valorMinFiltro, $valorMaxFiltro, $ordenarPor);

$xlsx = new SimpleXlsxWriter();
$xlsx->setHeader(['Nº de série', 'Nome', 'Marca', 'Categoria', 'Quantidade', 'Valor unitário', 'Valor total', 'Cadastrado em', 'Atualizado em']);

foreach ($itens as $item) {
    $xlsx->addRow([
        $item['numero_serie'] ?? '',
        $item['nome'],
        $item['marca'] ?? '',
        $item['categoria_nome'] ?? '',
        (int)$item['quantidade'],
        (float)$item['valor_unitario'],
        (float)$item['valor_unitario'] * (int)$item['quantidade'],
        formatar_data($item['criado_em']),
        formatar_data($item['atualizado_em']),
    ]);
}

$xlsx->download('estoque.xlsx');
