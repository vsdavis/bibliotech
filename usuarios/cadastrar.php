<?php
/**
 * BiblioTech — Cadastro de Usuário
 *
 * Exibe o formulário de novo usuário.
 * O processamento é feito em usuarios-back.php (action=cadastrar).
 */

require_once __DIR__ . '/../includes/auth.php';

requirePermission('usuarios.cadastrar');

$pagina_ativa = 'usuarios';

// Recupera dados do POST anterior em caso de erro de validação
$old = $_SESSION['form_usuario'] ?? [];
unset($_SESSION['form_usuario']);

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="pagina-cabecalho">
    <div class="pagina-cabecalho-texto">
        <h1 class="pagina-titulo">Cadastrar Usuário</h1>
        <p class="pagina-subtitulo">
            Preencha os dados para criar um novo usuário do sistema.
        </p>
    </div>
    <a href="<?= e(BASE_URL) ?>/usuarios/listar.php" class="btn btn-secundario">
        &larr; Voltar
    </a>
</div>

<div class="card card-formulario">
    <form method="POST"
          action="<?= e(BASE_URL) ?>/usuarios/usuarios-back.php"
          autocomplete="off"
          novalidate>

        <?= csrf_input() ?>
        <input type="hidden" name="action" value="cadastrar">

        <!-- ─── Nome ────────────────────────────────────────────────────── -->
        <div class="form-grupo">
            <label for="nome" class="form-label obrigatorio">Nome completo</label>
            <input
                type="text"
                id="nome"
                name="nome"
                class="form-campo"
                value="<?= e($old['nome'] ?? '') ?>"
                maxlength="100"
                required
                autofocus
                placeholder="Ex.: Maria da Silva"
            >
        </div>

        <!-- ─── E-mail + Perfil ─────────────────────────────────────────── -->
        <div class="form-linha">
            <div class="form-grupo">
                <label for="email" class="form-label obrigatorio">E-mail</label>
                <input
                    type="email"
                    id="email"
                    name="email"
                    class="form-campo"
                    value="<?= e($old['email'] ?? '') ?>"
                    maxlength="100"
                    required
                    placeholder="usuario@exemplo.com"
                >
            </div>

            <div class="form-grupo">
                <label for="perfil" class="form-label obrigatorio">Perfil</label>
                <select id="perfil" name="perfil" class="form-campo" required>
                    <?php $sel = $old['perfil'] ?? 'bibliotecario'; ?>
                    <option value="bibliotecario" <?= $sel === 'bibliotecario' ? 'selected' : '' ?>>
                        Bibliotecário
                    </option>
                    <option value="admin" <?= $sel === 'admin' ? 'selected' : '' ?>>
                        Administrador
                    </option>
                </select>
                <small class="form-dica">
                    Administradores têm acesso total ao sistema.
                    Bibliotecários começam apenas com acesso ao Dashboard.
                </small>
            </div>
        </div>

        <!-- ─── Senha + Confirmação ─────────────────────────────────────── -->
        <div class="form-linha">
            <div class="form-grupo">
                <label for="senha" class="form-label obrigatorio">Senha</label>
                <input
                    type="password"
                    id="senha"
                    name="senha"
                    class="form-campo"
                    minlength="6"
                    required
                    autocomplete="new-password"
                    placeholder="Mínimo 6 caracteres"
                >
            </div>

            <div class="form-grupo">
                <label for="confirma" class="form-label obrigatorio">Confirmar senha</label>
                <input
                    type="password"
                    id="confirma"
                    name="confirma"
                    class="form-campo"
                    minlength="6"
                    required
                    autocomplete="new-password"
                    placeholder="Repita a senha"
                >
            </div>
        </div>

        <!-- ─── Ações ───────────────────────────────────────────────────── -->
        <div class="form-acoes">
            <button type="submit" class="btn btn-primario">Cadastrar Usuário</button>
            <a href="<?= e(BASE_URL) ?>/usuarios/listar.php" class="btn btn-secundario">Cancelar</a>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
