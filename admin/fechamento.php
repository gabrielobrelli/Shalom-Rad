<?php
/**
 * admin/fechamento.php — Relatório de fechamento: junta serviços, peças
 * de radiador concluídas, movimentações de estoque e produção por
 * profissional num único resumo, para qualquer período (dia, semana,
 * quinzena, mês, trimestre, semestre, ano ou datas escolhidas). Botão
 * de "final do dia" e também para qualquer data/período já passado.
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

// Protegido com try/catch: se alguma migração de banco ainda não tiver
// sido rodada (ex.: valor do estoque, número de série), isso mostra o
// motivo exato em vez de deixar a página em branco.
$erroFechamento = null;
$dados = null;
try {
    $dados = compilar_dados_fechamento($periodo['inicio'], $periodo['fim']);
} catch (Throwable $e) {
    $erroFechamento = $e->getMessage();
}

$queryExport = http_build_query([
    'tipo' => $tipo, 'data' => $data,
    'data_inicio' => $dataInicioPersonalizada, 'data_fim' => $dataFimPersonalizada,
]);

$rotulosPeriodo = [
    'dia' => 'Dia', 'semana' => 'Semana', 'quinzena' => 'Quinzena', 'mes' => 'Mês',
    'trimestre' => 'Trimestre', 'semestre' => 'Semestre', 'ano' => 'Ano', 'personalizado' => 'Período personalizado',
];

$tituloPagina = 'Fechamento — Shalom Radiadores';
$baseAssets = '../';
$paginaAtual = 'admin_servicos';
require __DIR__ . '/../includes/header.php';
?>

<main class="container largo">
    <div class="cartao">
        <div style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:10px;">
            <div>
                <h1>🧾 Fechamento</h1>
                <p class="subtitulo">Resumo completo do período: serviços, estoque e produção.</p>
            </div>
            <div class="acoes no-print" style="margin-top:0;">
                <a href="imprimir-fechamento.php?<?= h($queryExport) ?>" target="_blank" class="btn btn-secundario">🖨️ Imprimir</a>
                <a href="exportar-fechamento-pdf.php?<?= h($queryExport) ?>" class="btn btn-primario">📄 Baixar PDF</a>
            </div>
        </div>

        <form method="get" action="fechamento.php" class="linha-campos" style="align-items:end;">
            <div class="campo">
                <label for="tipo">Período</label>
                <select id="tipo" name="tipo" onchange="
                    document.getElementById('campo-data-ref').style.display = this.value === 'personalizado' ? 'none' : 'block';
                    document.getElementById('campos-personalizado').style.display = this.value === 'personalizado' ? 'flex' : 'none';
                ">
                    <?php foreach ($rotulosPeriodo as $valor => $rotulo): ?>
                        <option value="<?= $valor ?>" <?= $tipo === $valor ? 'selected' : '' ?>><?= h($rotulo) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="campo" id="campo-data-ref" style="display:<?= $tipo === 'personalizado' ? 'none' : 'block' ?>;">
                <label for="data">Uma data dentro do período</label>
                <input type="date" id="data" name="data" value="<?= h($data) ?>">
            </div>
            <div class="linha-campos" id="campos-personalizado" style="display:<?= $tipo === 'personalizado' ? 'flex' : 'none' ?>; flex:2;">
                <div class="campo"><label for="data_inicio">De</label><input type="date" id="data_inicio" name="data_inicio" value="<?= h($dataInicioPersonalizada) ?>"></div>
                <div class="campo"><label for="data_fim">Até</label><input type="date" id="data_fim" name="data_fim" value="<?= h($dataFimPersonalizada) ?>"></div>
            </div>
            <div class="campo"><button type="submit" class="btn btn-secundario btn-bloco">Gerar fechamento</button></div>
        </form>
    </div>

    <?php if ($erroFechamento): ?>
        <div class="cartao">
            <div class="alerta alerta-erro">
                ⚠️ Não foi possível montar o fechamento: <?= h($erroFechamento) ?><br>
                <small>Isso costuma acontecer quando alguma migração de banco (valor do estoque, número de série, lembretes) ainda não foi rodada no phpMyAdmin. Confira as migrações mais recentes na pasta <code>documentacao/</code>.</small>
            </div>
        </div>
    <?php else: ?>

    <div class="cartao">
        <h2 style="margin-top:0;"><?= h($periodo['rotulo']) ?></h2>
        <div class="alerta alerta-info"><?= h($dados['resumo_textual']) ?></div>

        <div class="totais" style="flex-wrap:wrap;">
            <div class="item-total"><div class="rotulo">Serviços</div><div class="valor"><?= $dados['totais_servicos']['quantidade'] ?></div></div>
            <div class="item-total"><div class="rotulo">Total líquido</div><div class="valor"><?= h(formatar_dinheiro($dados['totais_servicos']['liquido'])) ?></div></div>
            <div class="item-total"><div class="rotulo">Peças concluídas</div><div class="valor"><?= count($dados['pecas']) ?></div></div>
            <div class="item-total"><div class="rotulo">Movimentações de estoque</div><div class="valor"><?= count($dados['movimentacoes']) ?></div></div>
        </div>
    </div>

    <div class="cartao">
        <h2 style="margin-top:0;">📈 Evolução no período</h2>
        <div class="grafico-wrap"><?= renderizar_grafico_svg($dados['evolucao_diaria']) ?></div>
    </div>

    <div class="cartao">
        <h2 style="margin-top:0;">💳 Por forma de pagamento</h2>
        <?php if (empty($dados['totais_servicos']['por_forma'])): ?>
            <div class="alerta alerta-info">ℹ️ Nenhum pagamento registrado neste período.</div>
        <?php else: ?>
            <div class="tabela-itens-wrap">
                <table class="tabela-itens">
                    <thead><tr><th>Forma</th><th class="num">Total recebido</th></tr></thead>
                    <tbody>
                        <?php foreach ($dados['totais_servicos']['por_forma'] as $forma => $valor): ?>
                            <tr><td data-rotulo="Forma"><?= h(rotulo_forma_pagamento($forma)) ?></td><td data-rotulo="Total" class="num"><?= h(formatar_dinheiro($valor)) ?></td></tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <div class="cartao">
        <h2 style="margin-top:0;">📅 Serviços do período</h2>
        <?php if (empty($dados['servicos'])): ?>
            <div class="alerta alerta-info">ℹ️ Nenhum serviço lançado neste período.</div>
        <?php else: ?>
            <div class="tabela-itens-wrap">
                <table class="tabela-itens">
                    <thead><tr><th>Data</th><th>Carro/Cliente</th><th>Profissionais</th><th>Observação</th><th class="num">Cobrado</th><th class="num">Líquido</th></tr></thead>
                    <tbody>
                        <?php foreach ($dados['servicos'] as $s): ?>
                            <tr>
                                <td data-rotulo="Data"><?= h(date('d/m/Y', strtotime($s['data_servico']))) ?></td>
                                <td data-rotulo="Carro/Cliente"><?= h($s['carro_cliente']) ?></td>
                                <td data-rotulo="Profissionais"><?= h($s['profissionais_nomes'] ?? '—') ?></td>
                                <td data-rotulo="Observação"><?= h($s['observacao_adm'] ?? '—') ?></td>
                                <td data-rotulo="Cobrado" class="num"><?= h(formatar_dinheiro((float)$s['valor_cobrado'])) ?></td>
                                <td data-rotulo="Líquido" class="num"><?= h(formatar_dinheiro((float)$s['valor_cobrado'] - (float)$s['desconto'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <div class="cartao">
        <h2 style="margin-top:0;">🔧 Radiadores concluídos (pedidos de clientes)</h2>
        <?php if (empty($dados['pecas'])): ?>
            <div class="alerta alerta-info">ℹ️ Nenhuma peça concluída neste período.</div>
        <?php else: ?>
            <div class="tabela-itens-wrap">
                <table class="tabela-itens">
                    <thead><tr><th>Data</th><th>Cliente</th><th>Peça/Modelo</th><th class="num">Quantidade</th><th>Quem fez</th><th>Observação</th></tr></thead>
                    <tbody>
                        <?php foreach ($dados['pecas'] as $p): ?>
                            <tr>
                                <td data-rotulo="Data"><?= h(formatar_data($p['feito_em'])) ?></td>
                                <td data-rotulo="Cliente"><?= h($p['cliente_nome']) ?></td>
                                <td data-rotulo="Peça/Modelo"><?= h($p['modelo_nome']) ?></td>
                                <td data-rotulo="Quantidade" class="num"><?= (int)$p['quantidade_feita'] ?></td>
                                <td data-rotulo="Quem fez"><?= h($p['profissionais_nomes'] ?? '—') ?></td>
                                <td data-rotulo="Observação"><?= h($p['observacao_adm'] ?? '—') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <div class="cartao">
        <h2 style="margin-top:0;">📦 Movimentações de estoque</h2>
        <?php if (empty($dados['movimentacoes'])): ?>
            <div class="alerta alerta-info">ℹ️ Nenhuma movimentação de estoque neste período.</div>
        <?php else: ?>
            <div class="tabela-itens-wrap">
                <table class="tabela-itens">
                    <thead><tr><th>Data</th><th>Item</th><th>Tipo</th><th class="num">Quantidade</th></tr></thead>
                    <tbody>
                        <?php foreach ($dados['movimentacoes'] as $m):
                            $rotuloTipo = ['entrada' => '⬆️ Entrada', 'saida' => '⬇️ Saída', 'cadastro' => '🆕 Cadastro'][$m['tipo']] ?? h($m['tipo']);
                        ?>
                            <tr>
                                <td data-rotulo="Data"><?= h(formatar_data($m['criado_em'])) ?></td>
                                <td data-rotulo="Item"><?= h($m['item_nome']) ?><?= !empty($m['marca']) ? ' (' . h($m['marca']) . ')' : '' ?></td>
                                <td data-rotulo="Tipo"><?= $rotuloTipo ?></td>
                                <td data-rotulo="Quantidade" class="num"><?= (int)$m['quantidade'] ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <div class="cartao">
        <h2 style="margin-top:0;">🏆 Produção por profissional no período</h2>
        <?php if (empty($dados['ranking'])): ?>
            <div class="alerta alerta-info">ℹ️ Nenhuma produção registrada por profissional neste período.</div>
        <?php else: ?>
            <div class="tabela-itens-wrap">
                <table class="tabela-itens">
                    <thead><tr><th>Profissional</th><th class="num">Peças</th><th class="num">Unidades</th><th class="num">Serviços</th></tr></thead>
                    <tbody>
                        <?php foreach ($dados['ranking'] as $r): ?>
                            <tr>
                                <td data-rotulo="Profissional"><?= h($r['nome']) ?></td>
                                <td data-rotulo="Peças" class="num"><?= (int)$r['total_pecas_feitas'] ?></td>
                                <td data-rotulo="Unidades" class="num"><?= (int)$r['total_unidades_feitas'] ?></td>
                                <td data-rotulo="Serviços" class="num"><?= (int)$r['total_servicos'] ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <?php endif; ?>
</main>

<?php require __DIR__ . '/../includes/footer.php'; ?>
