<?php
/**
 * ============================================================================
 * pedido_repo.php — Camada de acesso a dados dos PEDIDOS DE CLIENTES
 * ============================================================================
 * Este arquivo concentra todo o SQL relacionado às tabelas `orders` e
 * `order_items`. Tanto as páginas do cliente (index.php, pedido.php) quanto
 * as páginas da administração (admin/pedido.php, admin/index.php) usam
 * exclusivamente as funções daqui — nenhuma delas escreve SQL diretamente.
 *
 * Índice rápido das funções deste arquivo:
 *   - criar_pedido()              → cria um pedido novo (cliente)
 *   - inserir_itens_pedido()      → insere uma lista de itens, já mesclando
 *                                    duplicados entre si (mesmo Modelo/Nome)
 *   - buscar_pedido_por_token()   → usado pelo cliente (link público)
 *   - buscar_pedido_por_id()      → usado pela administração (painel)
 *   - listar_itens_pedido()       → lista os itens de um pedido
 *   - substituir_itens_pedido()   → regrava todos os itens (edição do cliente)
 *   - atualizar_item_admin()      → atualiza um item existente (campos admin)
 *   - adicionar_item_admin()      → adiciona item novo OU soma em item igual
 *   - remover_item()              → remove um item de um pedido específico
 *   - atualizar_status_pedido()   → muda o status (novo/em andamento/etc.)
 *   - excluir_pedido()            → apaga o pedido inteiro (admin, com CASCADE)
 *   - listar_pedidos_agrupados()  → lista para o painel administrativo
 * ============================================================================
 */
require_once __DIR__ . '/database.php';

/**
 * Cria um novo pedido (usado pelo cliente em index.php).
 * Gera um token seguro, grava o pedido e seus itens numa única transação.
 * Retorna o token gerado, que vira o link pessoal do pedido.
 */
function criar_pedido(string $clienteNome, array $itens): string
{
    $pdo = db();
    $token = gerar_token_pedido();

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('INSERT INTO orders (token, cliente_nome, status) VALUES (?, ?, ?)');
        $stmt->execute([$token, $clienteNome, 'novo']);
        $orderId = (int)$pdo->lastInsertId();

        inserir_itens_pedido($orderId, $itens);

        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }

    return $token;
}

/**
 * Insere uma lista de itens em um pedido.
 *
 * Antes de gravar, MESCLA itens duplicados dentro da própria lista
 * recebida: se dois itens tiverem o mesmo Modelo/Nome (ignorando
 * maiúsculas/minúsculas e espaços), as quantidades são somadas em um
 * único item, em vez de criar duas linhas repetidas. Isso cobre tanto
 * a criação de um pedido novo quanto a regravação completa feita pelo
 * cliente em substituir_itens_pedido().
 */
function inserir_itens_pedido(int $orderId, array $itens): void
{
    $itensMesclados = mesclar_itens_por_nome($itens);

    $pdo = db();
    $stmt = $pdo->prepare(
        'INSERT INTO order_items (order_id, modelo_nome, numero_serie, quantidade, situacao)
         VALUES (?, ?, ?, ?, ?)'
    );
    foreach ($itensMesclados as $item) {
        $modelo = trim((string)($item['modelo_nome'] ?? ''));
        if ($modelo === '') {
            continue;
        }
        $stmt->execute([
            $orderId,
            $modelo,
            trim((string)($item['numero_serie'] ?? '')) ?: null,
            inteiro_positivo($item['quantidade'] ?? 0),
            situacao_valida($item['situacao'] ?? 'normal'),
        ]);
    }
}

/**
 * Recebe uma lista "crua" de itens (vinda do formulário) e devolve uma
 * lista sem duplicados por Modelo/Nome: quando dois itens têm o mesmo
 * nome, soma quantidade + quantidade_feita, mantém o primeiro número de
 * série não vazio encontrado, usa a situação mais grave entre eles
 * (urgente > importante > normal) e concatena os campos de texto
 * "peças usadas" (carcaça/caixas/anéis) em vez de perder um dos dois.
 */
