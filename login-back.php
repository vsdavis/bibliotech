<?php
/**
 * BiblioTech — Backend do Login
 *
 * Etapas de segurança aplicadas:
 *   1. Aceita apenas requisições POST.
 *   2. Valida o token CSRF.
 *   3. Valida formato do e-mail (filter_var).
 *   4. Busca o usuário com PDO + prepared statement.
 *   5. Verifica a senha com password_verify() (NUNCA compara texto puro).
 *   6. Verifica se o usuário está ativo.
 *   7. session_regenerate_id(true) — anti-fixação de sessão.
 *   8. Define dados do usuário na sessão.
 *   9. Mensagem de erro genérica (não revela se o e-mail existe).
 */

require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/conexao.php';

// 1) Apenas POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirecionar(BASE_URL . '/login.php');
}

// 2) CSRF
csrf_validar();

// 3) Coleta e validação dos campos
$email = trim((string) ($_POST['email'] ?? ''));
$senha = (string) ($_POST['senha'] ?? '');

// Mantém o e-mail digitado caso ocorra erro (melhora UX)
$_SESSION['login_email'] = $email;

if ($email === '' || $senha === '') {
    flash('erro', 'Informe e-mail e senha.');
    redirecionar(BASE_URL . '/login.php');
}

if (!email_valido($email)) {
    flash('erro', 'E-mail inválido.');
    redirecionar(BASE_URL . '/login.php');
}

// Mensagem padrão (genérica, não revela se o e-mail existe)
$msg_invalida = 'E-mail ou senha incorretos.';

// 4) Busca do usuário
try {
    $sql = 'SELECT id, nome, email, senha, perfil, ativo
              FROM usuarios
             WHERE email = :email
             LIMIT 1';

    $stmt = $pdo->prepare($sql);
    $stmt->execute([':email' => $email]);
    $usuario = $stmt->fetch();

} catch (PDOException $e) {
    error_log('[BiblioTech] login: ' . $e->getMessage());
    flash('erro', 'Erro interno. Tente novamente em instantes.');
    redirecionar(BASE_URL . '/login.php');
}

if (!$usuario) {
    flash('erro', $msg_invalida);
    redirecionar(BASE_URL . '/login.php');
}

// 5) Verifica a senha (compara hash bcrypt)
if (!password_verify($senha, $usuario['senha'])) {
    flash('erro', $msg_invalida);
    redirecionar(BASE_URL . '/login.php');
}

// 6) Verifica se o usuário está ativo
if ((int) $usuario['ativo'] !== 1) {
    flash('erro', 'Conta desativada. Procure um administrador.');
    redirecionar(BASE_URL . '/login.php');
}

// 7) Anti-session-fixation
session_regenerate_id(true);

// 8) Dados do usuário na sessão
$_SESSION['usuario_id']     = (int) $usuario['id'];
$_SESSION['usuario_nome']   = $usuario['nome'];
$_SESSION['usuario_email']  = $usuario['email'];
$_SESSION['usuario_perfil'] = $usuario['perfil'];
$_SESSION['login_em']       = time();

// Regenera token CSRF para a nova sessão autenticada
unset($_SESSION['csrf_token']);

// Limpa o e-mail temporário
unset($_SESSION['login_email']);

flash('sucesso', 'Bem-vindo(a), ' . $usuario['nome'] . '!');

// 9) Redireciona ao dashboard
redirecionar(BASE_URL . '/dashboard.php');
