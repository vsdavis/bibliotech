<?php
/**
 * BiblioTech — Relatório de Empréstimos
 *
 * Relatório consolidado de empréstimos com:
 *   - Filtro por status (todos, ativo, devolvido, em_atraso)
 *   - Filtro por período (data_emprestimo de / até)
 *   - Atualização automática (no backend) de empréstimos vencidos
 *     para 'em_atraso' antes da listagem — sem depender do
 *     event_scheduler do MySQL.
 *   - Totais por status no topo do relatório.
 *
 * Segurança:
 *   - Autenticação obrigatória via auth.php
 *   - Permissão relatorios.visualizar exigida no backend
 *   - Status validado contra allow-list (whitelist)
 *   - Datas validadas com DateTime::createFromFormat (rejeita formatos inválidos)
 *   - Todas as queries usam PDO + prepared statements (named placeholders)
 *   - Nenhum dado de usuário é concatenado em SQL
 *   - Saída sempre escapada com e()
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/conexao.php';

requirePermission('relatorios.visualizar');

$pagina_ativa = 'relatorios';

// ─── Atualiza status em_atraso antes da listagem ─────────────────────────────
try {
    $pdo->exec(
        "UPDATE emprestimos
            SET status = 'em_atraso'
          WHERE status = 'ativo'
            AND data_prevista_devolucao < CURDATE()"
    );
} catch (PDOException $e) {
    error_log('[BiblioTech] relatorios/emprestimos atualiza atrasos: ' . $e->getMessage());
}

// ─── Validação dos filtros recebidos por GET ─────────────────────────────────

// Status: aceita apenas valores da allow-list
$status_validos = ['todos', 'ativo', 'devolvido', 'em_atraso'];
$status = trim((string) ($_GET['status'] ?? 'todos'));
if (!in_array($status, $status_validos, true)) {
    $status = 'todos';
}

// Período de data_emprestimo: precisa ser YYYY-MM-DD válido (e bate-volta)
$valida_data = static function (string $d): ?string {
    if ($d === '') return null;
    $dt = DateTime::createFromFormat('Y-m-d', $d);
    if ($dt === false || $dt->format('Y-m-d') !== $d) return null;
    return $d;
};

$data_de_raw  = trim((string) ($_GET['data_de']  ?? ''));
$data_ate_raw = trim((string) ($_GET['data_ate'] ?? ''));
$data_de  = $valida_data($data_de_raw);
$data_ate = $valida_data($data_ate_raw);

// Inversão acidental: se "de" > "até", inverte para manter o relatório útil
if ($data_de !== null && $data_ate !== null && strtotime($data_de) > strtotime($data_ate)) {
    [$data_de, $data_ate] = [$data_ate, $data_de];
}

// ─── Monta cláusula WHERE dinamicamente ──────────────────────────────────────
$where  = [];
$params = [];

if ($status !== 'todos') {
    $where[]           = 'e.status = :status';
    $params[':status'] = $status;
}

if ($data_de !== null) {
    $where[]           = 'e.data_emprestimo >= :data_de';
    $params[':data_de'] = $data_de;
}

if ($data_ate !== null) {
    $where[]            = 'e.data_emprestimo <= :data_ate';
    $params[':data_ate'] = $data_ate;
}

$clausula_where = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// ─── Busca os empréstimos do relatório ───────────────────────────────────────
$emprestimos = [];
try {
    $sql = "SELECT e.id,
                   e.status,
                   e.data_emprestimo,
                   e.data_prevista_devolucao,
                   e.data_devolucao,
                   a.nome      AS aluno_nome,
                   a.matricula AS aluno_matricula,
                   l.titulo    AS livro_titulo,
                   l.autor     AS livro_autor
              FROM emprestimos e
              INNER JOIN alunos a ON a.id = e.aluno_id
              INNER JOIN livros l ON l.id = e.livro_id
              $clausula_where
             ORDER BY e.data_emprestimo DESC, e.id DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $emprestimos = $stmt->fetchAll();

} catch (PDOException $e) {
    error_log('[BiblioTech] relatorios/emprestimos fetch: ' . $e->getMessage());
    flash('erro', 'Erro ao gerar o relatório de empréstimos.');
}

// ─── Totais por status (mesmo conjunto de filtros) ───────────────────────────
$totais = ['ativo' => 0, 'devolvido' => 0, 'em_atraso' => 0, 'total' => 0];
foreach ($emprestimos as $e) {
    $s = (string) $e['status'];
    if (isset($totais[$s])) {
        $totais[$s]++;
    }
    $totais['total']++;
}

// Helper de formatação de data
$fmt_br = static function (?string $iso): string {
    if (empty($iso)) return '—';
    $ts = strtotime($iso);
    return $ts === false ? '—' : date('d/m/Y', $ts);
};

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="pagina-cabecalho">
    <div class="pagina-cabecalho-texto">
        <h1 class="pagina-titulo">Relatório de Empréstimos</h1>
        <p class="pagina-subtitulo">
            Visão consolidada dos empréstimos cadastrados, filtrável por status e período.
        </p>
    </div>
    <div class="pagina-cabecalho-acoes">
        <a href="<?= e(BASE_URL) ?>/relatorios/index.php" class="btn btn-secundario">
            &larr; Voltar
        </a>
        <button type="button" class="btn btn-primario" onclick="window.print()">
            🖨️ Imprimir
        </button>
    </div>
</div>

<!-- ─── Filtros ─────────────────────────────────────────────────────────── -->
<div class="card mb-4 nao-imprimir">
    <form method="GET"
          action="<?= e(BASE_URL) ?>/relatorios/emprestimos.php"
          class="form-filtros">

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
            <label for="data_de">Empréstimo de</label>
            <input
                type="date"
                id="data_de"
                name="data_de"
                class="form-campo"
                value="<?= e($data_de ?? '') ?>"
            >
        </div>

        <div class="form-grupo">
            <label for="data_ate">até</label>
            <input
                type="date"
                id="data_ate"
                name="data_ate"
                class="form-campo"
                value="<?= e($data_ate ?? '') ?>"
            >
        </div>

        <div class="form-grupo form-grupo-acoes">
            <button type="submit" class="btn btn-primario">Filtrar</button>
            <a href="<?= e(BASE_URL) ?>/relatorios/emprestimos.php"
               class="btn btn-secundario">Limpar</a>
        </div>
    </form>
</div>

<!-- ─── Totais do relatório ─────────────────────────────────────────────── -->
<div class="cards-indicadores mb-4">
    <div class="card card-indicador">
        <span class="indicador-rotulo">Total</span>
        <span class="indicador-valor"><?= (int) $totais['total'] ?></span>
    </div>
    <div class="card card-indicador">
        <span class="indicador-rotulo">Ativos</span>
        <span class="indicador-valor"><?= (int) $totais['ativo'] ?></span>
    </div>
    <div class="card card-indicador">
        <span class="indicador-rotulo">Em atraso</span>
        <span class="indicador-valor"><?= (int) $totais['em_atraso'] ?></span>
    </div>
    <div class="card card-indicador">
        <span class="indicador-rotulo">Devolvidos</span>
        <span class="indicador-valor"><?= (int) $totais['devolvido'] ?></span>
    </div>
</div>

<!-- ─── Resumo de filtros aplicados (visível na impressão) ──────────────── -->
<div class="relatorio-cabecalho mb-2">
    <strong>Filtros aplicados:</strong>
    Status: <?= e(ucfirst(str_replace('_', ' ', $status))) ?>
    <?php if ($data_de !== null): ?> · De <?= e($fmt_br($data_de)) ?><?php endif; ?>
    <?php if ($data_ate !== null): ?> · Até <?= e($fmt_br($data_ate)) ?><?php endif; ?>
    · Gerado em <?= e(date('d/m/Y H:i')) ?>
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
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($emprestimos as $emp): ?>
                        <?php $st = (string) $emp['status']; ?>
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
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <p class="tabela-rodape">
            <?= (int) $totais['total'] ?> registro(s) listado(s).
        </p>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>