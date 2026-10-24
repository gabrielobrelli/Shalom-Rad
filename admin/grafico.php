<?php
/**
 * admin/grafico.php — Gráfico comparativo de faturamento (serviços) por
 * semana, mês ou um período personalizado escolhido pelo usuário.
 */
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/servicos_repo.php';

exigir_login_admin();

$tipo = in_array($_GET['tipo'] ?? '', ['semana', 'mes', 'personalizado'], true) ? $_GET['tipo'] : 'mes';
$quantidade = max(2, min(24, inteiro_positivo($_GET['quantidade'] ?? 6) ?: 6));
$dataInicio = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($_GET['data_inicio'] ?? '')) ? $_GET['data_inicio'] : date('Y-m-d', strtotime('-30 days'));
$dataFim = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($_GET['data_fim'] ?? '')) ? $_GET['data_fim'] : date('Y-m-d');

$periodos = construir_periodos_comparacao($tipo, $quantidade, $dataInicio, $dataFim);
$totalGeral = array_sum(array_column($periodos, 'total'));

$queryExport = http_build_query(['tipo' => $tipo, 'quantidade' => $quantidade, 'data_inicio' => $dataInicio, 'data_fim' => $dataFim]);

$tituloPagina = 'Gráfico comparativo — Shalom Radiadores';
$baseAssets = '../';
$paginaAtual = 'admin_servicos';
require __DIR__ . '/../includes/header.php';
?>

<main class="container largo">
    <div class="cartao">
        <div style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:10px;">
            <div>
                <h1>📊 Gráfico comparativo</h1>
                <p class="subtitulo">Compare o faturamento líquido de serviços por semana, mês, ou escolha seu próprio período.</p>
            </div>
            <div class="acoes no-print" style="margin-top:0;">
                <a href="imprimir-grafico.php?<?= h($queryExport) ?>" target="_blank" class="btn btn-secundario">🖨️ Imprimir</a>
                <a href="exportar-grafico-pdf.php?<?= h($queryExport) ?>" class="btn btn-secundario">📄 PDF</a>
            </div>
        </div>

        <form method="get" action="grafico.php" class="linha-campos" style="align-items:end;">
            <div class="campo">
                <label for="tipo">Comparar por</label>
                <select id="tipo" name="tipo" onchange="document.getElementById('campos-personalizado').style.display = this.value === 'personalizado' ? 'flex' : 'none';">
                    <option value="semana" <?= $tipo === 'semana' ? 'selected' : '' ?>>Semana</option>
                    <option value="mes" <?= $tipo === 'mes' ? 'selected' : '' ?>>Mês</option>
                    <option value="personalizado" <?= $tipo === 'personalizado' ? 'selected' : '' ?>>Período personalizado</option>
                </select>
            </div>
            <div class="campo" id="campo-quantidade" style="display:<?= $tipo === 'personalizado' ? 'none' : 'block' ?>;">
                <label for="quantidade">Quantos períodos</label>
                <input type="number" id="quantidade" name="quantidade" min="2" max="24" value="<?= $quantidade ?>">
            </div>
            <div class="linha-campos" id="campos-personalizado" style="display:<?= $tipo === 'personalizado' ? 'flex' : 'none' ?>; flex:2;">
                <div class="campo"><label for="data_inicio">De</label><input type="date" id="data_inicio" name="data_inicio" value="<?= h($dataInicio) ?>"></div>
                <div class="campo"><label for="data_fim">Até</label><input type="date" id="data_fim" name="data_fim" value="<?= h($dataFim) ?>"></div>
            </div>
            <div class="campo"><button type="submit" class="btn btn-secundario btn-bloco">Atualizar gráfico</button></div>
        </form>
    </div>

    <div class="cartao">
        <div class="totais" style="margin-bottom:18px;">
            <div class="item-total"><div class="rotulo">Total do período mostrado</div><div class="valor"><?= h(formatar_dinheiro($totalGeral)) ?></div></div>
        </div>
        <div class="grafico-wrap">
            <?= renderizar_grafico_svg($periodos) ?>
        </div>
    </div>
</main>

<?php require __DIR__ . '/../includes/footer.php'; ?>
