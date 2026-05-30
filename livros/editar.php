<?php
/**
 * BiblioTech — Edição de Livro
 *
 * Carrega os dados do livro pelo ID recebido via GET e exibe o formulário.
 * O processamento é feito em livros-back.php (action=editar).
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/conexao.php';

requirePermission('livros.editar');

$pagina_ativa = 'livros';

// ─── Valida o ID recebido por GET ─────────────────────────────────────────────
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1],
]);

if ($id === false || $id === null) {
    flash('erro', 'ID do livro inválido.');
    redirecionar(BASE_URL . '/livros/listar.php');
}

// ─── Busca o livro no banco ───────────────────────────────────────────────────
try {
    $stmt = $pdo->prepare(
        'SELECT id, titulo, autor, isbn, editora, ano_publicacao,
                quantidade_total, quantidade_disponivel, ativo
           FROM livros
          WHERE id = :id
            AND ativo = 1'
    );
    $stmt->execute([':id' => $id]);
    $livro = $stmt->fetch();

} catch (PDOException $e) {
    error_log('[BiblioTech] livros/editar busca: ' . $e->getMessage());
    flash('erro', 'Erro ao carregar dados do livro.');
    redirecionar(BASE_URL . '/livros/listar.php');
}

if (!$livro) {
    flash('erro', 'Livro não encontrado ou inativo.');
    redirecionar(BASE_URL . '/livros/listar.php');
}

// Se o back-end redirecionou de volta com erros, usa os dados do POST
// (armazenados em sessão); caso contrário usa os dados do banco.
$old = $_SESSION['form_livro'] ?? [];
unset($_SESSION['form_livro']);

$v = [
    'titulo'          => $old['titulo']          ?? $livro['titulo'],
    'autor'           => $old['autor']           ?? $livro['autor'],
    'isbn'            => $old['isbn']            ?? ($livro['isbn']    ?? ''),
    'editora'         => $old['editora']         ?? ($livro['editora'] ?? ''),
    'ano_publicacao'  => $old['ano_publicacao']  ?? ($livro['ano_publicacao'] ?? ''),
    'quantidade_total'=> $old['quantidade_total'] ?? $livro['quantidade_total'],
];

// Calcula exemplares emprestados para exibir no formulário como informação
$emprestados = (int) $livro['quantidade_total'] - (int) $livro['quantidade_disponivel'];

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="pagina-cabecalho">
    <div class="pagina-cabecalho-texto">
        <h1 class="pagina-titulo">Editar Livro</h1>
        <p class="pagina-subtitulo">Atualize os dados do livro selecionado.</p>
    </div>
    <a href="<?= e(BASE_URL) ?>/livros/listar.php" class="btn btn-secundario">
        &larr; Voltar
    </a>
</div>

<!-- Resumo atual do exemplar -->
<?php if ($emprestados > 0): ?>
    <div class="flash flash-info">
        <strong>Atenção:</strong>
        este livro possui <strong><?= $emprestados ?></strong> exemplar(es) emprestado(s) no momento.
        A quantidade total não poderá ser reduzida abaixo de <strong><?= $emprestados ?></strong>.
    </div>
<?php endif; ?>

<div class="card card-formulario">
    <form method="POST"
          action="<?= e(BASE_URL) ?>/livros/livros-back.php"
          novalidate>

        <!-- CSRF -->
        <?= csrf_input() ?>
        <input type="hidden" name="action" value="editar">
        <input type="hidden" name="id"     value="<?= (int) $livro['id'] ?>">

        <!-- ─── Título ──────────────────────────────────────────────────── -->
        <div class="form-grupo">
            <label for="titulo" class="form-label obrigatorio">Título</label>
            <input
                type="text"
                id="titulo"
                name="titulo"
                class="form-campo"
                value="<?= e($v['titulo']) ?>"
                maxlength="255"
                required
                autofocus
            >
        </div>

        <!-- ─── Autor ───────────────────────────────────────────────────── -->
        <div class="form-grupo">
            <label for="autor" class="form-label obrigatorio">Autor</label>
            <input
                type="text"
                id="autor"
                name="autor"
                class="form-campo"
                value="<?= e($v['autor']) ?>"
                maxlength="255"
                required
            >
        </div>

        <!-- ─── ISBN + Editora ──────────────────────────────────────────── -->
        <div class="form-linha">
            <div class="form-grupo">
                <label for="isbn" class="form-label">ISBN</label>
                <input
                    type="text"
                    id="isbn"
                    name="isbn"
                    class="form-campo"
                    value="<?= e($v['isbn']) ?>"
                    maxlength="20"
                >
            </div>

            <div class="form-grupo">
                <label for="editora" class="form-label">Editora</label>
                <input
                    type="text"
                    id="editora"
                    name="editora"
                    class="form-campo"
                    value="<?= e($v['editora']) ?>"
                    maxlength="255"
                >
            </div>
        </div>

        <!-- ─── Ano + Quantidade total ──────────────────────────────────── -->
        <div class="form-linha">
            <div class="form-grupo">
                <label for="ano_publicacao" class="form-label">Ano de Publicação</label>
                <input
                    type="number"
                    id="ano_publicacao"
                    name="ano_publicacao"
                    class="form-campo"
                    value="<?= e((string) $v['ano_publicacao']) ?>"
                    min="1000"
                    max="<?= (int) date('Y') ?>"
                >
            </div>

            <div class="form-grupo">
                <label for="quantidade_total" class="form-label obrigatorio">Quantidade Total</label>
                <input
                    type="number"
                    id="quantidade_total"
                    name="quantidade_total"
                    class="form-campo"
                    value="<?= e((string) $v['quantidade_total']) ?>"
                    min="<?= $emprestados ?>"
                    required
                >
                <small class="form-dica">
                    Disponível atual:
                    <strong><?= (int) $livro['quantidade_disponivel'] ?></strong>
                    &nbsp;|&nbsp; Emprestados:
                    <strong><?= $emprestados ?></strong>
                </small>
            </div>
        </div>

        <!-- ─── Ações ───────────────────────────────────────────────────── -->
        <div class="form-acoes">
            <button type="submit" class="btn btn-primario">Salvar Alterações</button>
            <a href="<?= e(BASE_URL) ?>/livros/listar.php" class="btn btn-secundario">Cancelar</a>
        </div>

    </form>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
