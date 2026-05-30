<?php
/**
 * BiblioTech — Listagem de Usuários
 *
 * Exibe a tabela de usuários com:
 *   - Busca por nome ou e-mail
 *   - Filtro por status (ativo/inativo/todos) e perfil
 *   - Ações: editar, permissões e inativar
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/conexao.php';

requirePermission('usuarios.visualizar');

$pagina_ativa = 'usuarios';

// ─── Filtros (GET) ───────────────────────────────────────────────────────────
$busca  = trim((string) ($_GET['busca']  ?? ''));
$status = trim((string) ($_GET['status'] ?? 'ativo'));
if (!in_array($status, ['ativo', 'inativo', 'todos'], true)) {
    $status = 'ativo';
}

$perfil = trim((string) ($_GET['perfil'] ?? 'todos'));
if (!in_array($perfil, ['todos', 'admin', 'bibliotecario'], true)) {
    $perfil = 'todos';
}

// ─── Paginação ───────────────────────────────────────────────────────────────
$por_pagina = 15;
$pagina_num = filter_input(INPUT_GET, 'pagina', FILTER_VALIDATE_INT, [
    'options' => ['default' => 1, 'min_range' => 1],
]);
$offset = ($pagina_num - 1) * $por_pagina;

// ─── Monta WHERE dinâmico ────────────────────────────────────────────────────
$where  = [];
$params = [];

if ($status === 'ativo') {
    $where[] = 'u.ativo = 1';
} elseif ($status === 'inativo') {
    $where[] = 'u.ativo = 0';
}

if ($perfil !== 'todos') {
    $where[]            = 'u.perfil = :perfil';
    $params[':perfil']  = $perfil;
}

if ($busca !== '') {
    $b = montarBuscaPalavras($busca, ['u.nome', 'u.email'], 'busca');
    $where  = array_merge($where,  $b['where']);
    $params = array_merge($params, $b['params']);
}

$clausula_where = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// ─── Contagem total ──────────────────────────────────────────────────────────
try {
    $sqlCount  = "SELECT COUNT(*) FROM usuarios u $clausula_where";
    $stmtCount = $pdo->prepare($sqlCount);
    $stmtCount->execute($params);
    $total_registros = (int) $stmtCount->fetchColumn();
} catch (PDOException $e) {
    error_log('[BiblioTech] usuarios/listar count: ' . $e->getMessage());
    $total_registros = 0;
}

$total_paginas = $total_registros > 0 ? (int) ceil($total_registros / $por_pagina) : 1;
if ($pagina_num > $total_paginas) {
    $pagina_num = $total_paginas;
    $offset     = ($pagina_num - 1) * $por_pagina;
}

// ─── Busca paginada ──────────────────────────────────────────────────────────
$usuarios = [];
try {
    $sql = "SELECT u.id, u.nome, u.email, u.perfil, u.ativo, u.criado_em
              FROM usuarios u
              $clausula_where
             ORDER BY u.nome ASC
             LIMIT :limite OFFSET :offset";

    $stmt = $pdo->prepare($sql);
    foreach ($params as $chave => $valor) {
        $stmt->bindValue($chave, $valor, PDO::PARAM_STR);
    }
    $stmt->bindValue(':limite', $por_pagina, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset,     PDO::PARAM_INT);
    $stmt->execute();
    $usuarios = $stmt->fetchAll();

} catch (PDOException $e) {
    error_log('[BiblioTech] usuarios/listar fetch: ' . $e->getMessage());
    flash('erro', 'Erro ao carregar lista de usuários.');
}

function url_pagina_usuarios(int $num, string $busca, string $status, string $perfil): string
{
    $qs = http_build_query([
        'pagina' => $num,
        'busca'  => $busca,
        'status' => $status,
        'perfil' => $perfil,
    ]);
    return e(BASE_URL . '/usuarios/listar.php?' . $qs);
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="pagina-cabecalho">
    <div class="pagina-cabecalho-texto">
        <h1 class="pagina-titulo">Usuários do Sistema</h1>
        <p class="pagina-subtitulo">
            Gerencie os usuários e suas permissões individuais.
        </p>
    </div>
    <?php if (hasPermission('usuarios.cadastrar')): ?>
        <a href="<?= e(BASE_URL) ?>/usuarios/cadastrar.php" class="btn btn-primario">
            + Novo Usuário
        </a>
    <?php endif; ?>
</div>

<!-- ─── Filtros ─────────────────────────────────────────────────────────── -->
<div class="card mb-4">
    <form method="GET" action="<?= e(BASE_URL) ?>/usuarios/listar.php" class="form-filtros">
        <div class="form-grupo">
            <label for="busca">Buscar</label>
            <input
                type="text"
                id="busca"
                name="busca"
                class="form-campo"
                placeholder="Nome ou e-mail…"
                value="<?= e($busca) ?>"
                maxlength="100"
            >
        </div>

        <div class="form-grupo">
            <label for="perfil">Perfil</label>
            <select id="perfil" name="perfil" class="form-campo">
                <option value="todos"         <?= $perfil === 'todos'         ? 'selected' : '' ?>>Todos</option>
                <option value="admin"         <?= $perfil === 'admin'         ? 'selected' : '' ?>>Administrador</option>
                <option value="bibliotecario" <?= $perfil === 'bibliotecario' ? 'selected' : '' ?>>Bibliotecário</option>
            </select>
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
            <a href="<?= e(BASE_URL) ?>/usuarios/listar.php" class="btn btn-secundario">Limpar</a>
        </div>
    </form>
</div>

<!-- ─── Tabela ──────────────────────────────────────────────────────────── -->
<div class="card">
    <?php if (empty($usuarios)): ?>
        <p class="tabela-vazia">Nenhum usuário encontrado com os filtros aplicados.</p>
    <?php else: ?>
        <div class="tabela-responsiva">
            <table class="tabela">
                <thead>
                    <tr>
                        <th>Nome</th>
                        <th>E-mail</th>
                        <th class="text-centro">Perfil</th>
                        <th class="text-centro">Status</th>
                        <th class="text-centro">Cadastrado em</th>
                        <th class="text-centro">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($usuarios as $u):
                        $eu_mesmo = ((int) $u['id'] === (int) $_SESSION['usuario_id']);
                    ?>
                        <tr>
                            <td data-label="Nome">
                                <?= e($u['nome']) ?>
                                <?php if ($eu_mesmo): ?>
                                    <span class="badge badge-info">Você</span>
                                <?php endif; ?>
                            </td>
                            <td data-label="E-mail"><?= e($u['email']) ?></td>
                            <td data-label="Perfil" class="text-centro">
                                <?php if ($u['perfil'] === 'admin'): ?>
                                    <span class="badge badge-info">Administrador</span>
                                <?php else: ?>
                                    <span class="badge">Bibliotecário</span>
                                <?php endif; ?>
                            </td>
                            <td data-label="Status" class="text-centro">
                                <?php if ((int) $u['ativo'] === 1): ?>
                                    <span class="badge badge-sucesso">Ativo</span>
                                <?php else: ?>
                                    <span class="badge badge-erro">Inativo</span>
                                <?php endif; ?>
                            </td>
                            <td data-label="Cadastrado em" class="text-centro">
                                <?= e(date('d/m/Y', strtotime($u['criado_em']))) ?>
                            </td>
                            <td data-label="Ações" class="text-centro acoes-tabela">
                                <?php if ((int) $u['ativo'] === 1): ?>
                                    <?php if (hasPermission('usuarios.editar')): ?>
                                        <a href="<?= e(BASE_URL) ?>/usuarios/editar.php?id=<?= (int) $u['id'] ?>"
                                           class="btn btn-sm btn-secundario"
                                           title="Editar usuário">
                                            Editar
                                        </a>
                                    <?php endif; ?>

                                    <?php if (hasPermission('usuarios.permissoes')): ?>
                                        <a href="<?= e(BASE_URL) ?>/usuarios/permissoes.php?id=<?= (int) $u['id'] ?>"
                                           class="btn btn-sm btn-secundario"
                                           title="Gerenciar permissões">
                                            Permissões
                                        </a>
                                    <?php endif; ?>

                                    <?php if (hasPermission('usuarios.inativar') && !$eu_mesmo): ?>
                                        <a href="<?= e(BASE_URL) ?>/usuarios/inativar.php?id=<?= (int) $u['id'] ?>"
                                           class="btn btn-sm btn-perigo"
                                           title="Inativar usuário">
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

        <!-- ─── Paginação ───────────────────────────────────────────────── -->
        <?php if ($total_paginas > 1): ?>
            <nav class="paginacao" aria-label="Paginação">
                <?php if ($pagina_num > 1): ?>
                    <a href="<?= url_pagina_usuarios($pagina_num - 1, $busca, $status, $perfil) ?>"
                       class="btn btn-sm btn-secundario">&laquo; Anterior</a>
                <?php endif; ?>

                <span class="paginacao-info">
                    Página <?= $pagina_num ?> de <?= $total_paginas ?>
                    &nbsp;·&nbsp; <?= $total_registros ?> registro(s)
                </span>

                <?php if ($pagina_num < $total_paginas): ?>
                    <a href="<?= url_pagina_usuarios($pagina_num + 1, $busca, $status, $perfil) ?>"
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
