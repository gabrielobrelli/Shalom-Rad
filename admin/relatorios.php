<?php
/**
 * admin/relatorios.php — Relatório financeiro dos serviços: quanto foi
 * arrecadado num período (dia, semana, mês, semestre ou ano), com o
 * detalhamento por forma de pagamento (Pix, dinheiro, débito, crédito).
 */
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/servicos_repo.php';

exigir_login_admin();

$periodo = in_array($_GET['periodo'] ?? '', ['dia','semana','mes','semestre','ano'], true) ? $_GET['periodo'] : 'mes';
$data = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($_GET['data'] ?? '')) ? $_GET['data'] : date('Y-m-d');

$ano = (int)date('Y', strtotime($data));
$mes = (int)date('n', strtotime($data));

switch ($periodo) {
    case 'dia':
        $inicio = $fim = $data;
        $rotuloPeriodo = 'Dia ' . date('d/m/Y', strtotime($data));
        break;
    case 'semana':
        $diaSemana = (int)date('w', strtotime($data));
        $inicio = date('Y-m-d', strtotime($data . ' -' . $diaSemana . ' days'));
        $fim = date('Y-m-d', strtotime($inicio . ' +6 days'));
        $rotuloPeriodo = 'Semana de ' . date('d/m', strtotime($inicio)) . ' a ' . date('d/m/Y', strtotime($fim));
        break;
    case 'semestre':
        $inicioMes = $mes <= 6 ? 1 : 7;
        $fimMes = $mes <= 6 ? 6 : 12;
        $inicio = sprintf('%04d-%02d-01', $ano, $inicioMes);
        $fim = date('Y-m-t', strtotime(sprintf('%04d-%02d-01', $ano, $fimMes)));
        $rotuloPeriodo = ($mes <= 6 ? '1º semestre' : '2º semestre') . ' de ' . $ano;
        break;
    case 'ano':
        $inicio = sprintf('%04d-01-01', $ano);
        $fim = sprintf('%04d-12-31', $ano);
        $rotuloPeriodo = 'Ano de ' . $ano;
        break;
    default: // mes
        $inicio = sprintf('%04d-%02d-01', $ano, $mes);
        $fim = date('Y-m-t', strtotime($inicio));
        $nomesMes = ['', 'Janeiro','Fevereiro','Março','Abril','Maio','Junho','Julho','Agosto','Setembro','Outubro','Novembro','Dezembro'];
        $rotuloPeriodo = $nomesMes[$mes] . ' de ' . $ano;
}

$totais = totais_do_periodo($inicio, $fim);

$tituloPagina = 'Relatórios financeiros — Shalom Radiadores';
$baseAssets = '../';
$paginaAtual = 'admin_servicos';
require __DIR__ . '/../includes/header.php';
?>

<main class="container">
    <div class="cartao">
        <h1>💰 Relatórios financeiros</h1>
        <p class="subtitulo">Quanto foi arrecadado com os serviços em cada período.</p>

        <form method="get" action="relatorios.php" class="linha-campos" style="align-items:end;">
            <div class="campo">
                <label for="periodo">Período</label>
                <select id="periodo" name="periodo">
                    <option value="dia" <?= $periodo === 'dia' ? 'selected' : '' ?>>Dia</option>
                    <option value="semana" <?= $periodo === 'semana' ? 'selected' : '' ?>>Semana</option>
                    <option value="mes" <?= $periodo === 'mes' ? 'selected' : '' ?>>Mês</option>
                    <option value="semestre" <?= $periodo === 'semestre' ? 'selected' : '' ?>>Semestre</option>
                    <option value="ano" <?= $periodo === 'ano' ? 'selected' : '' ?>>Ano</option>
                </select>
            </div>
            <div class="campo">
                <label for="data">Data de referência</label>
                <input type="date" id="data" name="data" value="<?= h($data) ?>">
            </div>
            <div class="campo"><button type="submit" class="btn btn-secundario btn-bloco">Ver relatório</button></div>
        </form>
    </div>

    <div class="cartao">
        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
            <h2 style="margin:0;"><?= h($rotuloPeriodo) ?></h2>
            <a href="imprimir-relatorio.php?periodo=<?= h($periodo) ?>&data=<?= h($data) ?>" target="_blank" class="btn btn-secundario no-print">🖨️ Imprimir</a>
        </div>

        <div class="totais" style="flex-wrap:wrap;">
            <div class="item-total"><div class="rotulo">Serviços realizados</div><div class="valor"><?= $totais['quantidade'] ?></div></div>
            <div class="item-total"><div class="rotulo">Total bruto</div><div class="valor"><?= h(formatar_dinheiro($totais['bruto'])) ?></div></div>
            <div class="item-total"><div class="rotulo">Descontos</div><div class="valor"><?= h(formatar_dinheiro($totais['descontos'])) ?></div></div>
            <div class="item-total"><div class="rotulo">Total líquido</div><div class="valor"><?= h(formatar_dinheiro($totais['liquido'])) ?></div></div>
        </div>

        <h2 style="margin-top:24px;">Por forma de pagamento</h2>
        <?php if (empty($totais['por_forma'])): ?>
            <div class="alerta alerta-info">ℹ️ Nenhum pagamento registrado neste período.</div>
        <?php else: ?>
            <div class="tabela-itens-wrap">
                <table class="tabela-itens">
                    <thead><tr><th>Forma</th><th class="num">Total recebido</th></tr></thead>
                    <tbody>
                        <?php foreach ($totais['por_forma'] as $forma => $valor): ?>
                            <tr>
                                <td data-rotulo="Forma"><?= h(rotulo_forma_pagamento($forma)) ?></td>
                                <td data-rotulo="Total" class="num"><?= h(formatar_dinheiro($valor)) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</main>

<?php require __DIR__ . '/../includes/footer.php'; ?>
