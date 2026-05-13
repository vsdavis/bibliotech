<?php
/**
 * BiblioTech — Verificação de autenticação
 *
 * Inclua este arquivo no TOPO de toda página interna.
 * Se não houver sessão válida, redireciona ao login.
 *
 * Para checagem de permissões, use as funções de helpers.php:
 *   - isAdmin()
 *   - hasPermission('livros.editar')
 *   - requirePermission('livros.editar')   ← bloqueia se não tiver
 */

require_once __DIR__ . '/helpers.php';

if (!logado()) {
    flash('erro', 'É necessário fazer login para acessar essa página.');
    redirecionar(BASE_URL . '/login.php');
}

/**
 * Restringe o acesso a perfis específicos.
 *
 * Mantida para compatibilidade. Para novas páginas, prefira
 * requirePermission('codigo.permissao') que oferece controle
 * mais granular.
 *
 * @param string|array $perfis_permitidos
 */
function exigir_perfil(string|array $perfis_permitidos): void
{
    $perfis = is_array($perfis_permitidos)
            ? $perfis_permitidos
            : [$perfis_permitidos];

    if (!in_array($_SESSION['usuario_perfil'] ?? '', $perfis, true)) {
        flash('erro', 'Você não tem permissão para acessar essa funcionalidade.');
        redirecionar(BASE_URL . '/dashboard.php');
    }
}
