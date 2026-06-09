<?php
/**
 * BiblioTech — Confirmação de Inativação de Usuário
 *
 * Exibe a tela de confirmação antes de inativar.
 * O processamento efetivo é feito em usuarios-back.php (action=inativar).
 *
 * Salvaguardas exibidas:
 *   - Não permite inativar a si mesmo
 *   - Não permite inativar o último administrador ativo
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/conexao.php';

requirePermission('usuarios.inativar');

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
    error_log('[BiblioTech] usuarios/inativar busca: ' . $e->getMessage());
    flash('erro', 'Erro ao carregar dados do usuário.');
    redirecionar(BASE_URL . '/usuarios/listar.php');
}

if (!$usuario) {
    flash('erro', 'Usuário não encontrado.');
    redirecionar(BASE_URL . '/usuarios/listar.php');
}

if ((int) $usuario['ativo'] === 0) {
    flash('erro', 'Este usuário já está inativo.');
    redirecionar(BASE_URL . '/usuarios/listar.php');
}

// ─── Salvaguardas ────────────────────────────────────────────────────────────
$eu_mesmo  = ((int) $usuario['id'] === (int) $_SESSION['usuario_id']);
$bloqueado = false;
$motivo    = '';

if ($eu_mesmo) {
    $bloqueado = true;
    $motivo    = 'Você não pode inativar a sua própria conta. '
               . 'Peça a outro administrador para realizar esta operação.';
}

if (!$bloqueado && $usuario['perfil'] === 'admin') {
    try {
        $stmt = $pdo->prepare(
            "SELECT COUNT(*) FROM usuarios
              WHERE perfil = 'admin' AND ativo = 1 AND id <> :id"
        );
        $stmt->execute([':id' => $id]);
        $outros_admins = (int) $stmt->fetchColumn();

        if ($outros_admins === 0) {
            $bloqueado = true;
            $motivo    = 'Este é o único administrador ativo do sistema. '
                       . 'É necessário cadastrar/promover outro administrador antes '
                       . 'de inativar este.';
        }
    } catch (PDOException $e) {
        error_log('[BiblioTech] usuarios/inativar admin count: ' . $e->getMessage());
        $bloqueado = true;
        $motivo    = 'Erro ao validar a operação. Tente novamente.';
    }
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="pagina-cabecalho">
    <div class="pagina-cabecalho-texto">
        <h1 class="pagina-titulo">Inativar Usuário</h1>
        <p class="pagina-subtitulo">Confirme a inativação do usuário abaixo.</p>
    </div>
    <a href="<?= e(BASE_URL) ?>/usuarios/listar.php" class="btn btn-secundario">
        &larr; Voltar
    </a>
</div>

<?php if ($bloqueado): ?>
    <div class="flash flash-erro">
        <strong>Ação bloqueada.</strong> <?= e($motivo) ?>
    </div>

    <div class="card card-formulario">
        <div class="form-acoes">
            <a href="<?= e(BASE_URL) ?>/usuarios/listar.php" class="btn btn-primario">
                Voltar à Listagem
            </a>
        </div>
    </div>

<?php else: ?>

    <div class="card card-formulario">
        <div class="confirmacao-bloco">
            <div class="confirmacao-icone"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/><path d="M12 9v4"/><path d="M12 17h.01"/></svg></div>
            <p class="confirmacao-texto">
                Você está prestes a <strong>inativar</strong> o usuário abaixo:
            </p>

            <table class="tabela-detalhes">
                <tr>
                    <th>Nome</th>
                    <td><?= e($usuario['nome']) ?></td>
                </tr>
                <tr>
                    <th>E-mail</th>
                    <td><?= e($usuario['email']) ?></td>
                </tr>
                <tr>
                    <th>Perfil</th>
                    <td>
                        <?= e($usuario['perfil'] === 'admin'
                              ? 'Administrador'
                              : 'Bibliotecário') ?>
                    </td>
                </tr>
            </table>

            <p class="confirmacao-aviso">
                O usuário <strong>não será excluído</strong>; apenas ficará marcado como
                inativo e não conseguirá mais fazer login. As permissões individuais
                serão preservadas, podendo ser reativadas no futuro por um administrador.
            </p>
        </div>

        <form method="POST"
              action="<?= e(BASE_URL) ?>/usuarios/usuarios-back.php">

            <?= csrf_input() ?>
            <input type="hidden" name="action" value="inativar">
            <input type="hidden" name="id"     value="<?= (int) $usuario['id'] ?>">

            <div class="form-acoes">
                <button type="submit" class="btn btn-perigo">
                    Confirmar Inativação
                </button>
                <a href="<?= e(BASE_URL) ?>/usuarios/listar.php" class="btn btn-secundario">
                    Cancelar
                </a>
            </div>
        </form>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
