<?php
/**
 * admin/imprimir-fechamento.php — Versão para impressão do fechamento
 * completo de um período.
 */
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/relatorio_repo.php';

exigir_login_admin();

$tipo = in_array($_GET['tipo'] ?? '', TIPOS_PERIODO_FECHAMENTO, true) ? $_GET['tipo'] : 'dia';
$data = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($_GET['data'] ?? '')) ? $_GET['data'] : date('Y-m-d');
$dataInicioPersonalizada = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($_GET['data_inicio'] ?? '')) ? $_GET['data_inicio'] : date('Y-m-d', strtotime('-7 days'));
$dataFimPersonalizada = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($_GET['data_fim'] ?? '')) ? $_GET['data_fim'] : date('Y-m-d');

$periodo = calcular_periodo_fechamento($tipo, $data, $dataInicioPersonalizada, $dataFimPersonalizada);

try {
    $dados = compilar_dados_fechamento($periodo['inicio'], $periodo['fim']);
} catch (Throwable $e) {
    echo '<!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8"><title>Erro — Fechamento</title></head><body style="font-family:sans-serif;padding:24px;">';
    echo '<h2>⚠️ Não foi possível montar o fechamento</h2><p>' . h($e->getMessage()) . '</p>';
    echo '<p>Isso costuma acontecer quando alguma migração de banco ainda não foi rodada no phpMyAdmin.</p>';
    echo '</body></html>';
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<title>Fechamento — <?= h($periodo['rotulo']) ?></title>
<link rel="icon" type="image/png" href="../assets/img/oficina.png">
<link rel="stylesheet" href="../assets/css/print.css">
</head>
<body>

<div class="imp-acoes-tela"><button onclick="window.print()">🖨️ Imprimir esta página</button></div>

<div class="imp-cabecalho">
    <div class="imp-marca">Shalom Radiadores — Fechamento</div>
    <div class="imp-meta"><?= h($periodo['rotulo']) ?><br>Impresso em <?= h(date('d/m/Y H:i')) ?></div>
</div>
<div class="imp-faixa"></div>

<p><?= h($dados['resumo_textual']) ?></p>

<div class="imp-totais">
    <div>Serviços: <?= $dados['totais_servicos']['quantidade'] ?></div>
    <div>Total líquido: <?= h(formatar_dinheiro($dados['totais_servicos']['liquido'])) ?></div>
    <div>Peças concluídas: <?= count($dados['pecas']) ?></div>
    <div>Movimentações de estoque: <?= count($dados['movimentacoes']) ?></div>
</div>

<h3 style="margin-top:20px;">Por forma de pagamento</h3>
<table class="imp-tabela">
    <thead><tr><th>Forma</th><th class="num">Total recebido</th></tr></thead>
    <tbody>
        <?php foreach ($dados['totais_servicos']['por_forma'] as $forma => $valor): ?>
            <tr><td><?= h(rotulo_forma_pagamento($forma)) ?></td><td class="num"><?= h(formatar_dinheiro($valor)) ?></td></tr>
        <?php endforeach; ?>
        <?php if (empty($dados['totais_servicos']['por_forma'])): ?><tr><td colspan="2">Nenhum pagamento neste período.</td></tr><?php endif; ?>
    </tbody>
</table>

<h3 style="margin-top:20px;">Serviços do período</h3>
<table class="imp-tabela">
    <thead><tr><th>Data</th><th>Carro/Cliente</th><th>Profissionais</th><th>Observação</th><th class="num">Cobrado</th><th class="num">Líquido</th></tr></thead>
    <tbody>
        <?php foreach ($dados['servicos'] as $s): ?>
            <tr>
                <td><?= h(date('d/m/Y', strtotime($s['data_servico']))) ?></td>
                <td><?= h($s['carro_cliente']) ?></td>
                <td><?= h($s['profissionais_nomes'] ?? '—') ?></td>
                <td><?= h($s['observacao_adm'] ?? '—') ?></td>
                <td class="num"><?= h(formatar_dinheiro((float)$s['valor_cobrado'])) ?></td>
                <td class="num"><?= h(formatar_dinheiro((float)$s['valor_cobrado'] - (float)$s['desconto'])) ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($dados['servicos'])): ?><tr><td colspan="6">Nenhum serviço neste período.</td></tr><?php endif; ?>
    </tbody>
</table>

<h3 style="margin-top:20px;">Radiadores concluídos (pedidos de clientes)</h3>
<table class="imp-tabela">
    <thead><tr><th>Data</th><th>Cliente</th><th>Peça/Modelo</th><th class="num">Qtd.</th><th>Quem fez</th><th>Observação</th></tr></thead>
    <tbody>
        <?php foreach ($dados['pecas'] as $p): ?>
            <tr>
                <td><?= h(formatar_data($p['feito_em'])) ?></td>
                <td><?= h($p['cliente_nome']) ?></td>
                <td><?= h($p['modelo_nome']) ?></td>
                <td class="num"><?= (int)$p['quantidade_feita'] ?></td>
                <td><?= h($p['profissionais_nomes'] ?? '—') ?></td>
                <td><?= h($p['observacao_adm'] ?? '—') ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($dados['pecas'])): ?><tr><td colspan="6">Nenhuma peça concluída neste período.</td></tr><?php endif; ?>
    </tbody>
</table>

<h3 style="margin-top:20px;">Movimentações de estoque</h3>
<table class="imp-tabela">
    <thead><tr><th>Data</th><th>Item</th><th>Tipo</th><th class="num">Qtd.</th></tr></thead>
    <tbody>
        <?php foreach ($dados['movimentacoes'] as $m):
            $rotuloTipo = ['entrada' => 'Entrada', 'saida' => 'Saída', 'cadastro' => 'Cadastro'][$m['tipo']] ?? $m['tipo'];
        ?>
            <tr>
                <td><?= h(formatar_data($m['criado_em'])) ?></td>
                <td><?= h($m['item_nome']) ?><?= !empty($m['marca']) ? ' (' . h($m['marca']) . ')' : '' ?></td>
                <td><?= h($rotuloTipo) ?></td>
                <td class="num"><?= (int)$m['quantidade'] ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($dados['movimentacoes'])): ?><tr><td colspan="4">Nenhuma movimentação neste período.</td></tr><?php endif; ?>
    </tbody>
</table>

<h3 style="margin-top:20px;">Produção por profissional</h3>
<table class="imp-tabela">
    <thead><tr><th>Profissional</th><th class="num">Peças</th><th class="num">Unidades</th><th class="num">Serviços</th></tr></thead>
    <tbody>
        <?php foreach ($dados['ranking'] as $r): ?>
            <tr>
                <td><?= h($r['nome']) ?></td>
                <td class="num"><?= (int)$r['total_pecas_feitas'] ?></td>
                <td class="num"><?= (int)$r['total_unidades_feitas'] ?></td>
                <td class="num"><?= (int)$r['total_servicos'] ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($dados['ranking'])): ?><tr><td colspan="4">Nenhuma produção registrada.</td></tr><?php endif; ?>
    </tbody>
</table>

<div class="imp-rodape">Shalom Radiadores — documento interno, gerado automaticamente pelo sistema.</div>

</body>
</html>
