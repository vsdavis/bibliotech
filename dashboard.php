<?php
/**
 * BiblioTech — Dashboard
 *
 * Exibe os indicadores e atalhos de acordo com as permissões
 * individuais do usuário logado.
 *
 * Segurança aplicada:
 *   - requirePermission('dashboard.visualizar') bloqueia acesso sem permissão
 *   - Cada card de indicador só é exibido se o usuário tiver a permissão
 *     de visualizar o módulo correspondente
 *   - Cada botão de atalho só aparece se o usuário tiver a permissão de
 *     cadastrar no módulo correspondente
 *   - Nenhum dado é exibido a partir de permissões não autorizadas
 */

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/conexao.php';

requirePermission('dashboard.visualizar');

$titulo_pagina = 'Dashboard';
$pagina_ativa  = 'dashboard';

// ─── Flags de permissão usadas no template ───────────────────────────────────
$pode_ver_livros      = hasPermission('livros.visualizar');
$pode_ver_alunos      = hasPermission('alunos.visualizar');
$pode_ver_emprestimos = hasPermission('emprestimos.visualizar');
$pode_ver_relatorios  = hasPermission('relatorios.visualizar');
$pode_ver_usuarios    = hasPermission('usuarios.visualizar');

$pode_cadastrar_livro      = hasPermission('livros.cadastrar');
$pode_cadastrar_aluno      = hasPermission('alunos.cadastrar');
$pode_cadastrar_emprestimo = hasPermission('emprestimos.cadastrar');
$pode_cadastrar_usuario    = hasPermission('usuarios.cadastrar');

// ─── Busca os indicadores (apenas os que o usuário tem permissão) ─────────────
$total_livros           = 0;
$livros_disponiveis     = 0;
$total_alunos           = 0;
$emprestimos_ativos     = 0;
$emprestimos_atraso     = 0;
$emprestimos_devolvidos = 0;

try {
    $stmt = $pdo->query('SELECT * FROM vw_dashboard');
    $ind  = $stmt->fetch() ?: [];

    // Só lê os valores dos módulos que o usuário pode visualizar
    if ($pode_ver_livros) {
        $total_livros       = (int) ($ind['total_livros']       ?? 0);
        $livros_disponiveis = (int) ($ind['livros_disponiveis'] ?? 0);
    }
    if ($pode_ver_alunos) {
        $total_alunos = (int) ($ind['total_alunos'] ?? 0);
    }
    if ($pode_ver_emprestimos) {
        $emprestimos_ativos     = (int) ($ind['emprestimos_ativos']     ?? 0);
        $emprestimos_atraso     = (int) ($ind['emprestimos_atraso']     ?? 0);
        $emprestimos_devolvidos = (int) ($ind['emprestimos_devolvidos'] ?? 0);
    }

} catch (PDOException $e) {
    error_log('[BiblioTech] dashboard: ' . $e->getMessage());
    flash('erro', 'Não foi possível carregar os indicadores no momento.');
}

// ─── Data por extenso em PT-BR ───────────────────────────────────────────────
$dias_semana  = ['Domingo', 'Segunda-feira', 'Terça-feira', 'Quarta-feira',
                 'Quinta-feira', 'Sexta-feira', 'Sábado'];
$meses        = ['janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho',
                 'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'];
$data_extenso = $dias_semana[(int) date('w')]
              . ', ' . (int) date('d')
              . ' de ' . $meses[(int) date('n') - 1]
              . ' de ' . date('Y');

// ─── Conta quantos cards o usuário verá (para ajuste de layout) ──────────────
$total_cards = (int) $pode_ver_livros
             + (int) $pode_ver_alunos
             + (int) $pode_ver_emprestimos
             + (int) $pode_ver_emprestimos; // "em atraso" usa a mesma permissão

$total_atalhos = (int) $pode_cadastrar_livro
               + (int) $pode_cadastrar_aluno
               + (int) $pode_cadastrar_emprestimo
               + (int) $pode_cadastrar_usuario;

require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/navbar.php';
?>

