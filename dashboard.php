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

// ─── Dados para os gráficos (somente quem pode ver empréstimos) ──────────────
$meses_abrev   = ['jan', 'fev', 'mar', 'abr', 'mai', 'jun',
                  'jul', 'ago', 'set', 'out', 'nov', 'dez'];
$grafico_meses = [];   // rótulos (ex.: "jan/26")
$grafico_qtd   = [];   // quantidade por mês
$top_titulos   = [];   // títulos dos livros mais emprestados
$top_qtd       = [];   // quantidade de empréstimos por livro

if ($pode_ver_emprestimos) {
    // 1) Empréstimos por mês — monta os últimos 6 meses (inclusive vazios)
    $mapa_meses = [];
    for ($i = 5; $i >= 0; $i--) {
        $ts    = strtotime("first day of -$i month");
        $chave = date('Y-m', $ts);
        $mapa_meses[$chave] = [
            'rotulo' => $meses_abrev[(int) date('n', $ts) - 1] . '/' . date('y', $ts),
            'qtd'    => 0,
        ];
    }

    try {
        $inicio = date('Y-m-01', strtotime('first day of -5 month'));
        $stmt = $pdo->prepare(
            "SELECT DATE_FORMAT(data_emprestimo, '%Y-%m') AS ym, COUNT(*) AS qtd
               FROM emprestimos
              WHERE data_emprestimo >= :inicio
              GROUP BY ym"
        );
        $stmt->execute([':inicio' => $inicio]);
        foreach ($stmt->fetchAll() as $row) {
            if (isset($mapa_meses[$row['ym']])) {
                $mapa_meses[$row['ym']]['qtd'] = (int) $row['qtd'];
            }
        }
    } catch (PDOException $e) {
        error_log('[BiblioTech] dashboard grafico meses: ' . $e->getMessage());
    }

    foreach ($mapa_meses as $m) {
        $grafico_meses[] = $m['rotulo'];
        $grafico_qtd[]   = $m['qtd'];
    }

    // 2) Top 5 livros mais emprestados
    try {
        $stmt = $pdo->query(
            "SELECT l.titulo, COUNT(*) AS qtd
               FROM emprestimos e
               INNER JOIN livros l ON l.id = e.livro_id
              GROUP BY l.id, l.titulo
              ORDER BY qtd DESC, l.titulo ASC
              LIMIT 5"
        );
        foreach ($stmt->fetchAll() as $row) {
            $top_titulos[] = (string) $row['titulo'];
            $top_qtd[]     = (int) $row['qtd'];
        }
    } catch (PDOException $e) {
        error_log('[BiblioTech] dashboard top livros: ' . $e->getMessage());
    }
}

// Há dados suficientes para desenhar algum gráfico?
$total_6m       = array_sum($grafico_qtd);
$mes_atual_qtd  = $grafico_qtd ? end($grafico_qtd) : 0;
$tem_grafico    = $total_6m > 0 || !empty($top_qtd);

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