function mesclar_itens_por_nome(array $itens): array
{
    $ordemSituacao = ['normal' => 0, 'importante' => 1, 'urgente' => 2];
    $resultado = []; // chave = nome normalizado, valor = item mesclado

    foreach ($itens as $item) {
        $modelo = trim((string)($item['modelo_nome'] ?? ''));
        if ($modelo === '') {
            continue;
        }
        $chave = normalizar_nome($modelo);

        if (!isset($resultado[$chave])) {
            $resultado[$chave] = $item;
            $resultado[$chave]['modelo_nome'] = $modelo;
            continue;
        }

        // Já existe um item com este nome: soma quantidades e mescla os detalhes.
        $atual = $resultado[$chave];
        $atual['quantidade'] = inteiro_positivo($atual['quantidade'] ?? 0) + inteiro_positivo($item['quantidade'] ?? 0);
        $atual['quantidade_feita'] = inteiro_positivo($atual['quantidade_feita'] ?? 0) + inteiro_positivo($item['quantidade_feita'] ?? 0);

        if (empty($atual['numero_serie']) && !empty($item['numero_serie'])) {
            $atual['numero_serie'] = $item['numero_serie'];
        }

        $situacaoAtual = situacao_valida($atual['situacao'] ?? 'normal');
        $situacaoNova  = situacao_valida($item['situacao'] ?? 'normal');
        if (($ordemSituacao[$situacaoNova] ?? 0) > ($ordemSituacao[$situacaoAtual] ?? 0)) {
            $atual['situacao'] = $situacaoNova;
        }

        $atual['carcaca_utilizada']  = mesclar_texto($atual['carcaca_utilizada'] ?? '', $item['carcaca_utilizada'] ?? '');
        $atual['caixas_utilizadas']  = mesclar_texto($atual['caixas_utilizadas'] ?? '', $item['caixas_utilizadas'] ?? '');
        $atual['aneis']              = mesclar_texto($atual['aneis'] ?? '', $item['aneis'] ?? '');
        $atual['feito'] = !empty($atual['feito']) || !empty($item['feito']);

        $resultado[$chave] = $atual;
    }

    return array_values($resultado);
}

/** Busca um pedido pelo token seguro (link público do cliente). Retorna null se não encontrado. */
function buscar_pedido_por_token(string $token): ?array
{
    $pdo = db();
    $stmt = $pdo->prepare('SELECT * FROM orders WHERE token = ? LIMIT 1');
    $stmt->execute([$token]);
    $pedido = $stmt->fetch();
    return $pedido ?: null;
}

