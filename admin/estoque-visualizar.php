<?php
/**
 * admin/estoque-visualizar.php — "Ver tudo" do estoque: uma grade densa
 * em colunas (só organização visual, não agrupa por categoria) com busca
 * e filtros, pensada pra escanear o estoque inteiro rapidamente. Só
 * permite EDITAR itens já cadastrados — cadastro de item novo continua
 * em estoque.php.
 */
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/estoque_repo.php';

exigir_login_admin();

$mensagem = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'atualizar_item') {
    exigir_csrf();
    $itemId = inteiro_positivo($_POST['item_id'] ?? 0);
    $nome = trim((string)($_POST['nome'] ?? ''));
    $numeroSerie = trim((string)($_POST['numero_serie'] ?? ''));
    if ($itemId > 0 && $nome !== '' && $numeroSerie !== '' && numero_serie_em_uso($numeroSerie, $itemId)) {
        $mensagem = '⚠️ Já existe outro item com o número de série "' . h($numeroSerie) . '". Não foi salvo — use outro número.';
    } elseif ($itemId > 0 && $nome !== '') {
        try {
            $imagemNova = salvar_imagem_estoque($_FILES['imagem'] ?? null);
            atualizar_item_estoque(
                $itemId,
                $nome,
                $_POST['marca'] ?? '',
                inteiro_positivo($_POST['categoria_id'] ?? 0) ?: null,
                (float)str_replace(',', '.', $_POST['valor_unitario'] ?? 0),
                $numeroSerie ?: null,
                $imagemNova
            );
            $mensagem = 'Item atualizado.';
        } catch (RuntimeException $e) {
            $mensagem = '⚠️ ' . $e->getMessage();
        }
    }
}

$busca = trim((string)($_GET['busca'] ?? ''));
$categoriaFiltro = inteiro_positivo($_GET['categoria_id'] ?? 0);
$marcaFiltro = trim((string)($_GET['marca'] ?? ''));
$valorMinFiltro = trim((string)($_GET['valor_min'] ?? '')) !== '' ? (float)str_replace(',', '.', $_GET['valor_min']) : null;
$valorMaxFiltro = trim((string)($_GET['valor_max'] ?? '')) !== '' ? (float)str_replace(',', '.', $_GET['valor_max']) : null;
$ordenarPor = array_key_exists($_GET['ordenar'] ?? '', ORDENACOES_ESTOQUE) ? $_GET['ordenar'] : 'nome_asc';

$itens = listar_itens_estoque($busca, $categoriaFiltro, $marcaFiltro, $valorMinFiltro, $valorMaxFiltro, $ordenarPor);
$categorias = listar_categorias_estoque();
$marcas = listar_marcas_estoque();

$queryFiltros = http_build_query([
    'busca' => $busca, 'categoria_id' => $categoriaFiltro, 'marca' => $marcaFiltro,
    'valor_min' => $valorMinFiltro ?? '', 'valor_max' => $valorMaxFiltro ?? '', 'ordenar' => $ordenarPor,
]);

$tituloPagina = 'Estoque — visão geral — Shalom Radiadores';
$baseAssets = '../';
$paginaAtual = 'admin_estoque';
require __DIR__ . '/../includes/header.php';
?>

