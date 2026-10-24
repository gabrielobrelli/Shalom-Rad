<?php
/**
 * admin/backup.php — Gera e envia para download um backup completo do
 * sistema: todos os arquivos da pasta raiz do site (o "htdocs") + um
 * dump SQL atualizado do banco, juntos em um único .zip. O arquivo é
 * montado inteiro em memória e enviado direto — nunca é escrito no
 * disco do servidor (isso evita bloqueios comuns de hospedagens
 * compartilhadas ao tentar criar arquivos .zip em disco).
 */
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/backup_repo.php';

exigir_login_admin();

$erro = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'gerar_backup') {
    exigir_csrf();

    try {
        $zip = gerar_backup_zip();
        $nomeDownload = 'backup-shalom-radiadores-' . date('Y-m-d_H-i') . '.zip';
        $zip->enviarComoDownload($nomeDownload);
        exit;
    } catch (Throwable $e) {
        error_log('Erro ao gerar backup: ' . $e->getMessage());
        // Mostra o motivo real (não uma mensagem genérica) — assim dá pra
        // saber exatamente o que corrigir se acontecer de novo.
        $erro = $e->getMessage();
    }
}

$tituloPagina = 'Backup do sistema — Shalom Radiadores';
$baseAssets = '../';
$paginaAtual = 'admin_backup';
require __DIR__ . '/../includes/header.php';
?>

<main class="container">
    <div class="cartao">
        <span class="eyebrow">Painel administrativo</span>
        <h1>💾 Backup completo do sistema</h1>
        <p class="subtitulo">
            Gera, na hora, um arquivo .zip com toda a pasta raiz do site
            (PHP, CSS, imagens enviadas) e um dump atualizado de todo o
            banco de dados — pedidos, seminovos, estoque, serviços,
            profissionais, tudo como está agora. O download começa
            automaticamente ao clicar no botão abaixo.
        </p>

        <?php if ($erro): ?>
            <div class="alerta alerta-erro">⚠️ <?= h($erro) ?></div>
        <?php endif; ?>

        <div class="alerta alerta-info">
            ℹ️ O arquivo pode levar alguns segundos para ser gerado e
            pode ter alguns megabytes, dependendo da quantidade de fotos
            já enviadas ao sistema. Guarde-o em um lugar seguro (Google
            Drive, pendrive, e-mail para você mesmo etc.) — ele contém
            os dados completos da oficina, incluindo a senha de acesso
            ao banco de dados.
        </div>

        <form method="post" action="backup.php">
            <?= csrf_campo() ?>
            <input type="hidden" name="acao" value="gerar_backup">
            <button type="submit" class="btn btn-primario">⬇️ Gerar e baixar backup agora</button>
        </form>
    </div>
</main>

<?php require __DIR__ . '/../includes/footer.php'; ?>
