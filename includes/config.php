<?php
/**
 * Configuração central do sistema Shalom Radiadores.
 * Edite apenas os valores abaixo ao publicar no InfinityFree.
 */

// ---- Banco de dados -------------------------------------------------
define('DB_HOST', 'sql306.infinityfree.com');   // Host do MySQL (painel InfinityFree)
define('DB_NAME', 'if0_42757432_shalom_BD');    // Nome do banco
define('DB_USER', 'if0_42757432');              // Usuário do banco
define('DB_PASS', 'idn78910');                  // Senha do banco

// ---- WhatsApp ---------------------------------------------------------
define('WHATSAPP_NUMERO', '5514981276780');

// ---- URL base do site (sem barra no final) ---------------------------
define('SITE_URL', 'https://shalomrad.gt.tc');

// ---- Fuso horário -------------------------------------------------
date_default_timezone_set('America/Sao_Paulo');

// ---- Sessão segura -------------------------------------------------
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_samesite', 'Lax');
    session_start();
}

// ---- Exibição de erros (DESLIGUE em produção) -------------------------
error_reporting(E_ALL);
ini_set('display_errors', '0'); // mantenha 0 em produção; use logs
ini_set('log_errors', '1');
