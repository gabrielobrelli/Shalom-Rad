<?php
/**
 * includes/lembretes_repo.php — Quadro de lembretes livres da
 * administração: recadinhos rápidos, com título opcional, texto e um
 * nível de urgência (normal / importante / urgente — os mesmos usados
 * nos itens de pedido, ver situacao_valida()/icone_situacao() em
 * functions.php).
 */
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/functions.php';

/**
 * Lista todos os lembretes ordenados por URGÊNCIA primeiro (urgente >
 * importante > normal) e, dentro da mesma urgência, por ORDEM DE
 * CHEGADA (o mais antigo primeiro — quem chegou antes fica na frente).
 */
function listar_lembretes(): array
{
    return db()->query(
        "SELECT * FROM lembretes
         ORDER BY FIELD(urgencia, 'urgente', 'importante', 'normal'), criado_em ASC"
    )->fetchAll();
}

function buscar_lembrete(int $id): ?array
{
    $stmt = db()->prepare('SELECT * FROM lembretes WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $lembrete = $stmt->fetch();
    return $lembrete ?: null;
}

/** Cria um lembrete novo. O título é opcional (pode vir null). */
function criar_lembrete(?string $titulo, string $texto, string $urgencia): int
{
    $stmt = db()->prepare('INSERT INTO lembretes (titulo, texto, urgencia) VALUES (?, ?, ?)');
    $stmt->execute([$titulo ?: null, trim($texto), situacao_valida($urgencia)]);
    return (int)db()->lastInsertId();
}

function atualizar_lembrete(int $id, ?string $titulo, string $texto, string $urgencia): void
{
    $stmt = db()->prepare('UPDATE lembretes SET titulo = ?, texto = ?, urgencia = ? WHERE id = ?');
    $stmt->execute([$titulo ?: null, trim($texto), situacao_valida($urgencia), $id]);
}

function excluir_lembrete(int $id): void
{
    db()->prepare('DELETE FROM lembretes WHERE id = ?')->execute([$id]);
}
