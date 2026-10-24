<?php
/**
 * admin/profissionais.php — Cadastro de profissionais e ranking de
 * produtividade: quantas peças de radiador cada um já concluiu e quantos
 * serviços já realizou. Tem uma busca por nome para achar rápido.
 */
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/profissionais_repo.php';

exigir_login_admin();

$mensagem = null;
$erro = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    exigir_csrf();
    $acao = $_POST['acao'] ?? '';

    if ($acao === 'criar') {
        $nome = trim((string)($_POST['nome'] ?? ''));
        if ($nome === '') {
            $erro = 'Informe o nome do profissional.';
        } else {
            criar_profissional($nome);
            $mensagem = 'Profissional "' . h($nome) . '" cadastrado.';
        }
    } elseif ($acao === 'inativar') {
        inativar_profissional(inteiro_positivo($_POST['id'] ?? 0));
        $mensagem = 'Profissional marcado como inativo.';
    } elseif ($acao === 'reativar') {
        reativar_profissional(inteiro_positivo($_POST['id'] ?? 0));
        $mensagem = 'Profissional reativado.';
    }
}

$busca = trim((string)($_GET['busca'] ?? ''));
$ranking = ranking_profissionais($busca);

$tituloPagina = 'Profissionais — Shalom Radiadores';
$baseAssets = '../';
$paginaAtual = 'admin_servicos';
require __DIR__ . '/../includes/header.php';
?>

<main class="container">
    <div class="cartao">
        <h1>👷 Profissionais</h1>
        <p class="subtitulo">Cadastre a equipe da oficina e acompanhe quanto cada um já produziu.</p>

        <?php if ($mensagem): ?><div class="alerta alerta-sucesso">✅ <?= h($mensagem) ?></div><?php endif; ?>
        <?php if ($erro): ?><div class="alerta alerta-erro">⚠️ <?= h($erro) ?></div><?php endif; ?>

        <form method="post" action="profissionais.php" style="display:flex; gap:10px; flex-wrap:wrap; align-items:end;">
            <?= csrf_campo() ?>
            <input type="hidden" name="acao" value="criar">
            <div class="campo" style="flex:1; min-width:220px; margin-bottom:0;">
                <label for="nome">Nome do profissional</label>
                <input type="text" id="nome" name="nome" placeholder="Ex.: João">
            </div>
            <button type="submit" class="btn btn-primario">➕ Cadastrar</button>
        </form>
    </div>

    <div class="cartao">
        <h2>🔎 Ranking de produção</h2>
        <form method="get" action="profissionais.php" style="display:flex; gap:10px; margin-bottom:16px;">
            <input type="text" name="busca" value="<?= h($busca) ?>" placeholder="Buscar por nome..." style="flex:1;">
            <button type="submit" class="btn btn-secundario">Buscar</button>
        </form>

        <div class="tabela-itens-wrap">
            <table class="tabela-itens">
                <thead>
                    <tr>
                        <th>Nome</th>
                        <th class="num">Peças de radiador feitas</th>
                        <th class="num">Serviços realizados</th>
                        <th class="center">Status</th>
                        <th class="center">Ação</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($ranking as $p): ?>
                    <tr>
                        <td data-rotulo="Nome"><strong><?= h($p['nome']) ?></strong></td>
                        <td data-rotulo="Peças" class="num"><?= (int)$p['total_pecas_feitas'] ?></td>
                        <td data-rotulo="Serviços" class="num"><?= (int)$p['total_servicos'] ?></td>
                        <td data-rotulo="Status" class="center">
                            <span class="badge <?= $p['ativo'] ? 'badge-concluido' : 'badge-novo' ?>"><?= $p['ativo'] ? 'Ativo' : 'Inativo' ?></span>
                        </td>
                        <td data-rotulo="Ação" class="center">
                            <?php if ($p['ativo']): ?>
                                <button type="submit" form="form-inativar-<?= (int)$p['id'] ?>" class="btn btn-secundario btn-sm" onclick="return confirm('Marcar &quot;<?= h(addslashes($p['nome'])) ?>&quot; como inativo?');">Inativar</button>
                                <form id="form-inativar-<?= (int)$p['id'] ?>" method="post" action="profissionais.php" style="display:none;">
                                    <?= csrf_campo() ?><input type="hidden" name="acao" value="inativar"><input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                                </form>
                            <?php else: ?>
                                <button type="submit" form="form-reativar-<?= (int)$p['id'] ?>" class="btn btn-secundario btn-sm">Reativar</button>
                                <form id="form-reativar-<?= (int)$p['id'] ?>" method="post" action="profissionais.php" style="display:none;">
                                    <?= csrf_campo() ?><input type="hidden" name="acao" value="reativar"><input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($ranking)): ?>
                        <tr><td colspan="5">Nenhum profissional encontrado.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

<?php require __DIR__ . '/../includes/footer.php'; ?>
