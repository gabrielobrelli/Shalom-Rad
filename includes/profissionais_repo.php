<?php
/**
 * ============================================================================
 * profissionais_repo.php — Cadastro de profissionais da oficina.
 * Usado em dois lugares: para registrar quem concluiu cada peça de um
 * pedido (tabela order_item_profissionais) e quem realizou cada serviço
 * da agenda (tabela servico_profissionais) — um item/serviço pode ter
 * mais de um profissional envolvido.
 * ============================================================================
 */
require_once __DIR__ . '/database.php';

/** Lista profissionais ativos, em ordem alfabética (para preencher <select>). */
function listar_profissionais_ativos(): array
{
    return db()->query('SELECT * FROM profissionais WHERE ativo = 1 ORDER BY nome ASC')->fetchAll();
}

/** Lista todos os profissionais (inclusive inativos), para a tela de gerenciamento. */
function listar_todos_profissionais(): array
{
    return db()->query('SELECT * FROM profissionais ORDER BY nome ASC')->fetchAll();
}

function buscar_profissional(int $id): ?array
{
    $stmt = db()->prepare('SELECT * FROM profissionais WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

function criar_profissional(string $nome): void
{
    $nome = trim($nome);
    if ($nome === '') return;
    $stmt = db()->prepare('INSERT INTO profissionais (nome) VALUES (?)');
    $stmt->execute([$nome]);
}

/** "Remove" um profissional sem apagar o histórico: apenas marca como inativo,
 *  já que peças/serviços antigos continuam referenciando o profissional. */
function inativar_profissional(int $id): void
{
    $stmt = db()->prepare('UPDATE profissionais SET ativo = 0 WHERE id = ?');
    $stmt->execute([$id]);
}

function reativar_profissional(int $id): void
{
    $stmt = db()->prepare('UPDATE profissionais SET ativo = 1 WHERE id = ?');
    $stmt->execute([$id]);
}

/**
 * Contagem de "quanto cada profissional já fez", combinando:
 *   - peças de radiador marcadas como feitas (order_items.feito = 1)
 *   - serviços da agenda atribuídos a ele
 * Aceita um filtro opcional de busca por nome (usado na tela de pesquisa).
 */
function ranking_profissionais(string $busca = ''): array
{
    $sql = "SELECT p.id, p.nome, p.ativo,
                   COUNT(DISTINCT oi.id) AS total_pecas_feitas,
                   COUNT(DISTINCT s.id) AS total_servicos
            FROM profissionais p
            LEFT JOIN order_item_profissionais oip ON oip.profissional_id = p.id
            LEFT JOIN order_items oi ON oi.id = oip.order_item_id AND oi.feito = 1
            LEFT JOIN servico_profissionais sp ON sp.profissional_id = p.id
            LEFT JOIN servicos s ON s.id = sp.servico_id
            WHERE p.nome LIKE ?
            GROUP BY p.id
            ORDER BY (COUNT(DISTINCT oi.id) + COUNT(DISTINCT s.id)) DESC, p.nome ASC";
    $stmt = db()->prepare($sql);
    $stmt->execute(['%' . $busca . '%']);
    return $stmt->fetchAll();
}

/**
 * Igual a ranking_profissionais(), mas restrito a um período: só conta
 * peças marcadas como feitas (feito_em) e serviços (data_servico) dentro
 * do intervalo — usado no relatório de fechamento (admin/fechamento.php).
 * Só retorna profissionais que tiveram alguma produção no período.
 * Um item/serviço com mais de um profissional conta para todos eles.
 */
function ranking_profissionais_periodo(string $dataInicio, string $dataFim): array
{
    // IMPORTANTE: contamos oi.id e s.id (não oip.order_item_id nem
    // sp.servico_id), porque esses só ficam preenchidos quando o item/
    // serviço realmente bateu com o filtro de feito=1 + período.
    $sql = "SELECT p.id, p.nome,
                   COUNT(DISTINCT oi.id) AS total_pecas_feitas,
                   COALESCE(SUM(CASE WHEN oi.id IS NOT NULL THEN oi.quantidade_feita ELSE 0 END), 0) AS total_unidades_feitas,
                   COUNT(DISTINCT s.id) AS total_servicos
            FROM profissionais p
            LEFT JOIN order_item_profissionais oip ON oip.profissional_id = p.id
            LEFT JOIN order_items oi ON oi.id = oip.order_item_id AND oi.feito = 1 AND DATE(oi.feito_em) BETWEEN ? AND ?
            LEFT JOIN servico_profissionais sp ON sp.profissional_id = p.id
            LEFT JOIN servicos s ON s.id = sp.servico_id AND s.data_servico BETWEEN ? AND ?
            GROUP BY p.id
            HAVING (COUNT(DISTINCT oi.id) + COUNT(DISTINCT s.id)) > 0
            ORDER BY (COUNT(DISTINCT oi.id) + COUNT(DISTINCT s.id)) DESC, p.nome ASC";
    $stmt = db()->prepare($sql);
    $stmt->execute([$dataInicio, $dataFim, $dataInicio, $dataFim]);
    return $stmt->fetchAll();
}
