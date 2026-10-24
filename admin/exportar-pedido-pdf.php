<?php
/**
 * admin/exportar-pedido-pdf.php — Exportação em PDF do pedido, na visão
 * da ADMINISTRAÇÃO (inclui campos internos de produção).
 */
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/pedido_repo.php';
require_once __DIR__ . '/../includes/SimplePdfWriter.php';

exigir_login_admin();

$id = inteiro_positivo($_GET['id'] ?? 0);
$pedido = $id ? buscar_pedido_por_id($id) : null;
if (!$pedido) { die('Pedido não encontrado.'); }

$itens = listar_itens_pedido_para_impressao($id);

$pdf = new SimplePdfWriter('Shalom Radiadores', 'Pedido de ' . $pedido['cliente_nome'] . ' (via administração)');
$pdf->addInfo('Cliente', $pedido['cliente_nome']);
$pdf->addInfo('Status', rotulo_status($pedido['status']));
$pdf->addInfo('Atualizado em', formatar_data($pedido['atualizado_em']));
$pdf->addEspaco(6);

$linhas = array_map(fn($i) => [
    $i['modelo_nome'],
    (string)$i['quantidade'],
    (string)$i['quantidade_feita'],
    $i['feito'] ? 'PRONTO' : 'PENDENTE',
    $i['profissionais_nomes'] ?: '-',
    $i['observacao_adm'] ?: '-',
    trim(($i['carcaca_utilizada'] ?: '') . ' ' . ($i['caixas_utilizadas'] ? '/ ' . $i['caixas_utilizadas'] : '') . ($i['aneis'] ? ' / ' . $i['aneis'] : '')) ?: '-',
], $itens);

$destaques = [];
foreach ($itens as $indice => $i) {
    if ($i['feito']) {
        $destaques[] = ['linha' => $indice, 'coluna' => 3, 'cor' => 'verde'];
    }
}

$pdf->addTabela(
    ['Modelo/Nome', 'Qtd.', 'Feita', 'Status', 'Profissionais', 'Observação (ADM)', 'Peças utilizadas'],
    $linhas,
    [0.16, 0.06, 0.07, 0.11, 0.16, 0.2, 0.24],
    $destaques
);
$pdf->addTotais([
    'Total solicitado: ' . total_solicitado($itens) . ' peças',
    'Total feito: ' . total_feito($itens) . ' peças',
]);
$pdf->addRodape('Shalom Radiadores — documento interno, gerado automaticamente pelo sistema.');

$nomeArquivo = 'admin-pedido-' . preg_replace('/[^a-z0-9]+/i', '-', $pedido['cliente_nome']) . '.pdf';
$pdf->download($nomeArquivo);
