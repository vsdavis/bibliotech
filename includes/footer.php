</main>

<footer class="rodape">
    <div class="rodape-topo">
        <div class="rodape-marca">
            <span class="rodape-logo-badge" aria-hidden="true">
                <img src="<?= e(BASE_URL) ?>/assets/img/logo-icon.svg"
                     alt="" width="30" height="24">
            </span>
            <div class="rodape-marca-texto">
                <strong>BiblioTech</strong>
                <span>Sistema de Gerenciamento de Biblioteca Escolar</span>
            </div>
        </div>

        <a href="#" class="rodape-topo-link" aria-label="Voltar ao topo da página">
            <span aria-hidden="true">↑</span> Voltar ao topo
        </a>
    </div>

    <div class="rodape-base">
        <div class="rodape-base-conteudo">
            <span class="rodape-copy">
                &copy; <?= date('Y') ?> BiblioTech &middot; Todos os direitos reservados
            </span>
            <span class="rodape-credito">
                Projeto acadêmico <strong>ORBIT</strong>
            </span>
        </div>
    </div>
</footer>

<script src="<?= asset('assets/js/script.js') ?>"></script>
</body>
</html>
