<?php
/**
 * admin/seminovos.php — Seção interna "Concerto de Seminovos Marcio",
 * separada dos pedidos de clientes. Mesmo padrão de leitura/edição das
 * outras telas: abre fechada, só edita depois de clicar em "Editar itens".
 */
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/seminovos_repo.php';

exigir_login_admin();

$semi = obter_seminovos_order();
$semiId = (int)$semi['id'];

$mensagem = null;
$erro = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    exigir_csrf();
    $acao = $_POST['acao'] ?? '';

    if ($acao === 'adicionar_item') {
        $modelo = trim((string)($_POST['modelo_nome'] ?? ''));
        if ($modelo === '') {
            $erro = 'Informe o modelo/nome do item.';
        } else {
            $antes = count(listar_itens_seminovos($semiId));
            adicionar_item_seminovos(
                $semiId,
                $modelo,
                inteiro_positivo($_POST['quantidade_feita'] ?? 0),
                $_POST['nossas_pecas_usadas'] ?? ''
            );
            $depois = count(listar_itens_seminovos($semiId));
            $mensagem = ($depois > $antes)
                ? 'Item adicionado.'
                : 'Já existia um item "' . h($modelo) . '" — a quantidade foi somada e as peças usadas foram acrescentadas automaticamente.';
        }

    } elseif ($acao === 'salvar_itens') {
        foreach ($_POST['itens'] ?? [] as $itemId => $dados) {
            atualizar_item_seminovos(
                (int)$itemId,
                trim((string)($dados['modelo_nome'] ?? '')),
                inteiro_positivo($dados['quantidade_feita'] ?? 0),
                $dados['nossas_pecas_usadas'] ?? ''
            );
        }
        $mensagem = 'Alterações salvas com sucesso.';

    } elseif ($acao === 'remover_item') {
        remover_item_seminovos(inteiro_positivo($_POST['item_id'] ?? 0), $semiId);
        $mensagem = 'Item removido.';
    }
}

$itens = listar_itens_seminovos($semiId);
$totalGeral = total_geral_seminovos($itens);

$tituloPagina = 'Concerto de Seminovos Marcio — Administração';
$baseAssets = '../';
$paginaAtual = 'admin_seminovos';
require __DIR__ . '/../includes/header.php';
?>

