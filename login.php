<?php
/**
 * login.php — Autenticação da administração (usuário + senha).
 */
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

if (admin_logado()) {
    header('Location: ' . SITE_URL . '/admin/index.php');
    exit;
}

$erro = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    exigir_csrf();

    $username = trim((string)($_POST['username'] ?? ''));
    $senha    = (string)($_POST['senha'] ?? '');

    if ($username === '' || $senha === '') {
        $erro = 'Preencha usuário e senha.';
    } elseif (tentar_login($username, $senha)) {
        header('Location: ' . SITE_URL . '/admin/index.php');
        exit;
    } else {
        $erro = 'Usuário ou senha inválidos.';
    }
}

$tituloPagina = 'Login administrativo — Shalom Radiadores';
$baseAssets = '';
require __DIR__ . '/includes/header.php';
?>

<main class="container" style="max-width:420px;">
    <div class="cartao">
        <h1>🔒 Área da administração</h1>
        <p class="subtitulo">Acesso restrito à equipe da oficina.</p>

        <?php if ($erro): ?>
            <div class="alerta alerta-erro">⚠️ <?= h($erro) ?></div>
        <?php endif; ?>

        <form method="post" action="login.php" id="form-login">
            <?= csrf_campo() ?>
            <div class="campo">
                <label for="username">Usuário</label>
                <input type="text" id="username" name="username" required autofocus autocomplete="username">
            </div>
            <div class="campo">
                <label for="senha">Senha</label>
                <input type="password" id="senha" name="senha" required autocomplete="current-password">
            </div>
            <button type="submit" class="btn btn-primario btn-bloco">Entrar</button>
        </form>
    </div>
</main>

<script>
    // Garante que a tecla Enter, em qualquer um dos dois campos, envie o
    // formulário de login — sem depender só do comportamento padrão do
    // navegador, que às vezes falha nesse caso.
    (function () {
        var form = document.getElementById('form-login');
        if (!form) return;
        form.querySelectorAll('input').forEach(function (campo) {
            campo.addEventListener('keydown', function (e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    if (form.requestSubmit) form.requestSubmit();
                    else form.submit();
                }
            });
        });
    })();
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
