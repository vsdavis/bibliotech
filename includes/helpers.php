<?php
/**
 * BiblioTech — Funções auxiliares de uso geral
 *
 * Funções incluídas:
 *  - e()                     : escape seguro de HTML (anti-XSS)
 *  - redirecionar()          : redirect com encerramento garantido
 *  - flash()                 : grava mensagem flash (sucesso/erro/info)
 *  - exibir_flash()          : imprime e limpa mensagens flash
 *  - csrf_token()            : gera/recupera token CSRF da sessão
 *  - csrf_input()            : retorna o input hidden pronto
 *  - csrf_validar()          : valida o token enviado em $_POST
 *  - email_valido()          : valida formato de e-mail (filter_var)
 *  - logado()                : indica se há sessão ativa
 *  - isAdmin()               : indica se o usuário logado é administrador
 *  - hasPermission($codigo)  : verifica se o usuário possui uma permissão
 *  - requirePermission($cod) : bloqueia o acesso se não tiver a permissão
 *  - carregar_permissoes()   : carrega as permissões do usuário (lazy)
 */

// Constante de URL base do projeto. Ajuste se você renomear a pasta.
if (!defined('BASE_URL')) {
    define('BASE_URL', '/bibliotech');
}

// Inicia a sessão com cookies seguros (apenas se ainda não foi iniciada)
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,    // impede leitura via JavaScript
        'samesite' => 'Lax',   // mitigação de CSRF
    ]);
    session_start();
}

/**
 * Escapa string para uso seguro em HTML, prevenindo XSS.
 */
function e(?string $valor): string
{
    return htmlspecialchars($valor ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

/**
 * Redireciona e encerra a execução.
 */
function redirecionar(string $url): void
{
    header('Location: ' . $url);
    exit;
}

/**
 * Indica se há um usuário logado na sessão atual.
 */
function logado(): bool
{
    return !empty($_SESSION['usuario_id']);
}

/**
 * Define uma mensagem flash que aparecerá na próxima página renderizada.
 */
function flash(string $tipo, string $mensagem): void
{
    $_SESSION['flash'][] = [
        'tipo'     => $tipo,
        'mensagem' => $mensagem,
    ];
}

/**
 * Imprime e limpa todas as mensagens flash pendentes (HTML seguro).
 */
function exibir_flash(): string
{
    if (empty($_SESSION['flash'])) {
        return '';
    }

    $html = '';
    foreach ($_SESSION['flash'] as $msg) {
        $html .= '<div class="flash flash-' . e($msg['tipo']) . '">'
              .  e($msg['mensagem'])
              .  '<button type="button" class="flash-fechar" aria-label="Fechar">&times;</button>'
              .  '</div>';
    }

    unset($_SESSION['flash']);
    return $html;
}

/**
 * Gera (uma vez por sessão) ou retorna o token CSRF atual.
 */
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Retorna o campo HTML hidden pronto para inserir em um formulário.
 */
function csrf_input(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

/**
 * Valida o token CSRF enviado pelo formulário.
 */
function csrf_validar(): void
{
    $enviado    = $_POST['csrf_token']    ?? '';
    $armazenado = $_SESSION['csrf_token'] ?? '';

    if (!is_string($enviado)
        || $armazenado === ''
        || !hash_equals($armazenado, $enviado)) {

        http_response_code(403);
        flash('erro', 'Sessão expirada ou requisição inválida. Tente novamente.');
        redirecionar(BASE_URL . '/login.php');
    }
}

/**
 * Valida o formato de um endereço de e-mail.
 */
function email_valido(string $email): bool
{
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}


/* ============================================================
 *  CONTROLE DE PERMISSÕES
 * ============================================================ */

/**
 * Indica se o usuário logado tem perfil "admin".
 * Administradores têm acesso total por padrão.
 */
function isAdmin(): bool
{
    return ($_SESSION['usuario_perfil'] ?? '') === 'admin';
}

/**
 * Carrega (uma vez por requisição) as permissões individuais
 * do usuário logado a partir da tabela usuario_permissoes.
 *
 * Resultado fica disponível no array global $GLOBALS['__permissoes_usuario']
 * como ['livros.visualizar' => true, 'livros.editar' => true, ...].
 *
 * Não usa $_SESSION para evitar permissões "presas" após alteração
 * pelo administrador — é sempre uma fonte fresca por requisição.
 */
function carregar_permissoes(): void
{
    static $carregado = false;
    if ($carregado) {
        return;
    }

    if (!logado()) {
        $GLOBALS['__permissoes_usuario'] = [];
        $carregado = true;
        return;
    }

    // Garante a conexão (idempotente: require_once dedupe)
    require_once __DIR__ . '/conexao.php';
    global $pdo;

    if (!isset($pdo) || !($pdo instanceof PDO)) {
        $GLOBALS['__permissoes_usuario'] = [];
        $carregado = true;
        return;
    }

    try {
        $stmt = $pdo->prepare(
            'SELECT p.codigo
               FROM usuario_permissoes up
               INNER JOIN permissoes p ON p.id = up.permissao_id
              WHERE up.usuario_id = :uid
                AND up.permitido  = 1
                AND p.ativo       = 1'
        );
        $stmt->execute([':uid' => (int) $_SESSION['usuario_id']]);
        $codigos = $stmt->fetchAll(PDO::FETCH_COLUMN);

        // array_flip permite checagem O(1) com isset()
        $GLOBALS['__permissoes_usuario'] = array_flip($codigos);

    } catch (PDOException $e) {
        error_log('[BiblioTech] carregar_permissoes: ' . $e->getMessage());
        $GLOBALS['__permissoes_usuario'] = [];
    }

    $carregado = true;
}

/**
 * Verifica se o usuário logado possui a permissão informada.
 *
 *   - Admin sempre retorna true (acesso total por padrão).
 *   - Demais perfis: consulta a tabela usuario_permissoes.
 *
 * @param string $codigo Código da permissão (ex.: 'livros.editar')
 */
function hasPermission(string $codigo): bool
{
    if (!logado()) {
        return false;
    }

    if (isAdmin()) {
        return true;
    }

    carregar_permissoes();

    return isset($GLOBALS['__permissoes_usuario'][$codigo]);
}

/**
 * Bloqueia o acesso à página atual se o usuário logado
 * não possuir a permissão informada.
 *
 * Em caso de bloqueio:
 *   - Grava flash de erro
 *   - Redireciona ao dashboard
 *   - Encerra a execução
 *
 * @param string $codigo Código da permissão exigida (ex.: 'usuarios.editar')
 */
function requirePermission(string $codigo): void
{
    if (!hasPermission($codigo)) {
        http_response_code(403);
        flash('erro', 'Você não tem permissão para acessar essa funcionalidade.');
        redirecionar(BASE_URL . '/dashboard.php');
    }
}
