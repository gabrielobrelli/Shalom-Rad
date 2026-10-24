<?php
/**
 * index.php — Página inicial: onde o CLIENTE cria um pedido novo, sem
 * precisar de conta ou login. Ao enviar, o pedido é gravado no banco e o
 * cliente é redirecionado para pedido.php, sua página pessoal (protegida
 * por um token secreto na URL).
 */
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/pedido_repo.php';

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
            header('Location: ' . SITE_URL . '/pedido.php?token=' . urlencode($token) . '&novo=1');
            exit;
        }
    }
}

$tituloPagina = 'Novo pedido — Shalom Radiadores';
$baseAssets = '';
$paginaAtual = 'cliente';
$ocultarVoltar = true;
require __DIR__ . '/includes/header.php';
?>

<main class="container">

    <div class="hero-banner">
        <div class="hero-banner__conteudo">
            <span class="eyebrow">Shalom Radiadores</span>
            <h1>Conserto e recondicionamento de radiadores</h1>
            <p>Faça seu pedido abaixo — sem cadastro, sem senha.</p>
            <div class="hero-banner__features">
                <span class="hero-feature">✅ Sem cadastro</span>
                <span class="hero-feature">🔗 Acompanhe pelo link</span>
                <span class="hero-feature">⚡ Tempo real</span>
            </div>
        </div>
    </div>

    <div class="cartao">
        <h1>📝 Novo pedido</h1>
        <p class="subtitulo">Preencha os dados do veículo e das peças abaixo.</p>

        <?php if ($erro): ?>
            <div class="alerta alerta-erro">⚠️ <?= h($erro) ?></div>
        <?php endif; ?>

        <form id="form-pedido" method="post" action="index.php">
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
                <button type="submit" class="btn btn-primario">📨 Enviar pedido</button>
            </div>
        </form>
    </div>

    <p class="rodape-link">Já tem um pedido? Use o link que você recebeu para acompanhá-lo.</p>
</main>

<script>window.ITENS_INICIAIS = [];</script>
<script src="assets/js/pedido.js"></script>

<?php require __DIR__ . '/includes/footer.php'; ?>
