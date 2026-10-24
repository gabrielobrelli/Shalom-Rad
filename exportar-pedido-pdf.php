<?php
/**
 * exportar-pedido-pdf.php — Gera o PDF do pedido na visão do CLIENTE
 * (sem os campos internos da administração). Usa a classe SimplePdfWriter,
 * que monta o arquivo .pdf sem depender de bibliotecas externas.
 */
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/pedido_repo.php';
require_once __DIR__ . '/includes/SimplePdfWriter.php';

$token = trim((string)($_GET['token'] ?? ''));
if ($token === '') { die('Link inválido.'); }

$pedido = buscar_pedido_por_token($token);
if (!$pedido) { die('Pedido não encontrado.'); }

$itens = listar_itens_pedido_para_impressao((int)$pedido['id']);

$pdf = new SimplePdfWriter('Shalom Radiadores', 'Pedido de ' . $pedido['cliente_nome']);
$pdf->addInfo('Cliente', $pedido['cliente_nome']);
$pdf->addInfo('Criado em', formatar_data($pedido['criado_em']));
$pdf->addInfo('Atualizado em', formatar_data($pedido['atualizado_em']));
$pdf->addInfo('Status', rotulo_status($pedido['status']));
$pdf->addEspaco(6);

$linhas = array_map(fn($i) => [
    $i['modelo_nome'],
    $i['numero_serie'] ?: '-',
    (string)$i['quantidade'],
    rotulo_situacao($i['situacao']),
    $i['feito'] ? 'PRONTO' : 'PENDENTE',
], $itens);

$destaques = [];
foreach ($itens as $indice => $i) {
    if ($i['feito']) {
        $destaques[] = ['linha' => $indice, 'coluna' => 4, 'cor' => 'verde'];
    }
}

$pdf->addTabela(['Modelo/Nome', 'Nº de série', 'Qtd.', 'Situação', 'Status'], $linhas, [0.32, 0.22, 0.12, 0.17, 0.17], $destaques);
$pdf->addTotais(['Total solicitado: ' . total_solicitado($itens) . ' peças']);
$pdf->addRodape('Shalom Radiadores — documento gerado automaticamente pelo sistema.');

$nomeArquivo = 'pedido-' . preg_replace('/[^a-z0-9]+/i', '-', $pedido['cliente_nome']) . '.pdf';
$pdf->download($nomeArquivo);
