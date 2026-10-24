<?php
/**
 * admin/lembretes.php — Quadro de lembretes livres: a administração
 * pode lançar recadinhos rápidos (com título ou não), escolher uma cor,
 * editar e excluir. Sem nenhuma outra regra além disso.
 */
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/lembretes_repo.php';

exigir_login_admin();

$mensagem = null;
$erro = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    exigir_csrf();
    $acao = $_POST['acao'] ?? '';

    if ($acao === 'salvar_lembrete') {
        $titulo = trim((string)($_POST['titulo'] ?? ''));
        $texto = trim((string)($_POST['texto'] ?? ''));
        $urgencia = (string)($_POST['urgencia'] ?? 'normal');
        $lembreteId = inteiro_positivo($_POST['lembrete_id'] ?? 0);

        if ($texto === '') {
            $erro = 'Escreva algum texto para o lembrete.';
        } elseif ($lembreteId > 0) {
            atualizar_lembrete($lembreteId, $titulo, $texto, $urgencia);
            $mensagem = 'Lembrete atualizado.';
        } else {
            criar_lembrete($titulo, $texto, $urgencia);
            $mensagem = 'Lembrete adicionado.';
        }

    } elseif ($acao === 'excluir_lembrete') {
        excluir_lembrete(inteiro_positivo($_POST['lembrete_id'] ?? 0));
        $mensagem = 'Lembrete removido.';
    }
}

$editando = isset($_GET['editar']) ? buscar_lembrete(inteiro_positivo($_GET['editar'])) : null;
$lembretes = listar_lembretes();

$niveisUrgencia = ['urgente' => 'Urgente', 'importante' => 'Importante', 'normal' => 'Normal'];
$urgenciaAtual = $editando['urgencia'] ?? 'normal';

$tituloPagina = 'Lembretes — Shalom Radiadores';
$baseAssets = '../';
$paginaAtual = 'admin_lembretes';
require __DIR__ . '/../includes/header.php';
?>

<main class="container">

    <div class="cartao" id="form-topo">
        <span class="eyebrow">Painel administrativo</span>
        <h1>🗒️ Lembretes</h1>
        <p class="subtitulo">Recadinhos rápidos para a equipe — com título ou não, marcados por urgência. Os mais urgentes aparecem primeiro.</p>

        <?php if ($mensagem): ?><div class="alerta alerta-sucesso">✅ <?= h($mensagem) ?></div><?php endif; ?>
        <?php if ($erro): ?><div class="alerta alerta-erro">⚠️ <?= h($erro) ?></div><?php endif; ?>

        <form method="post" action="lembretes.php">
            <?= csrf_campo() ?>
            <input type="hidden" name="acao" value="salvar_lembrete">
            <input type="hidden" name="lembrete_id" value="<?= $editando ? (int)$editando['id'] : 0 ?>">

            <div class="campo">
                <label>Título (opcional)</label>
                <input type="text" name="titulo" maxlength="150" placeholder="Ex.: Ligar para o fornecedor"
                       value="<?= h($editando['titulo'] ?? '') ?>">
            </div>

            <div class="campo">
                <label>Lembrete *</label>
                <textarea name="texto" rows="3" required placeholder="Escreva o recado aqui..."><?= h($editando['texto'] ?? '') ?></textarea>
            </div>

            <div class="campo">
                <label>Urgência</label>
                <div class="seletor-urgencia">
                    <?php foreach ($niveisUrgencia as $valor => $rotulo): ?>
                        <label class="urgencia-opcao lembrete-urgencia-<?= $valor ?>">
                            <input type="radio" name="urgencia" value="<?= $valor ?>" <?= $urgenciaAtual === $valor ? 'checked' : '' ?>>
                            <span><?= icone_situacao($valor) ?> <?= $rotulo ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="acoes">
                <button type="submit" class="btn btn-primario"><?= $editando ? '💾 Salvar alterações' : '➕ Adicionar lembrete' ?></button>
                <?php if ($editando): ?>
                    <a href="lembretes.php" class="btn btn-secundario">✖️ Cancelar edição</a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <div class="cartao">
        <h2 style="margin-top:0;">📌 Quadro de lembretes</h2>

        <?php if (empty($lembretes)): ?>
            <div class="alerta alerta-info">ℹ️ Nenhum lembrete por aqui ainda.</div>
        <?php else: ?>
            <div class="lembretes-grade">
                <?php foreach ($lembretes as $l): ?>
                    <div class="lembrete-nota lembrete-urgencia-<?= h($l['urgencia']) ?>">
                        <?php if (!empty($l['titulo'])): ?>
                            <div class="lembrete-nota__titulo"><?= icone_situacao($l['urgencia']) ?> <?= h($l['titulo']) ?></div>
                        <?php endif; ?>
                        <div class="lembrete-nota__texto"><?= nl2br(h($l['texto'])) ?></div>
                        <div class="lembrete-nota__rodape">
                            <span><?= icone_situacao($l['urgencia']) ?> <?= h(rotulo_situacao($l['urgencia'])) ?> · <?= h(formatar_data($l['criado_em'])) ?></span>
                            <div class="lembrete-nota__acoes">
                                <a href="lembretes.php?editar=<?= (int)$l['id'] ?>#form-topo" title="Editar">✏️</a>
                                <button type="submit" form="form-excluir-lembrete-<?= (int)$l['id'] ?>"
                                        onclick="return confirm('Excluir este lembrete?');" title="Excluir">🗑️</button>
                            </div>
                        </div>
                    </div>
                    <form id="form-excluir-lembrete-<?= (int)$l['id'] ?>" method="post" action="lembretes.php" style="display:none;">
                        <?= csrf_campo() ?>
                        <input type="hidden" name="acao" value="excluir_lembrete">
                        <input type="hidden" name="lembrete_id" value="<?= (int)$l['id'] ?>">
                    </form>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

</main>

<?php require __DIR__ . '/../includes/footer.php'; ?>
