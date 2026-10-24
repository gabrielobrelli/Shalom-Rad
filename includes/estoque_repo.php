<?php
/**
 * ============================================================================
 * estoque_repo.php — Camada de acesso a dados do módulo de ESTOQUE.
 * ============================================================================
 * Índice rápido:
 *   - Categorias:  listar_categorias_estoque() / criar_categoria_estoque() /
 *                  excluir_categoria_estoque()
 *   - Itens:       listar_itens_estoque() [feed com filtros] /
 *                  buscar_item_estoque() / criar_item_estoque() /
 *                  ajustar_quantidade_estoque() [+ / -] /
 *                  excluir_item_estoque() / listar_marcas_estoque()
 *   - Upload:      salvar_imagem_estoque() — trata o upload opcional da
 *                  foto do item, com validação de tipo/tamanho.
 * ============================================================================
 */
require_once __DIR__ . '/database.php';

// ---------------------------------------------------------------------
// Categorias
// ---------------------------------------------------------------------

function listar_categorias_estoque(): array
{
    return db()->query('SELECT * FROM estoque_categorias ORDER BY nome ASC')->fetchAll();
}

/** Cria uma categoria nova. Se o nome já existir, simplesmente ignora (evita duplicar). */
function criar_categoria_estoque(string $nome): void
{
    $nome = trim($nome);
    if ($nome === '') return;
    $stmt = db()->prepare('INSERT IGNORE INTO estoque_categorias (nome) VALUES (?)');
    $stmt->execute([$nome]);
}

/** Exclui uma categoria. Os itens que a usavam ficam "Sem categoria" (ON DELETE SET NULL). */
function excluir_categoria_estoque(int $id): void
{
    $stmt = db()->prepare('DELETE FROM estoque_categorias WHERE id = ?');
    $stmt->execute([$id]);
}

// ---------------------------------------------------------------------
// Itens
// ---------------------------------------------------------------------

/** Lista as marcas já cadastradas (sem repetir), para popular o filtro. */
function listar_marcas_estoque(): array
{
    $stmt = db()->query("SELECT DISTINCT marca FROM estoque_itens WHERE marca IS NOT NULL AND marca <> '' ORDER BY marca ASC");
    return array_column($stmt->fetchAll(), 'marca');
}

/**
 * Lista os itens do estoque em formato "feed" (mais recentemente
 * atualizados primeiro), com filtros opcionais de busca por texto
 * (nome ou marca), categoria e marca exata — usados na área de pesquisa.
 */
/** Quantidade igual ou abaixo disso é considerada "estoque baixo" (borda vermelha + aviso). */
const LIMITE_ESTOQUE_BAIXO = 2;

/** Valor total do estoque hoje (soma de quantidade × valor unitário de cada item). */
function valor_total_estoque(): float
{
    return (float)db()->query('SELECT COALESCE(SUM(quantidade * valor_unitario), 0) FROM estoque_itens')->fetchColumn();
}

/** Itens com quantidade baixa (≤ LIMITE_ESTOQUE_BAIXO), do mais crítico pro menos crítico. */
function listar_itens_estoque_baixo(): array
{
    $stmt = db()->prepare('SELECT id, nome, marca, quantidade FROM estoque_itens WHERE quantidade <= ? ORDER BY quantidade ASC, nome ASC');
    $stmt->execute([LIMITE_ESTOQUE_BAIXO]);
    return $stmt->fetchAll();
}

/** Opções válidas de ordenação da lista de estoque, com o texto exibido no seletor. */
const ORDENACOES_ESTOQUE = [
    'nome_asc' => 'Nome (A → Z)',
    'nome_desc' => 'Nome (Z → A)',
    'quantidade_asc' => 'Quantidade (menor → maior)',
    'quantidade_desc' => 'Quantidade (maior → menor)',
    'valor_asc' => 'Valor (menor → maior)',
    'valor_desc' => 'Valor (maior → menor)',
    'data_desc' => 'Atualizado mais recente',
    'data_asc' => 'Atualizado mais antigo',
];

/**
 * Lista itens do estoque com busca livre (nome, marca ou número de
 * série), filtros de categoria, marca e faixa de valor unitário, e
 * ordenação escolhida (padrão: nome em ordem alfabética).
 */
