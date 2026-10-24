<?php
/**
 * admin/servico-novo.php — Área DEDICADA para lançar (ou editar) um
 * serviço, separada do histórico do dia em servicos.php. Chegando aqui
 * sem `servico_id` na URL, é um lançamento novo; com `servico_id`, edita
 * um serviço já existente (só dentro da janela de 24h — ver
 * pode_editar_servico() em servicos_repo.php).
 */
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/servicos_repo.php';
require_once __DIR__ . '/../includes/profissionais_repo.php';

exigir_login_admin();

$data = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($_GET['data'] ?? '')) ? $_GET['data'] : date('Y-m-d');
$servicoId = inteiro_positivo($_GET['servico_id'] ?? 0);
$servico = $servicoId ? buscar_servico($servicoId) : null;
$modoEdicao = $servico !== null;
$editavel = $modoEdicao ? pode_editar_servico($servico) : true;

// Preenchimento automático quando chegamos aqui logo depois de finalizar
// um pedido (ver admin/pedido.php) — o valor cobrado continua opcional,
// a administração decide se lança o valor agora ou deixa para depois.
$carroClientePrefil = trim((string)($_GET['carro_cliente'] ?? ''));
$origemPedidoId = inteiro_positivo($_GET['origem_pedido_id'] ?? 0);

$erro = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    exigir_csrf();

    $carroCliente = trim((string)($_POST['carro_cliente'] ?? ''));
    $pagamentos = json_decode($_POST['pagamentos_json'] ?? '[]', true) ?: [];
    $dataServico = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($_POST['data_servico'] ?? '')) ? $_POST['data_servico'] : $data;
    $servicoIdPost = inteiro_positivo($_POST['servico_id'] ?? 0);

    if ($carroCliente === '') {
        $erro = 'Preencha o campo Carro e Cliente.';
    } elseif ($servicoIdPost > 0) {
        // Modo edição — revalida a janela de 24h no servidor (não confia no formulário).
        $servicoExistente = buscar_servico($servicoIdPost);
        if (!$servicoExistente) {
            $erro = 'Serviço não encontrado.';
        } elseif (!pode_editar_servico($servicoExistente)) {
            $erro = 'Esse serviço foi lançado há mais de 24 horas — a edição não está mais disponível, só a exclusão pela tela de histórico.';
        } else {
            atualizar_servico(
                $servicoIdPost,
                $servicoExistente['data_servico'],
                $carroCliente,
                $_POST['descricao'] ?? '',
                array_map('intval', (array)($_POST['profissional_ids'] ?? [])),
                (float)str_replace(',', '.', $_POST['valor_cobrado'] ?? 0),
                (float)str_replace(',', '.', $_POST['desconto'] ?? 0),
                $pagamentos,
                $_POST['observacao_adm'] ?? ''
            );
            header('Location: servicos.php?visao=dia&data=' . urlencode($servicoExistente['data_servico']) . '&atualizado=1');
            exit;
        }
    } else {
        criar_servico(
            $dataServico,
            $carroCliente,
            $_POST['descricao'] ?? '',
            array_map('intval', (array)($_POST['profissional_ids'] ?? [])),
            (float)str_replace(',', '.', $_POST['valor_cobrado'] ?? 0),
            (float)str_replace(',', '.', $_POST['desconto'] ?? 0),
            $pagamentos,
            $_POST['observacao_adm'] ?? ''
        );
        header('Location: servicos.php?visao=dia&data=' . urlencode($dataServico) . '&lancado=1');
        exit;
    }
}

$profissionais = listar_profissionais_ativos();

$tituloPagina = ($modoEdicao ? 'Editar serviço' : 'Lançar serviço') . ' — Shalom Radiadores';
$baseAssets = '../';
$paginaAtual = 'admin_servicos';
require __DIR__ . '/../includes/header.php';
?>

