<?php
/**
 * BiblioTech — Conexão com o banco de dados (PDO)
 *
 * Boas práticas aplicadas:
 *  - Usa PDO com prepared statements reais (impede SQL Injection).
 *  - ATTR_ERRMODE => EXCEPTION: erros viram exceções controladas.
 *  - ATTR_EMULATE_PREPARES => false: força prepared statements no servidor.
 *  - charset utf8mb4 (suporte completo a Unicode).
 *  - Erros técnicos NUNCA são exibidos ao usuário; vão para o error_log.
 */

// Configurações do banco (ajuste se o seu XAMPP usar outras credenciais)
const DB_HOST    = 'localhost';
const DB_NAME    = 'bibliotech';
const DB_USER    = 'root';
const DB_PASS    = '';
const DB_CHARSET = 'utf8mb4';

$dsn = 'mysql:host=' . DB_HOST
     . ';dbname='   . DB_NAME
     . ';charset='  . DB_CHARSET;

$opcoes = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $opcoes);
} catch (PDOException $e) {
    // Log interno (somente para o desenvolvedor)
    error_log('[BiblioTech] Falha de conexão: ' . $e->getMessage());

    // Mensagem genérica para o usuário final
    http_response_code(500);
    exit('Erro ao conectar ao banco de dados. Tente novamente em instantes.');
}
