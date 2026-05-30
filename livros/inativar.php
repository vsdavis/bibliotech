<?php
/**
 * BiblioTech — Confirmação de Inativação de Livro
 *
 * Exibe uma tela de confirmação antes de inativar o livro.
 * O processamento efetivo é feito em livros-back.php (action=inativar).
 *
 * Segurança:
 *   - Autenticação obrigatória via auth.php
 *   - ID validado como inteiro positivo
 *   - Token CSRF no formulário de confirmação
 *   - Verifica no banco se o livro existe e está ativo
 *   - Mostra contagem de empréstimos ativos/em atraso para contexto
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/conexao.php';

requirePermission('livros.inativar');

$pagina_ativa = 'livros';

// ─── Valida o ID recebido por GET ─────────────────────────────────────────────
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1],
]);

if ($id === false || $id === null) {
    flash('erro', 'ID do livro inválido.');
    redirecionar(BASE_URL . '/livros/listar.php');
}

// ─── Busca o livro ────────────────────────────────────────────────────────────
try {
    $stmt = $pdo->prepare(
        'SELECT id, titulo, autor, isbn, quantidade_total, quantidade_disponivel, ativo
           FROM livros
          WHERE id = :id'
    );
    $stmt->execute([':id' => $id]);
    $livro = $stmt->fetch();

} catch (PDOException $e) {
    error_log('[BiblioTech] livros/inativar busca: ' . $e->getMessage());
    flash('erro', 'Erro ao carregar dados do livro.');
    redirecionar(BASE_URL . '/livros/listar.php');
}

if (!$livro) {
    flash('erro', 'Livro não encontrado.');
    redirecionar(BASE_URL . '/livros/listar.php');
}

if ((int) $livro['ativo'] === 0) {
    flash('erro', 'Este livro já está inativo.');
    redirecionar(BASE_URL . '/livros/listar.php');
}

// ─── Conta empréstimos ativos ou em atraso ────────────────────────────────────
try {
    $stmtEmp = $pdo->prepare(
        "SELECT COUNT(*) AS total
           FROM emprestimos
          WHERE livro_id = :id
            AND status  IN ('ativo', 'em_atraso')"
    );
    $stmtEmp->execute([':id' => $id]);
    $emprestimos_ativos = (int) $stmtEmp->fetchColumn();

} catch (PDOException $e) {
    error_log('[BiblioTech] livros/inativar emp count: ' . $e->getMessage());
    $emprestimos_ativos = 0;
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="pagina-cabecalho">
    <div class="pagina-cabecalho-texto">
        <h1 class="pagina-titulo">Inativar Livro</h1>
        <p class="pagina-subtitulo">Confirme a inativação do livro abaixo.</p>
    </div>
    <a href="<?= e(BASE_URL) ?>/livros/listar.php" class="btn btn-secundario">
        &larr; Voltar
    </a>
</div>

<?php if ($emprestimos_ativos > 0): ?>
    <!-- Livro com empréstimos ativos: bloqueia a ação -->
    <div class="flash flash-erro">
        <strong>Ação bloqueada.</strong>
        Este livro possui <strong><?= $emprestimos_ativos ?></strong> empréstimo(s)
        ativo(s) ou em atraso. Não é possível inativá-lo enquanto houver exemplares fora da
        biblioteca. Aguarde todas as devoluções.
    </div>

    <div class="card card-formulario">
        <div class="form-acoes">
            <a href="<?= e(BASE_URL) ?>/livros/listar.php" class="btn btn-primario">
                Voltar à Listagem
            </a>
        </div>
    </div>

<?php else: ?>
    <!-- Livro sem empréstimos ativos: exibe confirmação -->
    <div class="card card-formulario">

        <div class="confirmacao-bloco">
            <div class="confirmacao-icone">⚠️</div>
            <p class="confirmacao-texto">
                Você está prestes a <strong>inativar</strong> o seguinte livro:
            </p>

            <table class="tabela-detalhes">
                <tr>
                    <th>Título</th>
                    <td><?= e($livro['titulo']) ?></td>
                </tr>
                <tr>
                    <th>Autor</th>
                    <td><?= e($livro['autor']) ?></td>
                </tr>
                <?php if (!empty($livro['isbn'])): ?>
                <tr>
                    <th>ISBN</th>
                    <td><?= e($livro['isbn']) ?></td>
                </tr>
                <?php endif; ?>
                <tr>
                    <th>Quantidade Total</th>
                    <td><?= (int) $livro['quantidade_total'] ?></td>
                </tr>
                <tr>
                    <th>Disponível</th>
                    <td><?= (int) $livro['quantidade_disponivel'] ?></td>
                </tr>
            </table>

            <p class="confirmacao-aviso">
                O livro <strong>não será excluído</strong>; apenas ficará marcado como
                inativo e não aparecerá na listagem padrão nem estará disponível para
                novos empréstimos.
            </p>
        </div>

        <form method="POST"
              action="<?= e(BASE_URL) ?>/livros/livros-back.php">

            <!-- CSRF -->
            <?= csrf_input() ?>
            <input type="hidden" name="action" value="inativar">
            <input type="hidden" name="id"     value="<?= (int) $livro['id'] ?>">

            <div class="form-acoes">
                <button type="submit" class="btn btn-perigo">
                    Confirmar Inativação
                </button>
                <a href="<?= e(BASE_URL) ?>/livros/listar.php" class="btn btn-secundario">
                    Cancelar
                </a>
            </div>
        </form>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
