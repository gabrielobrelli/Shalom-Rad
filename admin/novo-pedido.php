<?php
/**
 * admin/novo-pedido.php — Permite que a própria ADMINISTRAÇÃO crie um
 * pedido de cliente diretamente pelo painel (por exemplo, quando o
 * pedido chega por telefone ou pessoalmente na oficina, sem o cliente
 * preencher o formulário público em index.php).
 *
 * Usa exatamente a mesma função criar_pedido() do fluxo do cliente — o
 * pedido criado aqui é idêntico a um criado pelo cliente (mesmo token,
 * mesma possibilidade de o cliente reabrir e editar depois). A única
 * diferença é que, ao salvar, a administração é redirecionada para a
 * tela interna admin/pedido.php (não para a página pública do cliente).
 */
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/pedido_repo.php';

exigir_login_admin();

$erro = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    exigir_csrf();

    $clienteNome = trim((string)($_POST['cliente_nome'] ?? ''));
    $itensJson   = $_POST['itens_json'] ?? '[]';
    $itens       = json_decode($itensJson, true);

    if ($clienteNome === '') {
        $erro = 'Informe o nome do cliente/empresa.';
    } elseif (!is_array($itens) || count($itens) === 0) {
        $erro = 'Adicione ao menos um item ao pedido.';
    } else {
        $itensValidos = array_filter($itens, fn($i) => !empty($i['modelo_nome']) && (int)($i['quantidade'] ?? 0) > 0);
        if (count($itensValidos) === 0) {
            $erro = 'Adicione ao menos um item válido (com modelo e quantidade).';
        } else {
            $token = criar_pedido($clienteNome, $itensValidos);
            $pedidoCriado = buscar_pedido_por_token($token);
            header('Location: pedido.php?id=' . (int)$pedidoCriado['id'] . '&criado=1');
            exit;
        }
    }
}

$tituloPagina = 'Novo pedido (admin) — Shalom Radiadores';
$baseAssets = '../';
$paginaAtual = 'admin_novo_pedido';
require __DIR__ . '/../includes/header.php';
?>

<main class="container">
    <div class="cartao">
        <h1>📝 Criar pedido pela administração</h1>
        <p class="subtitulo">Use esta tela para lançar um pedido recebido por telefone, WhatsApp ou pessoalmente na oficina. O cliente poderá acompanhar e editar depois pelo link do pedido, normalmente.</p>

        <?php if ($erro): ?>
            <div class="alerta alerta-erro">⚠️ <?= h($erro) ?></div>
        <?php endif; ?>

        <form id="form-pedido" method="post" action="novo-pedido.php">
            <?= csrf_campo() ?>
            <input type="hidden" name="itens_json" id="itens_json">

            <div class="campo">
                <label for="cliente_nome">Nome do cliente / empresa *</label>
                <input type="text" id="cliente_nome" name="cliente_nome" required
                       value="<?= h($_POST['cliente_nome'] ?? '') ?>" placeholder="Ex.: Auto Peças Bandeirantes">
            </div>

            <h2 style="margin-top:26px;">🔧 Itens do pedido</h2>
            <div id="sem-itens" class="alerta alerta-info" style="display:none;">
                ℹ️ Nenhum item adicionado ainda. Clique em "Adicionar item" abaixo.
            </div>

            <div class="tabela-itens-wrap">
                <table class="tabela-itens">
                    <thead>
                        <tr>
                            <th>Modelo/Nome</th>
                            <th>Nº de série</th>
                            <th class="num">Quantidade</th>
                            <th>Situação</th>
                            <th class="center">Ação</th>
                        </tr>
                    </thead>
                    <tbody id="corpo-itens"></tbody>
                </table>
            </div>

            <button type="button" id="btn-adicionar-item" class="btn btn-secundario" style="margin-top:14px;">➕ Adicionar item</button>

            <div class="totais">
                <div class="item-total">
                    <div class="rotulo">Total de peças solicitadas</div>
                    <div class="valor" id="total-pecas">0</div>
                </div>
            </div>

            <div class="acoes">
                <button type="submit" class="btn btn-primario">📨 Criar pedido</button>
                <a href="pedidos.php" class="btn btn-secundario">✖️ Cancelar</a>
            </div>
        </form>
    </div>
</main>

<script>window.ITENS_INICIAIS = [];</script>
<script src="../assets/js/pedido.js"></script>

<?php require __DIR__ . '/../includes/footer.php'; ?>
