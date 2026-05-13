<?php
/**
 * BiblioTech — Cadastro de Aluno
 *
 * Exibe o formulário de novo aluno.
 * O processamento é feito em alunos-back.php (action=cadastrar).
 */

require_once __DIR__ . '/../includes/auth.php';

requirePermission('alunos.cadastrar');

$pagina_ativa = 'alunos';

// Recupera dados do formulário preservados em sessão após erro de validação
$old = $_SESSION['form_aluno'] ?? [];
unset($_SESSION['form_aluno']);

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="pagina-cabecalho">
    <div class="pagina-cabecalho-texto">
        <h1 class="pagina-titulo">Cadastrar Aluno</h1>
        <p class="pagina-subtitulo">Preencha os dados para adicionar um novo aluno.</p>
    </div>
    <a href="<?= e(BASE_URL) ?>/alunos/listar.php" class="btn btn-secundario">
        &larr; Voltar
    </a>
</div>

<div class="card card-formulario">
    <form method="POST"
          action="<?= e(BASE_URL) ?>/alunos/alunos-back.php"
          novalidate>

        <!-- CSRF -->
        <?= csrf_input() ?>
        <input type="hidden" name="action" value="cadastrar">

        <!-- ─── Nome ────────────────────────────────────────────────────── -->
        <div class="form-grupo">
            <label for="nome" class="form-label obrigatorio">Nome</label>
            <input
                type="text"
                id="nome"
                name="nome"
                class="form-campo"
                value="<?= e($old['nome'] ?? '') ?>"
                maxlength="150"
                required
                autofocus
                placeholder="Ex.: João da Silva"
            >
        </div>

        <!-- ─── Matrícula ───────────────────────────────────────────────── -->
        <div class="form-grupo">
            <label for="matricula" class="form-label obrigatorio">Matrícula</label>
            <input
                type="text"
                id="matricula"
                name="matricula"
                class="form-campo"
                value="<?= e($old['matricula'] ?? '') ?>"
                maxlength="50"
                required
                placeholder="Ex.: 2024001"
            >
            <small class="form-dica">A matrícula deve ser única no sistema.</small>
        </div>

        <!-- ─── Turma + Telefone ─────────────────────────────────────────── -->
        <div class="form-linha">
            <div class="form-grupo">
                <label for="turma" class="form-label">Turma</label>
                <input
                    type="text"
                    id="turma"
                    name="turma"
                    class="form-campo"
                    value="<?= e($old['turma'] ?? '') ?>"
                    maxlength="50"
                    placeholder="Ex.: 3º A"
                >
            </div>

            <div class="form-grupo">
                <label for="telefone" class="form-label">Telefone</label>
                <input
                    type="text"
                    id="telefone"
                    name="telefone"
                    class="form-campo"
                    value="<?= e($old['telefone'] ?? '') ?>"
                    maxlength="20"
                    placeholder="Ex.: (11) 91234-5678"
                >
            </div>
        </div>

        <!-- ─── E-mail ──────────────────────────────────────────────────── -->
        <div class="form-grupo">
            <label for="email" class="form-label">E-mail</label>
            <input
                type="email"
                id="email"
                name="email"
                class="form-campo"
                value="<?= e($old['email'] ?? '') ?>"
                maxlength="150"
                placeholder="Ex.: joao@email.com"
            >
            <small class="form-dica">Opcional. Se preenchido, deve ser um endereço válido.</small>
        </div>

        <!-- ─── Ações ───────────────────────────────────────────────────── -->
        <div class="form-acoes">
            <button type="submit" class="btn btn-primario">Cadastrar Aluno</button>
            <a href="<?= e(BASE_URL) ?>/alunos/listar.php" class="btn btn-secundario">Cancelar</a>
        </div>

    </form>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>