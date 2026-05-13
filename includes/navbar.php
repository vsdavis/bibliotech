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
?>
<header class="topbar">
    <div class="topbar-conteudo">
        <a href="<?= e(BASE_URL) ?>/dashboard.php" class="topbar-logo">
            <span class="logo-icone">📚</span>
            <span class="logo-texto">BiblioTech</span>
        </a>

        <nav class="topbar-nav" aria-label="Menu principal">
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
                        <a href="<?= e(BASE_URL) ?>/emprestimos/relatorio.php">Relatórios</a>
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
            <span class="usuario-nome">
                <?= e($_SESSION['usuario_nome'] ?? '') ?>
            </span>
            <span class="usuario-perfil">
                <?= e(ucfirst($_SESSION['usuario_perfil'] ?? '')) ?>
            </span>
            <a href="<?= e(BASE_URL) ?>/logout.php" class="btn-logout">Sair</a>
        </div>

        <button class="topbar-toggle" aria-label="Abrir menu">☰</button>
    </div>
</header>

<main class="conteudo">
    <?= exibir_flash() ?>