/** Busca um pedido pelo ID interno (uso exclusivo da administração). */
function buscar_pedido_por_id(int $id): ?array
{
    $pdo = db();
    $stmt = $pdo->prepare('SELECT * FROM orders WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $pedido = $stmt->fetch();
    return $pedido ?: null;
}

/** Lista todos os itens de um pedido, na ordem em que foram criados (usado na edição). */
function listar_itens_pedido(int $orderId): array
{
    $stmt = db()->prepare('SELECT * FROM order_items WHERE order_id = ? ORDER BY id ASC');
    $stmt->execute([$orderId]);
    return $stmt->fetchAll();
}

/**
 * Lista os itens de um pedido para a TELA DE LEITURA (não editável):
 * itens ainda não feitos aparecem primeiro (na ordem em que foram
 * criados) e os já marcados como "Feito" vão sendo empurrados para o
 * final da lista, na ordem em que foram concluídos. Isso faz a lista
 * "subir" o que falta e mandar pra baixo o que já está pronto, sem
 * precisar reordenar nada manualmente.
 */
function listar_itens_pedido_para_leitura(int $orderId): array
{
    $stmt = db()->prepare(
        "SELECT oi.*,
                (SELECT GROUP_CONCAT(p.nome ORDER BY p.nome SEPARATOR ', ')
                 FROM order_item_profissionais oip
                 INNER JOIN profissionais p ON p.id = oip.profissional_id
                 WHERE oip.order_item_id = oi.id) AS profissionais_nomes
         FROM order_items oi WHERE order_id = ? ORDER BY feito ASC, feito_em ASC, id ASC"
    );
    $stmt->execute([$orderId]);
    return $stmt->fetchAll();
}

/**
 * Lista os itens de um pedido para IMPRESSÃO/EXPORTAÇÃO: os itens AINDA
 * NÃO prontos ficam por cima dos já concluídos, e dentro de cada grupo
 * fica em ordem alfabética pelo nome da peça. Cada item já vem com os
 * nomes de todos os profissionais responsáveis (pode ter mais de um).
 */
function listar_itens_pedido_para_impressao(int $orderId): array
{
    $stmt = db()->prepare(
        "SELECT oi.*,
                (SELECT GROUP_CONCAT(p.nome ORDER BY p.nome SEPARATOR ', ')
                 FROM order_item_profissionais oip
                 INNER JOIN profissionais p ON p.id = oip.profissional_id
                 WHERE oip.order_item_id = oi.id) AS profissionais_nomes
         FROM order_items oi WHERE order_id = ? ORDER BY feito ASC, modelo_nome ASC"
    );
    $stmt->execute([$orderId]);
    return $stmt->fetchAll();
}

/**
 * Marca (ou desmarca) rapidamente um item como "Feito" DIRETO da tela de
 * leitura, sem precisar abrir o modo de edição completo. Grava também
 * quem foram os profissionais responsáveis (pode ser mais de um) e a
 * quantidade efetivamente feita.
 */
function marcar_item_pronto(int $itemId, int $orderId, int $quantidadeFeita, array $profissionalIds = []): void
{
    $stmt = db()->prepare(
        'UPDATE order_items
         SET feito = 1, quantidade_feita = ?, feito_em = NOW()
         WHERE id = ? AND order_id = ?'
    );
    $stmt->execute([$quantidadeFeita, $itemId, $orderId]);
    definir_profissionais_item($itemId, $profissionalIds);
}

/** Substitui a lista de profissionais responsáveis por um item do pedido. */
function definir_profissionais_item(int $itemId, array $profissionalIds): void
{
    $pdo = db();
    $pdo->prepare('DELETE FROM order_item_profissionais WHERE order_item_id = ?')->execute([$itemId]);
    $stmt = $pdo->prepare('INSERT IGNORE INTO order_item_profissionais (order_item_id, profissional_id) VALUES (?, ?)');
    foreach (array_unique(array_filter(array_map('intval', $profissionalIds))) as $profissionalId) {
        if ($profissionalId > 0) {
            $stmt->execute([$itemId, $profissionalId]);
        }
    }
}

/** Lista os profissionais (id + nome) responsáveis por um item do pedido. */
function buscar_profissionais_item(int $itemId): array
{
    $stmt = db()->prepare(
        'SELECT p.id, p.nome FROM order_item_profissionais oip
         INNER JOIN profissionais p ON p.id = oip.profissional_id
         WHERE oip.order_item_id = ? ORDER BY p.nome ASC'
    );
    $stmt->execute([$itemId]);
    return $stmt->fetchAll();
}

/** Salva/atualiza a observação interna do ADM sobre um item do pedido. */
/**
 * Salva de uma vez só os campos internos do ADM que ficam sempre
 * visíveis/editáveis na tela de leitura do pedido (sem precisar abrir o
 * modo de edição completo): observação, carcaça utilizada, caixas
 * utilizadas e anéis.
 */
function atualizar_detalhes_item(int $itemId, int $orderId, string $observacao, string $carcaca, string $caixas, string $aneis): void
{
    $stmt = db()->prepare(
        'UPDATE order_items
         SET observacao_adm = ?, carcaca_utilizada = ?, caixas_utilizadas = ?, aneis = ?
         WHERE id = ? AND order_id = ?'
    );
    $stmt->execute([
        trim($observacao) ?: null,
        trim($carcaca) ?: null,
        trim($caixas) ?: null,
        trim($aneis) ?: null,
        $itemId, $orderId,
    ]);
}

function reabrir_item(int $itemId, int $orderId): void
{
    $stmt = db()->prepare(
        'UPDATE order_items SET feito = 0, feito_em = NULL WHERE id = ? AND order_id = ?'
    );
    $stmt->execute([$itemId, $orderId]);
}

/**
 * Substitui todos os itens de um pedido pelos itens enviados.
 * Usado quando o CLIENTE edita e salva o próprio pedido: a lista antiga
 * é apagada e a nova lista (já mesclada por inserir_itens_pedido) é
 * gravada no lugar. Também atualiza a data de última modificação.
 */
function substituir_itens_pedido(int $orderId, array $itens): void
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $del = $pdo->prepare('DELETE FROM order_items WHERE order_id = ?');
        $del->execute([$orderId]);

        inserir_itens_pedido($orderId, $itens);

        $touch = $pdo->prepare('UPDATE orders SET atualizado_em = NOW() WHERE id = ?');
        $touch->execute([$orderId]);

        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/**
 * Atualiza todos os campos (do cliente e da administração) de UM item
 * já existente. Usado na tela de edição em massa do admin/pedido.php,
 * onde cada linha da tabela é salva de uma vez.
 */
function atualizar_item_admin(int $itemId, array $dados): void
{
    $stmt = db()->prepare(
        'UPDATE order_items SET
            modelo_nome = ?, numero_serie = ?, quantidade = ?, situacao = ?,
            quantidade_feita = ?, feito = ?, carcaca_utilizada = ?, caixas_utilizadas = ?, aneis = ?
         WHERE id = ?'
    );
    $stmt->execute([
        trim((string)$dados['modelo_nome']),
        trim((string)($dados['numero_serie'] ?? '')) ?: null,
        inteiro_positivo($dados['quantidade'] ?? 0),
        situacao_valida($dados['situacao'] ?? 'normal'),
        inteiro_positivo($dados['quantidade_feita'] ?? 0),
        !empty($dados['feito']) ? 1 : 0,
        trim((string)($dados['carcaca_utilizada'] ?? '')) ?: null,
        trim((string)($dados['caixas_utilizadas'] ?? '')) ?: null,
        trim((string)($dados['aneis'] ?? '')) ?: null,
        $itemId,
    ]);
}

/**
 * Adiciona um item novo a um pedido pelo painel administrativo.
 *
 * Antes de inserir, verifica se já existe um item com o MESMO
 * Modelo/Nome (ignorando maiúsculas/acentuação de caixa) neste pedido.
 * Se existir, em vez de criar uma linha duplicada, SOMA as quantidades
 * no item já existente e mescla os campos de texto (Carcaça utilizada,
 * Caixas utilizadas, Anéis), preservando o que já tinha sido anotado.
 * Só cria uma linha nova quando realmente não há nenhum item igual.
 */
function adicionar_item_admin(int $orderId, array $dados): void
{
    $modelo = trim((string)$dados['modelo_nome']);
    $chaveNova = normalizar_nome($modelo);

    $existente = null;
    foreach (listar_itens_pedido($orderId) as $item) {
        if (normalizar_nome($item['modelo_nome']) === $chaveNova) {
            $existente = $item;
            break;
        }
    }

    if ($existente) {
        atualizar_item_admin((int)$existente['id'], [
            'modelo_nome'        => $existente['modelo_nome'],
            'numero_serie'       => $existente['numero_serie'] ?: ($dados['numero_serie'] ?? ''),
            'quantidade'         => inteiro_positivo($existente['quantidade']) + inteiro_positivo($dados['quantidade'] ?? 0),
            'situacao'           => $existente['situacao'],
            'quantidade_feita'   => inteiro_positivo($existente['quantidade_feita']) + inteiro_positivo($dados['quantidade_feita'] ?? 0),
            'feito'              => $existente['feito'],
            'carcaca_utilizada'  => mesclar_texto($existente['carcaca_utilizada'] ?? '', $dados['carcaca_utilizada'] ?? ''),
            'caixas_utilizadas'  => mesclar_texto($existente['caixas_utilizadas'] ?? '', $dados['caixas_utilizadas'] ?? ''),
            'aneis'              => mesclar_texto($existente['aneis'] ?? '', $dados['aneis'] ?? ''),
        ]);
        return;
    }

    $stmt = db()->prepare(
        'INSERT INTO order_items
            (order_id, modelo_nome, numero_serie, quantidade, situacao,
             quantidade_feita, feito, carcaca_utilizada, caixas_utilizadas, aneis)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $orderId,
        $modelo,
        trim((string)($dados['numero_serie'] ?? '')) ?: null,
        inteiro_positivo($dados['quantidade'] ?? 0),
        situacao_valida($dados['situacao'] ?? 'normal'),
        inteiro_positivo($dados['quantidade_feita'] ?? 0),
        !empty($dados['feito']) ? 1 : 0,
        trim((string)($dados['carcaca_utilizada'] ?? '')) ?: null,
        trim((string)($dados['caixas_utilizadas'] ?? '')) ?: null,
        trim((string)($dados['aneis'] ?? '')) ?: null,
    ]);
}

