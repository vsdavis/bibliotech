<?php
/**
 * BiblioTech — Relatório de Livros
 *
 * Lista todo o acervo ativo da biblioteca com:
 *   - Quantidade total
 *   - Quantidade disponível
 *   - Quantidade emprestada (= total − disponível)
 *   - Destaque visual para livros sem exemplares disponíveis
 *
 * Segurança:
 *   - Autenticação obrigatória via auth.php
 *   - Permissão relatorios.visualizar exigida no backend
 *   - Filtro "estoque" validado contra allow-list (whitelist)
 *   - Busca textual sempre parametrizada (PDO + LIKE com placeholder)
 *   - Nenhum dado de usuário é concatenado em SQL
 *   - Saída sempre escapada com e() / cast (int)
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/conexao.php';

requirePermission('relatorios.visualizar');

$pagina_ativa = 'relatorios';

// ─── Validação dos filtros recebidos por GET ─────────────────────────────────

// Filtro de estoque: aceita apenas valores da allow-list
$estoque_validos = ['todos', 'com_estoque', 'sem_estoque'];
$estoque = trim((string) ($_GET['estoque'] ?? 'todos'));
if (!in_array($estoque, $estoque_validos, true)) {
    $estoque = 'todos';
}

// Busca textual livre (será usada com LIKE e placeholder — nunca concatenada)
$busca = trim((string) ($_GET['busca'] ?? ''));
if (mb_strlen($busca) > 150) {
    $busca = mb_substr($busca, 0, 150);
}

// ─── Monta cláusula WHERE dinamicamente ──────────────────────────────────────
$where  = ['l.ativo = 1']; // relatório lista apenas livros ATIVOS
$params = [];

if ($estoque === 'com_estoque') {
    $where[] = 'l.quantidade_disponivel > 0';
} elseif ($estoque === 'sem_estoque') {
    $where[] = 'l.quantidade_disponivel = 0';
}

if ($busca !== '') {
    $b = montarBuscaPalavras($busca, ['l.titulo', 'l.autor', 'l.isbn'], 'busca');
    $where  = array_merge($where,  $b['where']);
    $params = array_merge($params, $b['params']);
}

$clausula_where = 'WHERE ' . implode(' AND ', $where);

// ─── Busca os livros ─────────────────────────────────────────────────────────
$livros = [];
try {
    $sql = "SELECT l.id,
                   l.titulo,
                   l.autor,
                   l.isbn,
                   l.quantidade_total,
                   l.quantidade_disponivel,
                   (l.quantidade_total - l.quantidade_disponivel) AS quantidade_emprestada
              FROM livros l
              $clausula_where
             ORDER BY l.titulo ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $livros = $stmt->fetchAll();

} catch (PDOException $e) {
    error_log('[BiblioTech] relatorios/livros fetch: ' . $e->getMessage());
    flash('erro', 'Erro ao gerar o relatório de livros.');
}

// ─── Totais para o resumo do relatório ───────────────────────────────────────
$total_titulos       = count($livros);
$total_exemplares    = 0;
$total_disponiveis   = 0;
$total_emprestados   = 0;
$titulos_sem_estoque = 0;

foreach ($livros as $l) {
    $qt = (int) $l['quantidade_total'];
    $qd = (int) $l['quantidade_disponivel'];
    $total_exemplares  += $qt;
    $total_disponiveis += $qd;
    $total_emprestados += ($qt - $qd);
    if ($qd === 0) {
        $titulos_sem_estoque++;
    }
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="pagina-cabecalho">
    <div class="pagina-cabecalho-texto">
        <h1 class="pagina-titulo">Relatório de Livros</h1>
        <p class="pagina-subtitulo">
            Visão consolidada do acervo ativo, com totais de exemplares
            disponíveis e emprestados de cada título.
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
          action="<?= e(BASE_URL) ?>/relatorios/livros.php"
          class="form-filtros">

        <div class="form-grupo">
            <label for="busca">Buscar</label>
            <input
                type="text"
                id="busca"
                name="busca"
                class="form-campo"
                placeholder="Título, autor ou ISBN"
                value="<?= e($busca) ?>"
                maxlength="150"
            >
        </div>

        <div class="form-grupo">
            <label for="estoque">Estoque</label>
            <select id="estoque" name="estoque" class="form-campo">
                <option value="todos"       <?= $estoque === 'todos'       ? 'selected' : '' ?>>Todos</option>
                <option value="com_estoque" <?= $estoque === 'com_estoque' ? 'selected' : '' ?>>Com exemplares</option>
                <option value="sem_estoque" <?= $estoque === 'sem_estoque' ? 'selected' : '' ?>>Sem exemplares</option>
            </select>
        </div>

        <div class="form-grupo form-grupo-acoes">
            <button type="submit" class="btn btn-primario">Filtrar</button>
            <a href="<?= e(BASE_URL) ?>/relatorios/livros.php" class="btn btn-secundario">Limpar</a>
        </div>
    </form>
</div>

<!-- ─── Indicadores ─────────────────────────────────────────────────────── -->
<div class="cards-indicadores mb-4">
    <div class="card card-indicador">
        <span class="indicador-rotulo">Títulos listados</span>
        <span class="indicador-valor"><?= (int) $total_titulos ?></span>
    </div>
    <div class="card card-indicador">
        <span class="indicador-rotulo">Total de exemplares</span>
        <span class="indicador-valor"><?= (int) $total_exemplares ?></span>
    </div>
    <div class="card card-indicador">
        <span class="indicador-rotulo">Disponíveis</span>
        <span class="indicador-valor"><?= (int) $total_disponiveis ?></span>
    </div>
    <div class="card card-indicador">
        <span class="indicador-rotulo">Emprestados</span>
        <span class="indicador-valor"><?= (int) $total_emprestados ?></span>
    </div>
    <div class="card card-indicador">
        <span class="indicador-rotulo">Sem exemplares</span>
        <span class="indicador-valor"><?= (int) $titulos_sem_estoque ?></span>
    </div>
</div>

<!-- ─── Cabeçalho do relatório (visível na impressão) ───────────────────── -->
<div class="relatorio-cabecalho mb-2">
    <strong>Filtros aplicados:</strong>
    Estoque: <?= e(ucfirst(str_replace('_', ' ', $estoque))) ?>
    <?php if ($busca !== ''): ?> · Busca: "<?= e($busca) ?>"<?php endif; ?>
    · Gerado em <?= e(date('d/m/Y H:i')) ?>
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
                        <th class="text-centro">Total</th>
                        <th class="text-centro">Disponível</th>
                        <th class="text-centro">Emprestados</th>
                        <th class="text-centro">Situação</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($livros as $livro): ?>
                        <?php
                            $qt = (int) $livro['quantidade_total'];
                            $qd = (int) $livro['quantidade_disponivel'];
                            $qe = (int) $livro['quantidade_emprestada'];
                            $sem_estoque = ($qd === 0);
                        ?>
                        <tr class="<?= $sem_estoque ? 'linha-alerta' : '' ?>">
                            <td data-label="Título"><?= e($livro['titulo']) ?></td>
                            <td data-label="Autor"><?= e($livro['autor']) ?></td>
                            <td data-label="ISBN"><?= e($livro['isbn'] ?? '—') ?></td>
                            <td data-label="Total" class="text-centro"><?= $qt ?></td>
                            <td data-label="Disponível" class="text-centro">
                                <?php if ($sem_estoque): ?>
                                    <span class="badge badge-erro"><?= $qd ?></span>
                                <?php else: ?>
                                    <span class="badge badge-sucesso"><?= $qd ?></span>
                                <?php endif; ?>
                            </td>
                            <td data-label="Emprestados" class="text-centro"><?= $qe ?></td>
                            <td data-label="Situação" class="text-centro">
                                <?php if ($sem_estoque): ?>
                                    <span class="badge badge-erro">Sem exemplares</span>
                                <?php else: ?>
                                    <span class="badge badge-sucesso">Disponível</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <p class="tabela-rodape">
            <?= (int) $total_titulos ?> título(s) listado(s).
        </p>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>