<?php
/**
 * BiblioTech — Listagem de Empréstimos
 *
 * Exibe a tabela de empréstimos com:
 *   - Filtros: status (todos/ativo/devolvido/em_atraso),
 *              aluno (nome ou matrícula),
 *              livro (título/autor/ISBN),
 *              período de data de empréstimo (de/até).
 *   - Atualização automática (no backend) de empréstimos vencidos
 *     para o status 'em_atraso' antes da listagem — sem depender
 *     do event_scheduler do MySQL.
 *   - Cálculo de dias de atraso para empréstimos não devolvidos.
 *   - Ação "Devolver" apenas para empréstimos ainda não devolvidos
 *     e somente para usuários com permissão emprestimos.devolver.
 *
 * Segurança:
 *   - Autenticação obrigatória via auth.php
 *   - Permissão emprestimos.visualizar exigida no backend
 *   - Todos os parâmetros sanitizados antes do uso
 *   - Todas as queries usam PDO + prepared statements (named placeholders)
 *   - Saída sempre escapada com e()
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/conexao.php';

requirePermission('emprestimos.visualizar');

$pagina_ativa = 'emprestimos';

// ─── Atualização automática de empréstimos em atraso ─────────────────────────
// Marca como 'em_atraso' todos os empréstimos ainda ativos cuja
// data prevista de devolução já passou. Operação idempotente,
// não requer transação (uma única tabela, único statement).
try {
    $pdo->exec(
        "UPDATE emprestimos
            SET status = 'em_atraso'
          WHERE status = 'ativo'
            AND data_prevista_devolucao < CURDATE()"
    );
} catch (PDOException $e) {
    error_log('[BiblioTech] emprestimos/listar atualiza atrasos: ' . $e->getMessage());
    // Continua a listagem mesmo se a atualização falhar
}

// ─── Parâmetros de filtro (GET) ──────────────────────────────────────────────
$status_validos = ['todos', 'ativo', 'devolvido', 'em_atraso'];
$status         = trim((string) ($_GET['status'] ?? 'todos'));
if (!in_array($status, $status_validos, true)) {
    $status = 'todos';
}

$busca_aluno = trim((string) ($_GET['busca_aluno'] ?? ''));
$busca_livro = trim((string) ($_GET['busca_livro'] ?? ''));
$data_de     = trim((string) ($_GET['data_de']     ?? ''));
$data_ate    = trim((string) ($_GET['data_ate']    ?? ''));

// Valida formato YYYY-MM-DD; descarta silenciosamente se inválido
$valida_data = static function (string $d): ?string {
    if ($d === '') return null;
    $dt = DateTime::createFromFormat('Y-m-d', $d);
    if ($dt === false || $dt->format('Y-m-d') !== $d) return null;
    return $d;
};
$data_de_val  = $valida_data($data_de);
$data_ate_val = $valida_data($data_ate);

// ─── Paginação ───────────────────────────────────────────────────────────────
$por_pagina = 20;
$pagina_num = filter_input(INPUT_GET, 'pagina', FILTER_VALIDATE_INT, [
    'options' => ['default' => 1, 'min_range' => 1],
]);
$offset = ($pagina_num - 1) * $por_pagina;

// ─── Monta cláusula WHERE dinamicamente ──────────────────────────────────────
$where  = [];
$params = [];

if ($status !== 'todos') {
    $where[]            = 'e.status = :status';
    $params[':status']  = $status;
}

if ($busca_aluno !== '') {
    $b = montarBuscaPalavras($busca_aluno, ['a.nome', 'a.matricula'], 'baluno');
    $where  = array_merge($where,  $b['where']);
    $params = array_merge($params, $b['params']);
}

if ($busca_livro !== '') {
    $b = montarBuscaPalavras($busca_livro, ['l.titulo', 'l.autor', 'l.isbn'], 'blivro');
    $where  = array_merge($where,  $b['where']);
    $params = array_merge($params, $b['params']);
}

if ($data_de_val !== null) {
    $where[]              = 'e.data_emprestimo >= :datade';
    $params[':datade']    = $data_de_val;
}

if ($data_ate_val !== null) {
    $where[]              = 'e.data_emprestimo <= :dataate';
    $params[':dataate']   = $data_ate_val;
}

$clausula_where = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// ─── Contagem total para paginação ───────────────────────────────────────────
$total_registros = 0;
try {
    $sqlCount = "SELECT COUNT(*)
                   FROM emprestimos e
                   INNER JOIN alunos a ON a.id = e.aluno_id
                   INNER JOIN livros l ON l.id = e.livro_id
                 $clausula_where";

    $stmtCount = $pdo->prepare($sqlCount);
    $stmtCount->execute($params);
    $total_registros = (int) $stmtCount->fetchColumn();

} catch (PDOException $e) {
    error_log('[BiblioTech] emprestimos/listar count: ' . $e->getMessage());
}

$total_paginas = $total_registros > 0 ? (int) ceil($total_registros / $por_pagina) : 1;
if ($pagina_num > $total_paginas) {
    $pagina_num = $total_paginas;
    $offset     = ($pagina_num - 1) * $por_pagina;
}

// ─── Busca os empréstimos da página atual ────────────────────────────────────
$emprestimos = [];
try {
    $sql = "SELECT e.id,
                   e.status,
                   e.data_emprestimo,
                   e.data_prevista_devolucao,
                   e.data_devolucao,
                   e.observacao,
                   a.nome      AS aluno_nome,
                   a.matricula AS aluno_matricula,
                   a.turma     AS aluno_turma,
                   l.titulo    AS livro_titulo,
                   l.autor     AS livro_autor,
                   u.nome      AS registrado_por,
                   CASE
                       WHEN e.data_devolucao IS NOT NULL THEN 0
                       ELSE GREATEST(0, DATEDIFF(CURDATE(), e.data_prevista_devolucao))
                   END AS dias_atraso
              FROM emprestimos e
              INNER JOIN alunos   a ON a.id = e.aluno_id
              INNER JOIN livros   l ON l.id = e.livro_id
              INNER JOIN usuarios u ON u.id = e.usuario_id
              $clausula_where
             ORDER BY
                   CASE e.status
                       WHEN 'em_atraso' THEN 0
                       WHEN 'ativo'     THEN 1
                       WHEN 'devolvido' THEN 2
                       ELSE 3
                   END,
                   e.data_emprestimo DESC,
                   e.id DESC
             LIMIT :limite OFFSET :offset";

    $stmt = $pdo->prepare($sql);
    foreach ($params as $chave => $valor) {
        $stmt->bindValue($chave, $valor, PDO::PARAM_STR);
    }
    $stmt->bindValue(':limite', $por_pagina, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset,     PDO::PARAM_INT);
    $stmt->execute();
    $emprestimos = $stmt->fetchAll();

} catch (PDOException $e) {
    error_log('[BiblioTech] emprestimos/listar fetch: ' . $e->getMessage());
    flash('erro', 'Erro ao carregar a lista de empréstimos.');
}

// ─── Permissões usadas no template ───────────────────────────────────────────
$pode_cadastrar = hasPermission('emprestimos.cadastrar');
$pode_devolver  = hasPermission('emprestimos.devolver');

// ─── Helper: monta URL de paginação mantendo filtros ─────────────────────────
function url_pagina_emp(int $num, array $filtros): string
{
    $qs = http_build_query(array_merge($filtros, ['pagina' => $num]));
    return e(BASE_URL . '/emprestimos/listar.php?' . $qs);
}

// Formata data ISO (Y-m-d) para exibição br (d/m/Y)
$fmt_br = static function (?string $iso): string {
    if (empty($iso)) return '—';
    $ts = strtotime($iso);
    return $ts === false ? '—' : date('d/m/Y', $ts);
};

$filtros_atuais = [
    'status'      => $status,
    'busca_aluno' => $busca_aluno,
    'busca_livro' => $busca_livro,
    'data_de'     => $data_de_val ?? '',
    'data_ate'    => $data_ate_val ?? '',
];

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="pagina-cabecalho">
    <div class="pagina-cabecalho-texto">
        <h1 class="pagina-titulo">Empréstimos</h1>
        <p class="pagina-subtitulo">Acompanhe os empréstimos ativos, devolvidos e em atraso.</p>
    </div>
    <?php if ($pode_cadastrar): ?>
        <a href="<?= e(BASE_URL) ?>/emprestimos/cadastrar.php" class="btn btn-primario">
            + Novo Empréstimo
        </a>
    <?php endif; ?>
</div>

<!-- ─── Filtros ─────────────────────────────────────────────────────────── -->
<div class="card mb-4">
    <form method="GET" action="<?= e(BASE_URL) ?>/emprestimos/listar.php" class="form-filtros">
        <div class="form-grupo">
            <label for="status">Status</label>
            <select id="status" name="status" class="form-campo">
                <option value="todos"     <?= $status === 'todos'     ? 'selected' : '' ?>>Todos</option>
                <option value="ativo"     <?= $status === 'ativo'     ? 'selected' : '' ?>>Ativos</option>
                <option value="em_atraso" <?= $status === 'em_atraso' ? 'selected' : '' ?>>Em atraso</option>
                <option value="devolvido" <?= $status === 'devolvido' ? 'selected' : '' ?>>Devolvidos</option>
            </select>
        </div>

        <div class="form-grupo">
            <label for="busca_aluno">Aluno / Matrícula</label>
            <input
                type="text"
                id="busca_aluno"
                name="busca_aluno"
                class="form-campo"
                placeholder="Nome ou matrícula"
                value="<?= e($busca_aluno) ?>"
                maxlength="100"
            >
        </div>

        <div class="form-grupo">
            <label for="busca_livro">Livro</label>
            <input
                type="text"
                id="busca_livro"
                name="busca_livro"
                class="form-campo"
                placeholder="Título, autor ou ISBN"
                value="<?= e($busca_livro) ?>"
                maxlength="150"
            >
        </div>

        <div class="form-grupo">
            <label for="data_de">Empréstimo de</label>
            <input
                type="date"
                id="data_de"
                name="data_de"
                class="form-campo"
                value="<?= e($data_de_val ?? '') ?>"
            >
        </div>

        <div class="form-grupo">
            <label for="data_ate">até</label>
            <input
                type="date"
                id="data_ate"
                name="data_ate"
                class="form-campo"
                value="<?= e($data_ate_val ?? '') ?>"
            >
        </div>

        <div class="form-grupo form-grupo-acoes">
            <button type="submit" class="btn btn-primario">Filtrar</button>
            <a href="<?= e(BASE_URL) ?>/emprestimos/listar.php" class="btn btn-secundario">Limpar</a>
        </div>
    </form>
</div>

<!-- ─── Tabela ──────────────────────────────────────────────────────────── -->
<div class="card">
    <?php if (empty($emprestimos)): ?>
        <p class="tabela-vazia">Nenhum empréstimo encontrado com os filtros aplicados.</p>
    <?php else: ?>
        <div class="tabela-responsiva">
            <table class="tabela">
                <thead>
                    <tr>
                        <th>Aluno</th>
                        <th>Matrícula</th>
                        <th>Livro</th>
                        <th>Empréstimo</th>
                        <th>Prevista</th>
                        <th>Devolução</th>
                        <th class="text-centro">Status</th>
                        <th class="text-centro">Atraso</th>
                        <th>Registrado por</th>
                        <?php if ($pode_devolver): ?>
                            <th class="text-centro">Ações</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($emprestimos as $emp): ?>
                        <?php
                            $st     = (string) $emp['status'];
                            $atraso = (int)    $emp['dias_atraso'];
                            $devolvido = ($st === 'devolvido');
                        ?>
                        <tr>
                            <td data-label="Aluno"><?= e($emp['aluno_nome']) ?></td>
                            <td data-label="Matrícula"><?= e($emp['aluno_matricula']) ?></td>
                            <td data-label="Livro">
                                <?= e($emp['livro_titulo']) ?>
                                <br><small class="texto-muted"><?= e($emp['livro_autor']) ?></small>
                            </td>
                            <td data-label="Empréstimo"><?= e($fmt_br($emp['data_emprestimo'])) ?></td>
                            <td data-label="Prevista"><?= e($fmt_br($emp['data_prevista_devolucao'])) ?></td>
                            <td data-label="Devolução"><?= e($fmt_br($emp['data_devolucao'])) ?></td>
                            <td data-label="Status" class="text-centro">
                                <?php if ($st === 'devolvido'): ?>
                                    <span class="badge badge-sucesso">Devolvido</span>
                                <?php elseif ($st === 'em_atraso'): ?>
                                    <span class="badge badge-erro">Em atraso</span>
                                <?php else: ?>
                                    <span class="badge badge-info">Ativo</span>
                                <?php endif; ?>
                            </td>
                            <td data-label="Atraso" class="text-centro">
                                <?php if (!$devolvido && $atraso > 0): ?>
                                    <span class="badge badge-erro"><?= $atraso ?> dia(s)</span>
                                <?php else: ?>
                                    <span class="texto-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td data-label="Registrado por"><?= e($emp['registrado_por']) ?></td>
                            <?php if ($pode_devolver): ?>
                                <td data-label="Ações" class="text-centro acoes-tabela">
                                    <?php if (!$devolvido): ?>
                                        <a href="<?= e(BASE_URL) ?>/emprestimos/devolver.php?id=<?= (int) $emp['id'] ?>"
                                           class="btn btn-sm btn-primario"
                                           title="Registrar devolução">
                                            Devolver
                                        </a>
                                    <?php else: ?>
                                        <span class="texto-muted">—</span>
                                    <?php endif; ?>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- ─── Paginação ───────────────────────────────────────────────── -->
        <?php if ($total_paginas > 1): ?>
            <nav class="paginacao" aria-label="Paginação">
                <?php if ($pagina_num > 1): ?>
                    <a href="<?= url_pagina_emp($pagina_num - 1, $filtros_atuais) ?>"
                       class="btn btn-sm btn-secundario">&laquo; Anterior</a>
                <?php endif; ?>

                <span class="paginacao-info">
                    Página <?= $pagina_num ?> de <?= $total_paginas ?>
                    &nbsp;·&nbsp; <?= $total_registros ?> registro(s)
                </span>

                <?php if ($pagina_num < $total_paginas): ?>
                    <a href="<?= url_pagina_emp($pagina_num + 1, $filtros_atuais) ?>"
                       class="btn btn-sm btn-secundario">Próxima &raquo;</a>
                <?php endif; ?>
            </nav>
        <?php else: ?>
            <p class="tabela-rodape">
                <?= $total_registros ?> registro(s) encontrado(s).
            </p>
        <?php endif; ?>

    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
