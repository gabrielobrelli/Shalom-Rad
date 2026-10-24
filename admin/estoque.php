<?php
/**
 * admin/estoque.php — Módulo de gerenciamento de ESTOQUE.
 * Mostra os itens em formato de "feed" (mais recentemente alterados
 * primeiro, como uma linha do tempo de atividade), com filtros de busca,
 * categoria e marca, cadastro de itens/categorias novas, e botões rápidos
 * de "+" / "−" para ajustar quantidade sem precisar abrir formulário de
 * edição nenhum.
 */
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/estoque_repo.php';

exigir_login_admin();

$mensagem = null;
$erro = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    exigir_csrf();
    $acao = $_POST['acao'] ?? '';

    if ($acao === 'criar_categoria') {
        $nome = trim((string)($_POST['nome_categoria'] ?? ''));
        if ($nome === '') {
            $erro = 'Informe o nome da categoria.';
        } else {
            criar_categoria_estoque($nome);
            $mensagem = 'Categoria "' . $nome . '" criada.';
        }

    } elseif ($acao === 'excluir_categoria') {
        excluir_categoria_estoque(inteiro_positivo($_POST['categoria_id'] ?? 0));
        $mensagem = 'Categoria removida. Os itens que a usavam ficaram sem categoria.';

    } elseif ($acao === 'criar_item') {
        $nome = trim((string)($_POST['nome'] ?? ''));
        $numeroSerie = trim((string)($_POST['numero_serie'] ?? ''));
        if ($nome === '') {
            $erro = 'Informe o nome do item.';
        } elseif ($numeroSerie !== '' && numero_serie_em_uso($numeroSerie)) {
            $erro = 'Já existe um item com o número de série "' . h($numeroSerie) . '". Use outro número.';
        } else {
            try {
                $imagemArquivo = salvar_imagem_estoque($_FILES['imagem'] ?? null);
                criar_item_estoque(
                    $nome,
                    $_POST['marca'] ?? '',
                    inteiro_positivo($_POST['categoria_id'] ?? 0) ?: null,
                    inteiro_positivo($_POST['quantidade'] ?? 0),
                    (float)str_replace(',', '.', $_POST['valor_unitario'] ?? 0),
                    $numeroSerie ?: null,
                    $imagemArquivo
                );
                $mensagem = 'Item "' . h($nome) . '" cadastrado no estoque.';
            } catch (RuntimeException $e) {
                $erro = $e->getMessage();
            }
        }

    } elseif ($acao === 'ajustar_quantidade') {
        $itemId = inteiro_positivo($_POST['item_id'] ?? 0);
        $tipo = ($_POST['tipo'] ?? '') === 'saida' ? 'saida' : 'entrada';
        ajustar_quantidade_estoque($itemId, 1, $tipo);

    } elseif ($acao === 'excluir_item') {
        excluir_item_estoque(inteiro_positivo($_POST['item_id'] ?? 0));
        $mensagem = 'Item removido do estoque.';
    }
}

$busca = trim((string)($_GET['busca'] ?? ''));
$categoriaFiltro = inteiro_positivo($_GET['categoria_id'] ?? 0);
$marcaFiltro = trim((string)($_GET['marca'] ?? ''));
$valorMinFiltro = trim((string)($_GET['valor_min'] ?? '')) !== '' ? (float)str_replace(',', '.', $_GET['valor_min']) : null;
$valorMaxFiltro = trim((string)($_GET['valor_max'] ?? '')) !== '' ? (float)str_replace(',', '.', $_GET['valor_max']) : null;
$ordenarPor = array_key_exists($_GET['ordenar'] ?? '', ORDENACOES_ESTOQUE) ? $_GET['ordenar'] : 'nome_asc';

$itens = listar_itens_estoque($busca, $categoriaFiltro, $marcaFiltro, $valorMinFiltro, $valorMaxFiltro, $ordenarPor);

// Se a migração do valor unitário (migracao-v5.sql) ainda não rodou no
// banco, a coluna "valor_unitario" não existe — sem essa proteção, a
// página inteira quebrava.
try {
    $valorTotalEstoque = valor_total_estoque();
} catch (Throwable $e) {
    $valorTotalEstoque = 0.0;
}
$categorias = listar_categorias_estoque();
$marcas = listar_marcas_estoque();

$queryFiltros = http_build_query([
    'busca' => $busca, 'categoria_id' => $categoriaFiltro, 'marca' => $marcaFiltro,
    'valor_min' => $valorMinFiltro ?? '', 'valor_max' => $valorMaxFiltro ?? '', 'ordenar' => $ordenarPor,
]);

$tituloPagina = 'Estoque — Shalom Radiadores';
$baseAssets = '../';
$paginaAtual = 'admin_estoque';
require __DIR__ . '/../includes/header.php';
?>

