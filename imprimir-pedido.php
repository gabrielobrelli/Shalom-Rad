<?php
/**
 * imprimir-pedido.php — Versão para impressão do pedido, na visão do
 * CLIENTE (sem campos internos da administração). Usa print.css, que é
 * independente do style.css do site (o cabeçalho/rodapé do site não
 * aparecem aqui de propósito).
 */
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/pedido_repo.php';

$token = trim((string)($_GET['token'] ?? ''));
if ($token === '') { die('Link inválido.'); }

$pedido = buscar_pedido_por_token($token);
if (!$pedido) { die('Pedido não encontrado.'); }

$itens = listar_itens_pedido_para_impressao((int)$pedido['id']);
$totalSolicitado = total_solicitado($itens);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<title>Impressão — Pedido de <?= h($pedido['cliente_nome']) ?></title>
<link rel="icon" type="image/png" href="assets/img/oficina.png">
<link rel="stylesheet" href="assets/css/print.css">
</head>
<body>

<div class="imp-acoes-tela">
    <button onclick="window.print()">🖨️ Imprimir esta página</button>
</div>

<div class="imp-cabecalho">
    <div class="imp-marca">Shalom Radiadores</div>
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
            <th>Nº de série</th>
            <th class="num">Quantidade</th>
            <th>Situação</th>
            <th>Status</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($itens as $item): ?>
        <tr>
            <td><?= h($item['modelo_nome']) ?></td>
            <td><?= h($item['numero_serie'] ?? '-') ?></td>
            <td class="num"><?= h((string)$item['quantidade']) ?></td>
            <td><span class="imp-selo imp-selo-<?= h($item['situacao']) ?>"><?= h(rotulo_situacao($item['situacao'])) ?></span></td>
            <td><?= $item['feito'] ? '<span style="color:#1a7a3d; font-weight:700;">✅ Pronto</span>' : '<span style="color:#8a6d00;">⏳ Pendente</span>' ?></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<div class="imp-totais">
    <div>Total solicitado: <?= h((string)$totalSolicitado) ?> peças</div>
</div>

<div class="imp-rodape">Shalom Radiadores — documento gerado automaticamente pelo sistema.</div>

</body>
</html>