<main class="container largo">
    <div class="cartao">
        <div style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:10px;">
            <div>
                <h1>🔧 Concerto de Seminovos Marcio</h1>
                <p class="subtitulo">Seção interna, independente dos pedidos de clientes.</p>
            </div>
            <div class="acoes no-print" style="margin-top:0;">
                <a href="imprimir-seminovos.php" target="_blank" class="btn btn-secundario">🖨️ Imprimir</a>
                <a href="exportar-seminovos.php" class="btn btn-secundario">📊 Excel</a>
                <a href="exportar-seminovos-pdf.php" class="btn btn-secundario">📄 PDF</a>
            </div>
        </div>

        <?php if ($mensagem): ?><div class="alerta alerta-sucesso">✅ <?= h($mensagem) ?></div><?php endif; ?>
        <?php if ($erro): ?><div class="alerta alerta-erro">⚠️ <?= h($erro) ?></div><?php endif; ?>

        <!-- ===================== MODO LEITURA (padrão) ===================== -->
        <div id="modo-leitura">
            <div class="aviso-modo-leitura no-print">🔒 Lista fechada para edição. Clique em "Editar itens" para alterar.</div>

            <div class="tabela-itens-wrap">
                <table class="tabela-itens">
                    <thead>
                        <tr>
                            <th>Modelo/Nome</th>
                            <th class="num">Quantidade Feita</th>
                            <th>Nossas Peças Usadas</th>
                        </tr>
                    </thead>
                    <tbody class="lista-fechada">
                        <?php foreach ($itens as $item): ?>
                        <tr>
                            <td data-rotulo="Modelo/Nome"><?= h($item['modelo_nome']) ?></td>
                            <td data-rotulo="Quantidade Feita" class="num"><?= (int)$item['quantidade_feita'] ?></td>
                            <td data-rotulo="Nossas Peças Usadas"><?= h($item['nossas_pecas_usadas'] ?: '-') ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="totais">
                <div class="item-total"><div class="rotulo">Total geral</div><div class="valor"><?= $totalGeral ?> peças</div></div>
            </div>

            <div class="acoes no-print">
                <button type="button" id="btn-abrir-edicao" class="btn btn-primario">✏️ Editar itens</button>
            </div>
        </div>

        <!-- ===================== MODO EDIÇÃO (oculto por padrão) ===================== -->
        <div id="modo-edicao" style="display:none;">
            <form method="post" action="seminovos.php">
                <?= csrf_campo() ?>
                <input type="hidden" name="acao" value="salvar_itens">

                <div class="tabela-itens-wrap">
                    <table class="tabela-itens">
                        <thead>
                            <tr>
                                <th>Modelo/Nome</th>
                                <th class="num">Quantidade Feita</th>
                                <th>Nossas Peças Usadas</th>
                                <th class="center">Ação</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($itens as $item): $iid = (int)$item['id']; ?>
                            <tr>
                                <td data-rotulo="Modelo/Nome"><input type="text" name="itens[<?= $iid ?>][modelo_nome]" value="<?= h($item['modelo_nome']) ?>" required></td>
                                <td data-rotulo="Quantidade Feita" class="num"><input type="number" min="0" name="itens[<?= $iid ?>][quantidade_feita]" value="<?= (int)$item['quantidade_feita'] ?>" style="width:100px;text-align:right"></td>
                                <td data-rotulo="Nossas Peças Usadas"><input type="text" name="itens[<?= $iid ?>][nossas_pecas_usadas]" value="<?= h($item['nossas_pecas_usadas'] ?? '') ?>"></td>
                                <td data-rotulo="Ação" class="center">
                                    <button type="submit" form="form-remover-semi-<?= $iid ?>" class="btn btn-perigo btn-sm" onclick="return confirm('Remover este item?');">🗑️</button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="totais">
                    <div class="item-total"><div class="rotulo">Total geral</div><div class="valor"><?= $totalGeral ?> peças</div></div>
                </div>

                <div class="acoes">
                    <button type="submit" class="btn btn-sucesso">💾 Salvar alterações</button>
                    <button type="button" id="btn-cancelar-edicao" class="btn btn-secundario">✖️ Cancelar</button>
                </div>
            </form>

            <?php foreach ($itens as $item): $iid = (int)$item['id']; ?>
                <form id="form-remover-semi-<?= $iid ?>" method="post" action="seminovos.php" style="display:none;">
                    <?= csrf_campo() ?>
                    <input type="hidden" name="acao" value="remover_item">
                    <input type="hidden" name="item_id" value="<?= $iid ?>">
                </form>
            <?php endforeach; ?>

            <h2 style="margin-top:28px;">➕ Adicionar novo item</h2>
            <p class="subtitulo" style="margin-top:-8px;">Se já existir um item com o mesmo Modelo/Nome, a quantidade será somada e as peças usadas serão acrescentadas ao que já estava anotado.</p>
            <form method="post" action="seminovos.php">
                <?= csrf_campo() ?>
                <input type="hidden" name="acao" value="adicionar_item">
                <div class="linha-campos">
                    <div class="campo"><label>Modelo/Nome</label><input type="text" name="modelo_nome" required placeholder="Ex.: Gol"></div>
                    <div class="campo"><label>Quantidade Feita</label><input type="number" min="0" name="quantidade_feita" value="0"></div>
                    <div class="campo"><label>Nossas Peças Usadas</label><input type="text" name="nossas_pecas_usadas" placeholder="Ex.: Caixa superior, caixa inferior e anéis"></div>
                </div>
                <button type="submit" class="btn btn-secundario">➕ Adicionar item</button>
            </form>
        </div>
    </div>
</main>

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
