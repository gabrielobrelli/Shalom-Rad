<?php
/**
 * admin/index.php — Home do painel administrativo: um resumo do dia
 * (cartões de indicadores) seguido de uma central de atalhos (hub) para
 * todas as áreas do sistema. A lista completa de pedidos fica em
 * admin/pedidos.php.
 */
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/pedido_repo.php';
require_once __DIR__ . '/../includes/estoque_repo.php';
require_once __DIR__ . '/../includes/servicos_repo.php';
require_once __DIR__ . '/../includes/lembretes_repo.php';

exigir_login_admin();

$pedidos = listar_pedidos_agrupados();
$pedidosEmAberto = count(array_filter($pedidos, fn($p) => $p['status'] !== 'concluido'));
$totalItensEstoque = count(listar_itens_estoque());
$servicosHoje = listar_servicos_do_dia(date('Y-m-d'));

// Se a migração dos lembretes (migracao-v3.sql/v4.sql) ainda não foi
// rodada no banco, a tabela "lembretes" não existe — sem essa proteção,
// o hub inteiro quebrava. Assim, some só o card fica com contagem 0.
try {
    $totalLembretes = count(listar_lembretes());
} catch (Throwable $e) {
    $totalLembretes = 0;
}

// Faturamento líquido de hoje (protegido: se algo faltar, mostra R$ 0,00 em vez de quebrar o hub).
try {
    $faturamentoHoje = totais_do_periodo(date('Y-m-d'), date('Y-m-d'))['liquido'];
} catch (Throwable $e) {
    $faturamentoHoje = 0.0;
}

$diasSemana = ['Domingo', 'Segunda-feira', 'Terça-feira', 'Quarta-feira', 'Quinta-feira', 'Sexta-feira', 'Sábado'];
$dataHojeExtenso = $diasSemana[(int)date('w')] . ', ' . date('d/m/Y');

$tituloPagina = 'Painel administrativo — Shalom Radiadores';
$baseAssets = '../';
$paginaAtual = 'admin_dashboard';
$ocultarVoltar = true;
require __DIR__ . '/../includes/header.php';
?>

<main class="container largo">

    <div class="hero-banner hero-admin">
        <div>
            <span class="eyebrow">Painel administrativo</span>
            <h1>Olá! 👋</h1>
            <p><?= h($dataHojeExtenso) ?></p>
        </div>
        <div class="hero-admin__acoes">
            <a href="fechamento.php" class="btn btn-primario">🧾 Fechamento</a>
            <a href="novo-pedido.php" class="btn btn-secundario" style="background:rgba(255,255,255,0.14); color:#fff; border-color:rgba(255,255,255,0.3);">➕ Novo pedido</a>
        </div>
    </div>

    <div class="stat-cards">
        <div class="stat-card">
            <span class="stat-card__icone">📋</span>
            <div class="stat-card__valor"><?= $pedidosEmAberto ?></div>
            <div class="stat-card__rotulo">Em aberto</div>
        </div>
        <div class="stat-card">
            <span class="stat-card__icone">📅</span>
            <div class="stat-card__valor"><?= count($servicosHoje) ?></div>
            <div class="stat-card__rotulo">Serviços hoje</div>
        </div>
        <div class="stat-card">
            <span class="stat-card__icone">💰</span>
            <div class="stat-card__valor" style="font-size:1.5rem;"><?= h(formatar_dinheiro($faturamentoHoje)) ?></div>
            <div class="stat-card__rotulo">Faturamento hoje</div>
        </div>
        <div class="stat-card">
            <span class="stat-card__icone">📦</span>
            <div class="stat-card__valor"><?= $totalItensEstoque ?></div>
            <div class="stat-card__rotulo">Em estoque</div>
        </div>
        <div class="stat-card">
            <span class="stat-card__icone">🗒️</span>
            <div class="stat-card__valor"><?= $totalLembretes ?></div>
            <div class="stat-card__rotulo">Lembretes</div>
        </div>
    </div>

    <div class="cartao">
        <span class="eyebrow">Acesso rápido</span>

        <div class="hub-modulos" style="margin-top:12px;">
            <a href="pedidos.php" class="hub-modulo">
                <span class="hub-modulo__icone">📋</span>
                <span class="hub-modulo__titulo">Pedidos</span>
                <span class="hub-modulo__desc"><?= $pedidosEmAberto ?> em aberto</span>
            </a>
            <a href="novo-pedido.php" class="hub-modulo">
                <span class="hub-modulo__icone">➕</span>
                <span class="hub-modulo__titulo">Novo pedido</span>
                <span class="hub-modulo__desc">Telefone ou presencial</span>
            </a>
            <a href="seminovos.php" class="hub-modulo">
                <span class="hub-modulo__icone">🔧</span>
                <span class="hub-modulo__titulo">Seminovos Marcio</span>
                <span class="hub-modulo__desc">Concertos internos</span>
            </a>
            <a href="estoque.php" class="hub-modulo">
                <span class="hub-modulo__icone">📦</span>
                <span class="hub-modulo__titulo">Estoque</span>
                <span class="hub-modulo__desc"><?= $totalItensEstoque ?> item(ns)</span>
            </a>
            <a href="servicos.php" class="hub-modulo">
                <span class="hub-modulo__icone">📅</span>
                <span class="hub-modulo__titulo">Serviços</span>
                <span class="hub-modulo__desc"><?= count($servicosHoje) ?> hoje</span>
            </a>
            <a href="grafico.php" class="hub-modulo">
                <span class="hub-modulo__icone">📊</span>
                <span class="hub-modulo__titulo">Gráfico</span>
                <span class="hub-modulo__desc">Faturamento por período</span>
            </a>
            <a href="relatorios.php" class="hub-modulo">
                <span class="hub-modulo__icone">💰</span>
                <span class="hub-modulo__titulo">Relatórios</span>
                <span class="hub-modulo__desc">Total por período</span>
            </a>
            <a href="profissionais.php" class="hub-modulo">
                <span class="hub-modulo__icone">👷</span>
                <span class="hub-modulo__titulo">Profissionais</span>
                <span class="hub-modulo__desc">Ranking de produção</span>
            </a>
            <a href="backup.php" class="hub-modulo">
                <span class="hub-modulo__icone">💾</span>
                <span class="hub-modulo__titulo">Backup</span>
                <span class="hub-modulo__desc">Site + banco de dados</span>
            </a>
            <a href="fechamento.php" class="hub-modulo">
                <span class="hub-modulo__icone">🧾</span>
                <span class="hub-modulo__titulo">Fechamento</span>
                <span class="hub-modulo__desc">PDF por período</span>
            </a>
            <a href="lembretes.php" class="hub-modulo">
                <span class="hub-modulo__icone">🗒️</span>
                <span class="hub-modulo__titulo">Lembretes</span>
                <span class="hub-modulo__desc"><?= $totalLembretes ?> no quadro</span>
            </a>
        </div>
    </div>
</main>

<?php require __DIR__ . '/../includes/footer.php'; ?>
