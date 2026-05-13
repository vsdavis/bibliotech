<?php
/**
 * BiblioTech — Cadastro de Livro
 *
 * Exibe o formulário de novo livro.
 * O processamento é feito em livros-back.php (action=cadastrar).
 */

require_once __DIR__ . '/../includes/auth.php';

$pagina_ativa = 'livros';

// Recupera dados do formulário que podem ter sido preservados em sessão
// (útil quando o back-end redireciona de volta após erro de validação)
$old = $_SESSION['form_livro'] ?? [];
unset($_SESSION['form_livro']);

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="pagina-cabecalho">
    <div class="pagina-cabecalho-texto">
        <h1 class="pagina-titulo">Cadastrar Livro</h1>
        <p class="pagina-subtitulo">Preencha os dados para adicionar um novo livro ao acervo.</p>
    </div>
    <a href="<?= e(BASE_URL) ?>/livros/listar.php" class="btn btn-secundario">
        &larr; Voltar
    </a>
</div>

<div class="card card-formulario">
    <form method="POST"
          action="<?= e(BASE_URL) ?>/livros/livros-back.php"
          novalidate>

        <!-- CSRF -->
        <?= csrf_input() ?>
        <input type="hidden" name="action" value="cadastrar">

        <!-- ─── Linha 1: Título ─────────────────────────────────────────── -->
        <div class="form-grupo">
            <label for="titulo" class="form-label obrigatorio">Título</label>
            <input
                type="text"
                id="titulo"
                name="titulo"
                class="form-campo"
                value="<?= e($old['titulo'] ?? '') ?>"
                maxlength="255"
                required
                autofocus
                placeholder="Ex.: Dom Casmurro"
            >
        </div>

        <!-- ─── Linha 2: Autor ──────────────────────────────────────────── -->
        <div class="form-grupo">
            <label for="autor" class="form-label obrigatorio">Autor</label>
            <input
                type="text"
                id="autor"
                name="autor"
                class="form-campo"
                value="<?= e($old['autor'] ?? '') ?>"
                maxlength="255"
                required
                placeholder="Ex.: Machado de Assis"
            >
        </div>

        <!-- ─── Linha 3: ISBN + Editora ─────────────────────────────────── -->
        <div class="form-linha">
            <div class="form-grupo">
                <label for="isbn" class="form-label">ISBN</label>
                <input
                    type="text"
                    id="isbn"
                    name="isbn"
                    class="form-campo"
                    value="<?= e($old['isbn'] ?? '') ?>"
                    maxlength="20"
                    placeholder="Ex.: 978-85-359-0277-5"
                >
            </div>

            <div class="form-grupo">
                <label for="editora" class="form-label">Editora</label>
                <input
                    type="text"
                    id="editora"
                    name="editora"
                    class="form-campo"
                    value="<?= e($old['editora'] ?? '') ?>"
                    maxlength="255"
                    placeholder="Ex.: Companhia das Letras"
                >
            </div>
        </div>

        <!-- ─── Linha 4: Ano + Quantidade total ─────────────────────────── -->
        <div class="form-linha">
            <div class="form-grupo">
                <label for="ano_publicacao" class="form-label">Ano de Publicação</label>
                <input
                    type="number"
                    id="ano_publicacao"
                    name="ano_publicacao"
                    class="form-campo"
                    value="<?= e($old['ano_publicacao'] ?? '') ?>"
                    min="1000"
                    max="<?= (int) date('Y') ?>"
                    placeholder="Ex.: 1899"
                >
            </div>

            <div class="form-grupo">
                <label for="quantidade_total" class="form-label obrigatorio">Quantidade Total</label>
                <input
                    type="number"
                    id="quantidade_total"
                    name="quantidade_total"
                    class="form-campo"
                    value="<?= e((string) ($old['quantidade_total'] ?? '1')) ?>"
                    min="0"
                    required
                    placeholder="Ex.: 5"
                >
                <small class="form-dica">
                    A quantidade disponível será igual ao total no cadastro inicial.
                </small>
            </div>
        </div>

        <!-- ─── Ações ───────────────────────────────────────────────────── -->
        <div class="form-acoes">
            <button type="submit" class="btn btn-primario">Cadastrar Livro</button>
            <a href="<?= e(BASE_URL) ?>/livros/listar.php" class="btn btn-secundario">Cancelar</a>
        </div>

    </form>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