/**
 * Remove um único item de um pedido.
 * A condição "AND order_id = ?" é essencial: garante que ninguém
 * consiga apagar um item de OUTRO pedido manipulando o número na URL.
 */
function remover_item(int $itemId, int $orderId): void
{
    $stmt = db()->prepare('DELETE FROM order_items WHERE id = ? AND order_id = ?');
    $stmt->execute([$itemId, $orderId]);
}

/** Atualiza o status geral do pedido (novo/em andamento/parcial/concluído). */
function atualizar_status_pedido(int $orderId, string $status): void
{
    $stmt = db()->prepare('UPDATE orders SET status = ? WHERE id = ?');
    $stmt->execute([$status, $orderId]);
}

/**
 * Exclui um pedido inteiro (e, por causa do ON DELETE CASCADE definido
 * no schema.sql, todos os seus itens junto). Ação exclusiva da
 * administração — a interface sempre pede confirmação antes de chamar
 * esta função, pois a exclusão não pode ser desfeita.
 */
function excluir_pedido(int $orderId): void
{
    $stmt = db()->prepare('DELETE FROM orders WHERE id = ?');
    $stmt->execute([$orderId]);
}

/**
 * Lista as peças de radiador marcadas como PRONTAS dentro de um período
 * (com base em `feito_em`), junto com o cliente e o profissional que fez
 * — usado no relatório de fechamento (admin/fechamento.php).
 */
