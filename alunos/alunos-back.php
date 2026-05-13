<?php
/**
 * BiblioTech — Backend do Módulo de Alunos
 *
 * Centraliza todas as operações de escrita:
 *   - action=cadastrar : insere novo aluno
 *   - action=editar    : atualiza aluno existente
 *   - action=inativar  : desativa aluno (ativo = 0)
 *
 * Regras de segurança aplicadas:
 *   · Apenas POST é aceito
 *   · CSRF validado em toda ação (csrf_validar())
 *   · Permissão verificada individualmente em cada action
 *   · Todos os IDs validados como inteiros positivos
 *   · Prepared statements em todas as queries (PDO)
 *   · Dados do usuário nunca concatenados no SQL
 *   · Sem exclusão física — apenas ativo = 0
 *   · Matrícula única verificada antes de inserir/atualizar
 *   · E-mail validado com FILTER_VALIDATE_EMAIL quando preenchido
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/conexao.php';

// ─── Somente POST ────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirecionar(BASE_URL . '/alunos/listar.php');
}

// ─── CSRF validado uma única vez antes de qualquer lógica ────────────────────
csrf_validar();

// ─── Action ──────────────────────────────────────────────────────────────────
$action = trim((string) ($_POST['action'] ?? ''));

// ============================================================================
// CADASTRAR
// ============================================================================
if ($action === 'cadastrar') {

    requirePermission('alunos.cadastrar');

    // --- Coleta e sanitização ------------------------------------------------
    $nome      = trim((string) ($_POST['nome']      ?? ''));
    $matricula = trim((string) ($_POST['matricula'] ?? ''));
    $turma     = trim((string) ($_POST['turma']     ?? ''));
    $email     = trim((string) ($_POST['email']     ?? ''));
    $telefone  = trim((string) ($_POST['telefone']  ?? ''));

    // --- Validações ----------------------------------------------------------
    $erros = [];

    if ($nome === '') {
        $erros[] = 'O nome é obrigatório.';
    } elseif (mb_strlen($nome) > 150) {
        $erros[] = 'O nome deve ter no máximo 150 caracteres.';
    }

    if ($matricula === '') {
        $erros[] = 'A matrícula é obrigatória.';
    } elseif (mb_strlen($matricula) > 50) {
        $erros[] = 'A matrícula deve ter no máximo 50 caracteres.';
    }

    if ($turma !== '' && mb_strlen($turma) > 50) {
        $erros[] = 'A turma deve ter no máximo 50 caracteres.';
    }

    if ($email !== '' && !email_valido($email)) {
        $erros[] = 'O e-mail informado não é válido.';
    }

    if ($email !== '' && mb_strlen($email) > 150) {
        $erros[] = 'O e-mail deve ter no máximo 150 caracteres.';
    }

    if ($telefone !== '' && mb_strlen($telefone) > 20) {
        $erros[] = 'O telefone deve ter no máximo 20 caracteres.';
    }

    // Verifica matrícula duplicada
    if ($matricula !== '' && empty($erros)) {
        try {
            $stmtDup = $pdo->prepare('SELECT COUNT(*) FROM alunos WHERE matricula = :matricula');
            $stmtDup->execute([':matricula' => $matricula]);
            if ((int) $stmtDup->fetchColumn() > 0) {
                $erros[] = 'Já existe um aluno cadastrado com esta matrícula.';
            }
        } catch (PDOException $e) {
            error_log('[BiblioTech] alunos/cadastrar dup check: ' . $e->getMessage());
            $erros[] = 'Erro ao verificar matrícula. Tente novamente.';
        }
    }

    if (!empty($erros)) {
        foreach ($erros as $erro) {
            flash('erro', $erro);
        }
        $_SESSION['form_aluno'] = compact('nome', 'matricula', 'turma', 'email', 'telefone');
        redirecionar(BASE_URL . '/alunos/cadastrar.php');
    }

    // --- Persistência --------------------------------------------------------
    try {
        $stmt = $pdo->prepare(
            'INSERT INTO alunos (nome, matricula, turma, email, telefone, ativo)
             VALUES (:nome, :matricula, :turma, :email, :telefone, 1)'
        );
        $stmt->execute([
            ':nome'      => $nome,
            ':matricula' => $matricula,
            ':turma'     => $turma    !== '' ? $turma    : null,
            ':email'     => $email    !== '' ? $email    : null,
            ':telefone'  => $telefone !== '' ? $telefone : null,
        ]);

        flash('sucesso', 'Aluno cadastrado com sucesso!');
        redirecionar(BASE_URL . '/alunos/listar.php');

    } catch (PDOException $e) {
        error_log('[BiblioTech] alunos/cadastrar insert: ' . $e->getMessage());
        flash('erro', 'Erro ao cadastrar aluno. Tente novamente.');
        $_SESSION['form_aluno'] = compact('nome', 'matricula', 'turma', 'email', 'telefone');
        redirecionar(BASE_URL . '/alunos/cadastrar.php');
    }
}

// ============================================================================
// EDITAR
// ============================================================================
if ($action === 'editar') {

    requirePermission('alunos.editar');

    // --- ID ------------------------------------------------------------------
    $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 1],
    ]);

    if ($id === false || $id === null) {
        flash('erro', 'ID do aluno inválido.');
        redirecionar(BASE_URL . '/alunos/listar.php');
    }

    // --- Coleta e sanitização ------------------------------------------------
    $nome      = trim((string) ($_POST['nome']      ?? ''));
    $matricula = trim((string) ($_POST['matricula'] ?? ''));
    $turma     = trim((string) ($_POST['turma']     ?? ''));
    $email     = trim((string) ($_POST['email']     ?? ''));
    $telefone  = trim((string) ($_POST['telefone']  ?? ''));

    // --- Validações ----------------------------------------------------------
    $erros = [];

    if ($nome === '') {
        $erros[] = 'O nome é obrigatório.';
    } elseif (mb_strlen($nome) > 150) {
        $erros[] = 'O nome deve ter no máximo 150 caracteres.';
    }

    if ($matricula === '') {
        $erros[] = 'A matrícula é obrigatória.';
    } elseif (mb_strlen($matricula) > 50) {
        $erros[] = 'A matrícula deve ter no máximo 50 caracteres.';
    }

    if ($turma !== '' && mb_strlen($turma) > 50) {
        $erros[] = 'A turma deve ter no máximo 50 caracteres.';
    }

    if ($email !== '' && !email_valido($email)) {
        $erros[] = 'O e-mail informado não é válido.';
    }

    if ($email !== '' && mb_strlen($email) > 150) {
        $erros[] = 'O e-mail deve ter no máximo 150 caracteres.';
    }

    if ($telefone !== '' && mb_strlen($telefone) > 20) {
        $erros[] = 'O telefone deve ter no máximo 20 caracteres.';
    }

    // Verifica matrícula duplicada excluindo o próprio aluno
    if ($matricula !== '' && empty($erros)) {
        try {
            $stmtDup = $pdo->prepare(
                'SELECT COUNT(*) FROM alunos WHERE matricula = :matricula AND id != :id'
            );
            $stmtDup->execute([':matricula' => $matricula, ':id' => $id]);
            if ((int) $stmtDup->fetchColumn() > 0) {
                $erros[] = 'Já existe outro aluno cadastrado com esta matrícula.';
            }
        } catch (PDOException $e) {
            error_log('[BiblioTech] alunos/editar dup check: ' . $e->getMessage());
            $erros[] = 'Erro ao verificar matrícula. Tente novamente.';
        }
    }

    if (!empty($erros)) {
        foreach ($erros as $erro) {
            flash('erro', $erro);
        }
        $_SESSION['form_aluno'] = compact('nome', 'matricula', 'turma', 'email', 'telefone');
        redirecionar(BASE_URL . '/alunos/editar.php?id=' . $id);
    }

    // --- Persistência --------------------------------------------------------
    try {
        $stmt = $pdo->prepare(
            'UPDATE alunos
                SET nome      = :nome,
                    matricula = :matricula,
                    turma     = :turma,
                    email     = :email,
                    telefone  = :telefone
              WHERE id = :id'
        );
        $stmt->execute([
            ':nome'      => $nome,
            ':matricula' => $matricula,
            ':turma'     => $turma    !== '' ? $turma    : null,
            ':email'     => $email    !== '' ? $email    : null,
            ':telefone'  => $telefone !== '' ? $telefone : null,
            ':id'        => $id,
        ]);

        if ($stmt->rowCount() === 0) {
            flash('erro', 'Nenhuma alteração foi salva. Verifique os dados e tente novamente.');
            redirecionar(BASE_URL . '/alunos/editar.php?id=' . $id);
        }

        flash('sucesso', 'Aluno atualizado com sucesso!');
        redirecionar(BASE_URL . '/alunos/listar.php');

    } catch (PDOException $e) {
        error_log('[BiblioTech] alunos/editar update: ' . $e->getMessage());
        flash('erro', 'Erro ao atualizar aluno. Tente novamente.');
        $_SESSION['form_aluno'] = compact('nome', 'matricula', 'turma', 'email', 'telefone');
        redirecionar(BASE_URL . '/alunos/editar.php?id=' . $id);
    }
}

// ============================================================================
// INATIVAR
// ============================================================================
if ($action === 'inativar') {

    requirePermission('alunos.inativar');

    // --- ID ------------------------------------------------------------------
    $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 1],
    ]);

    if ($id === false || $id === null) {
        flash('erro', 'ID do aluno inválido.');
        redirecionar(BASE_URL . '/alunos/listar.php');
    }

    try {
        // --- Confirma existência e status ativo ------------------------------
        $stmtAluno = $pdo->prepare('SELECT id, nome, ativo FROM alunos WHERE id = :id');
        $stmtAluno->execute([':id' => $id]);
        $aluno = $stmtAluno->fetch();

        if (!$aluno) {
            flash('erro', 'Aluno não encontrado.');
            redirecionar(BASE_URL . '/alunos/listar.php');
        }

        if ((int) $aluno['ativo'] === 0) {
            flash('erro', 'Este aluno já está inativo.');
            redirecionar(BASE_URL . '/alunos/listar.php');
        }

        // --- Regra de negócio: bloqueia se houver empréstimos pendentes ------
        $stmtEmp = $pdo->prepare(
            "SELECT COUNT(*) AS total
               FROM emprestimos
              WHERE aluno_id = :id
                AND status  IN ('ativo', 'atrasado')"
        );
        $stmtEmp->execute([':id' => $id]);
        $totalEmprestimos = (int) $stmtEmp->fetchColumn();

        if ($totalEmprestimos > 0) {
            flash('erro',
                'Não é possível inativar este aluno pois há ' . $totalEmprestimos .
                ' empréstimo(s) ativo(s) ou em atraso. Regularize antes de inativar.'
            );
            redirecionar(BASE_URL . '/alunos/listar.php');
        }

        // --- Inativa o aluno: apenas ativo = 0, sem exclusão física ----------
        $stmtUpd = $pdo->prepare(
            'UPDATE alunos SET ativo = 0 WHERE id = :id AND ativo = 1'
        );
        $stmtUpd->execute([':id' => $id]);

        if ($stmtUpd->rowCount() === 0) {
            flash('erro', 'Aluno não encontrado ou já inativo.');
            redirecionar(BASE_URL . '/alunos/listar.php');
        }

        flash('sucesso', 'Aluno inativado com sucesso!');
        redirecionar(BASE_URL . '/alunos/listar.php');

    } catch (PDOException $e) {
        error_log('[BiblioTech] alunos/inativar: ' . $e->getMessage());
        flash('erro', 'Erro ao inativar aluno. Tente novamente.');
        redirecionar(BASE_URL . '/alunos/listar.php');
    }
}

// ─── Ação desconhecida ───────────────────────────────────────────────────────
flash('erro', 'Ação inválida.');
redirecionar(BASE_URL . '/alunos/listar.php');