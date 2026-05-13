/**
 * BiblioTech — JavaScript global
 *
 * Funcionalidades:
 *  1. Toggle do menu mobile.
 *  2. Fechamento de mensagens flash (botão e auto-dismiss).
 *  3. Confirmação de ações destrutivas (atributo data-confirmar).
 *  4. Filtro dinâmico em tabelas (atributo data-filtro).
 *  5. Validação básica de formulários no cliente.
 *
 * Observação importante:
 *  Toda validação no cliente é apenas para UX — a validação
 *  oficial e segura é sempre feita no backend (PHP).
 */

(function () {
    'use strict';

    /* ─── 1. Toggle do menu mobile ──────────────────────── */
    const toggleBtn = document.querySelector('.topbar-toggle');
    const nav       = document.querySelector('.topbar-nav');
    if (toggleBtn && nav) {
        toggleBtn.addEventListener('click', () => {
            nav.classList.toggle('aberto');
        });
    }

    /* ─── 2. Mensagens flash ────────────────────────────── */
    document.querySelectorAll('.flash').forEach(flash => {
        const fechar = flash.querySelector('.flash-fechar');
        if (fechar) {
            fechar.addEventListener('click', () => removerFlash(flash));
        }
        // Auto-dismiss após 5 segundos
        setTimeout(() => removerFlash(flash), 5000);
    });

    function removerFlash(el) {
        if (!el || !el.parentNode) return;
        el.style.transition = 'opacity .3s, transform .3s';
        el.style.opacity    = '0';
        el.style.transform  = 'translateY(-8px)';
        setTimeout(() => el.remove(), 300);
    }

    /* ─── 3. Confirmação de ações destrutivas ───────────── */
    /* Use: <a href="..." data-confirmar="Tem certeza?">    */
    document.querySelectorAll('[data-confirmar]').forEach(el => {
        el.addEventListener('click', (e) => {
            const msg = el.getAttribute('data-confirmar') || 'Tem certeza?';
            if (!window.confirm(msg)) {
                e.preventDefault();
            }
        });
    });

    /* ─── 4. Filtro dinâmico de tabelas ─────────────────── */
    /* Use: <input data-filtro="#minhaTabela" placeholder="Buscar..."> */
    document.querySelectorAll('[data-filtro]').forEach(input => {
        const seletor = input.getAttribute('data-filtro');
        const tabela  = document.querySelector(seletor);
        if (!tabela) return;

        input.addEventListener('input', () => {
            const termo = input.value.toLowerCase().trim();
            tabela.querySelectorAll('tbody tr').forEach(linha => {
                const texto = linha.textContent.toLowerCase();
                linha.style.display = texto.includes(termo) ? '' : 'none';
            });
        });
    });

    /* ─── 5. Validação básica de formulários ────────────── */
    document.querySelectorAll('form[novalidate]').forEach(form => {
        form.addEventListener('submit', (e) => {
            let primeiroInvalido = null;

            // Campos obrigatórios
            form.querySelectorAll('[required]').forEach(campo => {
                const valor = (campo.value || '').trim();
                campo.classList.toggle('campo-invalido', valor === '');
                if (valor === '' && !primeiroInvalido) {
                    primeiroInvalido = campo;
                }
            });

            // Validação básica de e-mail
            form.querySelectorAll('input[type="email"]').forEach(campo => {
                if (campo.value && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(campo.value)) {
                    campo.classList.add('campo-invalido');
                    if (!primeiroInvalido) primeiroInvalido = campo;
                }
            });

            if (primeiroInvalido) {
                e.preventDefault();
                primeiroInvalido.focus();
            }
        });

        // Remove a marcação de inválido conforme o usuário digita
        form.addEventListener('input', (e) => {
            if (e.target && e.target.classList) {
                e.target.classList.remove('campo-invalido');
            }
        });
    });

})();
