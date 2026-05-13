<?php
/**
 * BiblioTech — Edição de Aluno
 *
 * Carrega os dados do aluno pelo ID recebido via GET e exibe o formulário.
 * O processamento é feito em alunos-back.php (action=editar).
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/conexao.php';

requirePermission('alunos.editar');

$pagina_ativa = 'alunos';

// ─── Valida o ID recebido por GET ─────────────────────────────────────────────
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1],
]);

if ($id === false || $id === null) {
    flash('erro', 'ID do aluno inválido.');
    redirecionar(BASE_URL . '/alunos/listar.php');
}

// ─── Busca o aluno no banco ───────────────────────────────────────────────────
try {
    $stmt = $pdo->prepare(
        'SELECT id, nome, matricula, turma, email, telefone, ativo
           FROM alunos
          WHERE id = :id'
    );
    $stmt->execute([':id' => $id]);
    $aluno = $stmt->fetch();

} catch (PDOException $e) {
    error_log('[BiblioTech] alunos/editar busca: ' . $e->getMessage());
    flash('erro', 'Erro ao carregar dados do aluno.');
    redirecionar(BASE_URL . '/alunos/listar.php');
}

if (!$aluno) {
    flash('erro', 'Aluno não encontrado.');
    redirecionar(BASE_URL . '/alunos/listar.php');
}

// Se o back-end redirecionou de volta com erros, usa os dados do POST
// (armazenados em sessão); caso contrário, usa os dados do banco.
$old = $_SESSION['form_aluno'] ?? [];
unset($_SESSION['form_aluno']);

$v = [
    'nome'      => $old['nome']      ?? $aluno['nome'],
    'matricula' => $old['matricula'] ?? $aluno['matricula'],
    'turma'     => $old['turma']     ?? ($aluno['turma']    ?? ''),
    'email'     => $old['email']     ?? ($aluno['email']    ?? ''),
    'telefone'  => $old['telefone']  ?? ($aluno['telefone'] ?? ''),
];

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="pagina-cabecalho">
    <div class="pagina-cabecalho-texto">
        <h1 class="pagina-titulo">Editar Aluno</h1>
        <p class="pagina-subtitulo">Atualize os dados do aluno selecionado.</p>
    </div>
    <a href="<?= e(BASE_URL) ?>/alunos/listar.php" class="btn btn-secundario">
        &larr; Voltar
    </a>
</div>

<?php if ((int) $aluno['ativo'] === 0): ?>
    <div class="flash flash-info">
        <strong>Atenção:</strong>
        este aluno está <strong>inativo</strong> e não estará disponível para novos empréstimos.
    </div>
<?php endif; ?>

<div class="card card-formulario">
    <form method="POST"
          action="<?= e(BASE_URL) ?>/alunos/alunos-back.php"
          novalidate>

        <!-- CSRF -->
        <?= csrf_input() ?>
        <input type="hidden" name="action" value="editar">
        <input type="hidden" name="id"     value="<?= (int) $aluno['id'] ?>">

        <!-- ─── Nome ────────────────────────────────────────────────────── -->
        <div class="form-grupo">
            <label for="nome" class="form-label obrigatorio">Nome</label>
            <input
                type="text"
                id="nome"
                name="nome"
                class="form-campo"
                value="<?= e($v['nome']) ?>"
                maxlength="150"
                required
                autofocus
            >
        </div>

        <!-- ─── Matrícula ───────────────────────────────────────────────── -->
        <div class="form-grupo">
            <label for="matricula" class="form-label obrigatorio">Matrícula</label>
            <input
                type="text"
                id="matricula"
                name="matricula"
                class="form-campo"
                value="<?= e($v['matricula']) ?>"
                maxlength="50"
                required
            >
            <small class="form-dica">A matrícula deve ser única no sistema.</small>
        </div>

        <!-- ─── Turma + Telefone ─────────────────────────────────────────── -->
        <div class="form-linha">
            <div class="form-grupo">
                <label for="turma" class="form-label">Turma</label>
                <input
                    type="text"
                    id="turma"
                    name="turma"
                    class="form-campo"
                    value="<?= e($v['turma']) ?>"
                    maxlength="50"
                >
            </div>

            <div class="form-grupo">
                <label for="telefone" class="form-label">Telefone</label>
                <input
                    type="text"
                    id="telefone"
                    name="telefone"
                    class="form-campo"
                    value="<?= e($v['telefone']) ?>"
                    maxlength="20"
                >
            </div>
        </div>

        <!-- ─── E-mail ──────────────────────────────────────────────────── -->
        <div class="form-grupo">
            <label for="email" class="form-label">E-mail</label>
            <input
                type="email"
                id="email"
                name="email"
                class="form-campo"
                value="<?= e($v['email']) ?>"
                maxlength="150"
            >
            <small class="form-dica">Opcional. Se preenchido, deve ser um endereço válido.</small>
        </div>

        <!-- ─── Ações ───────────────────────────────────────────────────── -->
        <div class="form-acoes">
            <button type="submit" class="btn btn-primario">Salvar Alterações</button>
            <a href="<?= e(BASE_URL) ?>/alunos/listar.php" class="btn btn-secundario">Cancelar</a>
        </div>

    </form>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>