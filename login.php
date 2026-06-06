<?php
/**
 * BiblioTech — Tela de Login
 *
 * Características:
 *  - Formulário com proteção CSRF.
 *  - Mantém o e-mail digitado em caso de erro (UX).
 *  - Se o usuário já está logado, vai direto ao dashboard.
 *  - Layout em dois painéis: marca (hero) + formulário.
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
    <meta name="theme-color" content="#22543D">
    <title>Login · BiblioTech</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= asset('assets/css/style.css') ?>">
</head>
<body class="tela-login">

    <main class="login-container">
        <div class="login-card">

            <!-- ── Painel marca (hero) ── -->
            <aside class="login-hero">
                <div class="login-hero-conteudo">
                    <span class="login-hero-icone" aria-hidden="true">
                        <img src="<?= e(BASE_URL) ?>/assets/img/logo-icon.svg"
                             alt="" width="60" height="48">
                    </span>
                    <h1 class="login-hero-titulo">BiblioTech</h1>
                    <p class="login-hero-sub">
                        Sistema de Gerenciamento de Biblioteca Escolar
                    </p>
                    <ul class="login-hero-lista">
                        <li>Controle de acervo e empréstimos</li>
                        <li>Gestão de alunos e usuários</li>
                        <li>Relatórios e indicadores em tempo real</li>
                    </ul>
                </div>
                <span class="login-hero-marca">ORBIT &middot; Projeto de Extensão</span>
            </aside>

            <!-- ── Painel do formulário ── -->
            <section class="login-painel">
                <header class="login-painel-cabecalho">
                    <h2>Bem-vindo de volta</h2>
                    <p>Entre com suas credenciais para acessar o sistema.</p>
                </header>

                <?= exibir_flash() ?>

                <form action="<?= e(BASE_URL) ?>/login-back.php"
                      method="POST"
                      class="login-form"
                      novalidate>

                    <?= csrf_input() ?>

                    <div class="campo">
                        <label for="email">E-mail</label>
                        <div class="campo-icone">
                            <svg class="campo-icone-svg" viewBox="0 0 24 24" fill="none"
                                 stroke="currentColor" stroke-width="1.8"
                                 stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <rect x="3" y="5" width="18" height="14" rx="2"/>
                                <path d="m3 7 9 6 9-6"/>
                            </svg>
                            <input type="email"
                                   id="email"
                                   name="email"
                                   value="<?= e($email_anterior) ?>"
                                   required
                                   autocomplete="username"
                                   autofocus
                                   placeholder="seu@email.com">
                        </div>
                    </div>

                    <div class="campo">
                        <label for="senha">Senha</label>
                        <div class="campo-icone">
                            <svg class="campo-icone-svg" viewBox="0 0 24 24" fill="none"
                                 stroke="currentColor" stroke-width="1.8"
                                 stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <rect x="4" y="11" width="16" height="10" rx="2"/>
                                <path d="M8 11V7a4 4 0 0 1 8 0v4"/>
                            </svg>
                            <input type="password"
                                   id="senha"
                                   name="senha"
                                   required
                                   autocomplete="current-password"
                                   placeholder="••••••••">
                            <button type="button"
                                    class="campo-senha-toggle"
                                    data-toggle-senha="senha"
                                    aria-label="Mostrar senha">
                                <svg class="icone-olho" viewBox="0 0 24 24" fill="none"
                                     stroke="currentColor" stroke-width="1.8"
                                     stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7Z"/>
                                    <circle cx="12" cy="12" r="3"/>
                                </svg>
                                <svg class="icone-olho-corte" viewBox="0 0 24 24" fill="none"
                                     stroke="currentColor" stroke-width="1.8"
                                     stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" hidden>
                                    <path d="M9.9 4.24A9.1 9.1 0 0 1 12 4c6.5 0 10 7 10 7a13.2 13.2 0 0 1-1.67 2.68"/>
                                    <path d="M6.1 6.1A13.3 13.3 0 0 0 2 11s3.5 7 10 7a9 9 0 0 0 5.9-2.1"/>
                                    <path d="m2 2 20 20"/>
                                </svg>
                            </button>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primario btn-bloco btn-lg">
                        Entrar
                    </button>
                </form>

                <footer class="login-painel-rodape">
                    <small>&copy; <?= date('Y') ?> BiblioTech</small>
                </footer>
            </section>

        </div>
    </main>

    <script src="<?= asset('assets/js/script.js') ?>"></script>
</body>
</html>
<?php
// Limpa o e-mail temporário só depois de renderizar a página
unset($_SESSION['login_email']);
