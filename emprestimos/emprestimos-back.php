<?php
/**
 * BiblioTech — Backend do Módulo de Empréstimos
 *
 * Centraliza todas as operações de escrita do módulo:
 *   - action=cadastrar : registra um novo empréstimo (transação)
 *   - action=devolver  : registra a devolução de um empréstimo (transação)
 *
 * Regras de segurança aplicadas:
 *   · Apenas POST é aceito (operações de escrita).
 *   · CSRF validado em toda ação (csrf_validar()).
 *   · Permissões verificadas no backend (requirePermission()).
 *   · IDs validados como inteiros positivos (filter_input + min_range=1).
 *   · Todas as queries usam PDO + prepared statements (named placeholders).
 *   · Nenhum dado do usuário é concatenado em SQL.
 *   · Datas validadas com DateTime::createFromFormat para evitar formatos inválidos.
 *   · usuario_id sempre vem da sessão — nunca confia no POST.
 *   · Transação com beginTransaction/commit/rollBack quando mais de uma tabela
 *     é afetada e o estado precisa ser consistente.
 *   · SELECT ... FOR UPDATE para travar a linha do livro e impedir condições
 *     de corrida com quantidade_disponivel (dois empréstimos simultâneos).
 *   · Empréstimos nunca são excluídos — apenas mudam de status.
 *   · Mensagens técnicas (PDOException) vão para error_log, nunca ao usuário.
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/conexao.php';

// ─── Somente POST ────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirecionar(BASE_URL . '/emprestimos/listar.php');
}

// ─── CSRF ────────────────────────────────────────────────────────────────────
csrf_validar();

// ─── Action ──────────────────────────────────────────────────────────────────
$action = trim((string) ($_POST['action'] ?? ''));

/**
 * Valida uma data no formato YYYY-MM-DD.
 * Retorna a string normalizada (Y-m-d) ou null se inválida.
 */
function validar_data_iso(string $data): ?string
{
    $dt = DateTime::createFromFormat('Y-m-d', $data);
    if ($dt === false) {
        return null;
    }
    // createFromFormat aceita '2025-02-31' e ajusta — checamos formatação reversa
    if ($dt->format('Y-m-d') !== $data) {
        return null;
    }
    return $data;
}


