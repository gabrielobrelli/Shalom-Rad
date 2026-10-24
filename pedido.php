<?php
/**
 * pedido.php — Página pessoal do pedido do CLIENTE, acessada pelo link
 * secreto (?token=...). Tem dois "modos" na mesma página, controlados por
 * JavaScript no final do arquivo:
 *   - Modo leitura (padrão): mostra a lista de itens de forma fechada,
 *     sem campos editáveis, só para conferência.
 *   - Modo edição: aparece ao clicar em "✏️ Editar pedido" e reaproveita o
 *     mesmo formulário dinâmico de index.php (assets/js/pedido.js) para
 *     adicionar/remover/alterar itens antes de salvar.
 */
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/pedido_repo.php';

$token = trim((string)($_GET['token'] ?? $_POST['token'] ?? ''));
if ($token === '') {
    http_response_code(400);
    die('Link inválido. Verifique se copiou o endereço completo.');
}

$pedido = buscar_pedido_por_token($token);
if (!$pedido) {
    http_response_code(404);
    die('Pedido não encontrado. Verifique se o link está correto.');
}

$erro = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    exigir_csrf();

    $itensJson = $_POST['itens_json'] ?? '[]';
    $itens = json_decode($itensJson, true);

    if (!is_array($itens) || count($itens) === 0) {
        $erro = 'O pedido precisa ter ao menos um item.';
    } else {
        $itensValidos = array_filter($itens, fn($i) => !empty($i['modelo_nome']) && (int)($i['quantidade'] ?? 0) > 0);
        if (count($itensValidos) === 0) {
            $erro = 'Adicione ao menos um item válido (com modelo e quantidade).';
        } else {
            substituir_itens_pedido((int)$pedido['id'], $itensValidos);
            header('Location: ' . SITE_URL . '/pedido.php?token=' . urlencode($token) . '&atualizado=1');
            exit;
        }
    }
}

$itens = listar_itens_pedido((int)$pedido['id']);
$totalSolicitado = total_solicitado($itens);

$mostrarWhatsapp = false;
$linkWhatsapp = '';
if (isset($_GET['novo'])) {
    $mostrarWhatsapp = true;
    $linkWhatsapp = link_whatsapp('Lista de pedidos enviada - ' . link_pedido($token));
} elseif (isset($_GET['atualizado'])) {
    $mostrarWhatsapp = true;
    $linkWhatsapp = link_whatsapp('Lista de pedidos atualizada - ' . link_pedido($token));
}

$tituloPagina = 'Pedido de ' . $pedido['cliente_nome'] . ' — Shalom Radiadores';
$baseAssets = '';
require __DIR__ . '/includes/header.php';
?>

