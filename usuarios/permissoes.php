<?php
/**
 * BiblioTech — Gerenciamento de Permissões do Usuário
 *
 * Tela com checkboxes agrupados por módulo, permitindo ao administrador
 * conceder/revogar permissões individuais de qualquer usuário.
 *
 * Salvaguardas:
 *   - Validação do ID via FILTER_VALIDATE_INT
 *   - Carga das permissões agrupadas por "grupo" da tabela permissoes
 *   - Estado inicial dos checkboxes vem de usuario_permissoes
 *   - O backend impede que o admin remova de si "usuarios.permissoes"
 *   - Token CSRF no formulário
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/conexao.php';

requirePermission('usuarios.permissoes');

$pagina_ativa = 'usuarios';

// ─── Valida ID ───────────────────────────────────────────────────────────────
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1],
]);

if ($id === false || $id === null) {
    flash('erro', 'ID do usuário inválido.');
    redirecionar(BASE_URL . '/usuarios/listar.php');
}

// ─── Busca o usuário-alvo ────────────────────────────────────────────────────
try {
    $stmt = $pdo->prepare(
        'SELECT id, nome, email, perfil, ativo
           FROM usuarios
          WHERE id = :id'
    );
    $stmt->execute([':id' => $id]);
    $usuario = $stmt->fetch();
} catch (PDOException $e) {
    error_log('[BiblioTech] usuarios/permissoes busca: ' . $e->getMessage());
    flash('erro', 'Erro ao carregar dados do usuário.');
    redirecionar(BASE_URL . '/usuarios/listar.php');
}

if (!$usuario) {
    flash('erro', 'Usuário não encontrado.');
    redirecionar(BASE_URL . '/usuarios/listar.php');
}

if ((int) $usuario['ativo'] === 0) {
    flash('erro', 'Não é possível editar permissões de um usuário inativo.');
    redirecionar(BASE_URL . '/usuarios/listar.php');
}

// ─── Carrega todas as permissões ─────────────────────────────────────────────
$permissoes_por_grupo = [];
try {
    $stmt = $pdo->query(
        'SELECT id, codigo, nome, descricao, grupo
           FROM permissoes
          WHERE ativo = 1
          ORDER BY grupo ASC, id ASC'
    );
    while ($p = $stmt->fetch()) {
        $permissoes_por_grupo[$p['grupo']][] = $p;
    }
} catch (PDOException $e) {
    error_log('[BiblioTech] usuarios/permissoes lista: ' . $e->getMessage());
    flash('erro', 'Erro ao carregar lista de permissões.');
    redirecionar(BASE_URL . '/usuarios/listar.php');
}

// ─── Carrega permissões já concedidas ao usuário ─────────────────────────────
$concedidas = [];
try {
    $stmt = $pdo->prepare(
        'SELECT permissao_id
           FROM usuario_permissoes
          WHERE usuario_id = :uid
            AND permitido  = 1'
    );
    $stmt->execute([':uid' => $id]);
    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $pid) {
        $concedidas[(int) $pid] = true;
    }
} catch (PDOException $e) {
    error_log('[BiblioTech] usuarios/permissoes carregadas: ' . $e->getMessage());
}

$eu_mesmo = ((int) $usuario['id'] === (int) $_SESSION['usuario_id']);

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="pagina-cabecalho">
    <div class="pagina-cabecalho-texto">
        <h1 class="pagina-titulo">Permissões do Usuário</h1>
        <p class="pagina-subtitulo">
            Marque as permissões que este usuário deve possuir.
        </p>
    </div>
    <a href="<?= e(BASE_URL) ?>/usuarios/listar.php" class="btn btn-secundario">
        &larr; Voltar
    </a>
</div>

<!-- ─── Cabeçalho com dados do usuário ─────────────────────────────────── -->
<div class="card mb-4">
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
                <?php if ($usuario['perfil'] === 'admin'): ?>
                    <span class="badge badge-info">Administrador</span>
                <?php else: ?>
                    <span class="badge">Bibliotecário</span>
                <?php endif; ?>
            </td>
        </tr>
    </table>

    <?php if ($usuario['perfil'] === 'admin'): ?>
        <div class="flash flash-info" style="margin-top:1rem;">
            <strong>Aviso:</strong>
            usuários com perfil <em>Administrador</em> têm acesso total por padrão,
            independentemente das permissões marcadas abaixo. As permissões aqui são
            mantidas apenas para controle e visualização.
        </div>
    <?php endif; ?>

    <?php if ($eu_mesmo): ?>
        <div class="flash flash-info" style="margin-top:1rem;">
            <strong>Atenção:</strong>
            você está editando suas <em>próprias</em> permissões. A permissão
            <strong>"Gerenciar permissões"</strong> não pode ser removida para evitar
            que você perca acesso a este painel.
        </div>
    <?php endif; ?>
</div>

<!-- ─── Formulário de permissões ──────────────────────────────────────── -->
<form method="POST"
      action="<?= e(BASE_URL) ?>/usuarios/usuarios-back.php"
      id="form-permissoes">

    <?= csrf_input() ?>
    <input type="hidden" name="action" value="permissoes">
    <input type="hidden" name="id"     value="<?= (int) $usuario['id'] ?>">

    <!-- Botões de seleção em massa -->
    <div class="card mb-4 acoes-permissoes">
        <button type="button" class="btn btn-sm btn-secundario" id="btn-marcar-todas">
            Marcar todas
        </button>
        <button type="button" class="btn btn-sm btn-secundario" id="btn-desmarcar-todas">
            Desmarcar todas
        </button>
    </div>

    <!-- Cartões por grupo -->
    <?php foreach ($permissoes_por_grupo as $grupo => $perms): ?>
        <div class="card mb-4 grupo-permissoes">
            <h3 class="grupo-permissoes-titulo">
                <?= e($grupo) ?>
            </h3>

            <div class="permissoes-grade">
                <?php foreach ($perms as $p):
                    $pid     = (int) $p['id'];
                    $marcado = isset($concedidas[$pid]);
                    $cid     = 'perm_' . $pid;

                    // Bloqueia desmarcar usuarios.permissoes do próprio admin
                    $bloquear_remocao = ($eu_mesmo
                        && $p['codigo'] === 'usuarios.permissoes');
                ?>
                    <label class="permissao-item" for="<?= e($cid) ?>">
                        <input
                            type="checkbox"
                            id="<?= e($cid) ?>"
                            name="permissoes[]"
                            value="<?= $pid ?>"
                            <?= $marcado          ? 'checked'  : '' ?>
                            <?= $bloquear_remocao ? 'checked disabled' : '' ?>
                        >
                        <?php if ($bloquear_remocao): ?>
                            <!-- Como o checkbox 'disabled' não envia valor, garantimos a permissão via hidden -->
                            <input type="hidden" name="permissoes[]" value="<?= $pid ?>">
                        <?php endif; ?>

                        <div class="permissao-texto">
                            <strong><?= e($p['nome']) ?></strong>
                            <code class="permissao-codigo"><?= e($p['codigo']) ?></code>
                            <?php if (!empty($p['descricao'])): ?>
                                <small><?= e($p['descricao']) ?></small>
                            <?php endif; ?>
                        </div>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endforeach; ?>

    <!-- Ações -->
    <div class="card card-formulario">
        <div class="form-acoes">
            <button type="submit" class="btn btn-primario">Salvar Permissões</button>
            <a href="<?= e(BASE_URL) ?>/usuarios/listar.php" class="btn btn-secundario">Cancelar</a>
        </div>
    </div>
</form>

<!-- Estilos específicos da grade de permissões -->
<style>
    .acoes-permissoes        { display:flex; gap:.5rem; }
    .grupo-permissoes-titulo { margin:0 0 1rem 0; padding-bottom:.5rem;
                               border-bottom:1px solid #e2e8f0; }
    .permissoes-grade        { display:grid;
                               grid-template-columns:repeat(auto-fill, minmax(280px, 1fr));
                               gap:.75rem; }
    .permissao-item          { display:flex; align-items:flex-start; gap:.6rem;
                               padding:.6rem; border:1px solid #e2e8f0;
                               border-radius:.4rem; cursor:pointer;
                               transition:background .15s; }
    .permissao-item:hover    { background:#f8fafc; }
    .permissao-item input[type=checkbox] { margin-top:.2rem; }
    .permissao-texto         { display:flex; flex-direction:column; gap:.2rem;
                               flex:1; }
    .permissao-texto strong  { color:#1e293b; }
    .permissao-codigo        { font-family:monospace; font-size:.78rem;
                               color:#64748b; background:#f1f5f9;
                               padding:.05rem .35rem; border-radius:.25rem;
                               width:fit-content; }
    .permissao-texto small   { color:#64748b; line-height:1.4; }
</style>

<!-- JS para "marcar/desmarcar todas" -->
<script>
(function () {
    const form          = document.getElementById('form-permissoes');
    const btnMarcar     = document.getElementById('btn-marcar-todas');
    const btnDesmarcar  = document.getElementById('btn-desmarcar-todas');

    function setAll(checked) {
        form.querySelectorAll('input[type="checkbox"]:not([disabled])')
            .forEach(cb => { cb.checked = checked; });
    }

    btnMarcar    && btnMarcar.addEventListener('click',    () => setAll(true));
    btnDesmarcar && btnDesmarcar.addEventListener('click', () => setAll(false));
})();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
