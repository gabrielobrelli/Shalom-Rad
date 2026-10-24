<?php
/**
 * admin/pedido.php — Detalhe de um pedido na visão da ADMINISTRAÇÃO.
 * Mostra os campos que só a administração vê/edita (Quantidade Feita,
 * Feito, Carcaça utilizada, Caixas utilizadas, Anéis).
 *
 * Assim como em pedido.php (cliente), a página abre em modo leitura
 * (lista "fechada") e só fica editável depois de clicar em
 * "✏️ Editar itens".
 */
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/pedido_repo.php';
require_once __DIR__ . '/../includes/profissionais_repo.php';

exigir_login_admin();

$id = inteiro_positivo($_GET['id'] ?? $_POST['id'] ?? 0);
$pedido = $id ? buscar_pedido_por_id($id) : null;

if (!$pedido) {
    http_response_code(404);
    die('Pedido não encontrado.');
}

$mensagem = null;
$erro = null;
$statusAntes = $pedido['status'] ?? null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    exigir_csrf();
    $acao = $_POST['acao'] ?? '';

    if ($acao === 'salvar_itens') {
        $itensPost = $_POST['itens'] ?? [];
        foreach ($itensPost as $itemId => $dados) {
            atualizar_item_admin((int)$itemId, $dados);
        }
        $itensAtuais = listar_itens_pedido($id);
        atualizar_status_pedido($id, calcular_status_pedido($itensAtuais));
        $mensagem = 'Alterações salvas com sucesso.';

    } elseif ($acao === 'adicionar_item') {
        $modelo = trim((string)($_POST['modelo_nome'] ?? ''));
        if ($modelo === '') {
            $erro = 'Informe o modelo/nome do novo item.';
        } else {
            $antes = count(listar_itens_pedido($id));
            adicionar_item_admin($id, [
                'modelo_nome' => $modelo,
                'numero_serie' => $_POST['numero_serie'] ?? '',
                'quantidade' => $_POST['quantidade'] ?? 0,
                'situacao' => $_POST['situacao'] ?? 'normal',
            ]);
            $depois = count(listar_itens_pedido($id));
            $mensagem = ($depois > $antes)
                ? 'Item adicionado.'
                : 'Já existia um item "' . h($modelo) . '" — as quantidades foram somadas automaticamente.';
        }

    } elseif ($acao === 'remover_item') {
        $itemId = inteiro_positivo($_POST['item_id'] ?? 0);
        remover_item($itemId, $id);
        $itensAtuais = listar_itens_pedido($id);
        atualizar_status_pedido($id, calcular_status_pedido($itensAtuais));
        $mensagem = 'Item removido.';

    } elseif ($acao === 'atualizar_status') {
        $status = $_POST['status'] ?? 'novo';
        $permitidos = ['novo', 'em_andamento', 'parcial', 'concluido'];
        if (in_array($status, $permitidos, true)) {
            atualizar_status_pedido($id, $status);
            $mensagem = 'Status atualizado.';
        }

    } elseif ($acao === 'marcar_pronto') {
        $itemId = inteiro_positivo($_POST['item_id'] ?? 0);
        $qtdFeita = inteiro_positivo($_POST['quantidade_feita'] ?? 0);
        $profissionalIds = array_map('intval', (array)($_POST['profissional_ids'] ?? []));
        marcar_item_pronto($itemId, $id, $qtdFeita, $profissionalIds);
        $itensAtuais = listar_itens_pedido($id);
        atualizar_status_pedido($id, calcular_status_pedido($itensAtuais));
        $mensagem = 'Peça marcada como pronta! ✅';

    } elseif ($acao === 'salvar_detalhes_item') {
        $itemId = inteiro_positivo($_POST['item_id'] ?? 0);
        atualizar_detalhes_item(
            $itemId, $id,
            (string)($_POST['observacao_adm'] ?? ''),
            (string)($_POST['carcaca_utilizada'] ?? ''),
            (string)($_POST['caixas_utilizadas'] ?? ''),
            (string)($_POST['aneis'] ?? '')
        );
        $mensagem = 'Detalhes salvos.';

    } elseif ($acao === 'reabrir_item') {
        $itemId = inteiro_positivo($_POST['item_id'] ?? 0);
        reabrir_item($itemId, $id);
        $itensAtuais = listar_itens_pedido($id);
        atualizar_status_pedido($id, calcular_status_pedido($itensAtuais));
        $mensagem = 'Item reaberto.';

    } elseif ($acao === 'excluir_pedido') {
        excluir_pedido($id);
        header('Location: pedidos.php?excluido=1');
        exit;
    }

    $pedido = buscar_pedido_por_id($id); // recarrega com dados atualizados

    // O pedido acabou de ser finalizado agora (não estava "concluído"
    // antes desta ação e passou a estar) — manda a administração direto
    // para o lançamento do serviço no extrato do dia, já com o nome do
    // cliente preenchido. O valor cobrado é opcional lá, então dá pra
    // pular sem preencher se não for o caso de registrar agora.
    if ($statusAntes !== 'concluido' && ($pedido['status'] ?? null) === 'concluido') {
        header('Location: servico-novo.php?data=' . date('Y-m-d')
            . '&carro_cliente=' . urlencode($pedido['cliente_nome'])
            . '&origem_pedido_id=' . $id);
        exit;
    }
}

