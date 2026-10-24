<?php
/**
 * admin/pedidos.php — Lista completa de pedidos de clientes, separada da
 * home do painel (admin/index.php). A partir daqui a administração pode
 * abrir um pedido para editar ou excluí-lo direto (com confirmação)
 * usando o botão 🗑️.
 */
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/pedido_repo.php';

exigir_login_admin();

$mensagem = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'excluir_pedido') {
    exigir_csrf();
    $id = inteiro_positivo($_POST['id'] ?? 0);
    if ($id > 0) {
        excluir_pedido($id);
        $mensagem = 'Pedido excluído com sucesso.';
    }
} elseif (isset($_GET['excluido'])) {
    $mensagem = 'Pedido excluído com sucesso.';
}

$pedidos = listar_pedidos_agrupados();
$pedidosEmAberto = count(array_filter($pedidos, fn($p) => $p['status'] !== 'concluido'));

$tituloPagina = 'Pedidos — Shalom Radiadores';
$baseAssets = '../';
$paginaAtual = 'admin_pedidos';
require __DIR__ . '/../includes/header.php';
?>

<main class="container largo">
    <div class="cartao">
        <div class="cabecalho-secao">
            <div>
                <h1>📋 Pedidos de clientes</h1>
                <p class="subtitulo"><?= $pedidosEmAberto ?> em aberto de <?= count($pedidos) ?> no total — ordem alfabética pelo nome do cliente.</p>
            </div>
            <div class="acoes">
                <a href="novo-pedido.php" class="btn btn-primario no-print">➕ Novo pedido</a>
            </div>
        </div>

        <?php if ($mensagem): ?><div class="alerta alerta-sucesso">✅ <?= h($mensagem) ?></div><?php endif; ?>

        <?php if (empty($pedidos)): ?>
            <div class="alerta alerta-info">ℹ️ Nenhum pedido cadastrado ainda.</div>
        <?php endif; ?>

        <div class="dashboard-lista">
            <?php foreach ($pedidos as $p): ?>
                <div class="dashboard-cliente">
                    <a href="pedido.php?id=<?= (int)$p['id'] ?>" style="text-decoration:none; color:inherit; flex:1; min-width:200px;">
                        <div class="nome"><?= h($p['cliente_nome']) ?></div>
                        <div class="meta">
                            <?= (int)$p['total_itens'] ?> item(ns) · <?= (int)$p['total_solicitado'] ?> peças solicitadas ·
                            <?= (int)$p['total_feito'] ?> feitas · Atualizado em <?= h(formatar_data($p['atualizado_em'])) ?>
                        </div>
                    </a>
                    <div class="dashboard-cliente__acoes">
                        <span class="badge badge-<?= h($p['status']) ?>"><?= icone_status($p['status']) ?> <?= h(rotulo_status($p['status'])) ?></span>
                        <button type="submit" form="form-excluir-<?= (int)$p['id'] ?>" class="btn btn-perigo btn-sm"
                                onclick="return confirm('Tem certeza que deseja EXCLUIR o pedido de &quot;<?= h(addslashes($p['cliente_nome'])) ?>&quot;? Essa ação não pode ser desfeita.');"
                                title="Excluir este pedido">🗑️</button>
                    </div>
                </div>
                <form id="form-excluir-<?= (int)$p['id'] ?>" method="post" action="pedidos.php" style="display:none;">
                    <?= csrf_campo() ?>
                    <input type="hidden" name="acao" value="excluir_pedido">
                    <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                </form>
            <?php endforeach; ?>
        </div>
    </div>
</main>

<?php require __DIR__ . '/../includes/footer.php'; ?>
