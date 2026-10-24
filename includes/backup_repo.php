<?php
/**
 * includes/backup_repo.php
 * ---------------------------------------------------------------------
 * Geração de um backup completo do sistema: TODOS os arquivos da pasta
 * raiz do site (o "htdocs" da InfinityFree — PHP, CSS, JS, imagens
 * enviadas) + um dump SQL fresco do banco de dados, tudo dentro de um
 * único .zip para o administrador baixar.
 *
 * A hospedagem (InfinityFree) não permite rodar o comando "mysqldump"
 * do sistema operacional, então o dump é gerado manualmente aqui,
 * lendo a estrutura de cada tabela (SHOW CREATE TABLE) e todos os
 * dados (SELECT *), e montando um arquivo .sql pronto para restaurar
 * em qualquer phpMyAdmin ou cliente MySQL.
 *
 * O .zip em si é montado com includes/SimpleZipWriter.php — um gerador
 * de ZIP em PHP puro, sem usar a extensão ZipArchive e sem nunca
 * escrever nada no disco do servidor (o arquivo é montado inteiro em
 * memória e enviado direto como download). Isso existe porque, em
 * hospedagens como a InfinityFree, tentar abrir um .zip para escrita no
 * disco (mesmo dentro da própria pasta do site) costuma ser bloqueado
 * pelo servidor — daí o backup nunca funcionava antes.
 */
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/SimpleZipWriter.php';

/** Pasta raiz do site (onde ficam index.php, admin/, includes/, assets/… — o "htdocs" da InfinityFree). */
function backup_raiz_site(): string
{
    return dirname(__DIR__);
}

/**
 * Gera o dump SQL (estrutura + dados) de todas as tabelas do banco
 * atual e devolve como uma única string pronta para salvar em .sql.
 * Equivale a um export completo feito pelo phpMyAdmin.
 */
function gerar_dump_sql(): string
{
    $pdo = db();
    $linhas = [];
    $linhas[] = '-- ===================================================================';
    $linhas[] = '-- Backup do banco de dados — Shalom Radiadores';
    $linhas[] = '-- Gerado automaticamente em: ' . date('d/m/Y H:i:s');
    $linhas[] = '-- ===================================================================';
    $linhas[] = '';
    $linhas[] = 'SET NAMES utf8mb4;';
    $linhas[] = 'SET FOREIGN_KEY_CHECKS = 0;';
    $linhas[] = '';

    $tabelas = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);

    foreach ($tabelas as $tabela) {
        $linhas[] = '-- --------------------------------------------------';
        $linhas[] = "-- Tabela: {$tabela}";
        $linhas[] = '-- --------------------------------------------------';

        $criar = $pdo->query("SHOW CREATE TABLE `{$tabela}`")->fetch(PDO::FETCH_ASSOC);
        $linhas[] = "DROP TABLE IF EXISTS `{$tabela}`;";
        $linhas[] = $criar['Create Table'] . ';';
        $linhas[] = '';

        $stmt = $pdo->query("SELECT * FROM `{$tabela}`");
        $colunas = null;
        $blocoValores = [];
        $contador = 0;

        while ($linha = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if ($colunas === null) {
                $colunas = '`' . implode('`, `', array_keys($linha)) . '`';
            }
            $valores = array_map(function ($v) use ($pdo) {
                return $v === null ? 'NULL' : $pdo->quote((string)$v);
            }, $linha);
            $blocoValores[] = '(' . implode(', ', $valores) . ')';
            $contador++;

            // Insere em blocos de 200 linhas para não gerar uma única
            // linha gigantesca no arquivo .sql.
            if ($contador % 200 === 0) {
                $linhas[] = "INSERT INTO `{$tabela}` ({$colunas}) VALUES";
                $linhas[] = implode(",\n", $blocoValores) . ';';
                $blocoValores = [];
            }
        }
        if ($blocoValores) {
            $linhas[] = "INSERT INTO `{$tabela}` ({$colunas}) VALUES";
            $linhas[] = implode(",\n", $blocoValores) . ';';
        }
        $linhas[] = '';
    }

    $linhas[] = 'SET FOREIGN_KEY_CHECKS = 1;';

    return implode("\n", $linhas) . "\n";
}

/**
 * Monta o backup completo (TODOS os arquivos da raiz do site + dump do
 * banco) e devolve um SimpleZipWriter já pronto para ser enviado como
 * download — sem nunca escrever nada em disco no servidor.
 */
function gerar_backup_zip(): SimpleZipWriter
{
    $raizSite = backup_raiz_site();

    $zip = new SimpleZipWriter();

    // 1) TODOS os arquivos da raiz do site (o "htdocs" inteiro: PHP,
    //    CSS, JS, imagens enviadas, tudo).
    $zip->addPastaRecursiva($raizSite, 'site/');

    // 2) Dump do banco de dados, gerado na hora (equivalente a um
    //    export completo do phpMyAdmin).
    $dump = gerar_dump_sql();
    $nomeArquivoSql = 'banco-de-dados-' . date('Y-m-d_H-i') . '.sql';
    $zip->addString('banco-de-dados/' . $nomeArquivoSql, $dump);

    // 3) Um LEIA-ME rápido explicando o conteúdo e como restaurar.
    $leiaMe = "BACKUP — Shalom Radiadores\n"
        . 'Gerado em: ' . date('d/m/Y H:i:s') . "\n\n"
        . "Conteúdo deste arquivo:\n"
        . "- /site/            -> TODOS os arquivos do sistema (a pasta raiz do site inteira: PHP, CSS, JS, imagens enviadas)\n"
        . "- /banco-de-dados/  -> dump SQL completo (estrutura + dados) do banco no momento do backup\n\n"
        . "Para restaurar em outra hospedagem:\n"
        . "1. Suba o conteúdo da pasta /site/ para a raiz do novo servidor (o \"htdocs\").\n"
        . "2. Crie um banco MySQL novo e importe o arquivo .sql (no phpMyAdmin, aba \"Importar\").\n"
        . "3. Ajuste includes/config.php com os dados do novo banco/hospedagem.\n\n"
        . "Guarde este arquivo em local seguro: ele contém a senha de acesso ao banco de dados.\n";
    $zip->addString('LEIA-ME.txt', $leiaMe);

    return $zip;
}
