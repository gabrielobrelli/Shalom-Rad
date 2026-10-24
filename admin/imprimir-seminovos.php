<?php
/**
 * admin/imprimir-seminovos.php — Versão para impressão da seção
 * "Concerto de Seminovos Marcio".
 */
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/seminovos_repo.php';

exigir_login_admin();

$semi = obter_seminovos_order();
$itens = listar_itens_seminovos_para_impressao((int)$semi['id']);
$totalGeral = total_geral_seminovos($itens);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<title>Impressão — Concerto de Seminovos Marcio</title>
<link rel="icon" type="image/png" href="../assets/img/oficina.png">
<link rel="stylesheet" href="../assets/css/print.css">
</head>
<body>

<div class="imp-acoes-tela">
    <button onclick="window.print()">🖨️ Imprimir esta página</button>
</div>

<div class="imp-cabecalho">
    <div class="imp-marca">Concerto de Seminovos Marcio</div>
    <div class="imp-meta">Impresso em <?= h(date('d/m/Y H:i')) ?></div>
</div>
<div class="imp-faixa"></div>

<table class="imp-tabela">
    <thead>
        <tr>
            <th>Modelo/Nome</th>
            <th class="num">Quantidade Feita</th>
            <th>Nossas Peças Usadas</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($itens as $item): ?>
        <tr>
            <td><?= h($item['modelo_nome']) ?></td>
            <td class="num"><?= (int)$item['quantidade_feita'] ?></td>
            <td><?= h($item['nossas_pecas_usadas'] ?? '-') ?></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<div class="imp-totais">
    <div>Total geral: <?= $totalGeral ?> peças</div>
</div>

<div class="imp-rodape">Shalom Radiadores — documento interno, gerado automaticamente pelo sistema.</div>

</body>
</html>
