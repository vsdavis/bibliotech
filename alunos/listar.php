<?php
/**
 * BiblioTech — Listagem de Alunos
 *
 * Exibe a tabela de alunos com:
 *   - Busca por nome e matrícula
 *   - Filtro por turma e status
 *   - Ações: editar e inativar
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/conexao.php';

requirePermission('alunos.visualizar');

$pagina_ativa = 'alunos';

// ─── Parâmetros de busca/filtro (GET) ────────────────────────────────────────
$busca  = trim((string) ($_GET['busca']  ?? ''));
$status = trim((string) ($_GET['status'] ?? 'ativo')); // ativo | inativo | todos
$turma  = trim((string) ($_GET['turma']  ?? ''));

if (!in_array($status, ['ativo', 'inativo', 'todos'], true)) {
    $status = 'ativo';
}

// ─── Monta cláusula WHERE dinamicamente ──────────────────────────────────────
$where  = [];
$params = [];

if ($status === 'ativo') {
    $where[] = 'a.ativo = 1';
} elseif ($status === 'inativo') {
    $where[] = 'a.ativo = 0';
}

if ($busca !== '') {
    $where[]         = '(a.nome LIKE :busca OR a.matricula LIKE :busca)';
    $params[':busca'] = '%' . $busca . '%';
}

if ($turma !== '') {
    $where[]         = 'a.turma LIKE :turma';
    $params[':turma'] = '%' . $turma . '%';
}

$clausula_where = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// ─── Consulta os alunos ───────────────────────────────────────────────────────
$alunos = [];
try {
    $sql  = "SELECT a.id, a.nome, a.matricula, a.turma, a.email, a.telefone, a.ativo
               FROM alunos a
              $clausula_where
              ORDER BY a.nome ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $alunos = $stmt->fetchAll();

} catch (PDOException $e) {
    error_log('[BiblioTech] alunos/listar: ' . $e->getMessage());
    flash('erro', 'Erro ao carregar lista de alunos.');
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="pagina-cabecalho">
    <div class="pagina-cabecalho-texto">
        <h1 class="pagina-titulo">Alunos</h1>
        <p class="pagina-subtitulo">Gerencie os alunos cadastrados na biblioteca.</p>
    </div>
    <?php if (hasPermission('alunos.cadastrar')): ?>
        <a href="<?= e(BASE_URL) ?>/alunos/cadastrar.php" class="btn btn-primario">
            + Novo Aluno
        </a>
    <?php endif; ?>
</div>

<!-- ─── Filtros ─────────────────────────────────────────────────────────── -->
<div class="card mb-4">
    <form method="GET" action="<?= e(BASE_URL) ?>/alunos/listar.php" class="form-filtros">

        <div class="form-grupo">
            <label for="busca">Buscar</label>
            <input
                type="text"
                id="busca"
                name="busca"
                class="form-campo"
                placeholder="Nome ou matrícula…"
                value="<?= e($busca) ?>"
                maxlength="150"
            >
        </div>

        <div class="form-grupo">
            <label for="turma">Turma</label>
            <input
                type="text"
                id="turma"
                name="turma"
                class="form-campo"
                placeholder="Ex.: 3º A"
                value="<?= e($turma) ?>"
                maxlength="50"
            >
        </div>

        <div class="form-grupo">
            <label for="status">Status</label>
            <select id="status" name="status" class="form-campo">
                <option value="ativo"   <?= $status === 'ativo'   ? 'selected' : '' ?>>Ativos</option>
                <option value="inativo" <?= $status === 'inativo' ? 'selected' : '' ?>>Inativos</option>
                <option value="todos"   <?= $status === 'todos'   ? 'selected' : '' ?>>Todos</option>
            </select>
        </div>

        <div class="form-grupo form-grupo-acoes">
            <button type="submit" class="btn btn-primario">Filtrar</button>
            <a href="<?= e(BASE_URL) ?>/alunos/listar.php" class="btn btn-secundario">Limpar</a>
        </div>

    </form>
</div>

<!-- ─── Tabela ──────────────────────────────────────────────────────────── -->
<div class="card">
    <?php if (empty($alunos)): ?>
        <p class="tabela-vazia">Nenhum aluno encontrado com os filtros aplicados.</p>
    <?php else: ?>
        <div class="tabela-responsiva">
            <table class="tabela">
                <thead>
                    <tr>
                        <th>Nome</th>
                        <th>Matrícula</th>
                        <th>Turma</th>
                        <th>E-mail</th>
                        <th>Telefone</th>
                        <th class="text-centro">Status</th>
                        <th class="text-centro">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($alunos as $aluno): ?>
                        <tr>
                            <td data-label="Nome"><?= e($aluno['nome']) ?></td>
                            <td data-label="Matrícula"><?= e($aluno['matricula']) ?></td>
                            <td data-label="Turma"><?= e($aluno['turma'] ?? '—') ?></td>
                            <td data-label="E-mail"><?= e($aluno['email'] ?? '—') ?></td>
                            <td data-label="Telefone"><?= e($aluno['telefone'] ?? '—') ?></td>
                            <td data-label="Status" class="text-centro">
                                <?php if ((int) $aluno['ativo'] === 1): ?>
                                    <span class="badge badge-sucesso">Ativo</span>
                                <?php else: ?>
                                    <span class="badge badge-erro">Inativo</span>
                                <?php endif; ?>
                            </td>
                            <td data-label="Ações" class="text-centro acoes-tabela">
                                <?php if ((int) $aluno['ativo'] === 1): ?>
                                    <?php if (hasPermission('alunos.editar')): ?>
                                        <a href="<?= e(BASE_URL) ?>/alunos/editar.php?id=<?= (int) $aluno['id'] ?>"
                                           class="btn btn-sm btn-secundario"
                                           title="Editar aluno">
                                            Editar
                                        </a>
                                    <?php endif; ?>
                                    <?php if (hasPermission('alunos.inativar')): ?>
                                        <a href="<?= e(BASE_URL) ?>/alunos/inativar.php?id=<?= (int) $aluno['id'] ?>"
                                           class="btn btn-sm btn-perigo"
                                           title="Inativar aluno">
                                            Inativar
                                        </a>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="texto-muted">—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <p class="tabela-rodape"><?= count($alunos) ?> aluno(s) encontrado(s).</p>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>