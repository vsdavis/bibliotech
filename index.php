<?php
/**
 * BiblioTech — Página inicial
 * Redireciona para o dashboard (se logado) ou para a tela de login.
 */

require_once __DIR__ . '/includes/helpers.php';

if (logado()) {
    redirecionar(BASE_URL . '/dashboard.php');
}

redirecionar(BASE_URL . '/login.php');
