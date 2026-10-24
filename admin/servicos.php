<?php
/**
 * admin/servicos.php — Histórico/agenda de SERVIÇOS. Três visões
 * controladas pela URL (?visao=mes|semana|dia&data=YYYY-MM-DD):
 *   - mes    → grade de calendário do mês, com total do dia em cada célula
 *   - semana → 7 dias lado a lado, com os totais resumidos
 *   - dia    → histórico completo dos serviços do dia (sem formulário de
 *              lançamento aqui — isso fica em servico-novo.php, que tem
 *              tela própria, separada do histórico)
 */
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/servicos_repo.php';

exigir_login_admin();

$mensagem = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'excluir_servico') {
    exigir_csrf();
    excluir_servico(inteiro_positivo($_POST['servico_id'] ?? 0));
    $mensagem = 'Serviço excluído.';
}

$visao = in_array($_GET['visao'] ?? '', ['mes', 'semana', 'dia'], true) ? $_GET['visao'] : 'mes';
$data = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($_GET['data'] ?? '')) ? $_GET['data'] : date('Y-m-d');

if (isset($_GET['lancado'])) $mensagem = 'Serviço lançado com sucesso.';
if (isset($_GET['atualizado'])) $mensagem = 'Serviço atualizado com sucesso.';

$tituloPagina = 'Serviços — Shalom Radiadores';
$baseAssets = '../';
$paginaAtual = 'admin_servicos';
require __DIR__ . '/../includes/header.php';
?>

