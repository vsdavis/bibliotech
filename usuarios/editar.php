<?php
/**
 * BiblioTech — Edição de Usuário
 *
 * Carrega o usuário pelo ID recebido via GET e exibe o formulário.
 * O processamento é feito em usuarios-back.php (action=editar).
 *
 * Senha: deixar em branco para mantê-la inalterada.
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/conexao.php';

requirePermission('usuarios.editar');

$pagina_ativa = 'usuarios';

// ─── Valida o ID recebido por GET ────────────────────────────────────────────
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1],
]);

if ($id === false || $id === null) {
    flash('erro', 'ID do usuário inválido.');
    redirecionar(BASE_URL . '/usuarios/listar.php');
}

// ─── Busca o usuário ─────────────────────────────────────────────────────────
try {
    $stmt = $pdo->prepare(
        'SELECT id, nome, email, perfil, ativo
           FROM usuarios
          WHERE id = :id'
    );
    $stmt->execute([':id' => $id]);
    $usuario = $stmt->fetch();

} catch (PDOException $e) {
    error_log('[BiblioTech] usuarios/editar busca: ' . $e->getMessage());
    flash('erro', 'Erro ao carregar dados do usuário.');
    redirecionar(BASE_URL . '/usuarios/listar.php');
}

if (!$usuario) {
    flash('erro', 'Usuário não encontrado.');
    redirecionar(BASE_URL . '/usuarios/listar.php');
}

if ((int) $usuario['ativo'] === 0) {
    flash('erro', 'Não é possível editar um usuário inativo.');
    redirecionar(BASE_URL . '/usuarios/listar.php');
}

// Dados do POST anterior, em caso de erro de validação (sobrepõe os do banco)
$old = $_SESSION['form_usuario'] ?? [];
unset($_SESSION['form_usuario']);

$v = [
    'nome'   => $old['nome']   ?? $usuario['nome'],
    'email'  => $old['email']  ?? $usuario['email'],
    'perfil' => $old['perfil'] ?? $usuario['perfil'],
];

$eu_mesmo = ((int) $usuario['id'] === (int) $_SESSION['usuario_id']);

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="pagina-cabecalho">
    <div class="pagina-cabecalho-texto">
        <h1 class="pagina-titulo">Editar Usuário</h1>
        <p class="pagina-subtitulo">Atualize os dados do usuário selecionado.</p>
    </div>
    <a href="<?= e(BASE_URL) ?>/usuarios/listar.php" class="btn btn-secundario">
        &larr; Voltar
    </a>
</div>

<?php if ($eu_mesmo): ?>
    <div class="flash flash-info">
        <strong>Atenção:</strong>
        você está editando a sua própria conta.
        Tenha cuidado ao alterar perfil ou senha.
    </div>
<?php endif; ?>

<div class="card card-formulario">
    <form method="POST"
          action="<?= e(BASE_URL) ?>/usuarios/usuarios-back.php"
          autocomplete="off"
          novalidate>

        <?= csrf_input() ?>
        <input type="hidden" name="action" value="editar">
        <input type="hidden" name="id"     value="<?= (int) $usuario['id'] ?>">

        <!-- ─── Nome ────────────────────────────────────────────────────── -->
        <div class="form-grupo">
            <label for="nome" class="form-label obrigatorio">Nome completo</label>
            <input
                type="text"
                id="nome"
                name="nome"
                class="form-campo"
                value="<?= e($v['nome']) ?>"
                maxlength="100"
                required
                autofocus
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
                    value="<?= e($v['email']) ?>"
                    maxlength="100"
                    required
                >
            </div>

            <div class="form-grupo">
                <label for="perfil" class="form-label obrigatorio">Perfil</label>
                <select id="perfil" name="perfil" class="form-campo" required>
                    <option value="bibliotecario" <?= $v['perfil'] === 'bibliotecario' ? 'selected' : '' ?>>
                        Bibliotecário
                    </option>
                    <option value="admin" <?= $v['perfil'] === 'admin' ? 'selected' : '' ?>>
                        Administrador
                    </option>
                </select>
            </div>
        </div>

        <!-- ─── Senha (opcional) ────────────────────────────────────────── -->
        <fieldset class="form-fieldset">
            <legend>Alterar senha (opcional)</legend>
            <p class="form-dica">
                Deixe os campos abaixo em branco para manter a senha atual.
            </p>

            <div class="form-linha">
                <div class="form-grupo">
                    <label for="senha" class="form-label">Nova senha</label>
                    <input
                        type="password"
                        id="senha"
                        name="senha"
                        class="form-campo"
                        minlength="6"
                        autocomplete="new-password"
                        placeholder="Mínimo 6 caracteres"
                    >
                </div>

                <div class="form-grupo">
                    <label for="confirma" class="form-label">Confirmar nova senha</label>
                    <input
                        type="password"
                        id="confirma"
                        name="confirma"
                        class="form-campo"
                        minlength="6"
                        autocomplete="new-password"
                        placeholder="Repita a nova senha"
                    >
                </div>
            </div>
        </fieldset>

        <!-- ─── Ações ───────────────────────────────────────────────────── -->
        <div class="form-acoes">
            <button type="submit" class="btn btn-primario">Salvar Alterações</button>
            <a href="<?= e(BASE_URL) ?>/usuarios/listar.php" class="btn btn-secundario">Cancelar</a>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
