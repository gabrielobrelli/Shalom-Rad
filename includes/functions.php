<?php
/**
 * Funções utilitárias gerais do sistema.
 */
require_once __DIR__ . '/config.php';

/** Escapa texto para saída segura em HTML. */
function h(?string $texto): string
{
    return htmlspecialchars((string)$texto, ENT_QUOTES, 'UTF-8');
}

/** Gera um token seguro e único para o link do pedido. */
function gerar_token_pedido(): string
{
    return bin2hex(random_bytes(24)); // 48 caracteres hexadecimais
}

/** Gera / valida token CSRF armazenado na sessão. */
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_campo(): string
{
    return '<input type="hidden" name="csrf_token" value="' . h(csrf_token()) . '">';
}

function csrf_valido(?string $tokenRecebido): bool
{
    return !empty($tokenRecebido) && !empty($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $tokenRecebido);
}

function exigir_csrf(): void
{
    $token = $_POST['csrf_token'] ?? '';
    if (!csrf_valido($token)) {
        http_response_code(403);
        die('Sessão expirada ou requisição inválida. Volte e tente novamente.');
    }
}

/** Formata data para exibição em pt-BR. */
function formatar_data(?string $dataMysql): string
{
    if (empty($dataMysql)) {
        return '-';
    }
    $ts = strtotime($dataMysql);
    return $ts ? date('d/m/Y H:i', $ts) : '-';
}

/** Converte texto de situação em rótulo amigável. */
function rotulo_situacao(string $situacao): string
{
    $map = [
        'normal'     => 'Normal',
        'importante' => 'Importante',
        'urgente'    => 'Urgente',
    ];
    return $map[$situacao] ?? 'Normal';
}

/** Converte texto de status do pedido em rótulo amigável. */
function rotulo_status(string $status): string
{
    $map = [
        'novo'          => 'Novo',
        'em_andamento'  => 'Em andamento',
        'parcial'       => 'Parcial',
        'concluido'     => 'Concluído',
    ];
    return $map[$status] ?? 'Novo';
}

/** Monta o link completo e clicável do pedido a partir do token. */
function link_pedido(string $token): string
{
    return rtrim(SITE_URL, '/') . '/pedido.php?token=' . urlencode($token);
}

/** Monta o link do wa.me com mensagem pré-preenchida. */
function link_whatsapp(string $mensagem): string
{
    return 'https://wa.me/' . WHATSAPP_NUMERO . '?text=' . rawurlencode($mensagem);
}

/** Sanitiza e valida um inteiro não-negativo vindo de formulário. */
function inteiro_positivo($valor): int
{
    $n = filter_var($valor, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
    return $n === false ? 0 : $n;
}

/** Valida se a situação enviada é uma das permitidas. */
function situacao_valida(?string $valor): string
{
    $permitidas = ['normal', 'importante', 'urgente'];
    return in_array($valor, $permitidas, true) ? $valor : 'normal';
}

/** Recalcula e retorna o status sugerido do pedido a partir dos itens. */
function calcular_status_pedido(array $itens): string
{
    if (empty($itens)) {
        return 'novo';
    }
    $totalSolicitado = 0;
    $totalFeito = 0;
    foreach ($itens as $item) {
        $totalSolicitado += (int)$item['quantidade'];
        $totalFeito      += (int)$item['quantidade_feita'];
    }
    if ($totalFeito <= 0) {
        return 'novo';
    }
    if ($totalFeito >= $totalSolicitado && $totalSolicitado > 0) {
        return 'concluido';
    }
    return 'parcial';
}

/** Soma total solicitado de uma lista de itens. */
function total_solicitado(array $itens): int
{
    return array_sum(array_map(fn($i) => (int)$i['quantidade'], $itens));
}

/** Soma total feito de uma lista de itens. */
function total_feito(array $itens): int
{
    return array_sum(array_map(fn($i) => (int)$i['quantidade_feita'], $itens));
}

/**
 * Normaliza um nome para comparação (usado para detectar itens
 * "duplicados" ao adicionar um novo item com o mesmo Modelo/Nome).
 * Remove espaços nas pontas e ignora maiúsculas/minúsculas.
 */
function normalizar_nome(string $texto): string
{
    return mb_strtolower(trim($texto), 'UTF-8');
}

/**
 * Junta um texto "acumulado" (ex.: Nossas Peças Usadas, Carcaça
 * utilizada) com um texto novo, evitando repetir a mesma informação
 * duas vezes. Usado quando um item é somado a outro já existente na
 * lista (mesmo Modelo/Nome) e cada um trazia uma anotação diferente.
 * Ex.: mesclar_texto('Caixa inferior', 'Caixa superior')
 *      => 'Caixa inferior; Caixa superior'
 */
function mesclar_texto(?string $existente, ?string $novo): ?string
{
    $existente = trim((string)$existente);
    $novo = trim((string)$novo);

    if ($novo === '') {
        return $existente ?: null;
    }
    if ($existente === '') {
        return $novo;
    }
    // Evita duplicar a mesma anotação se ela já estiver presente no texto acumulado.
    if (mb_stripos($existente, $novo, 0, 'UTF-8') !== false) {
        return $existente;
    }
    return $existente . '; ' . $novo;
}

/** Emoji/ícone associado a cada nível de situação (usado nas listagens). */
function icone_situacao(string $situacao): string
{
    $map = [
        'normal'     => '🔵',
        'importante' => '⚠️',
        'urgente'    => '🔴',
    ];
    return $map[$situacao] ?? '🔵';
}

/** Emoji/ícone associado a cada status do pedido. */
function icone_status(string $status): string
{
    $map = [
        'novo'         => '🆕',
        'em_andamento' => '⚙️',
        'parcial'      => '🔶',
        'concluido'    => '✅',
    ];
    return $map[$status] ?? '🆕';
}

/** Formata um valor numérico como moeda brasileira (ex.: 1234.5 -> "R$ 1.234,50"). */
function formatar_dinheiro(float $valor): string
{
    return 'R$ ' . number_format($valor, 2, ',', '.');
}
