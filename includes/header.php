<?php
/**
 * ============================================================================
 * header.php — Cabeçalho compartilhado de TODAS as páginas do site
 * ============================================================================
 * Cada página que usa este include deve, ANTES de dar include, definir:
 *   $tituloPagina  (string)  → texto exibido na aba do navegador
 *   $baseAssets    (string)  → '' para páginas na raiz (index.php, pedido.php…)
 *                              '../' para páginas dentro de /admin/
 *   $paginaAtual   (string, opcional) → identifica o item de menu ativo:
 *                              'cliente' | 'admin_dashboard' | 'admin_pedidos' | 'admin_seminovos' |
 *                              'admin_estoque' | 'admin_servicos' | 'admin_novo_pedido'
 *   $ocultarVoltar (bool, opcional) → true nas páginas "raiz" de cada área
 *                              (index.php do cliente, admin/index.php), onde
 *                              não faz sentido mostrar o botão "← Voltar".
 *
 * Este arquivo já cuida de:
 *   - Carregar as fontes do Google Fonts (Barlow Condensed + Inter).
 *   - Definir o ícone da aba do navegador (favicon), lido de
 *     assets/img/oficina.png — a pasta já existe, basta enviar a imagem
 *     com esse nome exato para o favicon aparecer.
 *   - Mostrar a navegação certa dependendo se um administrador está
 *     logado ou não (função admin_logado(), vinda de includes/auth.php).
 *   - Menu mobile em formato de gaveta (evita amontoar botões na tela
 *     pequena) e uma barra "← Voltar" logo abaixo do cabeçalho, presente
 *     em quase todas as páginas para facilitar a navegação.
 */
require_once __DIR__ . '/auth.php'; // já traz config.php, database.php e functions.php

$baseAssets = $baseAssets ?? '';
$tituloPagina = $tituloPagina ?? 'Shalom Radiadores';
$paginaAtual = $paginaAtual ?? '';
$ocultarVoltar = $ocultarVoltar ?? false;
$linkVoltarPadrao = $baseAssets . (admin_logado() ? 'admin/index.php' : 'index.php');

// Cache-busting automático da logo/favicon: gera uma versão a partir da
// data de modificação do arquivo, para que, toda vez que você TROCAR a
// imagem em assets/img/oficina.png, o navegador busque a versão nova na
// hora — sem precisar orientar ninguém a limpar cache manualmente.
$caminhoLogoFisico = __DIR__ . '/../assets/img/oficina.png';
$versaoLogo = is_file($caminhoLogoFisico) ? filemtime($caminhoLogoFisico) : 1;
$urlLogo = $baseAssets . 'assets/img/oficina.png?v=' . $versaoLogo;
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= h($tituloPagina) ?></title>

<!-- Ícone da aba do navegador: envie o arquivo para assets/img/oficina.png -->
<link rel="icon" type="image/png" href="<?= h($urlLogo) ?>">
<link rel="apple-touch-icon" href="<?= h($urlLogo) ?>">

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@600;700;800&family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

<link rel="stylesheet" href="<?= $baseAssets ?>assets/css/style.css">
</head>
<body>

<header class="site-header no-print">
    <div class="site-header__inner">
        <a href="<?= $baseAssets ?>index.php" class="marca">
            <span class="selo"><img src="<?= h($urlLogo) ?>" alt="" onerror="this.style.display='none'; this.parentElement.textContent='SR';"></span>
            <span class="marca__texto">
                <strong>Shalom Radiadores</strong>
                <small>Manutenção e recondicionamento de radiadores</small>
            </span>
        </a>

        <button type="button" id="btn-menu-mobile" class="btn-menu-mobile" aria-label="Abrir menu" aria-expanded="false">☰ Menu</button>

        <nav id="nav-principal">
            <?php if (admin_logado()): ?>
                <a href="<?= $baseAssets ?>admin/index.php" class="btn-nav <?= $paginaAtual === 'admin_dashboard' ? 'ativo' : '' ?>">🏠 Home</a>
                <a href="<?= $baseAssets ?>admin/pedidos.php" class="btn-nav <?= $paginaAtual === 'admin_pedidos' ? 'ativo' : '' ?>">📋 Pedidos</a>
                <a href="<?= $baseAssets ?>admin/novo-pedido.php" class="btn-nav <?= $paginaAtual === 'admin_novo_pedido' ? 'ativo' : '' ?>">➕ Novo pedido</a>
                <a href="<?= $baseAssets ?>admin/seminovos.php" class="btn-nav <?= $paginaAtual === 'admin_seminovos' ? 'ativo' : '' ?>">🔧 Seminovos</a>
                <a href="<?= $baseAssets ?>admin/estoque.php" class="btn-nav <?= $paginaAtual === 'admin_estoque' ? 'ativo' : '' ?>">📦 Estoque</a>
                <a href="<?= $baseAssets ?>admin/servicos.php" class="btn-nav <?= $paginaAtual === 'admin_servicos' ? 'ativo' : '' ?>">📅 Serviços</a>
                <a href="<?= $baseAssets ?>logout.php" class="btn-nav btn-nav--sair">🚪 Sair</a>
            <?php else: ?>
                <a href="<?= $baseAssets ?>index.php" class="btn-nav <?= $paginaAtual === 'cliente' ? 'ativo' : '' ?>">🏠 Home</a>
                <a href="<?= $baseAssets ?>login.php" class="btn-nav">🔒 Administração</a>
            <?php endif; ?>
        </nav>
    </div>
    <div class="faixa-diagonal"></div>