<main class="container largo">
    <div class="cartao">
        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
            <h1>📅 Serviços</h1>
            <div class="acoes no-print" style="margin-top:0;">
                <a href="grafico.php" class="btn btn-secundario">📊 Gráfico</a>
                <a href="relatorios.php" class="btn btn-secundario">💰 Relatórios</a>
                <a href="profissionais.php" class="btn btn-secundario">👷 Profissionais</a>
            </div>
        </div>

        <?php if ($mensagem): ?><div class="alerta alerta-sucesso">✅ <?= h($mensagem) ?></div><?php endif; ?>

        <div class="acoes" style="margin-top:0;">
            <a href="servicos.php?visao=mes&data=<?= h($data) ?>" class="btn <?= $visao === 'mes' ? 'btn-primario' : 'btn-secundario' ?>">🗓️ Mês</a>
            <a href="servicos.php?visao=semana&data=<?= h($data) ?>" class="btn <?= $visao === 'semana' ? 'btn-primario' : 'btn-secundario' ?>">📆 Semana</a>
            <a href="servicos.php?visao=dia&data=<?= h($data) ?>" class="btn <?= $visao === 'dia' ? 'btn-primario' : 'btn-secundario' ?>">📋 Dia</a>
        </div>
    </div>

    <?php if ($visao === 'mes'): ?>
        <?php
        $ano = (int)date('Y', strtotime($data));
        $mes = (int)date('n', strtotime($data));
        $primeiroDia = sprintf('%04d-%02d-01', $ano, $mes);
        $diasNoMes = (int)date('t', strtotime($primeiroDia));
        $ultimoDia = sprintf('%04d-%02d-%02d', $ano, $mes, $diasNoMes);
        $diaSemanaInicio = (int)date('w', strtotime($primeiroDia)); // 0 = domingo
        $resumo = resumo_por_dia_no_periodo($primeiroDia, $ultimoDia);
        $mesAnterior = date('Y-m-d', strtotime($primeiroDia . ' -1 day'));
        $mesProximo = date('Y-m-d', strtotime($ultimoDia . ' +1 day'));
        $nomesMes = ['', 'Janeiro','Fevereiro','Março','Abril','Maio','Junho','Julho','Agosto','Setembro','Outubro','Novembro','Dezembro'];
        ?>
        <div class="cartao">
            <div class="acoes" style="justify-content:space-between; margin-top:0;">
                <a href="servicos.php?visao=mes&data=<?= h($mesAnterior) ?>" class="btn btn-secundario">← Mês anterior</a>
                <h2 style="margin:0;"><?= h($nomesMes[$mes]) ?> de <?= $ano ?></h2>
                <a href="servicos.php?visao=mes&data=<?= h($mesProximo) ?>" class="btn btn-secundario">Próximo mês →</a>
            </div>

            <div class="calendario-mes">
                <?php foreach (['Dom','Seg','Ter','Qua','Qui','Sex','Sáb'] as $nomeDia): ?>
                    <div class="calendario-mes__cabecalho"><?= $nomeDia ?></div>
                <?php endforeach; ?>

                <?php for ($i = 0; $i < $diaSemanaInicio; $i++): ?>
                    <div class="calendario-mes__vazio"></div>
                <?php endfor; ?>

                <?php for ($dia = 1; $dia <= $diasNoMes; $dia++):
                    $dataCelula = sprintf('%04d-%02d-%02d', $ano, $mes, $dia);
                    $info = $resumo[$dataCelula] ?? null;
                    $hoje = $dataCelula === date('Y-m-d');
                ?>
                    <a href="servicos.php?visao=dia&data=<?= $dataCelula ?>" class="calendario-mes__dia <?= $hoje ? 'calendario-mes__dia--hoje' : '' ?>">
                        <span class="calendario-mes__numero"><?= $dia ?></span>
                        <?php if ($info): ?>
                            <span class="calendario-mes__resumo"><?= $info['qtd'] ?> serv. · <?= h(formatar_dinheiro($info['total'])) ?></span>
                        <?php endif; ?>
                    </a>
                <?php endfor; ?>
            </div>
        </div>

    <?php elseif ($visao === 'semana'): ?>
        <?php
        $diaSemana = (int)date('w', strtotime($data));
        $inicioSemana = date('Y-m-d', strtotime($data . ' -' . $diaSemana . ' days'));
        $fimSemana = date('Y-m-d', strtotime($inicioSemana . ' +6 days'));
        $resumo = resumo_por_dia_no_periodo($inicioSemana, $fimSemana);
        $semanaAnterior = date('Y-m-d', strtotime($inicioSemana . ' -7 days'));
        $semanaProxima = date('Y-m-d', strtotime($inicioSemana . ' +7 days'));
        ?>
        <div class="cartao">
            <div class="acoes" style="justify-content:space-between; margin-top:0;">
                <a href="servicos.php?visao=semana&data=<?= h($semanaAnterior) ?>" class="btn btn-secundario">← Semana anterior</a>
                <h2 style="margin:0;"><?= h(formatar_data($inicioSemana . ' 00:00:00')) ?> a <?= h(formatar_data($fimSemana . ' 00:00:00')) ?></h2>
                <a href="servicos.php?visao=semana&data=<?= h($semanaProxima) ?>" class="btn btn-secundario">Próxima semana →</a>
            </div>

            <div class="calendario-semana">
                <?php for ($i = 0; $i < 7; $i++):
                    $dataCelula = date('Y-m-d', strtotime($inicioSemana . " +$i days"));
                    $info = $resumo[$dataCelula] ?? null;
                    $hoje = $dataCelula === date('Y-m-d');
                ?>
                    <a href="servicos.php?visao=dia&data=<?= $dataCelula ?>" class="calendario-semana__dia <?= $hoje ? 'calendario-mes__dia--hoje' : '' ?>">
                        <span class="calendario-semana__data"><?= h(date('d/m', strtotime($dataCelula))) ?></span>
                        <?php if ($info): ?>
                            <span class="calendario-mes__resumo"><?= $info['qtd'] ?> serviço(s) · <?= h(formatar_dinheiro($info['total'])) ?></span>
                        <?php else: ?>
                            <span class="calendario-mes__resumo">—</span>
                        <?php endif; ?>
                    </a>
                <?php endfor; ?>
            </div>
        </div>

    <?php else: /* visao === dia */ ?>
        <?php
        $diaAnterior = date('Y-m-d', strtotime($data . ' -1 day'));
        $diaProximo = date('Y-m-d', strtotime($data . ' +1 day'));
        $servicos = listar_servicos_do_dia($data);
        ?>
        <div class="cartao">
            <div class="acoes" style="justify-content:space-between; margin-top:0; flex-wrap:wrap;">
                <a href="servicos.php?visao=dia&data=<?= h($diaAnterior) ?>" class="btn btn-secundario">← Dia anterior</a>
                <h2 style="margin:0;"><?= h(formatar_data($data . ' 00:00:00')) ?></h2>
                <a href="servicos.php?visao=dia&data=<?= h($diaProximo) ?>" class="btn btn-secundario">Próximo dia →</a>
            </div>

            <div class="acoes no-print">
                <a href="servico-novo.php?data=<?= h($data) ?>" class="btn btn-primario">➕ Lançar serviço neste dia</a>
                <a href="imprimir-historico-servicos.php?data=<?= h($data) ?>" target="_blank" class="btn btn-secundario">🖨️ Imprimir</a>
                <a href="exportar-servicos.php?data=<?= h($data) ?>" class="btn btn-secundario">📊 Excel</a>
                <a href="exportar-servicos-pdf.php?data=<?= h($data) ?>" class="btn btn-secundario">📄 PDF</a>
            </div>

            <?php if (empty($servicos)): ?>
                <div class="alerta alerta-info" style="margin-top:14px;">ℹ️ Nenhum serviço lançado neste dia ainda.</div>
            <?php endif; ?>

            <div class="servicos-lista">
                <?php foreach ($servicos as $s): $editavel = pode_editar_servico($s); ?>
                    <div class="servico-card">
                        <div class="servico-card__topo">
                            <strong><?= h($s['carro_cliente']) ?></strong>
                            <span class="badge badge-normal"><?= h(formatar_dinheiro((float)$s['valor_cobrado'] - (float)$s['desconto'])) ?></span>
                        </div>
                        <?php if (!empty($s['descricao'])): ?><p class="servico-card__descricao"><?= nl2br(h($s['descricao'])) ?></p><?php endif; ?>
                        <?php if (!empty($s['observacao_adm'])): ?><p class="servico-card__observacao">🗒️ <?= nl2br(h($s['observacao_adm'])) ?></p><?php endif; ?>
                        <div class="servico-card__meta">
                            <?php if (!empty($s['profissionais_nomes'])): ?><span>👷 <?= h($s['profissionais_nomes']) ?></span><?php endif; ?>
                            <span>💵 Cobrado: <?= h(formatar_dinheiro((float)$s['valor_cobrado'])) ?></span>
                            <?php if ((float)$s['desconto'] > 0): ?><span>🏷️ Desconto: <?= h(formatar_dinheiro((float)$s['desconto'])) ?></span><?php endif; ?>
                            <span>🕒 Lançado em <?= h(formatar_data($s['criado_em'])) ?></span>
                            <?php if ($editavel): ?>
                                <span class="badge badge-em_andamento">✏️ Editável por mais <?= (int)ceil((24 * 3600 - (time() - strtotime($s['criado_em']))) / 3600) ?>h</span>
                            <?php else: ?>
                                <span class="badge badge-novo">🔒 Edição encerrada</span>
                            <?php endif; ?>
                        </div>
                        <div class="servico-card__pagamentos">
                            <?php foreach ($s['pagamentos'] as $p): ?>
                                <span class="badge badge-em_andamento">
                                    <?= h(rotulo_forma_pagamento($p['forma'])) ?>: <?= h(formatar_dinheiro((float)$p['valor'])) ?>
                                    <?= $p['parcelas'] ? ' (' . (int)$p['parcelas'] . 'x)' : '' ?>
                                    <?= !empty($p['conta']) ? ' · ' . h($p['conta']) : '' ?>
                                </span>
                            <?php endforeach; ?>
                        </div>
                        <div class="acoes no-print" style="margin-top:10px;">
                            <?php if ($editavel): ?>
                                <a href="servico-novo.php?data=<?= h($data) ?>&servico_id=<?= (int)$s['id'] ?>" class="btn btn-secundario btn-sm">✏️ Editar</a>
                            <?php endif; ?>
                            <button type="submit" form="form-excluir-serv-<?= (int)$s['id'] ?>" class="btn btn-perigo btn-sm" onclick="return confirm('Excluir este serviço?');">🗑️ Excluir</button>
                        </div>
                    </div>
                    <form id="form-excluir-serv-<?= (int)$s['id'] ?>" method="post" action="servicos.php?visao=dia&data=<?= h($data) ?>" style="display:none;">
                        <?= csrf_campo() ?>
                        <input type="hidden" name="acao" value="excluir_servico">
                        <input type="hidden" name="servico_id" value="<?= (int)$s['id'] ?>">
                    </form>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
</main>

<?php require __DIR__ . '/../includes/footer.php'; ?>