// ============================================================================
// CADASTRAR
// ============================================================================
if ($action === 'cadastrar') {

    requirePermission('emprestimos.cadastrar');

    // --- Coleta -------------------------------------------------------------
    $aluno_id                = filter_input(INPUT_POST, 'aluno_id', FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 1],
    ]);
    $livro_id                = filter_input(INPUT_POST, 'livro_id', FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 1],
    ]);
    $data_emprestimo         = trim((string) ($_POST['data_emprestimo']         ?? ''));
    $data_prevista_devolucao = trim((string) ($_POST['data_prevista_devolucao'] ?? ''));
    $observacao              = trim((string) ($_POST['observacao']              ?? ''));

    // Preserva os dados para repopular o formulário em caso de erro
    $_SESSION['form_emprestimo'] = [
        'aluno_id'                => $aluno_id !== false && $aluno_id !== null ? (int) $aluno_id : '',
        'livro_id'                => $livro_id !== false && $livro_id !== null ? (int) $livro_id : '',
        'data_emprestimo'         => $data_emprestimo,
        'data_prevista_devolucao' => $data_prevista_devolucao,
        'observacao'              => $observacao,
    ];

    // --- Validações ----------------------------------------------------------
    $erros = [];

    if ($aluno_id === false || $aluno_id === null) {
        $erros[] = 'Selecione um aluno válido.';
    }

    if ($livro_id === false || $livro_id === null) {
        $erros[] = 'Selecione um livro válido.';
    }

    if ($data_emprestimo === '') {
        $erros[] = 'A data do empréstimo é obrigatória.';
    } elseif (validar_data_iso($data_emprestimo) === null) {
        $erros[] = 'A data do empréstimo é inválida.';
    }

    if ($data_prevista_devolucao === '') {
        $erros[] = 'A data prevista de devolução é obrigatória.';
    } elseif (validar_data_iso($data_prevista_devolucao) === null) {
        $erros[] = 'A data prevista de devolução é inválida.';
    }

    // Comparação só se ambas as datas forem válidas
    if (empty($erros)
        && strtotime($data_prevista_devolucao) < strtotime($data_emprestimo)) {
        $erros[] = 'A data prevista de devolução deve ser igual ou posterior à data do empréstimo.';
    }

    if (mb_strlen($observacao) > 1000) {
        $erros[] = 'A observação deve ter no máximo 1000 caracteres.';
    }

    if (!empty($erros)) {
        foreach ($erros as $erro) {
            flash('erro', $erro);
        }
        redirecionar(BASE_URL . '/emprestimos/cadastrar.php');
    }

    // usuario_id sempre da sessão — JAMAIS confiar no POST
    $usuario_id = (int) ($_SESSION['usuario_id'] ?? 0);
    if ($usuario_id < 1) {
        flash('erro', 'Sessão inválida. Faça login novamente.');
        redirecionar(BASE_URL . '/login.php');
    }

    // --- Pré-checagens (fora da transação, apenas para mensagens amigáveis) --
    try {
        // Aluno existe e está ativo?
        $stmtAluno = $pdo->prepare(
            'SELECT id FROM alunos WHERE id = :id AND ativo = 1'
        );
        $stmtAluno->execute([':id' => (int) $aluno_id]);
        if (!$stmtAluno->fetch()) {
            flash('erro', 'Aluno não encontrado ou inativo.');
            redirecionar(BASE_URL . '/emprestimos/cadastrar.php');
        }

        // Livro existe e está ativo? (a disponibilidade é re-checada dentro da transação)
        $stmtLivro = $pdo->prepare(
            'SELECT id FROM livros WHERE id = :id AND ativo = 1'
        );
        $stmtLivro->execute([':id' => (int) $livro_id]);
        if (!$stmtLivro->fetch()) {
            flash('erro', 'Livro não encontrado ou inativo.');
            redirecionar(BASE_URL . '/emprestimos/cadastrar.php');
        }

    } catch (PDOException $e) {
        error_log('[BiblioTech] emprestimos/cadastrar precheck: ' . $e->getMessage());
        flash('erro', 'Erro ao validar os dados. Tente novamente.');
        redirecionar(BASE_URL . '/emprestimos/cadastrar.php');
    }

    // --- Transação ----------------------------------------------------------
    // Passos:
    //   1. SELECT ... FOR UPDATE no livro (trava a linha).
    //   2. Verifica quantidade_disponivel > 0 (re-verificação no backend).
    //   3. INSERT em emprestimos.
    //   4. UPDATE em livros (quantidade_disponivel = quantidade_disponivel - 1).
    //   5. COMMIT. Em qualquer falha, rollBack().
    try {
        $pdo->beginTransaction();

        // 1. Trava a linha do livro
        $stmtLock = $pdo->prepare(
            'SELECT quantidade_disponivel
               FROM livros
              WHERE id = :id
                AND ativo = 1
              FOR UPDATE'
        );
        $stmtLock->execute([':id' => (int) $livro_id]);
        $livro = $stmtLock->fetch();

        if (!$livro) {
            $pdo->rollBack();
            flash('erro', 'Livro não encontrado ou inativo.');
            redirecionar(BASE_URL . '/emprestimos/cadastrar.php');
        }

        // 2. Re-checa disponibilidade DENTRO da transação
        $disponivel = (int) $livro['quantidade_disponivel'];
        if ($disponivel < 1) {
            $pdo->rollBack();
            flash('erro', 'Não há exemplares disponíveis deste livro no momento.');
            redirecionar(BASE_URL . '/emprestimos/cadastrar.php');
        }

        // 3. Insere o empréstimo
        $stmtIns = $pdo->prepare(
            'INSERT INTO emprestimos
                (aluno_id, livro_id, usuario_id,
                 data_emprestimo, data_prevista_devolucao,
                 status, observacao)
             VALUES
                (:aluno_id, :livro_id, :usuario_id,
                 :data_emp, :data_prev,
                 \'ativo\', :obs)'
        );
        $stmtIns->execute([
            ':aluno_id'   => (int) $aluno_id,
            ':livro_id'   => (int) $livro_id,
            ':usuario_id' => $usuario_id,
            ':data_emp'   => $data_emprestimo,
            ':data_prev'  => $data_prevista_devolucao,
            ':obs'        => $observacao !== '' ? $observacao : null,
        ]);

        // 4. Decrementa a quantidade disponível (com guarda extra >= 1 para
        //    impossibilitar valor negativo mesmo sob condição de corrida)
        $stmtUpd = $pdo->prepare(
            'UPDATE livros
                SET quantidade_disponivel = quantidade_disponivel - 1
              WHERE id = :id
                AND quantidade_disponivel >= 1'
        );
        $stmtUpd->execute([':id' => (int) $livro_id]);

        if ($stmtUpd->rowCount() === 0) {
            // Nenhuma linha afetada significa que a guarda impediu o decremento.
            // Algo está inconsistente — aborta a transação.
            $pdo->rollBack();
            flash('erro', 'Não foi possível atualizar o estoque do livro. Tente novamente.');
            redirecionar(BASE_URL . '/emprestimos/cadastrar.php');
        }

        $pdo->commit();

        // Sucesso: limpa o "old" da sessão
        unset($_SESSION['form_emprestimo']);

        flash('sucesso', 'Empréstimo registrado com sucesso!');
        redirecionar(BASE_URL . '/emprestimos/listar.php');

    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('[BiblioTech] emprestimos/cadastrar tx: ' . $e->getMessage());
        flash('erro', 'Erro ao registrar empréstimo. Tente novamente.');
        redirecionar(BASE_URL . '/emprestimos/cadastrar.php');
    }
}


