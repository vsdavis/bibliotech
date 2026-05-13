<?php
/**
 * BiblioTech — Backend do Módulo de Usuários
 *
 * Centraliza todas as operações de escrita:
 *   - action=cadastrar   : cria usuário e concede permissões iniciais
 *   - action=editar      : atualiza usuário (senha opcional)
 *   - action=inativar    : marca usuário como inativo
 *   - action=permissoes  : salva permissões individuais
 *
 * Regras de segurança aplicadas:
 *   · Apenas POST é aceito
 *   · CSRF validado em toda ação
 *   · IDs validados como inteiros positivos
 *   · Prepared statements (PDO) em todas as queries
 *   · Verificação de permissão por ação (requirePermission)
 *   · password_hash() obrigatório antes de salvar senha
 *   · Transações nas operações que afetam mais de uma tabela
 *   · Salvaguardas: não inativa a si mesmo, não inativa último admin
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/conexao.php';

// ─── Somente POST ────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirecionar(BASE_URL . '/usuarios/listar.php');
}

// ─── CSRF ────────────────────────────────────────────────────────────────────
csrf_validar();

// ─── Action ──────────────────────────────────────────────────────────────────
$action = trim((string) ($_POST['action'] ?? ''));

// ============================================================================
// CADASTRAR
// ============================================================================
if ($action === 'cadastrar') {

    requirePermission('usuarios.cadastrar');

    // --- Coleta ---------------------------------------------------------------
    $nome     = trim((string) ($_POST['nome']     ?? ''));
    $email    = trim((string) ($_POST['email']    ?? ''));
    $senha    = (string)       ($_POST['senha']    ?? '');
    $confirma = (string)       ($_POST['confirma'] ?? '');
    $perfil   = trim((string) ($_POST['perfil']   ?? ''));

    // --- Validações ----------------------------------------------------------
    $erros = [];

    if ($nome === '' || mb_strlen($nome) > 100) {
        $erros[] = 'Informe um nome válido (até 100 caracteres).';
    }

    if ($email === '' || !email_valido($email) || mb_strlen($email) > 100) {
        $erros[] = 'Informe um e-mail válido.';
    }

    if ($senha === '' || mb_strlen($senha) < 6) {
        $erros[] = 'A senha é obrigatória e deve ter pelo menos 6 caracteres.';
    } elseif ($senha !== $confirma) {
        $erros[] = 'A senha e a confirmação não conferem.';
    }

    if (!in_array($perfil, ['admin', 'bibliotecario'], true)) {
        $erros[] = 'Perfil inválido.';
    }

    // --- E-mail duplicado ----------------------------------------------------
    if ($email !== '' && empty($erros)) {
        try {
            $stmt = $pdo->prepare('SELECT id FROM usuarios WHERE email = :e LIMIT 1');
            $stmt->execute([':e' => $email]);
            if ($stmt->fetch()) {
                $erros[] = 'Já existe um usuário com este e-mail.';
            }
        } catch (PDOException $e) {
            error_log('[BiblioTech] usuarios/cadastrar dup: ' . $e->getMessage());
            $erros[] = 'Erro ao validar e-mail.';
        }
    }

    if (!empty($erros)) {
        $_SESSION['form_usuario'] = [
            'nome'   => $nome,
            'email'  => $email,
            'perfil' => $perfil,
        ];
        foreach ($erros as $erro) {
            flash('erro', $erro);
        }
        redirecionar(BASE_URL . '/usuarios/cadastrar.php');
    }

    // --- Persistência (transação: usuario + permissões iniciais) -------------
    try {
        $pdo->beginTransaction();

        $hash = password_hash($senha, PASSWORD_DEFAULT);

        $stmt = $pdo->prepare(
            'INSERT INTO usuarios (nome, email, senha, perfil, ativo)
             VALUES (:nome, :email, :senha, :perfil, 1)'
        );
        $stmt->execute([
            ':nome'   => $nome,
            ':email'  => $email,
            ':senha'  => $hash,
            ':perfil' => $perfil,
        ]);
        $novo_id = (int) $pdo->lastInsertId();

        // Permissões iniciais por perfil
        if ($perfil === 'admin') {
            // Admin recebe TODAS as permissões ativas
            $pdo->prepare(
                'INSERT INTO usuario_permissoes (usuario_id, permissao_id, permitido)
                 SELECT :uid, p.id, 1
                   FROM permissoes p
                  WHERE p.ativo = 1'
            )->execute([':uid' => $novo_id]);
        } else {
            // Bibliotecário recebe apenas dashboard.visualizar como base
            $pdo->prepare(
                'INSERT INTO usuario_permissoes (usuario_id, permissao_id, permitido)
                 SELECT :uid, p.id, 1
                   FROM permissoes p
                  WHERE p.codigo = :cod
                    AND p.ativo  = 1'
            )->execute([
                ':uid' => $novo_id,
                ':cod' => 'dashboard.visualizar',
            ]);
        }

        $pdo->commit();

        flash('sucesso', 'Usuário cadastrado com sucesso! '
            . ($perfil === 'bibliotecario'
                ? 'Acesse "Permissões" para conceder os acessos necessários.'
                : 'Acesso total concedido por padrão.'));
        redirecionar(BASE_URL . '/usuarios/listar.php');

    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('[BiblioTech] usuarios/cadastrar: ' . $e->getMessage());
        flash('erro', 'Erro ao cadastrar usuário. Tente novamente.');
        redirecionar(BASE_URL . '/usuarios/cadastrar.php');
    }
}

// ============================================================================
// EDITAR
// ============================================================================
if ($action === 'editar') {

    requirePermission('usuarios.editar');

    // --- ID ------------------------------------------------------------------
    $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 1],
    ]);

    if ($id === false || $id === null) {
        flash('erro', 'ID do usuário inválido.');
        redirecionar(BASE_URL . '/usuarios/listar.php');
    }

    // --- Coleta --------------------------------------------------------------
    $nome     = trim((string) ($_POST['nome']     ?? ''));
    $email    = trim((string) ($_POST['email']    ?? ''));
    $senha    = (string)       ($_POST['senha']    ?? '');
    $confirma = (string)       ($_POST['confirma'] ?? '');
    $perfil   = trim((string) ($_POST['perfil']   ?? ''));

    // --- Validações ----------------------------------------------------------
    $erros = [];

    if ($nome === '' || mb_strlen($nome) > 100) {
        $erros[] = 'Informe um nome válido (até 100 caracteres).';
    }

    if ($email === '' || !email_valido($email) || mb_strlen($email) > 100) {
        $erros[] = 'Informe um e-mail válido.';
    }

    if (!in_array($perfil, ['admin', 'bibliotecario'], true)) {
        $erros[] = 'Perfil inválido.';
    }

    // Senha: opcional na edição, mas se preenchida deve ser válida
    if ($senha !== '') {
        if (mb_strlen($senha) < 6) {
            $erros[] = 'A nova senha deve ter pelo menos 6 caracteres.';
        } elseif ($senha !== $confirma) {
            $erros[] = 'A senha e a confirmação não conferem.';
        }
    }

    // --- Verifica se o usuário existe ----------------------------------------
    try {
        $stmt = $pdo->prepare(
            'SELECT id, perfil, ativo FROM usuarios WHERE id = :id LIMIT 1'
        );
        $stmt->execute([':id' => $id]);
        $alvo = $stmt->fetch();
    } catch (PDOException $e) {
        error_log('[BiblioTech] usuarios/editar busca: ' . $e->getMessage());
        flash('erro', 'Erro ao carregar usuário.');
        redirecionar(BASE_URL . '/usuarios/listar.php');
    }

    if (!$alvo) {
        flash('erro', 'Usuário não encontrado.');
        redirecionar(BASE_URL . '/usuarios/listar.php');
    }

    // --- E-mail duplicado (excluindo o próprio) ------------------------------
    if ($email !== '' && empty($erros)) {
        try {
            $stmt = $pdo->prepare(
                'SELECT id FROM usuarios WHERE email = :e AND id <> :id LIMIT 1'
            );
            $stmt->execute([':e' => $email, ':id' => $id]);
            if ($stmt->fetch()) {
                $erros[] = 'Já existe outro usuário com este e-mail.';
            }
        } catch (PDOException $e) {
            error_log('[BiblioTech] usuarios/editar dup: ' . $e->getMessage());
            $erros[] = 'Erro ao validar e-mail.';
        }
    }

    // --- Não permite rebaixar o último admin ativo ---------------------------
    if (empty($erros)
        && $alvo['perfil'] === 'admin'
        && $perfil !== 'admin') {

        try {
            $stmt = $pdo->prepare(
                "SELECT COUNT(*) FROM usuarios
                  WHERE perfil = 'admin'
                    AND ativo  = 1
                    AND id    <> :id"
            );
            $stmt->execute([':id' => $id]);
            $outros_admins = (int) $stmt->fetchColumn();

            if ($outros_admins === 0) {
                $erros[] = 'Não é possível rebaixar o único administrador ativo do sistema.';
            }
        } catch (PDOException $e) {
            error_log('[BiblioTech] usuarios/editar admin count: ' . $e->getMessage());
            $erros[] = 'Erro ao validar perfil.';
        }
    }

    if (!empty($erros)) {
        $_SESSION['form_usuario'] = [
            'nome'   => $nome,
            'email'  => $email,
            'perfil' => $perfil,
        ];
        foreach ($erros as $erro) {
            flash('erro', $erro);
        }
        redirecionar(BASE_URL . '/usuarios/editar.php?id=' . $id);
    }

    // --- Persistência --------------------------------------------------------
    try {
        if ($senha !== '') {
            $hash = password_hash($senha, PASSWORD_DEFAULT);
            $sql  = 'UPDATE usuarios
                        SET nome = :nome, email = :email,
                            senha = :senha, perfil = :perfil
                      WHERE id = :id';
            $params = [
                ':nome'   => $nome,
                ':email'  => $email,
                ':senha'  => $hash,
                ':perfil' => $perfil,
                ':id'     => $id,
            ];
        } else {
            $sql = 'UPDATE usuarios
                       SET nome = :nome, email = :email, perfil = :perfil
                     WHERE id = :id';
            $params = [
                ':nome'   => $nome,
                ':email'  => $email,
                ':perfil' => $perfil,
                ':id'     => $id,
            ];
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        // Se o usuário editou a si mesmo e mudou nome, atualiza a sessão
        if ($id === (int) $_SESSION['usuario_id']) {
            $_SESSION['usuario_nome']   = $nome;
            $_SESSION['usuario_email']  = $email;
            $_SESSION['usuario_perfil'] = $perfil;
        }

        flash('sucesso', 'Usuário atualizado com sucesso!');
        redirecionar(BASE_URL . '/usuarios/listar.php');

    } catch (PDOException $e) {
        error_log('[BiblioTech] usuarios/editar update: ' . $e->getMessage());
        flash('erro', 'Erro ao atualizar usuário. Tente novamente.');
        redirecionar(BASE_URL . '/usuarios/editar.php?id=' . $id);
    }
}

// ============================================================================
// INATIVAR
// ============================================================================
if ($action === 'inativar') {

    requirePermission('usuarios.inativar');

    // --- ID ------------------------------------------------------------------
    $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 1],
    ]);

    if ($id === false || $id === null) {
        flash('erro', 'ID do usuário inválido.');
        redirecionar(BASE_URL . '/usuarios/listar.php');
    }

    // --- Não permite inativar a si mesmo -------------------------------------
    if ($id === (int) $_SESSION['usuario_id']) {
        flash('erro', 'Você não pode inativar a sua própria conta.');
        redirecionar(BASE_URL . '/usuarios/listar.php');
    }

    try {
        // --- Carrega o usuário ----------------------------------------------
        $stmt = $pdo->prepare(
            'SELECT id, perfil, ativo FROM usuarios WHERE id = :id LIMIT 1'
        );
        $stmt->execute([':id' => $id]);
        $alvo = $stmt->fetch();

        if (!$alvo) {
            flash('erro', 'Usuário não encontrado.');
            redirecionar(BASE_URL . '/usuarios/listar.php');
        }

        if ((int) $alvo['ativo'] === 0) {
            flash('erro', 'Este usuário já está inativo.');
            redirecionar(BASE_URL . '/usuarios/listar.php');
        }

        // --- Não permite inativar o último admin ativo ----------------------
        if ($alvo['perfil'] === 'admin') {
            $stmt = $pdo->prepare(
                "SELECT COUNT(*) FROM usuarios
                  WHERE perfil = 'admin'
                    AND ativo  = 1
                    AND id    <> :id"
            );
            $stmt->execute([':id' => $id]);
            $outros_admins = (int) $stmt->fetchColumn();

            if ($outros_admins === 0) {
                flash('erro',
                    'Não é possível inativar o único administrador ativo do sistema.');
                redirecionar(BASE_URL . '/usuarios/listar.php');
            }
        }

        // --- Inativa --------------------------------------------------------
        $stmt = $pdo->prepare(
            'UPDATE usuarios SET ativo = 0 WHERE id = :id AND ativo = 1'
        );
        $stmt->execute([':id' => $id]);

        if ($stmt->rowCount() === 0) {
            flash('erro', 'Não foi possível inativar o usuário.');
            redirecionar(BASE_URL . '/usuarios/listar.php');
        }

        flash('sucesso', 'Usuário inativado com sucesso!');
        redirecionar(BASE_URL . '/usuarios/listar.php');

    } catch (PDOException $e) {
        error_log('[BiblioTech] usuarios/inativar: ' . $e->getMessage());
        flash('erro', 'Erro ao inativar usuário. Tente novamente.');
        redirecionar(BASE_URL . '/usuarios/listar.php');
    }
}

// ============================================================================
// PERMISSÕES
// ============================================================================
if ($action === 'permissoes') {

    requirePermission('usuarios.permissoes');

    // --- ID ------------------------------------------------------------------
    $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 1],
    ]);

    if ($id === false || $id === null) {
        flash('erro', 'ID do usuário inválido.');
        redirecionar(BASE_URL . '/usuarios/listar.php');
    }

    // --- Carrega usuário-alvo ------------------------------------------------
    try {
        $stmt = $pdo->prepare(
            'SELECT id, nome, perfil, ativo FROM usuarios WHERE id = :id LIMIT 1'
        );
        $stmt->execute([':id' => $id]);
        $alvo = $stmt->fetch();
    } catch (PDOException $e) {
        error_log('[BiblioTech] usuarios/permissoes busca: ' . $e->getMessage());
        flash('erro', 'Erro ao carregar usuário.');
        redirecionar(BASE_URL . '/usuarios/listar.php');
    }

    if (!$alvo) {
        flash('erro', 'Usuário não encontrado.');
        redirecionar(BASE_URL . '/usuarios/listar.php');
    }

    // --- Coleta IDs de permissões marcadas e converte para inteiros ----------
    $marcadas_brutas = $_POST['permissoes'] ?? [];
    if (!is_array($marcadas_brutas)) {
        $marcadas_brutas = [];
    }

    $marcadas_ids = [];
    foreach ($marcadas_brutas as $valor) {
        $int = filter_var($valor, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);
        if ($int !== false && $int !== null) {
            $marcadas_ids[$int] = true; // dedupe
        }
    }
    $marcadas_ids = array_keys($marcadas_ids);

    // --- Carrega permissões válidas e mapeia ID → código --------------------
    try {
        $stmt = $pdo->query(
            'SELECT id, codigo FROM permissoes WHERE ativo = 1'
        );
        $todas = $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log('[BiblioTech] usuarios/permissoes lista: ' . $e->getMessage());
        flash('erro', 'Erro ao carregar permissões.');
        redirecionar(BASE_URL . '/usuarios/permissoes.php?id=' . $id);
    }

    $ids_validos      = [];
    $codigo_por_id    = [];
    foreach ($todas as $p) {
        $ids_validos[]            = (int) $p['id'];
        $codigo_por_id[(int)$p['id']] = $p['codigo'];
    }

    // Filtra: apenas IDs que realmente existem em permissoes
    $marcadas_ids = array_values(array_intersect($marcadas_ids, $ids_validos));

    // --- Salvaguarda: admin não pode remover usuarios.permissoes de si mesmo
    if ($id === (int) $_SESSION['usuario_id'] && isAdmin()) {

        $codigos_marcados = array_map(
            fn($pid) => $codigo_por_id[$pid] ?? '',
            $marcadas_ids
        );

        if (!in_array('usuarios.permissoes', $codigos_marcados, true)) {
            flash('erro',
                'Você não pode remover de si mesmo a permissão "Gerenciar permissões". '
                . 'Caso contrário, perderia o acesso a este painel.');
            redirecionar(BASE_URL . '/usuarios/permissoes.php?id=' . $id);
        }
    }

    // --- Persistência (transação: delete + insert) --------------------------
    try {
        $pdo->beginTransaction();

        $pdo->prepare('DELETE FROM usuario_permissoes WHERE usuario_id = :uid')
            ->execute([':uid' => $id]);

        if (!empty($marcadas_ids)) {
            $stmt = $pdo->prepare(
                'INSERT INTO usuario_permissoes (usuario_id, permissao_id, permitido)
                 VALUES (:uid, :pid, 1)'
            );
            foreach ($marcadas_ids as $pid) {
                $stmt->execute([':uid' => $id, ':pid' => $pid]);
            }
        }

        $pdo->commit();

        flash('sucesso',
            'Permissões de "' . $alvo['nome'] . '" atualizadas com sucesso! '
            . count($marcadas_ids) . ' permissão(ões) ativa(s).'
        );
        redirecionar(BASE_URL . '/usuarios/permissoes.php?id=' . $id);

    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('[BiblioTech] usuarios/permissoes save: ' . $e->getMessage());
        flash('erro', 'Erro ao salvar permissões. Tente novamente.');
        redirecionar(BASE_URL . '/usuarios/permissoes.php?id=' . $id);
    }
}

// ─── Ação desconhecida ───────────────────────────────────────────────────────
flash('erro', 'Ação inválida.');
redirecionar(BASE_URL . '/usuarios/listar.php');