<main class="container">
    <div class="cartao">
        <h1><?= $modoEdicao ? '✏️ Editar serviço' : '➕ Lançar novo serviço' ?></h1>
        <p class="subtitulo">
            <?php if ($modoEdicao): ?>
                Alterando o serviço de <strong><?= h($servico['carro_cliente']) ?></strong>, lançado em <?= h(formatar_data($servico['criado_em'])) ?>.
            <?php else: ?>
                Esta área serve só para registrar um serviço — o histórico do dia fica na tela de Serviços.
            <?php endif; ?>
        </p>

        <?php if ($erro): ?><div class="alerta alerta-erro">⚠️ <?= h($erro) ?></div><?php endif; ?>

        <?php if ($origemPedidoId && !$modoEdicao): ?>
            <div class="alerta alerta-info">
                ✅ Pedido finalizado! Se quiser, lance o valor cobrado abaixo para
                já entrar no extrato do dia — ou deixe R$ 0,00 e clique em
                "Cancelar" para pular por enquanto.
            </div>
        <?php endif; ?>

        <?php if ($modoEdicao && !$editavel): ?>
            <div class="alerta alerta-info">🔒 Esse serviço foi lançado há mais de 24 horas — não é possível editar, só excluir pela tela de histórico do dia.</div>
            <a href="servicos.php?visao=dia&data=<?= h($servico['data_servico']) ?>" class="btn btn-secundario">← Voltar ao histórico do dia</a>
        <?php else: ?>

        <form id="form-servico" method="post" action="servico-novo.php?data=<?= h($data) ?><?= $servicoId ? '&servico_id=' . $servicoId : '' ?>">
            <?= csrf_campo() ?>
            <input type="hidden" name="servico_id" value="<?= $servicoId ?>">
            <input type="hidden" name="pagamentos_json" id="pagamentos_json">

            <div class="linha-campos">
                <div class="campo">
                    <label>Data do serviço *</label>
                    <input type="date" name="data_servico" value="<?= h($modoEdicao ? $servico['data_servico'] : $data) ?>" required <?= $modoEdicao ? 'disabled' : '' ?>>
                    <?php if ($modoEdicao): ?><input type="hidden" name="data_servico" value="<?= h($servico['data_servico']) ?>"><?php endif; ?>
                </div>
                <div class="campo">
                    <label>Carro e Cliente *</label>
                    <input type="text" name="carro_cliente" required placeholder="Ex.: Onix — Auto Peças Bandeirantes"
                           value="<?= h($modoEdicao ? $servico['carro_cliente'] : $carroClientePrefil) ?>">
                </div>
                <div class="campo">
                    <label>Profissionais (pode marcar mais de um)</label>
                    <span class="selecao-profissionais" style="max-width:none;">
                        <?php $profissionaisSelecionados = $modoEdicao ? ($servico['profissional_ids'] ?? []) : []; ?>
                        <?php foreach ($profissionais as $p): ?>
                            <label class="selecao-profissionais__opcao">
                                <input type="checkbox" name="profissional_ids[]" value="<?= (int)$p['id'] ?>" <?= in_array((int)$p['id'], $profissionaisSelecionados, true) ? 'checked' : '' ?>> <?= h($p['nome']) ?>
                            </label>
                        <?php endforeach; ?>
                    </span>
                </div>
            </div>

            <div class="campo">
                <label>Descrição / O que foi feito</label>
                <textarea name="descricao" rows="3" placeholder="Ex.: Troca de mangueira superior e limpeza do radiador."><?= h($modoEdicao ? $servico['descricao'] : '') ?></textarea>
            </div>

            <div class="campo">
                <label>🗒️ Observação (uso interno do ADM)</label>
                <textarea name="observacao_adm" rows="2" placeholder="Recado interno sobre este serviço — não aparece pro cliente."><?= h($modoEdicao ? ($servico['observacao_adm'] ?? '') : '') ?></textarea>
            </div>

            <div class="linha-campos">
                <div class="campo"><label>Valor cobrado (R$)</label><input type="number" step="0.01" min="0" name="valor_cobrado" value="<?= h((string)($modoEdicao ? $servico['valor_cobrado'] : 0)) ?>"></div>
                <div class="campo"><label>Desconto (R$)</label><input type="number" step="0.01" min="0" name="desconto" value="<?= h((string)($modoEdicao ? $servico['desconto'] : 0)) ?>"></div>
            </div>

            <label>Forma(s) de pagamento</label>
            <p class="subtitulo" style="margin-top:-6px;">Pode dividir entre mais de uma forma — clique em "+ Adicionar forma de pagamento" quantas vezes precisar.</p>
            <div id="corpo-pagamentos"></div>
            <button type="button" id="btn-adicionar-pagamento" class="btn btn-secundario btn-sm" style="margin-top:8px;">➕ Adicionar forma de pagamento</button>

            <div class="acoes">
                <button type="submit" class="btn btn-primario"><?= $modoEdicao ? '💾 Salvar alterações' : '💾 Lançar serviço' ?></button>
                <a href="servicos.php?visao=dia&data=<?= h($data) ?>" class="btn btn-secundario">✖️ Cancelar</a>
            </div>
        </form>

        <script>
            window.PAGAMENTOS_INICIAIS = <?= json_encode($modoEdicao ? array_map(fn($p) => [
                'forma' => $p['forma'], 'valor' => $p['valor'], 'parcelas' => $p['parcelas'], 'conta' => $p['conta'] ?? '',
            ], $servico['pagamentos']) : [], JSON_UNESCAPED_UNICODE) ?>;
        </script>
        <script src="../assets/js/servico.js"></script>
        <?php endif; ?>
    </div>
</main>

<?php require __DIR__ . '/../includes/footer.php'; ?>
