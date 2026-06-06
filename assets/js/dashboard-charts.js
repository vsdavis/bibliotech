/**
 * BiblioTech — Gráficos da Dashboard
 *
 * Renderiza dois gráficos simples com Chart.js:
 *   1. Empréstimos por mês (últimos 6 meses) — barras verticais
 *   2. Livros mais emprestados (top 5)       — barras horizontais
 *
 * Os dados vêm do PHP em window.BT_DADOS_GRAFICOS (já escapados via json_encode).
 * Tudo é apenas leitura/visualização — nenhuma alteração no servidor.
 */
(function () {
    'use strict';

    if (typeof Chart === 'undefined') return;          // Chart.js não carregou
    const dados = window.BT_DADOS_GRAFICOS;
    if (!dados) return;

    // ── Paleta Verde Sereno ──
    const VERDE        = '#2F855A';
    const VERDE_ESCURO = '#22543D';
    const VERDE_CLARO  = '#48B07C';
    const GRADE        = '#ECF0EE';
    const TEXTO        = '#5F6C72';

    // Padrões globais
    Chart.defaults.font.family = "'Inter', 'Segoe UI', Roboto, sans-serif";
    Chart.defaults.font.size = 12;
    Chart.defaults.color = TEXTO;

    const tooltipBase = {
        backgroundColor: VERDE_ESCURO,
        padding: 10,
        cornerRadius: 8,
        titleFont: { weight: '600' },
        displayColors: false
    };

    function plural(n) {
        return n + (n === 1 ? ' empréstimo' : ' empréstimos');
    }

    function truncar(txt, max) {
        return txt.length > max ? txt.slice(0, max - 1) + '…' : txt;
    }

    // ── 1) Empréstimos por mês ──
    const elMes = document.getElementById('grafEmprestimosMes');
    if (elMes && dados.meses) {
        new Chart(elMes, {
            type: 'bar',
            data: {
                labels: dados.meses.rotulos,
                datasets: [{
                    data: dados.meses.valores,
                    backgroundColor: VERDE,
                    hoverBackgroundColor: VERDE_ESCURO,
                    borderRadius: 6,
                    maxBarThickness: 46
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        ...tooltipBase,
                        callbacks: { label: (ctx) => plural(ctx.parsed.y) }
                    }
                },
                scales: {
                    x: { grid: { display: false }, border: { display: false } },
                    y: {
                        beginAtZero: true,
                        ticks: { precision: 0 },
                        grid: { color: GRADE },
                        border: { display: false }
                    }
                }
            }
        });
    }

    // ── 2) Livros mais emprestados (horizontal) ──
    const elTop = document.getElementById('grafTopLivros');
    if (elTop && dados.topLivros && dados.topLivros.rotulos.length) {
        new Chart(elTop, {
            type: 'bar',
            data: {
                labels: dados.topLivros.rotulos,
                datasets: [{
                    data: dados.topLivros.valores,
                    backgroundColor: VERDE_CLARO,
                    hoverBackgroundColor: VERDE,
                    borderRadius: 6,
                    maxBarThickness: 30
                }]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        ...tooltipBase,
                        callbacks: {
                            title: (itens) => itens[0].label,          // título completo
                            label: (ctx) => plural(ctx.parsed.x)
                        }
                    }
                },
                scales: {
                    x: {
                        beginAtZero: true,
                        ticks: { precision: 0 },
                        grid: { color: GRADE },
                        border: { display: false }
                    },
                    y: {
                        grid: { display: false },
                        border: { display: false },
                        ticks: { callback: function (v) { return truncar(String(this.getLabelForValue(v)), 24); } }
                    }
                }
            }
        });
    }

})();