<main class="container">

    <?php if ($mostrarWhatsapp): ?>
        <div class="cartao no-print" style="border-color:#25D366;">
            <h2 style="margin-bottom:6px;">✅ Pedido <?= isset($_GET['novo']) ? 'enviado' : 'atualizado' ?> com sucesso!</h2>
            <p class="subtitulo">Guarde o link desta página para acompanhar ou editar seu pedido depois. Envie a confirmação pelo WhatsApp:</p>
            <a href="<?= h($linkWhatsapp) ?>" target="_blank" rel="noopener" class="btn btn-whatsapp" id="btn-whatsapp">
                💬 Abrir WhatsApp e enviar confirmação
            </a>
        </div>
        <script>
            window.addEventListener('load', function () {
                try { window.open(document.getElementById('btn-whatsapp').href, '_blank'); } catch (e) {}
            });
        </script>
    <?php endif; ?>

    <div class="cartao">
        <div style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:10px;">
            <div>
                <h1><?= h($pedido['cliente_nome']) ?></h1>
                <p class="subtitulo">
                    Status: <span class="badge badge-<?= h($pedido['status']) ?>"><?= icone_status($pedido['status']) ?> <?= h(rotulo_status($pedido['status'])) ?></span>
                    &nbsp;·&nbsp; Criado em <?= h(formatar_data($pedido['criado_em'])) ?>
                    &nbsp;·&nbsp; Atualizado em <?= h(formatar_data($pedido['atualizado_em'])) ?>
                </p>
            </div>
            <div class="acoes no-print" style="margin-top:0;">
                <a href="imprimir-pedido.php?token=<?= h($token) ?>" target="_blank" class="btn btn-secundario">🖨️ Imprimir</a>
                <a href="exportar-pedido.php?token=<?= h($token) ?>" class="btn btn-secundario">📊 Excel</a>
                <a href="exportar-pedido-pdf.php?token=<?= h($token) ?>" class="btn btn-secundario">📄 PDF</a>
            </div>
        </div>

        <?php if ($erro): ?>
            <div class="alerta alerta-erro">⚠️ <?= h($erro) ?></div>
        <?php endif; ?>

        <!-- ===================== MODO LEITURA (padrão) ===================== -->
        <div id="modo-leitura">
            <div class="aviso-modo-leitura no-print">🔒 Esta lista está fechada para edição. Clique em "Editar pedido" para alterar os itens.</div>

            <div class="tabela-itens-wrap">
                <table class="tabela-itens">
                    <thead>
                        <tr>
                            <th>Modelo/Nome</th>
                            <th>Nº de série</th>
                            <th class="num">Quantidade</th>
                            <th>Situação</th>
                        </tr>
                    </thead>
                    <tbody class="lista-fechada">
                        <?php foreach ($itens as $item): ?>
                        <tr>
                            <td data-rotulo="Modelo/Nome"><?= h($item['modelo_nome']) ?></td>
                            <td data-rotulo="Nº de série"><?= h($item['numero_serie'] ?: '-') ?></td>
                            <td data-rotulo="Quantidade" class="num"><?= (int)$item['quantidade'] ?></td>
                            <td data-rotulo="Situação"><span class="badge badge-<?= h($item['situacao']) ?>"><?= icone_situacao($item['situacao']) ?> <?= h(rotulo_situacao($item['situacao'])) ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="totais">
                <div class="item-total">
                    <div class="rotulo">Total de peças solicitadas</div>
                    <div class="valor"><?= h((string)$totalSolicitado) ?></div>
                </div>
            </div>

            <div class="acoes no-print">
                <button type="button" id="btn-abrir-edicao" class="btn btn-primario">✏️ Editar pedido</button>
            </div>
        </div>

        <!-- ===================== MODO EDIÇÃO (oculto por padrão) ===================== -->
        <div id="modo-edicao" style="display:none;">
            <form id="form-pedido" method="post" action="pedido.php?token=<?= h($token) ?>">
                <?= csrf_campo() ?>
                <input type="hidden" name="token" value="<?= h($token) ?>">
                <input type="hidden" name="itens_json" id="itens_json">

                <h2 style="margin-top:22px;">🔧 Itens do pedido</h2>
                <div id="sem-itens" class="alerta alerta-info" style="display:none;">
                    ℹ️ Nenhum item no pedido. Clique em "Adicionar item" abaixo.
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
                        <div class="valor" id="total-pecas"><?= h((string)$totalSolicitado) ?></div>
                    </div>
                </div>

                <div class="acoes">
                    <button type="submit" class="btn btn-sucesso">💾 Salvar alterações</button>
                    <button type="button" id="btn-cancelar-edicao" class="btn btn-secundario">✖️ Cancelar</button>
                </div>
            </form>
        </div>
    </div>

    <p class="rodape-link no-print">Este link é pessoal e dá acesso ao seu pedido. Não compartilhe com terceiros.</p>
</main>

<script>
    window.ITENS_INICIAIS = <?= json_encode(array_map(function ($i) {
        return [
            'modelo_nome'  => $i['modelo_nome'],
            'numero_serie' => $i['numero_serie'],
            'quantidade'   => (int)$i['quantidade'],
            'situacao'     => $i['situacao'],
        ];
    }, $itens), JSON_UNESCAPED_UNICODE) ?>;
</script>
<script src="assets/js/pedido.js"></script>
<script>
    // Alterna entre o modo leitura (fechado) e o modo edição desta página.
    // Mantido separado de pedido.js porque essa lógica de "abrir/fechar"
    // só existe aqui em pedido.php (index.php não tem pedido já salvo).
    (function () {
        const leitura = document.getElementById('modo-leitura');
        const edicao = document.getElementById('modo-edicao');
        const btnAbrir = document.getElementById('btn-abrir-edicao');
        const btnCancelar = document.getElementById('btn-cancelar-edicao');

        if (btnAbrir) {
            btnAbrir.addEventListener('click', function () {
                leitura.style.display = 'none';
                edicao.style.display = 'block';
            });
        }
        if (btnCancelar) {
            btnCancelar.addEventListener('click', function () {
                // Recarrega a página: forma mais simples e segura de descartar
                // qualquer alteração ainda não salva e voltar ao modo fechado.
                window.location.reload();
            });
        }
    })();
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
