<?php
/**
 * admin/imprimir-pedido.php — Versão para impressão do pedido, na visão
 * da ADMINISTRAÇÃO (inclui os campos internos de produção).
 */
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/pedido_repo.php';

exigir_login_admin();

$id = inteiro_positivo($_GET['id'] ?? 0);
$pedido = $id ? buscar_pedido_por_id($id) : null;
if (!$pedido) { die('Pedido não encontrado.'); }

$itens = listar_itens_pedido_para_impressao($id);
$totalSolicitado = total_solicitado($itens);
$totalFeito = total_feito($itens);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<title>Impressão (admin) — Pedido de <?= h($pedido['cliente_nome']) ?></title>
<link rel="icon" type="image/png" href="../assets/img/oficina.png">
<link rel="stylesheet" href="../assets/css/print.css">
</head>
<body>

<div class="imp-acoes-tela">
    <button onclick="window.print()">🖨️ Imprimir esta página</button>
</div>

<div class="imp-cabecalho">
    <div class="imp-marca">Shalom Radiadores — Via administração</div>
    <div class="imp-meta">Impresso em <?= h(date('d/m/Y H:i')) ?></div>
</div>
<div class="imp-faixa"></div>

<div class="imp-info">
    <div><strong>Cliente:</strong> <?= h($pedido['cliente_nome']) ?></div>
    <div><strong>Criado em:</strong> <?= h(formatar_data($pedido['criado_em'])) ?></div>
    <div><strong>Última atualização:</strong> <?= h(formatar_data($pedido['atualizado_em'])) ?></div>
    <div><strong>Status:</strong> <span class="imp-selo imp-selo-<?= h($pedido['status']) ?>"><?= h(rotulo_status($pedido['status'])) ?></span></div>
</div>

<table class="imp-tabela">
    <thead>
        <tr>
            <th>Modelo/Nome</th>
            <th>Nº Série</th>
            <th class="num">Qtd.</th>
            <th>Situação</th>
            <th class="num">Qtd. Feita</th>
            <th class="center">Feito</th>
            <th>Profissionais</th>
            <th>Observação (ADM)</th>
            <th>Carcaça</th>
            <th>Caixas</th>
            <th>Anéis</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($itens as $item): ?>
        <tr>
            <td><?= h($item['modelo_nome']) ?></td>
            <td><?= h($item['numero_serie'] ?? '-') ?></td>
            <td class="num"><?= (int)$item['quantidade'] ?></td>
            <td><span class="imp-selo imp-selo-<?= h($item['situacao']) ?>"><?= h(rotulo_situacao($item['situacao'])) ?></span></td>
            <td class="num"><?= (int)$item['quantidade_feita'] ?></td>
            <td class="center"><?= $item['feito'] ? '<span style="color:#1a7a3d; font-weight:700;">✅</span>' : '☐' ?></td>
            <td><?= h($item['profissionais_nomes'] ?? '-') ?></td>
            <td><?= h($item['observacao_adm'] ?? '-') ?></td>
            <td><?= h($item['carcaca_utilizada'] ?? '-') ?></td>
            <td><?= h($item['caixas_utilizadas'] ?? '-') ?></td>
            <td><?= h($item['aneis'] ?? '-') ?></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<div class="imp-totais">
    <div>Total solicitado: <?= $totalSolicitado ?> peças</div>
    <div>Total feito: <?= $totalFeito ?> peças</div>
</div>

<div class="imp-rodape">Shalom Radiadores — documento interno, gerado automaticamente pelo sistema.</div>

</body>
</html>