function listar_pecas_feitas_periodo(string $dataInicio, string $dataFim): array
{
    $stmt = db()->prepare(
        "SELECT oi.modelo_nome, oi.quantidade_feita, oi.feito_em, oi.observacao_adm,
                o.cliente_nome,
                (SELECT GROUP_CONCAT(p.nome ORDER BY p.nome SEPARATOR ', ')
                 FROM order_item_profissionais oip
                 INNER JOIN profissionais p ON p.id = oip.profissional_id
                 WHERE oip.order_item_id = oi.id) AS profissionais_nomes
         FROM order_items oi
         INNER JOIN orders o ON o.id = oi.order_id
         WHERE oi.feito = 1 AND DATE(oi.feito_em) BETWEEN ? AND ?
         ORDER BY oi.feito_em ASC"
    );
    $stmt->execute([$dataInicio, $dataFim]);
    return $stmt->fetchAll();
}

/** Lista todos os pedidos agrupados por cliente com totais, para o painel administrativo. */
function listar_pedidos_agrupados(): array
{
    $sql = "SELECT o.id, o.token, o.cliente_nome, o.status, o.criado_em, o.atualizado_em,
                   COALESCE(SUM(i.quantidade), 0) AS total_solicitado,
                   COALESCE(SUM(i.quantidade_feita), 0) AS total_feito,
                   COUNT(i.id) AS total_itens
            FROM orders o
            LEFT JOIN order_items i ON i.order_id = o.id
            GROUP BY o.id
            ORDER BY (o.status = 'concluido') DESC, o.cliente_nome ASC";
    return db()->query($sql)->fetchAll();
}