function listar_itens_estoque(
    string $busca = '',
    int $categoriaId = 0,
    string $marca = '',
    ?float $valorMin = null,
    ?float $valorMax = null,
    string $ordenar = 'nome_asc'
): array {
    $sql = 'SELECT i.*, c.nome AS categoria_nome
            FROM estoque_itens i
            LEFT JOIN estoque_categorias c ON c.id = i.categoria_id
            WHERE 1=1';
    $params = [];

    if ($busca !== '') {
        $sql .= ' AND (i.nome LIKE ? OR i.marca LIKE ? OR i.numero_serie LIKE ?)';
        $params[] = '%' . $busca . '%';
        $params[] = '%' . $busca . '%';
        $params[] = '%' . $busca . '%';
    }
    if ($categoriaId > 0) {
        $sql .= ' AND i.categoria_id = ?';
        $params[] = $categoriaId;
    }
    if ($marca !== '') {
        $sql .= ' AND i.marca = ?';
        $params[] = $marca;
    }
    if ($valorMin !== null) {
        $sql .= ' AND i.valor_unitario >= ?';
        $params[] = $valorMin;
    }
    if ($valorMax !== null) {
        $sql .= ' AND i.valor_unitario <= ?';
        $params[] = $valorMax;
    }

    $colunasOrdenacao = [
        'nome_asc' => 'i.nome ASC',
        'nome_desc' => 'i.nome DESC',
        'quantidade_asc' => 'i.quantidade ASC, i.nome ASC',
        'quantidade_desc' => 'i.quantidade DESC, i.nome ASC',
        'valor_asc' => 'i.valor_unitario ASC, i.nome ASC',
        'valor_desc' => 'i.valor_unitario DESC, i.nome ASC',
        'data_desc' => 'i.atualizado_em DESC',
        'data_asc' => 'i.atualizado_em ASC',
    ];
    $sql .= ' ORDER BY ' . ($colunasOrdenacao[$ordenar] ?? $colunasOrdenacao['nome_asc']);

    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function buscar_item_estoque(int $id): ?array
{
    $stmt = db()->prepare(
        'SELECT i.*, c.nome AS categoria_nome FROM estoque_itens i
         LEFT JOIN estoque_categorias c ON c.id = i.categoria_id
         WHERE i.id = ? LIMIT 1'
    );
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

/** Cadastra um item novo no estoque e registra o movimento inicial ("cadastro"). */
/** Diz se um número de série já está em uso por outro item (pra avisar antes de duplicar). */
function numero_serie_em_uso(string $numeroSerie, int $ignorarId = 0): bool
{
    $stmt = db()->prepare('SELECT id FROM estoque_itens WHERE numero_serie = ? AND id != ? LIMIT 1');
    $stmt->execute([$numeroSerie, $ignorarId]);
    return (bool)$stmt->fetch();
}

function criar_item_estoque(string $nome, ?string $marca, ?int $categoriaId, int $quantidade, float $valorUnitario, ?string $numeroSerie, ?string $imagemArquivo): int
{
    $pdo = db();
    $stmt = $pdo->prepare(
        'INSERT INTO estoque_itens (nome, marca, categoria_id, quantidade, valor_unitario, numero_serie, imagem_arquivo)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        trim($nome), trim((string)$marca) ?: null, $categoriaId ?: null, $quantidade,
        max(0, $valorUnitario), trim((string)$numeroSerie) ?: null, $imagemArquivo,
    ]);
    $novoId = (int)$pdo->lastInsertId();

    if ($quantidade > 0) {
        registrar_movimentacao_estoque($novoId, 'cadastro', $quantidade);
    }
    return $novoId;
}

/**
 * Ajusta a quantidade de um item (botão "+" ou "-" do feed).
 * `$delta` é sempre positivo; `$tipo` diz se é entrada ou saída.
 * A quantidade nunca fica negativa (uma saída maior que o estoque
 * disponível simplesmente zera o item, em vez de gerar número negativo).
 */
function ajustar_quantidade_estoque(int $itemId, int $delta, string $tipo): void
{
    $item = buscar_item_estoque($itemId);
    if (!$item) return;

    $novaQuantidade = $tipo === 'entrada'
        ? (int)$item['quantidade'] + $delta
        : max(0, (int)$item['quantidade'] - $delta);

    $stmt = db()->prepare('UPDATE estoque_itens SET quantidade = ? WHERE id = ?');
    $stmt->execute([$novaQuantidade, $itemId]);

    registrar_movimentacao_estoque($itemId, $tipo, $delta);
}

function excluir_item_estoque(int $id): void
{
    $item = buscar_item_estoque($id);
    $stmt = db()->prepare('DELETE FROM estoque_itens WHERE id = ?');
    $stmt->execute([$id]);

    // Remove também o arquivo de imagem do disco, se houver.
    if ($item && !empty($item['imagem_arquivo'])) {
        $caminho = __DIR__ . '/../uploads/estoque/' . $item['imagem_arquivo'];
        if (is_file($caminho)) {
            @unlink($caminho);
        }
    }
}

/**
 * Atualiza os dados cadastrais de um item já existente (nome, marca,
 * categoria e, opcionalmente, uma nova imagem). Usada pela tela
 * "Estoque — visão geral" (admin/estoque-visualizar.php), que só permite
 * editar itens já cadastrados, nunca criar novos. A quantidade NÃO é
 * alterada por esta função — isso continua sendo feito só pelos botões
 * ➕/➖, para manter o registro de movimentações consistente.
 */
function atualizar_item_estoque(int $id, string $nome, ?string $marca, ?int $categoriaId, float $valorUnitario, ?string $numeroSerie, ?string $novaImagem): void
{
    $item = buscar_item_estoque($id);
    if (!$item) return;

    if ($novaImagem && !empty($item['imagem_arquivo'])) {
        $antiga = __DIR__ . '/../uploads/estoque/' . $item['imagem_arquivo'];
        if (is_file($antiga)) @unlink($antiga);
    }
    $imagemFinal = $novaImagem ?: $item['imagem_arquivo'];

    $stmt = db()->prepare('UPDATE estoque_itens SET nome = ?, marca = ?, categoria_id = ?, valor_unitario = ?, numero_serie = ?, imagem_arquivo = ? WHERE id = ?');
    $stmt->execute([trim($nome), trim((string)$marca) ?: null, $categoriaId ?: null, max(0, $valorUnitario), trim((string)$numeroSerie) ?: null, $imagemFinal, $id]);
}

function registrar_movimentacao_estoque(int $itemId, string $tipo, int $quantidade): void
{
    $stmt = db()->prepare('INSERT INTO estoque_movimentacoes (item_id, tipo, quantidade) VALUES (?, ?, ?)');
    $stmt->execute([$itemId, $tipo, $quantidade]);
}

/**
 * Lista as movimentações de estoque (cadastro/entrada/saída) dentro de
 * um período, com o nome do item — usado no relatório de fechamento
 * (admin/fechamento.php).
 */
function listar_movimentacoes_periodo(string $dataInicio, string $dataFim): array
{
    $stmt = db()->prepare(
        'SELECT m.tipo, m.quantidade, m.criado_em, i.nome AS item_nome, i.marca
         FROM estoque_movimentacoes m
         INNER JOIN estoque_itens i ON i.id = m.item_id
         WHERE DATE(m.criado_em) BETWEEN ? AND ?
         ORDER BY m.criado_em ASC'
    );
    $stmt->execute([$dataInicio, $dataFim]);
    return $stmt->fetchAll();
}

// ---------------------------------------------------------------------
// Upload de imagem (opcional)
// ---------------------------------------------------------------------

/**
 * Trata o upload opcional da foto do item. Recebe o array $_FILES['imagem']
 * (ou null/vazio se o campo não foi preenchido). Valida extensão e
 * tamanho, salva com um nome único em /uploads/estoque/ e devolve o nome
 * do arquivo salvo (para gravar em estoque_itens.imagem_arquivo).
 * Retorna null se o campo foi deixado em branco (imagem é opcional).
 * Lança RuntimeException com uma mensagem clara se algo der errado no
 * envio — antes essas falhas eram ignoradas em silêncio e o item ficava
 * sem imagem sem avisar ninguém.
 */
function salvar_imagem_estoque(?array $arquivo): ?string
{
    if (empty($arquivo) || ($arquivo['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null; // campo deixado em branco — tudo bem, imagem é opcional
    }
    if ($arquivo['error'] !== UPLOAD_ERR_OK) {
        $codigosConhecidos = [
            UPLOAD_ERR_INI_SIZE => 'o arquivo é maior do que o limite do servidor',
            UPLOAD_ERR_FORM_SIZE => 'o arquivo é maior do que o limite permitido no formulário',
            UPLOAD_ERR_PARTIAL => 'o envio foi interrompido no meio do caminho',
        ];
        $motivo = $codigosConhecidos[$arquivo['error']] ?? ('código de erro ' . $arquivo['error']);
        throw new RuntimeException('Não foi possível enviar a imagem: ' . $motivo . '. Tente novamente com um arquivo menor.');
    }

    $extensoesPermitidas = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    $extensao = strtolower(pathinfo($arquivo['name'], PATHINFO_EXTENSION));
    if (!in_array($extensao, $extensoesPermitidas, true)) {
        throw new RuntimeException('Formato de imagem não aceito. Use JPG, PNG, GIF ou WEBP.');
    }

    $tamanhoMaximo = 4 * 1024 * 1024; // 4 MB
    if ($arquivo['size'] > $tamanhoMaximo) {
        throw new RuntimeException('A imagem tem mais de 4 MB. Escolha um arquivo menor.');
    }

    // Auto-cria a pasta de destino se ela não existir no servidor — isso
    // evita que o upload falhe silenciosamente só porque a pasta
    // "uploads/estoque" não veio junto no envio dos arquivos do site.
    $pastaDestino = __DIR__ . '/../uploads/estoque';
    if (!is_dir($pastaDestino)) {
        @mkdir($pastaDestino, 0755, true);
    }
    if (!is_dir($pastaDestino) || !is_writable($pastaDestino)) {
        throw new RuntimeException('A pasta "uploads/estoque" não existe (ou não tem permissão de escrita) no servidor. Crie essa pasta pelo gerenciador de arquivos da hospedagem.');
    }

    $nomeArquivo = bin2hex(random_bytes(12)) . '.' . $extensao;
    $destino = $pastaDestino . '/' . $nomeArquivo;

    if (!move_uploaded_file($arquivo['tmp_name'], $destino)) {
        throw new RuntimeException('Não foi possível salvar a imagem no servidor.');
    }

    return $nomeArquivo;
}
