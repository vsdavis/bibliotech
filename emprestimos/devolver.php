<?php
/**
 * BiblioTech — Confirmação de Devolução de Empréstimo
 *
 * Exibe o resumo do empréstimo e um formulário para confirmar a devolução.
 * O processamento efetivo é feito em emprestimos-back.php (action=devolver).
 *
 * Segurança:
 *   - Autenticação obrigatória via auth.php
 *   - Permissão emprestimos.devolver exigida no backend
 *   - ID validado como inteiro positivo (GET)
 *   - Verifica no banco se o empréstimo existe
 *   - Bloqueia a tela se o empréstimo já estiver devolvido
 *   - Token CSRF no formulário de confirmação
 *   - Saída sempre escapada com e()
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/conexao.php';

requirePermission('emprestimos.devolver');

$pagina_ativa = 'emprestimos';

// ─── Valida o ID recebido por GET ─────────────────────────────────────────────
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1],
]);

if ($id === false || $id === null) {
    flash('erro', 'ID do empréstimo inválido.');
    redirecionar(BASE_URL . '/emprestimos/listar.php');
}

// ─── Busca o empréstimo com dados relacionados ────────────────────────────────
try {
    $sql = 'SELECT e.id,
                   e.aluno_id,
                   e.livro_id,
                   e.data_emprestimo,
                   e.data_prevista_devolucao,
                   e.data_devolucao,
                   e.status,
                   e.observacao,
                   a.nome      AS aluno_nome,
                   a.matricula AS aluno_matricula,
                   a.turma     AS aluno_turma,
                   l.titulo    AS livro_titulo,
                   l.autor     AS livro_autor,
                   l.isbn      AS livro_isbn,
                   u.nome      AS registrado_por,
                   CASE
                       WHEN e.data_devolucao IS NOT NULL THEN 0
                       ELSE GREATEST(0, DATEDIFF(CURDATE(), e.data_prevista_devolucao))
                   END AS dias_atraso
              FROM emprestimos e
              INNER JOIN alunos   a ON a.id = e.aluno_id
              INNER JOIN livros   l ON l.id = e.livro_id
              INNER JOIN usuarios u ON u.id = e.usuario_id
             WHERE e.id = :id';

    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id' => $id]);
    $emp = $stmt->fetch();

} catch (PDOException $e) {
    error_log('[BiblioTech] emprestimos/devolver busca: ' . $e->getMessage());
    flash('erro', 'Erro ao carregar dados do empréstimo.');
    redirecionar(BASE_URL . '/emprestimos/listar.php');
}

if (!$emp) {
    flash('erro', 'Empréstimo não encontrado.');
    redirecionar(BASE_URL . '/emprestimos/listar.php');
}

$ja_devolvido = ($emp['status'] === 'devolvido') || !empty($emp['data_devolucao']);

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="pagina-cabecalho">
    <div class="pagina-cabecalho-texto">
        <h1 class="pagina-titulo">Registrar Devolução</h1>
        <p class="pagina-subtitulo">Confirme os dados abaixo antes de registrar a devolução.</p>
    </div>
    <a href="<?= e(BASE_URL) ?>/emprestimos/listar.php" class="btn btn-secundario">
        &larr; Voltar
    </a>
</div>

<?php if ($ja_devolvido): ?>

    <!-- Empréstimo já devolvido: bloqueia a ação -->
    <div class="flash flash-erro">
        <strong>Ação bloqueada.</strong>
        Este empréstimo já foi devolvido em
        <strong><?= e(date('d/m/Y', strtotime((string) $emp['data_devolucao']))) ?></strong>
        e não pode ser devolvido novamente.
    </div>

    <div class="card card-formulario">
        <div class="form-acoes">
            <a href="<?= e(BASE_URL) ?>/emprestimos/listar.php" class="btn btn-primario">
                Voltar à Listagem
            </a>
        </div>
    </div>

<?php else: ?>

    <div class="card card-formulario">

        <div class="confirmacao-bloco">
            <div class="confirmacao-icone"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg></div>
            <p class="confirmacao-texto">
                Você está prestes a registrar a <strong>devolução</strong> do empréstimo abaixo:
            </p>

            <table class="tabela-detalhes">
                <tr>
                    <th>Aluno</th>
                    <td><?= e($emp['aluno_nome']) ?></td>
                </tr>
                <tr>
                    <th>Matrícula</th>
                    <td><?= e($emp['aluno_matricula']) ?></td>
                </tr>
                <?php if (!empty($emp['aluno_turma'])): ?>
                <tr>
                    <th>Turma</th>
                    <td><?= e($emp['aluno_turma']) ?></td>
                </tr>
                <?php endif; ?>
                <tr>
                    <th>Livro</th>
                    <td>
                        <?= e($emp['livro_titulo']) ?>
                        <br><small class="texto-muted">por <?= e($emp['livro_autor']) ?></small>
                    </td>
                </tr>
                <?php if (!empty($emp['livro_isbn'])): ?>
                <tr>
                    <th>ISBN</th>
                    <td><?= e($emp['livro_isbn']) ?></td>
                </tr>
                <?php endif; ?>
                <tr>
                    <th>Data do Empréstimo</th>
                    <td><?= e(date('d/m/Y', strtotime((string) $emp['data_emprestimo']))) ?></td>
                </tr>
                <tr>
                    <th>Prevista para Devolução</th>
                    <td><?= e(date('d/m/Y', strtotime((string) $emp['data_prevista_devolucao']))) ?></td>
                </tr>
                <tr>
                    <th>Status Atual</th>
                    <td>
                        <?php if ((int) $emp['dias_atraso'] > 0): ?>
                            <span class="badge badge-erro">
                                Em atraso (<?= (int) $emp['dias_atraso'] ?> dia(s))
                            </span>
                        <?php else: ?>
                            <span class="badge badge-sucesso">Ativo</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th>Registrado por</th>
                    <td><?= e($emp['registrado_por']) ?></td>
                </tr>
                <?php if (!empty($emp['observacao'])): ?>
                <tr>
                    <th>Observação</th>
                    <td><?= nl2br(e($emp['observacao'])) ?></td>
                </tr>
                <?php endif; ?>
            </table>
        </div>

        <form method="POST"
              action="<?= e(BASE_URL) ?>/emprestimos/emprestimos-back.php"
              novalidate>

            <!-- CSRF -->
            <?= csrf_input() ?>
            <input type="hidden" name="action" value="devolver">
            <input type="hidden" name="id"     value="<?= (int) $emp['id'] ?>">

            <div class="form-grupo">
                <label for="data_devolucao" class="form-label obrigatorio">Data da Devolução</label>
                <input
                    type="date"
                    id="data_devolucao"
                    name="data_devolucao"
                    class="form-campo"
                    value="<?= e(date('Y-m-d')) ?>"
                    min="<?= e((string) $emp['data_emprestimo']) ?>"
                    required
                >
                <small class="form-dica">
                    Não pode ser anterior à data do empréstimo
                    (<?= e(date('d/m/Y', strtotime((string) $emp['data_emprestimo']))) ?>).
                </small>
            </div>

            <p class="confirmacao-aviso">
                Após confirmar, o exemplar será reincorporado ao acervo
                e este empréstimo passará para o status <strong>devolvido</strong>.
            </p>

            <div class="form-acoes">
                <button type="submit" class="btn btn-primario">
                    Confirmar Devolução
                </button>
                <a href="<?= e(BASE_URL) ?>/emprestimos/listar.php" class="btn btn-secundario">
                    Cancelar
                </a>
            </div>
        </form>
    </div>

<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