</header>

<script>
    (function () {
        const botao = document.getElementById('btn-menu-mobile');
        const nav = document.getElementById('nav-principal');
        if (!botao || !nav) return;
        botao.addEventListener('click', function () {
            const aberto = nav.classList.toggle('nav-aberta');
            botao.setAttribute('aria-expanded', aberto ? 'true' : 'false');
        });
    })();
</script>

<?php
// Aviso flutuante de estoque baixo (só pra administradores logados, em
// qualquer página do painel — não só na tela de Estoque). Protegido com
// try/catch porque isso roda em TODA página do admin: se alguma coisa
// falhar aqui (ex.: uma migração de banco ainda não rodada), o aviso
// simplesmente não aparece, em vez de derrubar a página inteira.
$itensEstoqueBaixo = [];
if (admin_logado()) {
    try {
        require_once __DIR__ . '/estoque_repo.php';
        $itensEstoqueBaixo = listar_itens_estoque_baixo();
    } catch (Throwable $e) {
        $itensEstoqueBaixo = [];
    }
}
?>
<?php if ($itensEstoqueBaixo): ?>
<div id="alerta-estoque-baixo" class="alerta-estoque-flutuante no-print"></div>
<script>
(function () {
    var itens = <?= json_encode(array_map(fn($i) => [
        'id' => (int)$i['id'],
        'nome' => $i['nome'] . (!empty($i['marca']) ? ' (' . $i['marca'] . ')' : ''),
        'quantidade' => (int)$i['quantidade'],
    ], $itensEstoqueBaixo)) ?>;
    var linkEstoque = <?= json_encode($baseAssets . 'admin/estoque.php') ?>;
    var chaveHoje = 'shalom_estoque_baixo_ocultos_' + new Date().toISOString().slice(0, 10);
    var container = document.getElementById('alerta-estoque-baixo');
    var ocultos = [];
    try { ocultos = JSON.parse(localStorage.getItem(chaveHoje) || '[]'); } catch (e) { ocultos = []; }

    function esconderItem(id) {
        if (ocultos.indexOf(id) === -1) ocultos.push(id);
        try { localStorage.setItem(chaveHoje, JSON.stringify(ocultos)); } catch (e) {}
        renderizar();
    }

    function renderizar() {
        var visiveis = itens.filter(function (i) { return ocultos.indexOf(i.id) === -1; });
        container.textContent = '';
        if (visiveis.length === 0) { container.style.display = 'none'; return; }
        container.style.display = 'flex';

        var titulo = document.createElement('div');
        titulo.className = 'alerta-estoque-flutuante__titulo';
        titulo.textContent = '⚠️ Estoque acabando';
        container.appendChild(titulo);

        visiveis.forEach(function (item) {
            var linha = document.createElement('div');
            linha.className = 'alerta-estoque-flutuante__item';

            var span = document.createElement('span');
            span.textContent = item.nome + ' — ' + item.quantidade + (item.quantidade === 1 ? ' unidade' : ' unidades');
            linha.appendChild(span);

            var botao = document.createElement('button');
            botao.type = 'button';
            botao.className = 'alerta-estoque-flutuante__fechar';
            botao.setAttribute('aria-label', 'Dispensar aviso');
            botao.textContent = '✖';
            botao.addEventListener('click', function () { esconderItem(item.id); });
            linha.appendChild(botao);

            container.appendChild(linha);
        });

        var rodape = document.createElement('a');
        rodape.className = 'alerta-estoque-flutuante__link';
        rodape.href = linkEstoque;
        rodape.textContent = 'Ver estoque →';
        container.appendChild(rodape);
    }

    renderizar();
})();
</script>
<?php endif; ?>

<?php if (!$ocultarVoltar): ?>
<div class="barra-voltar no-print">
    <div class="barra-voltar__inner">
        <a href="<?= h($linkVoltarPadrao) ?>" class="btn-voltar"
           onclick="if (document.referrer && document.referrer.indexOf(window.location.host) !== -1) { window.history.back(); return false; }">
            ← Voltar
        </a>
    </div>
</div>
<?php endif; ?>
