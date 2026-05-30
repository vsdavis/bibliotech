<?php
require_once __DIR__ . '/helpers.php';

// Não exibe navbar se o usuário não estiver logado
if (!logado()) {
    return;
}

// Variável definida pela página atual (ex.: $pagina_ativa = 'livros')
$pagina_ativa = $pagina_ativa ?? '';

function nav_active(string $nome, string $atual): string
{
    return $nome === $atual ? ' class="active"' : '';
}

// Iniciais do usuário para o avatar (apenas visual)
$nome_usuario = trim($_SESSION['usuario_nome'] ?? '');
$iniciais     = '?';
if ($nome_usuario !== '') {
    $partes    = preg_split('/\s+/', $nome_usuario);
    $iniciais  = mb_strtoupper(mb_substr($partes[0], 0, 1, 'UTF-8'), 'UTF-8');
    if (count($partes) > 1) {
        $iniciais .= mb_strtoupper(mb_substr(end($partes), 0, 1, 'UTF-8'), 'UTF-8');
    }
}
?>
<header class="topbar">
    <div class="topbar-conteudo">
        <a href="<?= e(BASE_URL) ?>/dashboard.php" class="topbar-logo">
            <img src="<?= e(BASE_URL) ?>/assets/img/logo-icon.svg"
                 alt=""
                 class="logo-img"
                 width="46" height="36"
                 aria-hidden="true">
            <span class="logo-texto">BiblioTech</span>
        </a>

        <nav class="topbar-nav" id="topbar-nav" aria-label="Menu principal">
            <ul>
                <?php if (hasPermission('dashboard.visualizar')): ?>
                    <li<?= nav_active('dashboard', $pagina_ativa) ?>>
                        <a href="<?= e(BASE_URL) ?>/dashboard.php">Dashboard</a>
                    </li>
                <?php endif; ?>

                <?php if (hasPermission('livros.visualizar')): ?>
                    <li<?= nav_active('livros', $pagina_ativa) ?>>
                        <a href="<?= e(BASE_URL) ?>/livros/listar.php">Livros</a>
                    </li>
                <?php endif; ?>

                <?php if (hasPermission('alunos.visualizar')): ?>
                    <li<?= nav_active('alunos', $pagina_ativa) ?>>
                        <a href="<?= e(BASE_URL) ?>/alunos/listar.php">Alunos</a>
                    </li>
                <?php endif; ?>

                <?php if (hasPermission('emprestimos.visualizar')): ?>
                    <li<?= nav_active('emprestimos', $pagina_ativa) ?>>
                        <a href="<?= e(BASE_URL) ?>/emprestimos/listar.php">Empréstimos</a>
                    </li>
                <?php endif; ?>

                <?php if (hasPermission('relatorios.visualizar')): ?>
                    <li<?= nav_active('relatorios', $pagina_ativa) ?>>
                        <a href="<?= e(BASE_URL) ?>/relatorios/index.php">Relatórios</a>
                    </li>
                <?php endif; ?>

                <?php if (hasPermission('usuarios.visualizar')): ?>
                    <li<?= nav_active('usuarios', $pagina_ativa) ?>>
                        <a href="<?= e(BASE_URL) ?>/usuarios/listar.php">Usuários</a>
                    </li>
                <?php endif; ?>
            </ul>
        </nav>

        <div class="topbar-usuario">
            <span class="usuario-avatar" aria-hidden="true"><?= e($iniciais) ?></span>
            <div class="usuario-info">
                <span class="usuario-nome">
                    <?= e($_SESSION['usuario_nome'] ?? '') ?>
                </span>
                <span class="usuario-perfil">
                    <?= e(ucfirst($_SESSION['usuario_perfil'] ?? '')) ?>
                </span>
            </div>
            <a href="<?= e(BASE_URL) ?>/logout.php" class="btn-logout" title="Encerrar sessão">
                <span aria-hidden="true">⎋</span>
                <span class="btn-logout-texto">Sair</span>
            </a>
        </div>

        <button
            class="topbar-toggle"
            type="button"
            aria-label="Abrir menu"
            aria-controls="topbar-nav"
            aria-expanded="false">
            <span aria-hidden="true">☰</span>
        </button>
    </div>
</header>

<main class="conteudo">
    <?= exibir_flash() ?>
