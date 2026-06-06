/**
 * BiblioTech — JavaScript global
 *
 * Funcionalidades:
 *  1. Toggle do menu mobile (com aria-expanded e fechamento ao clicar fora).
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
    const overlay   = document.getElementById('nav-overlay');

    if (toggleBtn && nav) {

        function abrirMenu() {
            nav.classList.add('aberto');
            toggleBtn.setAttribute('aria-expanded', 'true');
            toggleBtn.setAttribute('aria-label', 'Fechar menu');
            document.body.classList.add('menu-aberto');
            if (overlay) {
                overlay.hidden = false;
                // força reflow para a transição de opacidade acontecer
                void overlay.offsetWidth;
                overlay.classList.add('visivel');
            }
        }

        function fecharMenu() {
            nav.classList.remove('aberto');
            toggleBtn.setAttribute('aria-expanded', 'false');
            toggleBtn.setAttribute('aria-label', 'Abrir menu');
            document.body.classList.remove('menu-aberto');
            if (overlay) {
                overlay.classList.remove('visivel');
                setTimeout(() => { overlay.hidden = true; }, 300);
            }
        }

        function alternarMenu() {
            nav.classList.contains('aberto') ? fecharMenu() : abrirMenu();
        }

        toggleBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            alternarMenu();
        });

        // Fecha ao clicar no overlay
        if (overlay) {
            overlay.addEventListener('click', fecharMenu);
        }

        // Fecha ao clicar em qualquer link do menu (navegação no mobile)
        nav.querySelectorAll('a').forEach(link => {
            link.addEventListener('click', () => {
                if (nav.classList.contains('aberto')) fecharMenu();
            });
        });

        // Fecha o menu ao clicar fora dele
        document.addEventListener('click', (e) => {
            if (!nav.classList.contains('aberto')) return;
            if (nav.contains(e.target) || toggleBtn.contains(e.target)) return;
            fecharMenu();
        });

        // Fecha ao pressionar Esc
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && nav.classList.contains('aberto')) {
                fecharMenu();
                toggleBtn.focus();
            }
        });

        // Se a janela for redimensionada para desktop, garante menu fechado
        window.addEventListener('resize', () => {
            if (window.innerWidth > 768 && nav.classList.contains('aberto')) {
                fecharMenu();
            }
        });
    }

    /* ─── 2. Mensagens flash ────────────────────────────── */
    document.querySelectorAll('.flash, .alert').forEach(flash => {
        const fechar = flash.querySelector('.flash-fechar, .alert-close');
        if (fechar) {
            fechar.addEventListener('click', () => removerFlash(flash));
        }
        // Auto-dismiss após 5 segundos (apenas mensagens de sucesso/info)
        const ehErro = flash.classList.contains('flash-erro') ||
                       flash.classList.contains('alert-danger');
        if (!ehErro) {
            setTimeout(() => removerFlash(flash), 5000);
        }
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

    /* ─── 6. Mostrar / ocultar senha ────────────────────── */
    /* Use: <button data-toggle-senha="idDoInput"> ... </button>  */
    document.querySelectorAll('[data-toggle-senha]').forEach(botao => {
        const alvoId = botao.getAttribute('data-toggle-senha');
        const input  = document.getElementById(alvoId);
        if (!input) return;

        const olhoAberto = botao.querySelector('.icone-olho');
        const olhoCorte  = botao.querySelector('.icone-olho-corte');

        botao.addEventListener('click', () => {
            const visivel = input.type === 'text';
            input.type = visivel ? 'password' : 'text';
            botao.setAttribute('aria-label', visivel ? 'Mostrar senha' : 'Ocultar senha');

            if (olhoAberto && olhoCorte) {
                olhoAberto.hidden = !visivel;
                olhoCorte.hidden  = visivel;
            }
        });
    });

    /* ─── 7. Voltar ao topo (rodapé) ────────────────────── */
    document.querySelectorAll('.rodape-topo-link').forEach(link => {
        link.addEventListener('click', (e) => {
            e.preventDefault();
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });
    });

    /* ─── 8. Select pesquisável (combobox) ──────────────── */
    /* Use: <select data-busca data-busca-placeholder="..."> ...   */
    /* O <select> original é mantido (oculto) para o envio do      */
    /* formulário — o backend NÃO muda em nada.                    */

    function normalizar(txt) {
        return (txt || '')
            .toString()
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '') // remove acentos
            .toLowerCase()
            .trim();
    }

    function escaparHtml(txt) {
        const div = document.createElement('div');
        div.textContent = txt;
        return div.innerHTML;
    }

    function escaparRegex(txt) {
        return txt.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    }

    function destacar(rotulo, tokens) {
        const partes = tokens.filter(Boolean).map(escaparRegex);
        const seguro = escaparHtml(rotulo);
        if (!partes.length) return seguro;
        const re = new RegExp('(' + partes.join('|') + ')', 'gi');
        return seguro.replace(re, '<mark>$1</mark>');
    }

    document.querySelectorAll('select[data-busca]').forEach(select => {
        // Não aprimora selects desabilitados (ex.: sem itens disponíveis)
        if (select.disabled) return;

        // Monta a lista de opções (ignora o placeholder de valor vazio)
        const opcoes = Array.from(select.options)
            .filter(opt => opt.value !== '')
            .map(opt => ({
                value: opt.value,
                rotulo: opt.textContent.replace(/\s+/g, ' ').trim(),
                norm: normalizar(opt.textContent)
            }));

        const placeholder = select.getAttribute('data-busca-placeholder')
            || 'Digite para buscar…';

        // Estrutura do combobox
        const combo = document.createElement('div');
        combo.className = 'combo';

        const input = document.createElement('input');
        input.type = 'text';
        input.className = 'combo-input form-campo';
        input.placeholder = placeholder;
        input.autocomplete = 'off';
        input.setAttribute('role', 'combobox');
        input.setAttribute('aria-autocomplete', 'list');
        input.setAttribute('aria-expanded', 'false');
        if (select.id) input.setAttribute('aria-label', placeholder);

        const seta = document.createElement('span');
        seta.className = 'combo-seta';
        seta.setAttribute('aria-hidden', 'true');
        seta.textContent = '▾';

        const lista = document.createElement('ul');
        lista.className = 'combo-lista';
        lista.setAttribute('role', 'listbox');
        lista.hidden = true;

        combo.appendChild(input);
        combo.appendChild(seta);
        combo.appendChild(lista);

        // Oculta o select original e injeta o combobox logo após ele
        select.classList.add('combo-select-oculto');
        select.setAttribute('tabindex', '-1');
        select.setAttribute('aria-hidden', 'true');
        select.parentNode.insertBefore(combo, select.nextSibling);

        let indiceAtivo = -1;
        let filtradas = opcoes.slice();

        // Inicializa com o valor já selecionado (ex.: após erro de validação)
        if (select.value) {
            const atual = opcoes.find(o => o.value === select.value);
            if (atual) input.value = atual.rotulo;
        }

        function abrir() {
            lista.hidden = false;
            combo.classList.add('aberto');
            input.setAttribute('aria-expanded', 'true');
        }
        function fechar() {
            lista.hidden = true;
            combo.classList.remove('aberto');
            input.setAttribute('aria-expanded', 'false');
            indiceAtivo = -1;
        }

        function selecionar(opt) {
            select.value = opt.value;
            input.value = opt.rotulo;
            input.classList.remove('campo-invalido');
            // dispara 'change' caso algo dependa do select
            select.dispatchEvent(new Event('change', { bubbles: true }));
            fechar();
        }

        function renderizar(tokens) {
            lista.innerHTML = '';
            if (!filtradas.length) {
                const vazio = document.createElement('li');
                vazio.className = 'combo-vazio';
                vazio.textContent = 'Nenhum resultado encontrado.';
                lista.appendChild(vazio);
                return;
            }
            filtradas.forEach((opt, i) => {
                const li = document.createElement('li');
                li.className = 'combo-item' + (i === indiceAtivo ? ' ativo' : '');
                li.setAttribute('role', 'option');
                li.dataset.value = opt.value;
                li.innerHTML = destacar(opt.rotulo, tokens);
                li.addEventListener('mousedown', (e) => {
                    e.preventDefault(); // evita perder o foco antes do clique
                    selecionar(opt);
                });
                lista.appendChild(li);
            });
        }

        function filtrar() {
            const termo = normalizar(input.value);
            const tokens = termo.split(/\s+/).filter(Boolean);

            // Cada palavra digitada deve estar contida no texto da opção.
            // Assim "harr" encontra "Harry Potter" e "potter rowling" também.
            filtradas = !tokens.length
                ? opcoes.slice()
                : opcoes.filter(o => tokens.every(t => o.norm.includes(t)));

            indiceAtivo = filtradas.length ? 0 : -1;
            renderizar(tokens);
        }

        // ── Eventos ──
        input.addEventListener('input', () => { abrir(); filtrar(); });

        input.addEventListener('focus', () => { abrir(); filtrar(); });

        input.addEventListener('keydown', (e) => {
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                if (lista.hidden) { abrir(); filtrar(); }
                indiceAtivo = Math.min(indiceAtivo + 1, filtradas.length - 1);
                atualizarAtivo();
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                indiceAtivo = Math.max(indiceAtivo - 1, 0);
                atualizarAtivo();
            } else if (e.key === 'Enter') {
                if (!lista.hidden && indiceAtivo >= 0 && filtradas[indiceAtivo]) {
                    e.preventDefault();
                    selecionar(filtradas[indiceAtivo]);
                }
            } else if (e.key === 'Escape') {
                if (!lista.hidden) { e.preventDefault(); fechar(); }
            }
        });

        function atualizarAtivo() {
            Array.from(lista.querySelectorAll('.combo-item')).forEach((li, i) => {
                li.classList.toggle('ativo', i === indiceAtivo);
                if (i === indiceAtivo) li.scrollIntoView({ block: 'nearest' });
            });
        }

        // Fecha ao clicar fora
        document.addEventListener('click', (e) => {
            if (!combo.contains(e.target)) fechar();
        });

        // Se o usuário sair do campo sem escolher, restaura o rótulo válido
        input.addEventListener('blur', () => {
            setTimeout(() => {
                const atual = opcoes.find(o => o.value === select.value);
                input.value = atual ? atual.rotulo : '';
            }, 150);
        });

        // Validação visual no envio (o select fica oculto)
        const form = select.closest('form');
        if (form) {
            form.addEventListener('submit', () => {
                if (select.required && !select.value) {
                    input.classList.add('campo-invalido');
                }
            });
        }
        input.addEventListener('input', () => input.classList.remove('campo-invalido'));
    });

})();