<section class="pagina dashboard">

    <!-- ─── Hero / saudação ──────────────────────────────────────────────── -->
    <div class="dash-hero">
        <div class="dash-hero-info">
            <span class="dash-hero-data"><?= e($data_extenso) ?></span>
            <h1 class="dash-hero-titulo">Olá, <?= e($_SESSION['usuario_nome']) ?> 👋</h1>
            <p class="dash-hero-sub">
                Você está conectado como
                <strong><?= e(ucfirst($_SESSION['usuario_perfil'])) ?></strong>.
                <?php if ($total_cards > 0): ?>
                    Aqui está a visão geral da biblioteca.
                <?php else: ?>
                    Use o menu acima para navegar pelas funcionalidades disponíveis.
                <?php endif; ?>
            </p>
        </div>

        <?php if ($total_atalhos > 0): ?>
        <div class="dash-hero-acoes">
            <?php
            $icone_mais = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14"/><path d="M5 12h14"/></svg>';
            ?>
            <?php if ($pode_cadastrar_emprestimo): ?>
                <a class="dash-acao" href="<?= e(BASE_URL) ?>/emprestimos/cadastrar.php"><?= $icone_mais ?> Empréstimo</a>
            <?php endif; ?>
            <?php if ($pode_cadastrar_livro): ?>
                <a class="dash-acao" href="<?= e(BASE_URL) ?>/livros/cadastrar.php"><?= $icone_mais ?> Livro</a>
            <?php endif; ?>
            <?php if ($pode_cadastrar_aluno): ?>
                <a class="dash-acao" href="<?= e(BASE_URL) ?>/alunos/cadastrar.php"><?= $icone_mais ?> Aluno</a>
            <?php endif; ?>
            <?php if ($pode_cadastrar_usuario): ?>
                <a class="dash-acao" href="<?= e(BASE_URL) ?>/usuarios/cadastrar.php"><?= $icone_mais ?> Usuário</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- ─── Cards de indicadores ─────────────────────────────────────────── -->
    <?php if ($total_cards > 0): ?>
    <div class="dash-stats">

        <?php if ($pode_ver_livros): ?>
        <?php $pct_disp = $total_livros > 0 ? (int) round($livros_disponiveis / $total_livros * 100) : 0; ?>
        <div class="dash-card dash-card--livros">
            <div class="dash-card-topo">
                <span class="dash-card-icone" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
                </span>
                <span class="dash-card-rotulo">Livros no acervo</span>
            </div>
            <strong class="dash-card-valor"><?= e((string) $total_livros) ?></strong>
            <div class="dash-card-barra" role="progressbar"
                 aria-valuenow="<?= $pct_disp ?>" aria-valuemin="0" aria-valuemax="100"
                 aria-label="Percentual de exemplares disponíveis">
                <span style="width: <?= $pct_disp ?>%"></span>
            </div>
            <span class="dash-card-extra">
                <?= e((string) $livros_disponiveis) ?> disponíveis<?= $total_livros > 0 ? ' (' . $pct_disp . '%)' : '' ?>
            </span>
        </div>
        <?php endif; ?>

        <?php if ($pode_ver_alunos): ?>
        <div class="dash-card dash-card--alunos">
            <div class="dash-card-topo">
                <span class="dash-card-icone" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M22 10 12 5 2 10l10 5 10-5z"/><path d="M6 12v5c0 1 2.5 2.5 6 2.5s6-1.5 6-2.5v-5"/></svg>
                </span>
                <span class="dash-card-rotulo">Alunos cadastrados</span>
            </div>
            <strong class="dash-card-valor"><?= e((string) $total_alunos) ?></strong>
            <span class="dash-card-extra">ativos no sistema</span>
        </div>
        <?php endif; ?>

        <?php if ($pode_ver_emprestimos): ?>
        <div class="dash-card dash-card--emprestimos">
            <div class="dash-card-topo">
                <span class="dash-card-icone" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12a9 9 0 0 1 15-6.7L21 8"/><path d="M21 3v5h-5"/><path d="M21 12a9 9 0 0 1-15 6.7L3 16"/><path d="M3 21v-5h5"/></svg>
                </span>
                <span class="dash-card-rotulo">Empréstimos ativos</span>
            </div>
            <strong class="dash-card-valor"><?= e((string) $emprestimos_ativos) ?></strong>
            <span class="dash-card-extra"><?= e((string) $emprestimos_devolvidos) ?> já devolvidos</span>
        </div>

        <?php $atraso_critico = $emprestimos_atraso > 0; ?>
        <div class="dash-card dash-card--atraso <?= $atraso_critico ? 'is-critico' : '' ?>">
            <div class="dash-card-topo">
                <span class="dash-card-icone" aria-hidden="true">
                    <?php if ($atraso_critico): ?>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/><path d="M12 9v4"/><path d="M12 17h.01"/></svg>
                    <?php else: ?>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><path d="m9 11 3 3L22 4"/></svg>
                    <?php endif; ?>
                </span>
                <span class="dash-card-rotulo">Em atraso</span>
            </div>
            <strong class="dash-card-valor"><?= e((string) $emprestimos_atraso) ?></strong>
            <span class="dash-card-extra"><?= $atraso_critico ? 'requer atenção' : 'tudo em dia' ?></span>
        </div>
        <?php endif; ?>

    </div>
    <?php endif; ?>

    <!-- ─── Gráficos ─────────────────────────────────────────────────────── -->
    <?php if ($pode_ver_emprestimos): ?>
    <div class="dash-graficos">

        <div class="card dash-grafico">
            <div class="card-header">
                <h3>Empréstimos por mês</h3>
                <span class="dash-grafico-meta">
                    <?= e((string) $total_6m) ?> nos últimos 6 meses
                    &middot; <?= e((string) $mes_atual_qtd) ?> neste mês
                </span>
            </div>
            <div class="card-body">
                <?php if ($tem_grafico): ?>
                    <div class="dash-grafico-canvas">
                        <canvas id="grafEmprestimosMes" aria-label="Gráfico de empréstimos por mês" role="img"></canvas>
                    </div>
                <?php else: ?>
                    <p class="dash-grafico-vazio">Ainda não há empréstimos registrados para gerar o gráfico.</p>
                <?php endif; ?>
            </div>
        </div>

        <div class="card dash-grafico">
            <div class="card-header">
                <h3>Livros mais emprestados</h3>
                <span class="dash-grafico-meta">Top 5 do acervo</span>
            </div>
            <div class="card-body">
                <?php if (!empty($top_qtd)): ?>
                    <div class="dash-grafico-canvas">
                        <canvas id="grafTopLivros" aria-label="Gráfico dos livros mais emprestados" role="img"></canvas>
                    </div>
                <?php else: ?>
                    <p class="dash-grafico-vazio">Ainda não há empréstimos suficientes para ranquear os livros.</p>
                <?php endif; ?>
            </div>
        </div>

    </div>
    <?php endif; ?>

    <!-- ─── Mensagem para usuários sem nenhum acesso visível ─────────────── -->
    <?php if ($total_cards === 0 && $total_atalhos === 0): ?>
    <div class="flash flash-info">
        Sua conta ainda não possui permissões configuradas além do acesso ao painel.
        Solicite ao administrador que configure suas permissões em
        <strong>Usuários → Permissões</strong>.
    </div>
    <?php endif; ?>

</section>

<?php if ($pode_ver_emprestimos && $tem_grafico): ?>
    <?php $json_flags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE; ?>
    <script src="<?= asset('assets/js/chart.umd.js') ?>"></script>
    <script>
        window.BT_DADOS_GRAFICOS = {
            meses: {
                rotulos: <?= json_encode($grafico_meses, $json_flags) ?>,
                valores: <?= json_encode($grafico_qtd, $json_flags) ?>
            },
            topLivros: {
                rotulos: <?= json_encode($top_titulos, $json_flags) ?>,
                valores: <?= json_encode($top_qtd, $json_flags) ?>
            }
        };
    </script>
    <script src="<?= asset('assets/js/dashboard-charts.js') ?>"></script>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>