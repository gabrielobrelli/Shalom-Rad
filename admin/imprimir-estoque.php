<?php
/**
 * admin/imprimir-estoque.php — Lista de estoque pronta pra impressão,
 * respeitando os mesmos filtros usados na tela (busca/categoria/marca).
 */
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/estoque_repo.php';

exigir_login_admin();

$busca = trim((string)($_GET['busca'] ?? ''));
$categoriaFiltro = inteiro_positivo($_GET['categoria_id'] ?? 0);
$marcaFiltro = trim((string)($_GET['marca'] ?? ''));
$valorMinFiltro = trim((string)($_GET['valor_min'] ?? '')) !== '' ? (float)str_replace(',', '.', $_GET['valor_min']) : null;
$valorMaxFiltro = trim((string)($_GET['valor_max'] ?? '')) !== '' ? (float)str_replace(',', '.', $_GET['valor_max']) : null;
$ordenarPor = array_key_exists($_GET['ordenar'] ?? '', ORDENACOES_ESTOQUE) ? $_GET['ordenar'] : 'nome_asc';
$itens = listar_itens_estoque($busca, $categoriaFiltro, $marcaFiltro, $valorMinFiltro, $valorMaxFiltro, $ordenarPor);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<title>Estoque — Shalom Radiadores</title>
<link rel="icon" type="image/png" href="../assets/img/oficina.png">
<link rel="stylesheet" href="../assets/css/print.css">
</head>
<body>

<div class="imp-acoes-tela"><button onclick="window.print()">🖨️ Imprimir esta página</button></div>

<div class="imp-cabecalho">
    <div class="imp-marca">Shalom Radiadores — Estoque</div>
    <div class="imp-meta">Impresso em <?= h(date('d/m/Y H:i')) ?><br><?= count($itens) ?> item(ns) — <?= h(formatar_dinheiro(valor_total_estoque())) ?> em estoque<br>Ordenado por: <?= h(ORDENACOES_ESTOQUE[$ordenarPor]) ?></div>
</div>
<div class="imp-faixa"></div>

<table class="imp-tabela">
    <thead><tr><th>Nº de série</th><th>Nome</th><th>Marca</th><th>Categoria</th><th class="num">Quantidade</th><th class="num">Valor unit.</th><th class="num">Valor total</th><th>Atualizado em</th></tr></thead>
    <tbody>
        <?php foreach ($itens as $item): ?>
        <tr>
            <td><?= h($item['numero_serie'] ?? '-') ?></td>
            <td><?= h($item['nome']) ?></td>
            <td><?= h($item['marca'] ?? '-') ?></td>
            <td><?= h($item['categoria_nome'] ?? '-') ?></td>
            <td class="num"><?= (int)$item['quantidade'] ?></td>
            <td class="num"><?= h(formatar_dinheiro((float)$item['valor_unitario'])) ?></td>
            <td class="num"><?= h(formatar_dinheiro((float)$item['valor_unitario'] * (int)$item['quantidade'])) ?></td>
            <td><?= h(formatar_data($item['atualizado_em'])) ?></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<div class="imp-rodape">Shalom Radiadores — documento interno, gerado automaticamente pelo sistema.</div>

</body>
</html>
