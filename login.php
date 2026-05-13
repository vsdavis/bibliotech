<?php
/**
 * BiblioTech — Tela de Login
 *
 * Características:
 *  - Formulário com proteção CSRF.
 *  - Mantém o e-mail digitado em caso de erro (UX).
 *  - Se o usuário já está logado, vai direto ao dashboard.
 */

require_once __DIR__ . '/includes/helpers.php';

if (logado()) {
    redirecionar(BASE_URL . '/dashboard.php');
}

$email_anterior = $_SESSION['login_email'] ?? '';
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login · BiblioTech</title>
    <link rel="stylesheet" href="<?= e(BASE_URL) ?>/assets/css/style.css">
</head>
<body class="tela-login">

    <main class="login-container">
        <div class="login-card">

            <div class="login-cabecalho">
                <span class="logo-icone-grande">📚</span>
                <h1>BiblioTech</h1>
                <p>Sistema de Gerenciamento de Biblioteca Escolar</p>
            </div>

            <?= exibir_flash() ?>

            <form action="<?= e(BASE_URL) ?>/login-back.php"
                  method="POST"
                  class="login-form"
                  novalidate>

                <?= csrf_input() ?>

                <div class="campo">
                    <label for="email">E-mail</label>
                    <input type="email"
                           id="email"
                           name="email"
                           value="<?= e($email_anterior) ?>"
                           required
                           autocomplete="username"
                           autofocus>
                </div>

                <div class="campo">
                    <label for="senha">Senha</label>
                    <input type="password"
                           id="senha"
                           name="senha"
                           required
                           autocomplete="current-password">
                </div>

                <button type="submit" class="btn btn-primario btn-bloco">
                    Entrar
                </button>
            </form>

            <div class="login-rodape">
                <small>&copy; <?= date('Y') ?> BiblioTech</small>
            </div>

        </div>
    </main>

    <script src="<?= e(BASE_URL) ?>/assets/js/script.js"></script>
</body>
</html>
<?php
// Limpa o e-mail temporário só depois de renderizar a página
unset($_SESSION['login_email']);
