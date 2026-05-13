<?php
/**
 * BiblioTech — Logout seguro
 *
 * Procedimento recomendado pela documentação do PHP:
 *   1. Limpa todas as variáveis de sessão.
 *   2. Apaga o cookie de sessão do navegador.
 *   3. Destrói os dados de sessão no servidor.
 */

require_once __DIR__ . '/includes/helpers.php';

// 1) Limpa todas as variáveis da sessão
$_SESSION = [];

// 2) Apaga o cookie de sessão
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        [
            'expires'  => time() - 42000,
            'path'     => $params['path'],
            'domain'   => $params['domain'],
            'secure'   => $params['secure'],
            'httponly' => $params['httponly'],
            'samesite' => $params['samesite'] ?? 'Lax',
        ]
    );
}

// 3) Destrói os dados da sessão no servidor
session_destroy();

// Inicia uma sessão nova e limpa para gravar a flash de despedida
session_start();
session_regenerate_id(true);
flash('sucesso', 'Você saiu do sistema com segurança.');

redirecionar(BASE_URL . '/login.php');
