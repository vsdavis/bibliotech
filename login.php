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
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 680 142"
                     class="logo-completo"
                     role="img"
                     aria-label="BiblioTech — Sistema Escolar">
                  <title>BiblioTech</title>
                  <defs>
                    <style>
                      @import url('https://fonts.googleapis.com/css2?family=Outfit:wght@300;700&amp;display=swap');
                    </style>
                  </defs>
                  <g transform="translate(182, 68)">
                    <g transform="rotate(-13, 0, 22)">
                      <rect x="-36" y="-33" width="36" height="55" rx="6" fill="#1A5C38"/>
                      <rect x="-30" y="-18" width="22" height="3"  rx="1.5" fill="white" opacity="0.82"/>
                      <rect x="-30" y="-8"  width="22" height="3"  rx="1.5" fill="white" opacity="0.82"/>
                      <rect x="-30" y="2"   width="14" height="3"  rx="1.5" fill="white" opacity="0.82"/>
                    </g>
                    <g transform="rotate(13, 0, 22)">
                      <rect x="0" y="-33" width="36" height="55" rx="6" fill="#48B07C"/>
                      <circle cx="18" cy="-4"  r="4.5" fill="white" opacity="0.90"/>
                      <circle cx="8"  cy="-19" r="2.5" fill="white" opacity="0.70"/>
                      <circle cx="28" cy="-19" r="2.5" fill="white" opacity="0.70"/>
                      <circle cx="18" cy="12"  r="2.5" fill="white" opacity="0.70"/>
                      <line x1="18" y1="-4" x2="8"  y2="-19" stroke="white" stroke-width="1.5" stroke-linecap="round" opacity="0.55"/>
                      <line x1="18" y1="-4" x2="28" y2="-19" stroke="white" stroke-width="1.5" stroke-linecap="round" opacity="0.55"/>
                      <line x1="18" y1="-4" x2="18" y2="12"  stroke="white" stroke-width="1.5" stroke-linecap="round" opacity="0.55"/>
                    </g>
                    <rect x="-5" y="-27" width="10" height="49" rx="5" fill="#0F3A25"/>
                  </g>
                  <line x1="250" y1="40" x2="250" y2="102" stroke="#C8D5CC" stroke-width="0.75"/>
                  <text y="80" font-family="'Outfit', 'Helvetica Neue', Helvetica, Arial, sans-serif">
                    <tspan x="264" font-size="48" font-weight="700" fill="#1A5C38">Biblio</tspan><tspan font-size="48" font-weight="300" fill="#48B07C">Tech</tspan>
                  </text>
                  <text x="266" y="99"
                    font-family="'Outfit', 'Helvetica Neue', Helvetica, Arial, sans-serif"
                    font-size="12" font-weight="300" fill="#9CA3AF" letter-spacing="3.5">SISTEMA ESCOLAR</text>
                </svg>
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
