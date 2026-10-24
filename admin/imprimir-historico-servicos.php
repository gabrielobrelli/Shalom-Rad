<?php
/**
 * admin/imprimir-historico-servicos.php — Extrato/nota (informal, não é
 * um documento fiscal) com os serviços de um dia, pronto pra imprimir.
 */
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/servicos_repo.php';

exigir_login_admin();

$data = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($_GET['data'] ?? '')) ? $_GET['data'] : date('Y-m-d');
$servicos = listar_servicos_do_dia($data);
$totais = totais_do_periodo($data, $data);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<title>Extrato de serviços — <?= h(date('d/m/Y', strtotime($data))) ?></title>
<link rel="icon" type="image/png" href="../assets/img/oficina.png">
<link rel="stylesheet" href="../assets/css/print.css">
</head>
<body>

<div class="imp-acoes-tela"><button onclick="window.print()">🖨️ Imprimir esta página</button></div>

<div class="imp-cabecalho">
    <div class="imp-marca">Shalom Radiadores — Extrato de Serviços</div>
    <div class="imp-meta">Dia <?= h(date('d/m/Y', strtotime($data))) ?><br>Impresso em <?= h(date('d/m/Y H:i')) ?></div>
</div>
<div class="imp-faixa"></div>

<?php if (empty($servicos)): ?>
    <p>Nenhum serviço lançado neste dia.</p>
<?php else: ?>
    <?php foreach ($servicos as $s): ?>
        <div class="imp-extrato-item">
            <div class="imp-extrato-item__topo">
                <strong><?= h($s['carro_cliente']) ?></strong>
                <span><?= h(formatar_dinheiro((float)$s['valor_cobrado'] - (float)$s['desconto'])) ?></span>
            </div>
            <?php if (!empty($s['descricao'])): ?><div class="imp-extrato-item__desc"><?= nl2br(h($s['descricao'])) ?></div><?php endif; ?>
            <?php if (!empty($s['observacao_adm'])): ?><div class="imp-extrato-item__desc">🗒️ <?= nl2br(h($s['observacao_adm'])) ?></div><?php endif; ?>
            <div class="imp-extrato-item__meta">
                <?php if (!empty($s['profissionais_nomes'])): ?>Profissionais: <?= h($s['profissionais_nomes']) ?> · <?php endif; ?>
                Cobrado: <?= h(formatar_dinheiro((float)$s['valor_cobrado'])) ?>
                <?php if ((float)$s['desconto'] > 0): ?> · Desconto: <?= h(formatar_dinheiro((float)$s['desconto'])) ?><?php endif; ?>
                <?php foreach ($s['pagamentos'] as $p): ?>
                    · <?= h(rotulo_forma_pagamento($p['forma'])) ?>: <?= h(formatar_dinheiro((float)$p['valor'])) ?><?= $p['parcelas'] ? ' (' . (int)$p['parcelas'] . 'x)' : '' ?><?= !empty($p['conta']) ? ' [' . h($p['conta']) . ']' : '' ?>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

<div class="imp-totais">
    <div>Serviços: <?= $totais['quantidade'] ?></div>
    <div>Total bruto: <?= h(formatar_dinheiro($totais['bruto'])) ?></div>
    <div>Total líquido: <?= h(formatar_dinheiro($totais['liquido'])) ?></div>
</div>

<div class="imp-rodape">Shalom Radiadores — documento interno, não é nota fiscal.</div>

</body>
</html>