<main class="container largo">
    <div class="cartao">
        <div style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:10px;">
            <div>
                <h1>📁 Estoque — visão geral</h1>
                <p class="subtitulo">Todos os itens de uma vez, em colunas — pra escanear rápido. Clique em ✏️ pra editar; para cadastrar item novo ou ajustar quantidade, use a tela de Estoque normal.</p>
            </div>
            <div class="acoes no-print" style="margin-top:0;">
                <a href="estoque.php" class="btn btn-secundario">📦 Ir para cadastro/estoque</a>
                <a href="imprimir-estoque.php?<?= h($queryFiltros) ?>" target="_blank" class="btn btn-secundario">🖨️ Imprimir</a>
                <a href="exportar-estoque.php?<?= h($queryFiltros) ?>" class="btn btn-secundario">📊 Excel</a>
                <a href="exportar-estoque-pdf.php?<?= h($queryFiltros) ?>" class="btn btn-secundario">📄 PDF</a>
            </div>
        </div>

        <?php if ($mensagem): ?><div class="alerta alerta-sucesso">✅ <?= h($mensagem) ?></div><?php endif; ?>

        <form method="get" action="estoque-visualizar.php" class="linha-campos" style="align-items:end;">
            <div class="campo">
                <label for="busca">🔎 Nome, marca ou nº de série</label>
                <input type="text" id="busca" name="busca" value="<?= h($busca) ?>">
            </div>
            <div class="campo">
                <label for="categoria_id">Categoria</label>
                <select id="categoria_id" name="categoria_id">
                    <option value="0">Todas</option>
                    <?php foreach ($categorias as $c): ?>
                        <option value="<?= (int)$c['id'] ?>" <?= $categoriaFiltro === (int)$c['id'] ? 'selected' : '' ?>><?= h($c['nome']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="campo">
                <label for="marca">Marca</label>
                <select id="marca" name="marca">
                    <option value="">Todas</option>
                    <?php foreach ($marcas as $m): ?>
                        <option value="<?= h($m) ?>" <?= $marcaFiltro === $m ? 'selected' : '' ?>><?= h($m) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="campo">
                <label for="valor_min">Valor de (R$)</label>
                <input type="number" step="0.01" min="0" id="valor_min" name="valor_min" value="<?= h($valorMinFiltro !== null ? (string)$valorMinFiltro : '') ?>">
            </div>
            <div class="campo">
                <label for="valor_max">Valor até (R$)</label>
                <input type="number" step="0.01" min="0" id="valor_max" name="valor_max" value="<?= h($valorMaxFiltro !== null ? (string)$valorMaxFiltro : '') ?>">
            </div>
            <div class="campo">
                <label for="ordenar">Ordenar por</label>
                <select id="ordenar" name="ordenar">
                    <?php foreach (ORDENACOES_ESTOQUE as $valor => $rotulo): ?>
                        <option value="<?= $valor ?>" <?= $ordenarPor === $valor ? 'selected' : '' ?>><?= h($rotulo) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="campo"><button type="submit" class="btn btn-secundario btn-bloco">Filtrar</button></div>
        </form>
    </div>

    <div class="estoque-colunas">
        <?php if (empty($itens)): ?>
            <div class="cartao"><div class="alerta alerta-info">ℹ️ Nenhum item encontrado com esses filtros.</div></div>
        <?php endif; ?>

        <?php foreach ($itens as $item): $iid = (int)$item['id']; ?>
            <div class="cartao estoque-mini-card<?= (int)$item['quantidade'] <= 2 ? ' estoque-baixo' : '' ?>" id="card-item-<?= $iid ?>">
                <div class="estoque-mini-card__visao">
                    <div class="estoque-mini-card__img">
                        <?php if (!empty($item['imagem_arquivo'])): ?>
                            <img src="../uploads/estoque/<?= h($item['imagem_arquivo']) ?>" alt="">
                        <?php else: ?>
                            <span>📦</span>
                        <?php endif; ?>
                    </div>
                    <strong><?= h($item['nome']) ?></strong>
                    <?php if (!empty($item['numero_serie'])): ?>
                        <div class="estoque-mini-card__serie">🔖 <?= h($item['numero_serie']) ?></div>
                    <?php endif; ?>
                    <div class="estoque-mini-card__tags">
                        <?php if (!empty($item['marca'])): ?><span class="badge badge-normal">🏷️ <?= h($item['marca']) ?></span><?php endif; ?>
                        <?php if (!empty($item['categoria_nome'])): ?><span class="badge badge-importante"><?= h($item['categoria_nome']) ?></span><?php endif; ?>
                    </div>
                    <div class="estoque-mini-card__qtd">Em estoque: <strong><?= (int)$item['quantidade'] ?></strong></div>
                    <?php if ((float)$item['valor_unitario'] > 0): ?>
                        <div class="estoque-mini-card__qtd">Valor unit.: <strong><?= h(formatar_dinheiro((float)$item['valor_unitario'])) ?></strong></div>
                    <?php endif; ?>
                    <button type="button" class="btn btn-secundario btn-sm btn-editar-item-estoque" data-alvo="edicao-item-<?= $iid ?>">✏️ Editar</button>
                </div>

                <form id="edicao-item-<?= $iid ?>" method="post" action="estoque-visualizar.php?<?= h($queryFiltros) ?>"
                      enctype="multipart/form-data" class="estoque-mini-card__edicao" style="display:none;">
                    <?= csrf_campo() ?>
                    <input type="hidden" name="acao" value="atualizar_item">
                    <input type="hidden" name="item_id" value="<?= $iid ?>">
                    <div class="campo"><label>Nome</label><input type="text" name="nome" value="<?= h($item['nome']) ?>" required></div>
                    <div class="campo"><label>Marca</label><input type="text" name="marca" value="<?= h($item['marca'] ?? '') ?>"></div>
                    <div class="campo">
                        <label>Categoria</label>
                        <select name="categoria_id">
                            <option value="0">Sem categoria</option>
                            <?php foreach ($categorias as $c): ?>
                                <option value="<?= (int)$c['id'] ?>" <?= (int)$item['categoria_id'] === (int)$c['id'] ? 'selected' : '' ?>><?= h($c['nome']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="campo"><label>Valor unitário (R$)</label><input type="number" step="0.01" min="0" name="valor_unitario" value="<?= h((string)$item['valor_unitario']) ?>"></div>
                    <div class="campo"><label>Número de série</label><input type="text" name="numero_serie" value="<?= h($item['numero_serie'] ?? '') ?>" placeholder="Ex.: EST-000042"></div>
                    <div class="campo"><label>Trocar imagem (opcional)</label><input type="file" name="imagem" accept="image/png,image/jpeg,image/webp,image/gif"></div>
                    <div class="acoes">
                        <button type="submit" class="btn btn-sucesso btn-sm">💾 Salvar</button>
                        <button type="button" class="btn btn-secundario btn-sm btn-cancelar-item-estoque" data-alvo="edicao-item-<?= $iid ?>">✖️ Cancelar</button>
                    </div>
                </form>
            </div>
        <?php endforeach; ?>
    </div>
</main>

<script>
    document.querySelectorAll('.btn-editar-item-estoque').forEach(function (botao) {
        botao.addEventListener('click', function () {
            document.getElementById(botao.getAttribute('data-alvo')).style.display = 'flex';
            botao.style.display = 'none';
        });
    });
    document.querySelectorAll('.btn-cancelar-item-estoque').forEach(function (botao) {
        botao.addEventListener('click', function () {
            document.getElementById(botao.getAttribute('data-alvo')).style.display = 'none';
        });
    });
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>
