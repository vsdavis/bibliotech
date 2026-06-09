<?php
/**
 * BiblioTech — Confirmação de Inativação de Aluno
 *
 * Exibe uma tela de confirmação antes de inativar o aluno.
 * O processamento efetivo é feito em alunos-back.php (action=inativar).
 *
 * Segurança:
 *   - Autenticação obrigatória via auth.php
 *   - Permissão alunos.inativar verificada
 *   - ID validado como inteiro positivo
 *   - Token CSRF no formulário de confirmação
 *   - Verifica no banco se o aluno existe e está ativo
 *   - Bloqueia inativação se houver empréstimos ativos ou em atraso
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/conexao.php';

requirePermission('alunos.inativar');

$pagina_ativa = 'alunos';

// ─── Valida o ID recebido por GET ─────────────────────────────────────────────
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1],
]);

if ($id === false || $id === null) {
    flash('erro', 'ID do aluno inválido.');
    redirecionar(BASE_URL . '/alunos/listar.php');
}

// ─── Busca o aluno ────────────────────────────────────────────────────────────
try {
    $stmt = $pdo->prepare(
        'SELECT id, nome, matricula, turma, ativo
           FROM alunos
          WHERE id = :id'
    );
    $stmt->execute([':id' => $id]);
    $aluno = $stmt->fetch();

} catch (PDOException $e) {
    error_log('[BiblioTech] alunos/inativar busca: ' . $e->getMessage());
    flash('erro', 'Erro ao carregar dados do aluno.');
    redirecionar(BASE_URL . '/alunos/listar.php');
}

if (!$aluno) {
    flash('erro', 'Aluno não encontrado.');
    redirecionar(BASE_URL . '/alunos/listar.php');
}

if ((int) $aluno['ativo'] === 0) {
    flash('erro', 'Este aluno já está inativo.');
    redirecionar(BASE_URL . '/alunos/listar.php');
}

// ─── Conta empréstimos ativos ou em atraso ────────────────────────────────────
$emprestimos_ativos = 0;
try {
    $stmtEmp = $pdo->prepare(
        "SELECT COUNT(*) AS total
           FROM emprestimos
          WHERE aluno_id = :id
            AND status  IN ('ativo', 'em_atraso')"
    );
    $stmtEmp->execute([':id' => $id]);
    $emprestimos_ativos = (int) $stmtEmp->fetchColumn();

} catch (PDOException $e) {
    // Módulo de empréstimos ainda não criado: assume 0 e segue
    error_log('[BiblioTech] alunos/inativar emp count: ' . $e->getMessage());
    $emprestimos_ativos = 0;
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="pagina-cabecalho">
    <div class="pagina-cabecalho-texto">
        <h1 class="pagina-titulo">Inativar Aluno</h1>
        <p class="pagina-subtitulo">Confirme a inativação do aluno abaixo.</p>
    </div>
    <a href="<?= e(BASE_URL) ?>/alunos/listar.php" class="btn btn-secundario">
        &larr; Voltar
    </a>
</div>

<?php if ($emprestimos_ativos > 0): ?>
    <!-- Aluno com empréstimos ativos: bloqueia a ação -->
    <div class="flash flash-erro">
        <strong>Ação bloqueada.</strong>
        Este aluno possui <strong><?= $emprestimos_ativos ?></strong> empréstimo(s)
        ativo(s) ou em atraso. Não é possível inativá-lo enquanto houver livros por devolver.
        Regularize os empréstimos antes de inativar.
    </div>

    <div class="card card-formulario">
        <div class="form-acoes">
            <a href="<?= e(BASE_URL) ?>/alunos/listar.php" class="btn btn-primario">
                Voltar à Listagem
            </a>
        </div>
    </div>

<?php else: ?>
    <!-- Aluno sem empréstimos ativos: exibe confirmação -->
    <div class="card card-formulario">

        <div class="confirmacao-bloco">
            <div class="confirmacao-icone"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/><path d="M12 9v4"/><path d="M12 17h.01"/></svg></div>
            <p class="confirmacao-texto">
                Você está prestes a <strong>inativar</strong> o seguinte aluno:
            </p>

            <table class="tabela-detalhes">
                <tr>
                    <th>Nome</th>
                    <td><?= e($aluno['nome']) ?></td>
                </tr>
                <tr>
                    <th>Matrícula</th>
                    <td><?= e($aluno['matricula']) ?></td>
                </tr>
                <?php if (!empty($aluno['turma'])): ?>
                <tr>
                    <th>Turma</th>
                    <td><?= e($aluno['turma']) ?></td>
                </tr>
                <?php endif; ?>
            </table>

            <p class="confirmacao-aviso">
                O aluno <strong>não será excluído</strong>; apenas ficará marcado como
                inativo e não estará disponível para novos empréstimos.
            </p>
        </div>

        <form method="POST"
              action="<?= e(BASE_URL) ?>/alunos/alunos-back.php">

            <!-- CSRF -->
            <?= csrf_input() ?>
            <input type="hidden" name="action" value="inativar">
            <input type="hidden" name="id"     value="<?= (int) $aluno['id'] ?>">

            <div class="form-acoes">
                <button type="submit" class="btn btn-perigo">
                    Confirmar Inativação
                </button>
                <a href="<?= e(BASE_URL) ?>/alunos/listar.php" class="btn btn-secundario">
                    Cancelar
                </a>
            </div>
        </form>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>