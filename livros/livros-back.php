<?php
/**
 * BiblioTech — Backend do Módulo de Livros
 *
 * Centraliza todas as operações de escrita:
 *   - action=cadastrar : insere novo livro
 *   - action=editar    : atualiza livro existente
 *   - action=inativar  : desativa livro (ativo = 0)
 *
 * Regras de segurança aplicadas:
 *   · Apenas POST é aceito
 *   · CSRF validado em toda ação
 *   · Todos os IDs validados como inteiros positivos
 *   · Prepared statements em todas as queries (PDO)
 *   · Dados do usuário nunca concatenados no SQL
 *   · Transação quando mais de uma tabela seria afetada (inativar)
 *   · Sem exclusão física — apenas ativo = 0
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/conexao.php';

// ─── Somente POST ────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirecionar(BASE_URL . '/livros/listar.php');
}

// ─── CSRF ────────────────────────────────────────────────────────────────────
csrf_validar();

// ─── Action ──────────────────────────────────────────────────────────────────
$action = trim((string) ($_POST['action'] ?? ''));

// ============================================================================
// CADASTRAR
// ============================================================================
if ($action === 'cadastrar') {

    requirePermission('livros.cadastrar');

    // --- Coleta e sanitização ------------------------------------------------
    $titulo          = trim((string) ($_POST['titulo']          ?? ''));
    $autor           = trim((string) ($_POST['autor']           ?? ''));
    $isbn            = trim((string) ($_POST['isbn']            ?? ''));
    $editora         = trim((string) ($_POST['editora']         ?? ''));
    $ano_publicacao  = trim((string) ($_POST['ano_publicacao']  ?? ''));
    $quantidade_total = (int) ($_POST['quantidade_total'] ?? -1);

    // --- Validações ----------------------------------------------------------
    $erros = [];

    if ($titulo === '') {
        $erros[] = 'O título é obrigatório.';
    } elseif (mb_strlen($titulo) > 255) {
        $erros[] = 'O título deve ter no máximo 255 caracteres.';
    }

    if ($autor === '') {
        $erros[] = 'O autor é obrigatório.';
    } elseif (mb_strlen($autor) > 255) {
        $erros[] = 'O autor deve ter no máximo 255 caracteres.';
    }

    if ($isbn !== '' && mb_strlen($isbn) > 20) {
        $erros[] = 'O ISBN deve ter no máximo 20 caracteres.';
    }

    if ($editora !== '' && mb_strlen($editora) > 255) {
        $erros[] = 'A editora deve ter no máximo 255 caracteres.';
    }

    // Ano: opcional, mas quando preenchido deve ser numérico entre 1000 e ano atual
    $ano_val = null;
    if ($ano_publicacao !== '') {
        if (!ctype_digit($ano_publicacao)) {
            $erros[] = 'O ano de publicação deve conter apenas números.';
        } else {
            $ano_int = (int) $ano_publicacao;
            if ($ano_int < 1000 || $ano_int > (int) date('Y')) {
                $erros[] = 'O ano de publicação deve estar entre 1000 e ' . date('Y') . '.';
            } else {
                $ano_val = $ano_int;
            }
        }
    }

    if ($quantidade_total < 0) {
        $erros[] = 'A quantidade total não pode ser negativa.';
    }

    if (!empty($erros)) {
        foreach ($erros as $erro) {
            flash('erro', $erro);
        }
        redirecionar(BASE_URL . '/livros/cadastrar.php');
    }

    // Ao cadastrar, quantidade_disponivel = quantidade_total
    $quantidade_disponivel = $quantidade_total;

    // --- Persistência --------------------------------------------------------
    try {
        $sql = 'INSERT INTO livros
                    (titulo, autor, isbn, editora, ano_publicacao,
                     quantidade_total, quantidade_disponivel, ativo)
                VALUES
                    (:titulo, :autor, :isbn, :editora, :ano,
                     :qtd_total, :qtd_disp, 1)';

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':titulo'   => $titulo,
            ':autor'    => $autor,
            ':isbn'     => $isbn     !== '' ? $isbn    : null,
            ':editora'  => $editora  !== '' ? $editora : null,
            ':ano'      => $ano_val,
            ':qtd_total' => $quantidade_total,
            ':qtd_disp'  => $quantidade_disponivel,
        ]);

        flash('sucesso', 'Livro cadastrado com sucesso!');
        redirecionar(BASE_URL . '/livros/listar.php');

    } catch (PDOException $e) {
        error_log('[BiblioTech] livros/cadastrar: ' . $e->getMessage());
        flash('erro', 'Erro ao cadastrar livro. Tente novamente.');
        redirecionar(BASE_URL . '/livros/cadastrar.php');
    }
}

// ============================================================================
// EDITAR
// ============================================================================
if ($action === 'editar') {

    requirePermission('livros.editar');

    // --- ID ------------------------------------------------------------------
    $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 1],
    ]);

    if ($id === false || $id === null) {
        flash('erro', 'ID do livro inválido.');
        redirecionar(BASE_URL . '/livros/listar.php');
    }

    // --- Coleta e sanitização ------------------------------------------------
    $titulo          = trim((string) ($_POST['titulo']         ?? ''));
    $autor           = trim((string) ($_POST['autor']          ?? ''));
    $isbn            = trim((string) ($_POST['isbn']           ?? ''));
    $editora         = trim((string) ($_POST['editora']        ?? ''));
    $ano_publicacao  = trim((string) ($_POST['ano_publicacao'] ?? ''));
    $quantidade_total = (int) ($_POST['quantidade_total'] ?? -1);

    // --- Validações ----------------------------------------------------------
    $erros = [];

    if ($titulo === '') {
        $erros[] = 'O título é obrigatório.';
    } elseif (mb_strlen($titulo) > 255) {
        $erros[] = 'O título deve ter no máximo 255 caracteres.';
    }

    if ($autor === '') {
        $erros[] = 'O autor é obrigatório.';
    } elseif (mb_strlen($autor) > 255) {
        $erros[] = 'O autor deve ter no máximo 255 caracteres.';
    }

    if ($isbn !== '' && mb_strlen($isbn) > 20) {
        $erros[] = 'O ISBN deve ter no máximo 20 caracteres.';
    }

    if ($editora !== '' && mb_strlen($editora) > 255) {
        $erros[] = 'A editora deve ter no máximo 255 caracteres.';
    }

    $ano_val = null;
    if ($ano_publicacao !== '') {
        if (!ctype_digit($ano_publicacao)) {
            $erros[] = 'O ano de publicação deve conter apenas números.';
        } else {
            $ano_int = (int) $ano_publicacao;
            if ($ano_int < 1000 || $ano_int > (int) date('Y')) {
                $erros[] = 'O ano de publicação deve estar entre 1000 e ' . date('Y') . '.';
            } else {
                $ano_val = $ano_int;
            }
        }
    }

    if ($quantidade_total < 0) {
        $erros[] = 'A quantidade total não pode ser negativa.';
    }

    if (!empty($erros)) {
        foreach ($erros as $erro) {
            flash('erro', $erro);
        }
        redirecionar(BASE_URL . '/livros/editar.php?id=' . $id);
    }

    // --- Busca livro atual para recalcular quantidade_disponivel -------------
    try {
        $stmtAtual = $pdo->prepare(
            'SELECT quantidade_total, quantidade_disponivel FROM livros WHERE id = :id AND ativo = 1'
        );
        $stmtAtual->execute([':id' => $id]);
        $livroAtual = $stmtAtual->fetch();

    } catch (PDOException $e) {
        error_log('[BiblioTech] livros/editar busca: ' . $e->getMessage());
        flash('erro', 'Erro ao buscar dados do livro.');
        redirecionar(BASE_URL . '/livros/editar.php?id=' . $id);
    }

    if (!$livroAtual) {
        flash('erro', 'Livro não encontrado ou inativo.');
        redirecionar(BASE_URL . '/livros/listar.php');
    }

    // Recalcula disponivel: mantém proporção de emprestados
    $emprestados          = (int) $livroAtual['quantidade_total'] - (int) $livroAtual['quantidade_disponivel'];
    $nova_qtd_disponivel  = $quantidade_total - $emprestados;

    // Disponível não pode ser negativo (mais exemplares emprestados do que o novo total)
    if ($nova_qtd_disponivel < 0) {
        flash('erro', 'A nova quantidade total (' . $quantidade_total . ') é menor do que os '
              . $emprestados . ' exemplar(es) atualmente emprestado(s). '
              . 'Aguarde as devoluções antes de reduzir o acervo.');
        redirecionar(BASE_URL . '/livros/editar.php?id=' . $id);
    }

    // Disponível não pode superar o total
    if ($nova_qtd_disponivel > $quantidade_total) {
        $nova_qtd_disponivel = $quantidade_total;
    }

    // --- Persistência --------------------------------------------------------
    try {
        $sql = 'UPDATE livros
                   SET titulo               = :titulo,
                       autor                = :autor,
                       isbn                 = :isbn,
                       editora              = :editora,
                       ano_publicacao       = :ano,
                       quantidade_total     = :qtd_total,
                       quantidade_disponivel = :qtd_disp
                 WHERE id   = :id
                   AND ativo = 1';

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':titulo'    => $titulo,
            ':autor'     => $autor,
            ':isbn'      => $isbn    !== '' ? $isbn    : null,
            ':editora'   => $editora !== '' ? $editora : null,
            ':ano'       => $ano_val,
            ':qtd_total' => $quantidade_total,
            ':qtd_disp'  => $nova_qtd_disponivel,
            ':id'        => $id,
        ]);

        if ($stmt->rowCount() === 0) {
            flash('erro', 'Nenhuma alteração foi salva. Verifique os dados e tente novamente.');
            redirecionar(BASE_URL . '/livros/editar.php?id=' . $id);
        }

        flash('sucesso', 'Livro atualizado com sucesso!');
        redirecionar(BASE_URL . '/livros/listar.php');

    } catch (PDOException $e) {
        error_log('[BiblioTech] livros/editar update: ' . $e->getMessage());
        flash('erro', 'Erro ao atualizar livro. Tente novamente.');
        redirecionar(BASE_URL . '/livros/editar.php?id=' . $id);
    }
}

// ============================================================================
// INATIVAR
// ============================================================================
if ($action === 'inativar') {

    requirePermission('livros.inativar');

    // --- ID ------------------------------------------------------------------
    $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 1],
    ]);

    if ($id === false || $id === null) {
        flash('erro', 'ID do livro inválido.');
        redirecionar(BASE_URL . '/livros/listar.php');
    }

    try {
        // --- Verifica empréstimos ativos ou em atraso ------------------------
        $stmtEmp = $pdo->prepare(
            "SELECT COUNT(*) AS total
               FROM emprestimos
              WHERE livro_id  = :id
                AND status   IN ('ativo', 'em_atraso')"
        );
        $stmtEmp->execute([':id' => $id]);
        $totalEmprestimos = (int) $stmtEmp->fetchColumn();

        if ($totalEmprestimos > 0) {
            flash('erro',
                'Não é possível inativar este livro pois há ' . $totalEmprestimos .
                ' empréstimo(s) ativo(s) ou em atraso. Aguarde as devoluções.'
            );
            redirecionar(BASE_URL . '/livros/listar.php');
        }

        // --- Inativa o livro (sem transação extra: apenas 1 tabela) ----------
        $stmtUpd = $pdo->prepare(
            'UPDATE livros SET ativo = 0 WHERE id = :id AND ativo = 1'
        );
        $stmtUpd->execute([':id' => $id]);

        if ($stmtUpd->rowCount() === 0) {
            flash('erro', 'Livro não encontrado ou já inativo.');
            redirecionar(BASE_URL . '/livros/listar.php');
        }

        flash('sucesso', 'Livro inativado com sucesso!');
        redirecionar(BASE_URL . '/livros/listar.php');

    } catch (PDOException $e) {
        error_log('[BiblioTech] livros/inativar: ' . $e->getMessage());
        flash('erro', 'Erro ao inativar livro. Tente novamente.');
        redirecionar(BASE_URL . '/livros/listar.php');
    }
}

// ─── Ação desconhecida ───────────────────────────────────────────────────────
flash('erro', 'Ação inválida.');
redirecionar(BASE_URL . '/livros/listar.php');
