<?php
/**
 * BiblioTech — Página Inicial de Relatórios
 *
 * Lista todos os relatórios disponíveis com links de acesso.
 * Exibe também alguns indicadores rápidos para o operador.
 *
 * Segurança:
 *   - Autenticação obrigatória via auth.php
 *   - Permissão relatorios.visualizar exigida no backend
 *   - Saída sempre escapada com e() / cast (int)
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/conexao.php';

requirePermission('relatorios.visualizar');

$pagina_ativa = 'relatorios';

// ─── Indicadores rápidos (somente para resumo no topo da página) ─────────────
$indicadores = [
    'total_livros'        => 0,
    'livros_sem_estoque'  => 0,
    'emprestimos_ativos'  => 0,
    'emprestimos_atraso'  => 0,
];

try {
    // Atualiza status em_atraso para refletir o estado real antes de contar
    $pdo->exec(
        "UPDATE emprestimos
            SET status = 'em_atraso'
          WHERE status = 'ativo'
            AND data_prevista_devolucao < CURDATE()"
    );

    $stmt = $pdo->query(
        "SELECT
            (SELECT COUNT(*) FROM livros WHERE ativo = 1)                            AS total_livros,
            (SELECT COUNT(*) FROM livros WHERE ativo = 1 AND quantidade_disponivel = 0) AS livros_sem_estoque,
            (SELECT COUNT(*) FROM emprestimos WHERE status = 'ativo')                AS emprestimos_ativos,
            (SELECT COUNT(*) FROM emprestimos WHERE status = 'em_atraso')            AS emprestimos_atraso"
    );
    $linha = $stmt->fetch();
    if ($linha) {
        $indicadores = array_map('intval', $linha);
    }

} catch (PDOException $e) {
    error_log('[BiblioTech] relatorios/index indicadores: ' . $e->getMessage());
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="pagina-cabecalho">
    <div class="pagina-cabecalho-texto">
        <h1 class="pagina-titulo">Relatórios</h1>
        <p class="pagina-subtitulo">
            Escolha um dos relatórios abaixo para visualizar os dados consolidados da biblioteca.
        </p>
    </div>
</div>

<!-- ─── Indicadores rápidos ─────────────────────────────────────────────── -->
<div class="cards-indicadores mb-4">
    <div class="card card-indicador">
        <span class="indicador-rotulo">Livros ativos</span>
        <span class="indicador-valor"><?= (int) $indicadores['total_livros'] ?></span>
    </div>
    <div class="card card-indicador">
        <span class="indicador-rotulo">Livros sem exemplares</span>
        <span class="indicador-valor"><?= (int) $indicadores['livros_sem_estoque'] ?></span>
    </div>
    <div class="card card-indicador">
        <span class="indicador-rotulo">Empréstimos ativos</span>
        <span class="indicador-valor"><?= (int) $indicadores['emprestimos_ativos'] ?></span>
    </div>
    <div class="card card-indicador">
        <span class="indicador-rotulo">Em atraso</span>
        <span class="indicador-valor"><?= (int) $indicadores['emprestimos_atraso'] ?></span>
    </div>
</div>

<!-- ─── Cards de Relatórios ─────────────────────────────────────────────── -->
<div class="cards-relatorios">

    <div class="card card-relatorio">
        <div class="card-relatorio-icone"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><rect x="8" y="2" width="8" height="4" rx="1"/><path d="M9 12h6"/><path d="M9 16h4"/></svg></div>
        <h2 class="card-relatorio-titulo">Relatório de Empréstimos</h2>
        <p class="card-relatorio-descricao">
            Consulte os empréstimos filtrando por status (todos, ativos,
            devolvidos ou em atraso) e por período da data de empréstimo.
            Visualiza aluno, matrícula, livro e datas envolvidas.
        </p>
        <a href="<?= e(BASE_URL) ?>/relatorios/emprestimos.php"
           class="btn btn-primario">
            Acessar relatório
        </a>
    </div>

    <div class="card card-relatorio">
        <div class="card-relatorio-icone"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg></div>
        <h2 class="card-relatorio-titulo">Relatório de Livros</h2>
        <p class="card-relatorio-descricao">
            Visualize todo o acervo ativo com quantidade total, disponível e
            emprestada de cada livro. Destaca rapidamente os títulos sem
            exemplares disponíveis no momento.
        </p>
        <a href="<?= e(BASE_URL) ?>/relatorios/livros.php"
           class="btn btn-primario">
            Acessar relatório
        </a>
    </div>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>