<main class="container largo">

    <div class="cartao">
        <div style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:10px;">
            <div>
                <h1>📦 Estoque</h1>
                <p class="subtitulo">Peças e materiais disponíveis na oficina. Os itens mais alterados recentemente aparecem primeiro, como um feed de atividade.</p>
            </div>
            <div class="acoes no-print" style="margin-top:0;">
                <a href="estoque-visualizar.php" class="btn btn-secundario">📁 Ver tudo (colunas)</a>
                <a href="imprimir-estoque.php?<?= h($queryFiltros) ?>" target="_blank" class="btn btn-secundario">🖨️ Imprimir</a>
                <a href="exportar-estoque.php?<?= h($queryFiltros) ?>" class="btn btn-secundario">📊 Excel</a>
                <a href="exportar-estoque-pdf.php?<?= h($queryFiltros) ?>" class="btn btn-secundario">📄 PDF</a>
            </div>
        </div>

        <?php if ($mensagem): ?><div class="alerta alerta-sucesso">✅ <?= h($mensagem) ?></div><?php endif; ?>
        <?php if ($erro): ?><div class="alerta alerta-erro">⚠️ <?= h($erro) ?></div><?php endif; ?>

        <div class="totais" style="flex-wrap:wrap;">
            <div class="item-total"><div class="rotulo">Itens cadastrados</div><div class="valor"><?= count($itens) ?></div></div>
            <div class="item-total"><div class="rotulo">Valor total em estoque</div><div class="valor"><?= h(formatar_dinheiro($valorTotalEstoque)) ?></div></div>
        </div>

        <!-- ================= Filtros / busca ================= -->
        <form method="get" action="estoque.php" class="linha-campos" style="align-items:end;">
            <div class="campo">
                <label for="busca">🔎 Nome, marca ou nº de série</label>
                <input type="text" id="busca" name="busca" value="<?= h($busca) ?>" placeholder="Ex.: mangueira, Bosch, EST-000042...">
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
            <div class="campo">
                <button type="submit" class="btn btn-secundario btn-bloco">Filtrar</button>
            </div>
        </form>
    </div>

    <!-- ================= Cadastro: categoria + item ================= -->
    <div class="cartao">
        <h2>📁 Categorias</h2>
        <div style="display:flex; flex-wrap:wrap; gap:8px; margin-bottom:14px;">
            <?php foreach ($categorias as $c): ?>
                <span class="badge badge-normal">
                    <?= h($c['nome']) ?>
                    <button type="submit" form="form-excluir-cat-<?= (int)$c['id'] ?>" class="btn-icone-remover" title="Excluir categoria" onclick="return confirm('Excluir a categoria &quot;<?= h(addslashes($c['nome'])) ?>&quot;? Os itens ficarão sem categoria.');">✕</button>
                </span>
                <form id="form-excluir-cat-<?= (int)$c['id'] ?>" method="post" action="estoque.php" style="display:none;">
                    <?= csrf_campo() ?>
                    <input type="hidden" name="acao" value="excluir_categoria">
                    <input type="hidden" name="categoria_id" value="<?= (int)$c['id'] ?>">
                </form>
            <?php endforeach; ?>
            <?php if (empty($categorias)): ?><span class="subtitulo" style="margin:0;">Nenhuma categoria criada ainda.</span><?php endif; ?>
        </div>
        <form method="post" action="estoque.php" style="display:flex; gap:10px; flex-wrap:wrap; align-items:end;">
            <?= csrf_campo() ?>
            <input type="hidden" name="acao" value="criar_categoria">
            <div class="campo" style="flex:1; min-width:200px; margin-bottom:0;">
                <label for="nome_categoria">Nova categoria</label>
                <input type="text" id="nome_categoria" name="nome_categoria" placeholder="Ex.: Mangueiras">
            </div>
            <button type="submit" class="btn btn-secundario">➕ Criar categoria</button>
        </form>
    </div>

    <div class="cartao">
        <h2>➕ Cadastrar novo item</h2>
        <form method="post" action="estoque.php" enctype="multipart/form-data">
            <?= csrf_campo() ?>
            <input type="hidden" name="acao" value="criar_item">
            <div class="linha-campos">
                <div class="campo"><label>Nome do item *</label><input type="text" name="nome" required placeholder="Ex.: Mangueira superior"></div>
                <div class="campo"><label>Marca</label><input type="text" name="marca" placeholder="Ex.: Bosch"></div>
                <div class="campo">
                    <label>Categoria</label>
                    <select name="categoria_id">
                        <option value="0">Sem categoria</option>
                        <?php foreach ($categorias as $c): ?>
                            <option value="<?= (int)$c['id'] ?>"><?= h($c['nome']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="campo"><label>Quantidade inicial</label><input type="number" min="0" name="quantidade" value="0"></div>
                <div class="campo"><label>Valor unitário (R$)</label><input type="number" step="0.01" min="0" name="valor_unitario" value="0"></div>
                <div class="campo"><label>Número de série (opcional)</label><input type="text" name="numero_serie" placeholder="Ex.: EST-000042"></div>
                <div class="campo"><label>Imagem (opcional)</label><input type="file" name="imagem" accept="image/png,image/jpeg,image/webp,image/gif"></div>
            </div>
            <button type="submit" class="btn btn-primario">💾 Cadastrar item</button>
        </form>
    </div>

    <!-- ================= Feed de itens ================= -->
    <div class="estoque-feed">
        <?php if (empty($itens)): ?>
            <div class="cartao"><div class="alerta alerta-info">ℹ️ Nenhum item encontrado com esses filtros.</div></div>
        <?php endif; ?>

        <?php foreach ($itens as $item): ?>
            <div class="cartao estoque-card<?= (int)$item['quantidade'] <= 2 ? ' estoque-baixo' : '' ?>">
                <div class="estoque-card__img">
                    <?php if (!empty($item['imagem_arquivo'])): ?>
                        <img src="../uploads/estoque/<?= h($item['imagem_arquivo']) ?>" alt="<?= h($item['nome']) ?>">
                    <?php else: ?>
                        <span class="estoque-card__img-placeholder">📦</span>
                    <?php endif; ?>
                </div>
                <div class="estoque-card__corpo">
                    <div class="estoque-card__topo">
                        <strong class="estoque-card__nome"><?= h($item['nome']) ?></strong>
                        <span class="estoque-card__data">🕒 <?= h(formatar_data($item['atualizado_em'])) ?></span>
                    </div>
                    <?php if (!empty($item['numero_serie'])): ?>
                        <div class="estoque-card__serie">🔖 <?= h($item['numero_serie']) ?></div>
                    <?php endif; ?>
                    <div class="estoque-card__tags">
                        <?php if (!empty($item['marca'])): ?><span class="badge badge-normal">🏷️ <?= h($item['marca']) ?></span><?php endif; ?>
                        <?php if (!empty($item['categoria_nome'])): ?><span class="badge badge-importante"><?= h($item['categoria_nome']) ?></span><?php endif; ?>
                    </div>
                    <div class="estoque-card__rodape">
                        <div class="estoque-card__qtd">
                            <span class="rotulo">Em estoque</span>
                            <span class="valor<?= (int)$item['quantidade'] <= 2 ? ' qtd-baixa' : '' ?>"><?= (int)$item['quantidade'] ?></span>
                        </div>
                        <?php if ((float)$item['valor_unitario'] > 0): ?>
                        <div class="estoque-card__qtd">
                            <span class="rotulo">Valor unitário</span>
                            <span class="valor" style="font-size:1.1rem;"><?= h(formatar_dinheiro((float)$item['valor_unitario'])) ?></span>
                        </div>
                        <div class="estoque-card__qtd">
                            <span class="rotulo">Valor total</span>
                            <span class="valor" style="font-size:1.1rem;"><?= h(formatar_dinheiro((float)$item['valor_unitario'] * (int)$item['quantidade'])) ?></span>
                        </div>
                        <?php endif; ?>
                        <form method="post" action="estoque.php" style="display:flex; gap:6px;">
                            <?= csrf_campo() ?>
                            <input type="hidden" name="acao" value="ajustar_quantidade">
                            <input type="hidden" name="item_id" value="<?= (int)$item['id'] ?>">
                            <button type="submit" name="tipo" value="saida" class="btn btn-secundario btn-sm" title="Retirar 1 do estoque">➖</button>
                            <button type="submit" name="tipo" value="entrada" class="btn btn-secundario btn-sm" title="Adicionar 1 ao estoque">➕</button>
                        </form>
                        <button type="submit" form="form-excluir-item-<?= (int)$item['id'] ?>" class="btn btn-perigo btn-sm" onclick="return confirm('Excluir &quot;<?= h(addslashes($item['nome'])) ?>&quot; do estoque? Essa ação não pode ser desfeita.');">🗑️</button>
                    </div>
                </div>
            </div>
            <form id="form-excluir-item-<?= (int)$item['id'] ?>" method="post" action="estoque.php" style="display:none;">
                <?= csrf_campo() ?>
                <input type="hidden" name="acao" value="excluir_item">
                <input type="hidden" name="item_id" value="<?= (int)$item['id'] ?>">
            </form>
        <?php endforeach; ?>
    </div>
</main>

<?php require __DIR__ . '/../includes/footer.php'; ?>
