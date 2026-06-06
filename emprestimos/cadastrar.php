<?php
/**
 * BiblioTech — Cadastro de Empréstimo
 *
 * Exibe o formulário para registrar um novo empréstimo.
 * O processamento é feito em emprestimos-back.php (action=cadastrar).
 *
 * Segurança:
 *   - Autenticação obrigatória via auth.php
 *   - Permissão emprestimos.cadastrar exigida no backend
 *   - Selects populados apenas com alunos ativos e livros ativos com estoque > 0
 *   - Token CSRF gerado no formulário
 *   - Saída sempre escapada com e()
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/conexao.php';

requirePermission('emprestimos.cadastrar');

$pagina_ativa = 'emprestimos';

// ─── Recupera dados preservados após erro de validação ───────────────────────
$old = $_SESSION['form_emprestimo'] ?? [];
unset($_SESSION['form_emprestimo']);

// ─── Carrega alunos ativos ───────────────────────────────────────────────────
$alunos = [];
try {
    $stmt = $pdo->query(
        'SELECT id, nome, matricula, turma
           FROM alunos
          WHERE ativo = 1
          ORDER BY nome ASC'
    );
    $alunos = $stmt->fetchAll();

} catch (PDOException $e) {
    error_log('[BiblioTech] emprestimos/cadastrar alunos: ' . $e->getMessage());
    flash('erro', 'Erro ao carregar a lista de alunos.');
}

// ─── Carrega livros ativos COM exemplares disponíveis ────────────────────────
$livros = [];
try {
    $stmt = $pdo->query(
        'SELECT id, titulo, autor, quantidade_disponivel
           FROM livros
          WHERE ativo = 1
            AND quantidade_disponivel > 0
          ORDER BY titulo ASC'
    );
    $livros = $stmt->fetchAll();

} catch (PDOException $e) {
    error_log('[BiblioTech] emprestimos/cadastrar livros: ' . $e->getMessage());
    flash('erro', 'Erro ao carregar a lista de livros.');
}

// ─── Defaults para datas ─────────────────────────────────────────────────────
$hoje = date('Y-m-d');
// Data prevista padrão: 7 dias à frente
$prev_padrao = date('Y-m-d', strtotime('+7 days'));

$data_emprestimo_val = $old['data_emprestimo']         ?? $hoje;
$data_prev_val       = $old['data_prevista_devolucao'] ?? $prev_padrao;
$aluno_sel           = (int) ($old['aluno_id']   ?? 0);
$livro_sel           = (int) ($old['livro_id']   ?? 0);
$observacao_val      = (string) ($old['observacao'] ?? '');

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="pagina-cabecalho">
    <div class="pagina-cabecalho-texto">
        <h1 class="pagina-titulo">Registrar Empréstimo</h1>
        <p class="pagina-subtitulo">Selecione o aluno, o livro e o prazo de devolução.</p>
    </div>
    <a href="<?= e(BASE_URL) ?>/emprestimos/listar.php" class="btn btn-secundario">
        &larr; Voltar
    </a>
</div>

<?php if (empty($alunos)): ?>
    <div class="flash flash-erro">
        Não há alunos ativos cadastrados. Cadastre alunos antes de registrar empréstimos.
    </div>
<?php endif; ?>

<?php if (empty($livros)): ?>
    <div class="flash flash-erro">
        Não há livros ativos com exemplares disponíveis para empréstimo no momento.
    </div>
<?php endif; ?>

<div class="card card-formulario">
    <form method="POST"
          action="<?= e(BASE_URL) ?>/emprestimos/emprestimos-back.php"
          novalidate>

        <!-- CSRF -->
        <?= csrf_input() ?>
        <input type="hidden" name="action" value="cadastrar">

        <!-- ─── Aluno ───────────────────────────────────────────────────── -->
        <div class="form-grupo">
            <label for="aluno_id" class="form-label obrigatorio">Aluno</label>
            <select id="aluno_id"
                    name="aluno_id"
                    class="form-campo"
                    data-busca
                    data-busca-placeholder="Digite o nome ou a matrícula do aluno…"
                    required
                    <?= empty($alunos) ? 'disabled' : '' ?>>
                <option value="">— Selecione um aluno —</option>
                <?php foreach ($alunos as $a): ?>
                    <option value="<?= (int) $a['id'] ?>"
                        <?= $aluno_sel === (int) $a['id'] ? 'selected' : '' ?>>
                        <?= e($a['nome']) ?> · Matrícula <?= e($a['matricula']) ?>
                        <?= !empty($a['turma']) ? ' · Turma ' . e($a['turma']) : '' ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- ─── Livro ───────────────────────────────────────────────────── -->
        <div class="form-grupo">
            <label for="livro_id" class="form-label obrigatorio">Livro</label>
            <select id="livro_id"
                    name="livro_id"
                    class="form-campo"
                    data-busca
                    data-busca-placeholder="Digite o título ou o autor do livro…"
                    required
                    <?= empty($livros) ? 'disabled' : '' ?>>
                <option value="">— Selecione um livro —</option>
                <?php foreach ($livros as $l): ?>
                    <option value="<?= (int) $l['id'] ?>"
                        <?= $livro_sel === (int) $l['id'] ? 'selected' : '' ?>>
                        <?= e($l['titulo']) ?> — <?= e($l['autor']) ?>
                        (<?= (int) $l['quantidade_disponivel'] ?> disp.)
                    </option>
                <?php endforeach; ?>
            </select>
            <small class="form-dica">
                Somente livros ativos com exemplares disponíveis são listados.
            </small>
        </div>

        <!-- ─── Datas ───────────────────────────────────────────────────── -->
        <div class="form-linha">
            <div class="form-grupo">
                <label for="data_emprestimo" class="form-label obrigatorio">Data do Empréstimo</label>
                <input
                    type="date"
                    id="data_emprestimo"
                    name="data_emprestimo"
                    class="form-campo"
                    value="<?= e($data_emprestimo_val) ?>"
                    required
                >
            </div>

            <div class="form-grupo">
                <label for="data_prevista_devolucao" class="form-label obrigatorio">Prevista para Devolução</label>
                <input
                    type="date"
                    id="data_prevista_devolucao"
                    name="data_prevista_devolucao"
                    class="form-campo"
                    value="<?= e($data_prev_val) ?>"
                    min="<?= e($data_emprestimo_val) ?>"
                    required
                >
                <small class="form-dica">
                    Deve ser igual ou posterior à data do empréstimo.
                </small>
            </div>
        </div>

        <!-- ─── Observação ──────────────────────────────────────────────── -->
        <div class="form-grupo">
            <label for="observacao" class="form-label">Observação</label>
            <textarea
                id="observacao"
                name="observacao"
                class="form-campo"
                rows="3"
                maxlength="1000"
                placeholder="Opcional. Ex.: livro de uso restrito, retirada por terceiro, etc."
            ><?= e($observacao_val) ?></textarea>
        </div>

        <!-- ─── Ações ───────────────────────────────────────────────────── -->
        <div class="form-acoes">
            <button type="submit"
                    class="btn btn-primario"
                    <?= (empty($alunos) || empty($livros)) ? 'disabled' : '' ?>>
                Registrar Empréstimo
            </button>
            <a href="<?= e(BASE_URL) ?>/emprestimos/listar.php" class="btn btn-secundario">
                Cancelar
            </a>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