<section class="pagina">

    <div class="pagina-cabecalho">
        <div>
            <h1>Dashboard</h1>
            <p class="subtitulo">Visão geral da biblioteca</p>
        </div>
        <div class="pagina-data"><?= e($data_extenso) ?></div>
    </div>

    <!-- ─── Cards de indicadores ─────────────────────────────────────────── -->
    <?php if ($total_cards > 0): ?>
    <div class="cards">

        <?php if ($pode_ver_livros): ?>
        <div class="card card-azul">
            <span class="card-icone">📚</span>
            <div>
                <span class="card-titulo">Livros no acervo</span>
                <strong class="card-valor"><?= e((string) $total_livros) ?></strong>
                <span class="card-extra"><?= e((string) $livros_disponiveis) ?> disponíveis</span>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($pode_ver_alunos): ?>
        <div class="card card-verde">
            <span class="card-icone">🎓</span>
            <div>
                <span class="card-titulo">Alunos cadastrados</span>
                <strong class="card-valor"><?= e((string) $total_alunos) ?></strong>
                <span class="card-extra">ativos no sistema</span>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($pode_ver_emprestimos): ?>
        <div class="card card-roxo">
            <span class="card-icone">🔄</span>
            <div>
                <span class="card-titulo">Empréstimos ativos</span>
                <strong class="card-valor"><?= e((string) $emprestimos_ativos) ?></strong>
                <span class="card-extra"><?= e((string) $emprestimos_devolvidos) ?> já devolvidos</span>
            </div>
        </div>

        <div class="card card-vermelho">
            <span class="card-icone">⚠️</span>
            <div>
                <span class="card-titulo">Em atraso</span>
                <strong class="card-valor"><?= e((string) $emprestimos_atraso) ?></strong>
                <span class="card-extra">requer atenção</span>
            </div>
        </div>
        <?php endif; ?>

    </div>
    <?php endif; ?>

    <!-- ─── Boas-vindas + atalhos ─────────────────────────────────────────── -->
    <div class="painel-bemvindo">
        <h2>Olá, <?= e($_SESSION['usuario_nome']) ?> 👋</h2>
        <p>
            Você está conectado como
            <strong><?= e(ucfirst($_SESSION['usuario_perfil'])) ?></strong>.
            <?php if ($total_atalhos > 0): ?>
                Use os atalhos abaixo ou o menu superior para gerenciar a biblioteca.
            <?php else: ?>
                Use o menu acima para navegar pelas funcionalidades disponíveis.
            <?php endif; ?>
        </p>

        <?php if ($total_atalhos > 0): ?>
        <div class="atalhos">

            <?php if ($pode_cadastrar_livro): ?>
                <a class="btn btn-primario" href="<?= e(BASE_URL) ?>/livros/cadastrar.php">
                    + Novo Livro
                </a>
            <?php endif; ?>

            <?php if ($pode_cadastrar_aluno): ?>
                <a class="btn btn-secundario" href="<?= e(BASE_URL) ?>/alunos/cadastrar.php">
                    + Novo Aluno
                </a>
            <?php endif; ?>

            <?php if ($pode_cadastrar_emprestimo): ?>
                <a class="btn btn-secundario" href="<?= e(BASE_URL) ?>/emprestimos/cadastrar.php">
                    + Novo Empréstimo
                </a>
            <?php endif; ?>

            <?php if ($pode_cadastrar_usuario): ?>
                <a class="btn btn-secundario" href="<?= e(BASE_URL) ?>/usuarios/cadastrar.php">
                    + Novo Usuário
                </a>
            <?php endif; ?>

        </div>
        <?php endif; ?>
    </div>

    <!-- ─── Mensagem para usuários sem nenhum acesso visível ─────────────── -->
    <?php if ($total_cards === 0 && $total_atalhos === 0): ?>
    <div class="flash flash-info">
        Sua conta ainda não possui permissões configuradas além do acesso ao painel.
        Solicite ao administrador que configure suas permissões em
        <strong>Usuários → Permissões</strong>.
    </div>
    <?php endif; ?>

</section>

<?php require __DIR__ . '/includes/footer.php'; ?>