// ============================================================================
// DEVOLVER
// ============================================================================
if ($action === 'devolver') {

    requirePermission('emprestimos.devolver');

    // --- ID ------------------------------------------------------------------
    $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 1],
    ]);

    if ($id === false || $id === null) {
        flash('erro', 'ID do empréstimo inválido.');
        redirecionar(BASE_URL . '/emprestimos/listar.php');
    }

    // --- Data de devolução ---------------------------------------------------
    $data_devolucao = trim((string) ($_POST['data_devolucao'] ?? ''));
    if ($data_devolucao === '') {
        $data_devolucao = date('Y-m-d');
    }
    if (validar_data_iso($data_devolucao) === null) {
        flash('erro', 'A data de devolução é inválida.');
        redirecionar(BASE_URL . '/emprestimos/devolver.php?id=' . $id);
    }

    // --- Transação ----------------------------------------------------------
    // Passos:
    //   1. SELECT ... FOR UPDATE no empréstimo (trava a linha).
    //   2. Verifica se existe e ainda não foi devolvido.
    //   3. Valida que data_devolucao >= data_emprestimo.
    //   4. SELECT ... FOR UPDATE no livro (trava também a linha do livro).
    //   5. Garante que quantidade_disponivel + 1 não excede quantidade_total.
    //   6. UPDATE em emprestimos (status='devolvido', data_devolucao=...).
    //   7. UPDATE em livros (quantidade_disponivel + 1).
    //   8. COMMIT. Em qualquer falha, rollBack().
    try {
        $pdo->beginTransaction();

        // 1. Trava o empréstimo
        $stmtLockEmp = $pdo->prepare(
            'SELECT id, livro_id, data_emprestimo, status, data_devolucao
               FROM emprestimos
              WHERE id = :id
              FOR UPDATE'
        );
        $stmtLockEmp->execute([':id' => $id]);
        $emprestimo = $stmtLockEmp->fetch();

        // 2. Existe?
        if (!$emprestimo) {
            $pdo->rollBack();
            flash('erro', 'Empréstimo não encontrado.');
            redirecionar(BASE_URL . '/emprestimos/listar.php');
        }

        // 2b. Já foi devolvido?
        if ($emprestimo['status'] === 'devolvido' || $emprestimo['data_devolucao'] !== null) {
            $pdo->rollBack();
            flash('erro', 'Este empréstimo já foi devolvido.');
            redirecionar(BASE_URL . '/emprestimos/listar.php');
        }

        // 3. Data de devolução >= data de empréstimo?
        if (strtotime($data_devolucao) < strtotime($emprestimo['data_emprestimo'])) {
            $pdo->rollBack();
            flash('erro', 'A data de devolução não pode ser anterior à data do empréstimo.');
            redirecionar(BASE_URL . '/emprestimos/devolver.php?id=' . $id);
        }

        // 4. Trava o livro (também travamos mesmo se já travamos o empréstimo,
        //    pois vamos alterar quantidade_disponivel)
        $stmtLockLivro = $pdo->prepare(
            'SELECT id, quantidade_total, quantidade_disponivel
               FROM livros
              WHERE id = :id
              FOR UPDATE'
        );
        $stmtLockLivro->execute([':id' => (int) $emprestimo['livro_id']]);
        $livro = $stmtLockLivro->fetch();

        if (!$livro) {
            // Não deveria acontecer (FK RESTRICT), mas verificamos por segurança
            $pdo->rollBack();
            flash('erro', 'Livro associado ao empréstimo não foi encontrado.');
            redirecionar(BASE_URL . '/emprestimos/listar.php');
        }

        // 5. Não permitir que quantidade_disponivel ultrapasse quantidade_total
        $total = (int) $livro['quantidade_total'];
        $disp  = (int) $livro['quantidade_disponivel'];
        if ($disp + 1 > $total) {
            $pdo->rollBack();
            flash('erro',
                'Inconsistência detectada: o estoque deste livro já está no máximo. '
              . 'A devolução não pode ser registrada. Contate um administrador.');
            redirecionar(BASE_URL . '/emprestimos/listar.php');
        }

        // 6. Marca como devolvido
        $stmtUpdEmp = $pdo->prepare(
            'UPDATE emprestimos
                SET status         = \'devolvido\',
                    data_devolucao = :data_dev
              WHERE id = :id
                AND status IN (\'ativo\', \'em_atraso\')'
        );
        $stmtUpdEmp->execute([
            ':data_dev' => $data_devolucao,
            ':id'       => $id,
        ]);

        if ($stmtUpdEmp->rowCount() === 0) {
            // Outra requisição pode ter devolvido antes — aborta
            $pdo->rollBack();
            flash('erro', 'Não foi possível registrar a devolução. O empréstimo pode já ter sido devolvido.');
            redirecionar(BASE_URL . '/emprestimos/listar.php');
        }

        // 7. Incrementa a quantidade disponível (com guarda no SQL para impedir
        //    que ultrapasse quantidade_total mesmo sob concorrência)
        $stmtUpdLivro = $pdo->prepare(
            'UPDATE livros
                SET quantidade_disponivel = quantidade_disponivel + 1
              WHERE id = :id
                AND quantidade_disponivel < quantidade_total'
        );
        $stmtUpdLivro->execute([':id' => (int) $emprestimo['livro_id']]);

        if ($stmtUpdLivro->rowCount() === 0) {
            $pdo->rollBack();
            flash('erro', 'Não foi possível atualizar o estoque do livro. Tente novamente.');
            redirecionar(BASE_URL . '/emprestimos/listar.php');
        }

        $pdo->commit();

        flash('sucesso', 'Devolução registrada com sucesso!');
        redirecionar(BASE_URL . '/emprestimos/listar.php');

    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('[BiblioTech] emprestimos/devolver tx: ' . $e->getMessage());
        flash('erro', 'Erro ao registrar devolução. Tente novamente.');
        redirecionar(BASE_URL . '/emprestimos/listar.php');
    }
}


// ─── Ação desconhecida ───────────────────────────────────────────────────────
flash('erro', 'Ação inválida.');
redirecionar(BASE_URL . '/emprestimos/listar.php');
