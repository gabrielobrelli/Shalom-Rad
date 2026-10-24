<?php
/**
 * ============================================================================
 * seminovos_repo.php — Camada de acesso a dados da seção
 * "Concerto de Seminovos Marcio" (área interna, sem relação com clientes)
 * ============================================================================
 * Índice rápido:
 *   - obter_seminovos_order()      → garante/retorna o registro-guarda-chuva
 *   - listar_itens_seminovos()     → lista os itens já cadastrados
 *   - adicionar_item_seminovos()   → adiciona item novo OU soma em item igual
 *   - atualizar_item_seminovos()   → atualiza um item (edição em massa)
 *   - remover_item_seminovos()     → remove um item específico
 *   - total_geral_seminovos()      → soma total de peças feitas
 * ============================================================================
 */
require_once __DIR__ . '/database.php';

/** Retorna (e garante que existe) o registro-guarda-chuva padrão da seção. */
function obter_seminovos_order(): array
{
    $pdo = db();
    $row = $pdo->query('SELECT * FROM seminovos_orders ORDER BY id ASC LIMIT 1')->fetch();
    if (!$row) {
        $pdo->exec("INSERT INTO seminovos_orders (titulo) VALUES ('Concerto de Seminovos Marcio')");
        $row = $pdo->query('SELECT * FROM seminovos_orders ORDER BY id ASC LIMIT 1')->fetch();
    }
    return $row;
}

/** Lista todos os itens da seção Seminovos, na ordem em que foram criados. */
function listar_itens_seminovos(int $seminovosOrderId): array
{
    $stmt = db()->prepare('SELECT * FROM seminovos_items WHERE seminovos_order_id = ? ORDER BY id ASC');
    $stmt->execute([$seminovosOrderId]);
    return $stmt->fetchAll();
}

/** Mesma lista, mas em ordem alfabética pelo nome da peça — usada na impressão/exportação. */
function listar_itens_seminovos_para_impressao(int $seminovosOrderId): array
{
    $stmt = db()->prepare('SELECT * FROM seminovos_items WHERE seminovos_order_id = ? ORDER BY modelo_nome ASC');
    $stmt->execute([$seminovosOrderId]);
    return $stmt->fetchAll();
}

/**
 * Adiciona um item novo à seção Seminovos.
 *
 * Assim como no pedido de cliente, verifica primeiro se já existe um
 * item com o MESMO Modelo/Nome. Se existir, SOMA a Quantidade Feita no
 * item já existente e vai ACRESCENTANDO o texto de "Nossas Peças
 * Usadas" (em vez de substituir), já que é comum registrar peças
 * diferentes usadas no mesmo modelo em datas diferentes. Só cria uma
 * linha nova quando não há nenhum item igual ainda.
 */
function adicionar_item_seminovos(int $seminovosOrderId, string $modelo, int $quantidadeFeita, ?string $pecasUsadas): void
{
    $modelo = trim($modelo);
    $chaveNova = normalizar_nome($modelo);

    foreach (listar_itens_seminovos($seminovosOrderId) as $item) {
        if (normalizar_nome($item['modelo_nome']) === $chaveNova) {
            atualizar_item_seminovos(
                (int)$item['id'],
                $item['modelo_nome'],
                inteiro_positivo($item['quantidade_feita']) + $quantidadeFeita,
                mesclar_texto($item['nossas_pecas_usadas'] ?? '', $pecasUsadas ?? '')
            );
            return;
        }
    }

    $stmt = db()->prepare(
        'INSERT INTO seminovos_items (seminovos_order_id, modelo_nome, quantidade_feita, nossas_pecas_usadas)
         VALUES (?, ?, ?, ?)'
    );
    $stmt->execute([$seminovosOrderId, $modelo, $quantidadeFeita, trim((string)$pecasUsadas) ?: null]);
}

/** Atualiza um item já existente (usado na edição em massa da tabela). */
function atualizar_item_seminovos(int $itemId, string $modelo, int $quantidadeFeita, ?string $pecasUsadas): void
{
    $stmt = db()->prepare(
        'UPDATE seminovos_items SET modelo_nome = ?, quantidade_feita = ?, nossas_pecas_usadas = ? WHERE id = ?'
    );
    $stmt->execute([trim($modelo), $quantidadeFeita, trim((string)$pecasUsadas) ?: null, $itemId]);
}

/** Remove um item específico da seção Seminovos. */
function remover_item_seminovos(int $itemId, int $seminovosOrderId): void
{
    $stmt = db()->prepare('DELETE FROM seminovos_items WHERE id = ? AND seminovos_order_id = ?');
    $stmt->execute([$itemId, $seminovosOrderId]);
}

/** Soma o total geral de peças feitas em todos os itens da seção. */
function total_geral_seminovos(array $itens): int
{
    return array_sum(array_map(fn($i) => (int)$i['quantidade_feita'], $itens));
}