if (isset($_GET['criado'])) {
    $mensagem = 'Pedido criado com sucesso.';
}

$itens = listar_itens_pedido($id);
$itensLeitura = listar_itens_pedido_para_leitura($id);
$totalSolicitado = total_solicitado($itens);
$totalFeito = total_feito($itens);
$token = $pedido['token'];
$profissionais = listar_profissionais_ativos();

$tituloPagina = 'Pedido de ' . $pedido['cliente_nome'] . ' — Administração';
$baseAssets = '../';
$paginaAtual = 'admin_pedidos';
require __DIR__ . '/../includes/header.php';
?>

<main class="container largo">

    <div class="cartao">
        <div style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:10px;">
            <div>
                <h1><?= h($pedido['cliente_nome']) ?></h1>
                <p class="subtitulo">
                    Criado em <?= h(formatar_data($pedido['criado_em'])) ?> ·
                    Atualizado em <?= h(formatar_data($pedido['atualizado_em'])) ?> ·
                    Link do cliente: <a href="<?= h(link_pedido($token)) ?>" target="_blank">abrir ↗</a>
                </p>
            </div>
            <div class="acoes no-print" style="margin-top:0;">
                <a href="imprimir-pedido.php?id=<?= $id ?>" target="_blank" class="btn btn-secundario">🖨️ Imprimir</a>
                <a href="exportar-pedido.php?id=<?= $id ?>" class="btn btn-secundario">📊 Excel</a>
                <a href="exportar-pedido-pdf.php?id=<?= $id ?>" class="btn btn-secundario">📄 PDF</a>
                <button type="submit" form="form-excluir-pedido" class="btn btn-perigo"
                        onclick="return confirm('Tem certeza que deseja EXCLUIR este pedido inteiro? Essa ação não pode ser desfeita.');">🗑️ Excluir pedido</button>
            </div>
        </div>

        <?php if ($mensagem): ?><div class="alerta alerta-sucesso">✅ <?= h($mensagem) ?></div><?php endif; ?>
        <?php if ($erro): ?><div class="alerta alerta-erro">⚠️ <?= h($erro) ?></div><?php endif; ?>

        <form method="post" action="pedido.php?id=<?= $id ?>" style="max-width:320px; margin-top:14px;">
            <?= csrf_campo() ?>
            <input type="hidden" name="acao" value="atualizar_status">
            <input type="hidden" name="id" value="<?= $id ?>">
            <label for="status">Status do pedido</label>
            <div style="display:flex; gap:8px;">
                <select name="status" id="status">
                    <?php foreach (['novo','em_andamento','parcial','concluido'] as $s): ?>
                        <option value="<?= $s ?>" <?= $pedido['status'] === $s ? 'selected' : '' ?>><?= icone_status($s) ?> <?= h(rotulo_status($s)) ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn btn-secundario">Atualizar</button>
            </div>
        </form>
    </div>

    <div class="cartao">
        <h2>🔧 Itens do pedido</h2>

        <!-- ===================== MODO LEITURA (padrão) ===================== -->
        <div id="modo-leitura">
            <div class="aviso-modo-leitura no-print">🔒 Observação, peças utilizadas e "pronto" já salvam direto aqui embaixo. Clique em "Editar itens" só para mudar nome, nº de série, quantidade, situação ou remover um item.</div>

            <div class="itens-pedido-grade">
                <?php foreach ($itensLeitura as $item): $iid = (int)$item['id']; ?>
                <div class="item-pedido-card <?= $item['feito'] ? 'item-pedido-card--feito' : 'item-pedido-card--pendente' ?>">
                    <div class="item-pedido-card__cabecalho">
                        <div class="item-pedido-card__titulo">
                            <strong class="item-pedido-card__nome"><?= h($item['modelo_nome']) ?></strong>
                            <?php if (!empty($item['numero_serie'])): ?><span class="item-pedido-card__serie">Nº <?= h($item['numero_serie']) ?></span><?php endif; ?>
                        </div>
                        <span class="badge badge-<?= h($item['situacao']) ?>"><?= icone_situacao($item['situacao']) ?> <?= h(rotulo_situacao($item['situacao'])) ?></span>
                    </div>

                    <div class="item-pedido-card__qtds">
                        <span>Solicitado: <strong><?= (int)$item['quantidade'] ?></strong></span>
                        <span>Feito: <strong><?= (int)$item['quantidade_feita'] ?></strong></span>
                    </div>

                    <div class="item-pedido-card__status">
                        <?php if ($item['feito']): ?>
                            <div class="feito-info">
                                ✅ <?php if (!empty($item['profissionais_nomes'])): ?>
                                    <strong><?= h($item['profissionais_nomes']) ?></strong>
                                <?php else: ?>
                                    <em>sem profissional</em>
                                <?php endif; ?>
                                <span class="feito-info__data"><?= h(formatar_data($item['feito_em'])) ?></span>
                                <button type="submit" form="form-reabrir-<?= $iid ?>" class="btn btn-secundario btn-sm no-print" onclick="return confirm('Reabrir este item? Ele voltará a aparecer como pendente.');">↩️ Reabrir</button>
                            </div>
                        <?php else: ?>
                            <form method="post" action="pedido.php?id=<?= $id ?>" class="form-marcar-pronto no-print">
                                <span class="form-marcar-pronto__rotulo">☐ Pendente — marcar como pronto:</span>
                                <?= csrf_campo() ?>
                                <input type="hidden" name="acao" value="marcar_pronto">
                                <input type="hidden" name="id" value="<?= $id ?>">
                                <input type="hidden" name="item_id" value="<?= $iid ?>">
                                <input type="number" name="quantidade_feita" min="0" value="<?= (int)$item['quantidade'] ?>" title="Quantidade feita" style="width:64px;">
                                <span class="selecao-profissionais" title="Quem fez (pode marcar mais de um)">
                                    <?php foreach ($profissionais as $p): ?>
                                        <label class="selecao-profissionais__opcao">
                                            <input type="checkbox" name="profissional_ids[]" value="<?= (int)$p['id'] ?>"> <?= h($p['nome']) ?>
                                        </label>
                                    <?php endforeach; ?>
                                </span>
                                <button type="submit" class="btn btn-sucesso btn-sm">✅ Pronto</button>
                            </form>
                        <?php endif; ?>
                    </div>

                    <form method="post" action="pedido.php?id=<?= $id ?>" class="item-pedido-card__detalhes no-print">
                        <?= csrf_campo() ?>
                        <input type="hidden" name="acao" value="salvar_detalhes_item">
                        <input type="hidden" name="id" value="<?= $id ?>">
                        <input type="hidden" name="item_id" value="<?= $iid ?>">
                        <div class="campo-mini campo-mini--largo">
                            <label>Observação (ADM)</label>
                            <textarea name="observacao_adm" rows="1" placeholder="Recado interno..."><?= h($item['observacao_adm'] ?? '') ?></textarea>
                        </div>
                        <div class="campo-mini">
                            <label>Carcaça utilizada</label>
                            <input type="text" name="carcaca_utilizada" value="<?= h($item['carcaca_utilizada'] ?? '') ?>">
                        </div>
                        <div class="campo-mini">
                            <label>Caixas utilizadas</label>
                            <input type="text" name="caixas_utilizadas" value="<?= h($item['caixas_utilizadas'] ?? '') ?>">
                        </div>
                        <div class="campo-mini">
                            <label>Anéis</label>
                            <input type="text" name="aneis" value="<?= h($item['aneis'] ?? '') ?>">
                        </div>
                        <button type="submit" class="btn btn-secundario btn-sm">💾 Salvar detalhes</button>
                    </form>

                    <div class="imprimir-somente item-pedido-card__impressao">
                        <?php if (!empty($item['observacao_adm'])): ?>Observação: <?= h($item['observacao_adm']) ?><br><?php endif; ?>
                        Carcaça: <?= h($item['carcaca_utilizada'] ?: '-') ?> ·
                        Caixas: <?= h($item['caixas_utilizadas'] ?: '-') ?> ·
                        Anéis: <?= h($item['aneis'] ?: '-') ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <?php foreach ($itensLeitura as $item): if (!$item['feito']) continue; $iid = (int)$item['id']; ?>
                <form id="form-reabrir-<?= $iid ?>" method="post" action="pedido.php?id=<?= $id ?>" style="display:none;">
                    <?= csrf_campo() ?>
                    <input type="hidden" name="acao" value="reabrir_item">
                    <input type="hidden" name="id" value="<?= $id ?>">
                    <input type="hidden" name="item_id" value="<?= $iid ?>">
                </form>
            <?php endforeach; ?>

            <div class="totais">
                <div class="item-total"><div class="rotulo">Total solicitado</div><div class="valor"><?= $totalSolicitado ?> peças</div></div>
                <div class="item-total"><div class="rotulo">Total feito</div><div class="valor"><?= $totalFeito ?> peças</div></div>
            </div>

            <div class="acoes no-print">
                <button type="button" id="btn-abrir-edicao" class="btn btn-primario">✏️ Editar itens</button>
            </div>
        </div>

        <!-- ===================== MODO EDIÇÃO (oculto por padrão) ===================== -->
        <div id="modo-edicao" style="display:none;">
            <form method="post" action="pedido.php?id=<?= $id ?>">
                <?= csrf_campo() ?>
                <input type="hidden" name="acao" value="salvar_itens">
                <input type="hidden" name="id" value="<?= $id ?>">

                <div class="tabela-itens-wrap">
                    <table class="tabela-itens">
                        <thead>
                            <tr>
                                <th>Modelo/Nome</th>
                                <th>Nº Série</th>
                                <th class="num">Qtd.</th>
                                <th>Situação</th>
                                <th class="num">Qtd. Feita</th>
                                <th class="center">Feito</th>
                                <th>Carcaça utilizada</th>
                                <th>Caixas utilizadas</th>
                                <th>Anéis</th>
                                <th class="center">Ação</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($itens as $item): $iid = (int)$item['id']; ?>
                            <tr>
                                <td data-rotulo="Modelo/Nome"><input type="text" name="itens[<?= $iid ?>][modelo_nome]" value="<?= h($item['modelo_nome']) ?>" required></td>
                                <td data-rotulo="Nº Série"><input type="text" name="itens[<?= $iid ?>][numero_serie]" value="<?= h($item['numero_serie'] ?? '') ?>"></td>
                                <td data-rotulo="Qtd." class="num"><input type="number" min="0" name="itens[<?= $iid ?>][quantidade]" value="<?= (int)$item['quantidade'] ?>" style="width:80px;text-align:right"></td>
                                <td data-rotulo="Situação">
                                    <select name="itens[<?= $iid ?>][situacao]">
                                        <?php foreach (['normal','importante','urgente'] as $s): ?>
                                            <option value="<?= $s ?>" <?= $item['situacao'] === $s ? 'selected' : '' ?>><?= icone_situacao($s) ?> <?= h(rotulo_situacao($s)) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td data-rotulo="Qtd. Feita" class="num"><input type="number" min="0" name="itens[<?= $iid ?>][quantidade_feita]" value="<?= (int)$item['quantidade_feita'] ?>" style="width:80px;text-align:right"></td>
                                <td data-rotulo="Feito" class="center">
                                    <div class="checkbox-linha" style="justify-content:center;">
                                        <input type="checkbox" name="itens[<?= $iid ?>][feito]" value="1" <?= $item['feito'] ? 'checked' : '' ?>>
                                    </div>
                                </td>
                                <td data-rotulo="Carcaça utilizada"><input type="text" name="itens[<?= $iid ?>][carcaca_utilizada]" value="<?= h($item['carcaca_utilizada'] ?? '') ?>"></td>
                                <td data-rotulo="Caixas utilizadas"><input type="text" name="itens[<?= $iid ?>][caixas_utilizadas]" value="<?= h($item['caixas_utilizadas'] ?? '') ?>"></td>
                                <td data-rotulo="Anéis"><input type="text" name="itens[<?= $iid ?>][aneis]" value="<?= h($item['aneis'] ?? '') ?>"></td>
                                <td data-rotulo="Ação" class="center">
                                    <button type="submit" form="form-remover-<?= $iid ?>" class="btn btn-perigo btn-sm" onclick="return confirm('Remover este item?');">🗑️</button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="totais">
                    <div class="item-total"><div class="rotulo">Total solicitado</div><div class="valor"><?= $totalSolicitado ?> peças</div></div>
                    <div class="item-total"><div class="rotulo">Total feito</div><div class="valor"><?= $totalFeito ?> peças</div></div>
                </div>

                <div class="acoes">
                    <button type="submit" class="btn btn-sucesso">💾 Salvar alterações</button>
                    <button type="button" id="btn-cancelar-edicao" class="btn btn-secundario">✖️ Cancelar</button>
                </div>
            </form>

            <!-- Formulários independentes de remoção (ligados via atributo form=) -->
            <?php foreach ($itens as $item): $iid = (int)$item['id']; ?>
                <form id="form-remover-<?= $iid ?>" method="post" action="pedido.php?id=<?= $id ?>" style="display:none;">
                    <?= csrf_campo() ?>
                    <input type="hidden" name="acao" value="remover_item">
                    <input type="hidden" name="id" value="<?= $id ?>">
                    <input type="hidden" name="item_id" value="<?= $iid ?>">
                </form>
            <?php endforeach; ?>

            <h2 style="margin-top:28px;">➕ Adicionar novo item</h2>
            <p class="subtitulo" style="margin-top:-8px;">Se já existir um item com o mesmo Modelo/Nome, as quantidades serão somadas automaticamente em vez de criar uma linha duplicada.</p>
            <form method="post" action="pedido.php?id=<?= $id ?>">
                <?= csrf_campo() ?>
                <input type="hidden" name="acao" value="adicionar_item">
                <input type="hidden" name="id" value="<?= $id ?>">
                <div class="linha-campos">
                    <div class="campo"><label>Modelo/Nome</label><input type="text" name="modelo_nome" required placeholder="Ex.: HB20"></div>
                    <div class="campo"><label>Nº de série</label><input type="text" name="numero_serie"></div>
                    <div class="campo"><label>Quantidade</label><input type="number" min="1" name="quantidade" value="1"></div>
                    <div class="campo">
                        <label>Situação</label>
                        <select name="situacao">
                            <option value="normal">🔵 Normal</option>
                            <option value="importante">⚠️ Importante</option>
                            <option value="urgente">🔴 Urgente</option>
                        </select>
                    </div>
                </div>
                <button type="submit" class="btn btn-secundario">➕ Adicionar item</button>
            </form>
        </div>
    </div>
</main>

<form id="form-excluir-pedido" method="post" action="pedido.php?id=<?= $id ?>" style="display:none;">
    <?= csrf_campo() ?>
    <input type="hidden" name="acao" value="excluir_pedido">
    <input type="hidden" name="id" value="<?= $id ?>">
</form>

<script>
    (function () {
        const leitura = document.getElementById('modo-leitura');
        const edicao = document.getElementById('modo-edicao');
        const btnAbrir = document.getElementById('btn-abrir-edicao');
        const btnCancelar = document.getElementById('btn-cancelar-edicao');
        if (btnAbrir) btnAbrir.addEventListener('click', function () { leitura.style.display = 'none'; edicao.style.display = 'block'; });
        if (btnCancelar) btnCancelar.addEventListener('click', function () { window.location.reload(); });
    })();
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>
