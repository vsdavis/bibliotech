<?php
/**
 * BiblioTech — Listagem de Livros
 *
 * Exibe a tabela de livros com:
 *   - Busca por título, autor e ISBN
 *   - Filtro por status (ativo/inativo/todos)
 *   - Paginação simples
 *   - Ações: editar e inativar
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/conexao.php';

$pagina_ativa = 'livros';

// ─── Parâmetros de busca/filtro (GET) ────────────────────────────────────────
$busca  = trim((string) ($_GET['busca']  ?? ''));
$status = trim((string) ($_GET['status'] ?? 'ativo')); // ativo | inativo | todos
if (!in_array($status, ['ativo', 'inativo', 'todos'], true)) {
    $status = 'ativo';
}

// ─── Paginação ───────────────────────────────────────────────────────────────
$por_pagina = 15;
$pagina_num = filter_input(INPUT_GET, 'pagina', FILTER_VALIDATE_INT, [
    'options' => ['default' => 1, 'min_range' => 1],
]);
$offset = ($pagina_num - 1) * $por_pagina;

// ─── Monta cláusula WHERE dinamicamente ──────────────────────────────────────
$where  = [];
$params = [];

if ($status === 'ativo') {
    $where[]          = 'l.ativo = 1';
} elseif ($status === 'inativo') {
    $where[]          = 'l.ativo = 0';
}
// 'todos' → sem filtro de ativo

if ($busca !== '') {
    $where[]            = '(l.titulo LIKE :busca OR l.autor LIKE :busca OR l.isbn LIKE :busca)';
    $params[':busca']   = '%' . $busca . '%';
}

$clausula_where = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// ─── Contagem total (para paginação) ─────────────────────────────────────────
try {
    $sqlCount = "SELECT COUNT(*) FROM livros l $clausula_where";
    $stmtCount = $pdo->prepare($sqlCount);
    $stmtCount->execute($params);
    $total_registros = (int) $stmtCount->fetchColumn();

} catch (PDOException $e) {
    error_log('[BiblioTech] livros/listar count: ' . $e->getMessage());
    $total_registros = 0;
}

$total_paginas = $total_registros > 0 ? (int) ceil($total_registros / $por_pagina) : 1;
if ($pagina_num > $total_paginas) {
    $pagina_num = $total_paginas;
    $offset     = ($pagina_num - 1) * $por_pagina;
}

// ─── Busca os livros da página atual ─────────────────────────────────────────
$livros = [];
try {
    $sql = "SELECT l.id,
                   l.titulo,
                   l.autor,
                   l.isbn,
                   l.ano_publicacao,
                   l.quantidade_total,
                   l.quantidade_disponivel,
                   l.ativo
              FROM livros l
             $clausula_where
             ORDER BY l.titulo ASC
             LIMIT :limite OFFSET :offset";

    $stmt = $pdo->prepare($sql);
    // Bind tipado (LIMIT/OFFSET exigem PDO::PARAM_INT quando EMULATE_PREPARES = false)
    foreach ($params as $chave => $valor) {
        $stmt->bindValue($chave, $valor, PDO::PARAM_STR);
    }
    $stmt->bindValue(':limite', $por_pagina, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset,     PDO::PARAM_INT);
    $stmt->execute();
    $livros = $stmt->fetchAll();

} catch (PDOException $e) {
    error_log('[BiblioTech] livros/listar fetch: ' . $e->getMessage());
    flash('erro', 'Erro ao carregar lista de livros.');
}

// ─── Helper: monta URL de paginação mantendo filtros ─────────────────────────
function url_pagina(int $num, string $busca, string $status): string
{
    $qs = http_build_query([
        'pagina' => $num,
        'busca'  => $busca,
        'status' => $status,
    ]);
    return e(BASE_URL . '/livros/listar.php?' . $qs);
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="pagina-cabecalho">
    <div class="pagina-cabecalho-texto">
        <h1 class="pagina-titulo">Acervo de Livros</h1>
        <p class="pagina-subtitulo">Gerencie os livros disponíveis na biblioteca.</p>
    </div>
    <a href="<?= e(BASE_URL) ?>/livros/cadastrar.php" class="btn btn-primario">
        + Novo Livro
    </a>
</div>

<!-- ─── Filtros ─────────────────────────────────────────────────────────── -->
<div class="card mb-4">
    <form method="GET" action="<?= e(BASE_URL) ?>/livros/listar.php" class="form-filtros">
        <div class="form-grupo">
            <label for="busca">Buscar</label>
            <input
                type="text"
                id="busca"
                name="busca"
                class="form-campo"
                placeholder="Título, autor ou ISBN…"
                value="<?= e($busca) ?>"
                maxlength="150"
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
            <a href="<?= e(BASE_URL) ?>/livros/listar.php" class="btn btn-secundario">Limpar</a>
        </div>
    </form>
</div>

<!-- ─── Tabela ──────────────────────────────────────────────────────────── -->
<div class="card">
    <?php if (empty($livros)): ?>
        <p class="tabela-vazia">Nenhum livro encontrado com os filtros aplicados.</p>
    <?php else: ?>
        <div class="tabela-responsiva">
            <table class="tabela">
                <thead>
                    <tr>
                        <th>Título</th>
                        <th>Autor</th>
                        <th>ISBN</th>
                        <th>Ano</th>
                        <th class="text-centro">Total</th>
                        <th class="text-centro">Disponível</th>
                        <th class="text-centro">Status</th>
                        <th class="text-centro">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($livros as $livro): ?>
                        <tr>
                            <td data-label="Título"><?= e($livro['titulo']) ?></td>
                            <td data-label="Autor"><?= e($livro['autor']) ?></td>
                            <td data-label="ISBN"><?= e($livro['isbn'] ?? '—') ?></td>
                            <td data-label="Ano"><?= e((string) ($livro['ano_publicacao'] ?? '—')) ?></td>
                            <td data-label="Total" class="text-centro">
                                <?= (int) $livro['quantidade_total'] ?>
                            </td>
                            <td data-label="Disponível" class="text-centro">
                                <?php
                                    $disp = (int) $livro['quantidade_disponivel'];
                                    $cls  = $disp === 0 ? 'badge badge-erro' : 'badge badge-sucesso';
                                ?>
                                <span class="<?= $cls ?>"><?= $disp ?></span>
                            </td>
                            <td data-label="Status" class="text-centro">
                                <?php if ((int) $livro['ativo'] === 1): ?>
                                    <span class="badge badge-sucesso">Ativo</span>
                                <?php else: ?>
                                    <span class="badge badge-erro">Inativo</span>
                                <?php endif; ?>
                            </td>
                            <td data-label="Ações" class="text-centro acoes-tabela">
                                <?php if ((int) $livro['ativo'] === 1): ?>
                                    <a href="<?= e(BASE_URL) ?>/livros/editar.php?id=<?= (int) $livro['id'] ?>"
                                       class="btn btn-sm btn-secundario"
                                       title="Editar livro">
                                        Editar
                                    </a>
                                    <a href="<?= e(BASE_URL) ?>/livros/inativar.php?id=<?= (int) $livro['id'] ?>"
                                       class="btn btn-sm btn-perigo"
                                       title="Inativar livro">
                                        Inativar
                                    </a>
                                <?php else: ?>
                                    <span class="texto-muted">—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- ─── Paginação ───────────────────────────────────────────────── -->
        <?php if ($total_paginas > 1): ?>
            <nav class="paginacao" aria-label="Paginação">
                <?php if ($pagina_num > 1): ?>
                    <a href="<?= url_pagina($pagina_num - 1, $busca, $status) ?>"
                       class="btn btn-sm btn-secundario">&laquo; Anterior</a>
                <?php endif; ?>

                <span class="paginacao-info">
                    Página <?= $pagina_num ?> de <?= $total_paginas ?>
                    &nbsp;·&nbsp; <?= $total_registros ?> registro(s)
                </span>

                <?php if ($pagina_num < $total_paginas): ?>
                    <a href="<?= url_pagina($pagina_num + 1, $busca, $status) ?>"
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
