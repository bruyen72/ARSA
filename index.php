<?php
/**
 * Roteador único do site ARSA (estilo "Flask manual").
 *
 * Todas as requisições passam por aqui (via .htaccess) e são
 * despachadas para a função de controller correspondente.
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/controllers/public.php';
require_once __DIR__ . '/includes/controllers/admin.php';

// Quando rodando com o servidor embutido do PHP (php -S), arquivos que já
// existem fisicamente (CSS, JS, imagens, uploads) são servidos direto,
// sem passar pelo roteador.
if (PHP_SAPI === 'cli-server') {
    $caminhoSolicitado = urldecode((string) parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
    $arquivoFisico = __DIR__ . $caminhoSolicitado;
    if ($caminhoSolicitado !== '/' && is_file($arquivoFisico)) {
        return false;
    }
}

$metodo = $_SERVER['REQUEST_METHOD'];
$uri = (string) parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Remove o BASE_URL (caso o site esteja numa subpasta) do caminho.
$caminho = $uri;
if (BASE_URL !== '' && str_starts_with($caminho, BASE_URL)) {
    $caminho = substr($caminho, strlen(BASE_URL));
}
if ($caminho === '' || $caminho === false) {
    $caminho = '/';
}
// Remove barra final (exceto na raiz).
if ($caminho !== '/' && substr($caminho, -1) === '/') {
    $caminho = rtrim($caminho, '/');
}

/**
 * Tabela de rotas: [método HTTP, regex do caminho, nome da função handler]
 * Os grupos capturados na regex são passados como argumentos do handler.
 */
$rotas = [
    // ---- Site público --------------------------------------------------
    ['GET', '#^/$#', 'home_page'],
    ['GET', '#^/produtos$#', 'produtos_page'],
    ['GET', '#^/produto/(\d+)$#', 'produto_page'],
    ['GET', '#^/servicos$#', 'servicos_page'],
    ['GET', '#^/quem-somos$#', 'quem_somos_page'],
    ['GET', '#^/contato$#', 'contato_page'],
    ['POST', '#^/contato$#', 'contato_submit'],
    ['GET', '#^/uploads/(.+)$#', 'serve_upload'],

    // ---- Admin: autenticação -------------------------------------------
    ['GET', '#^/admin/login$#', 'admin_login_page'],
    ['POST', '#^/admin/login$#', 'admin_login_submit'],
    ['GET', '#^/admin/logout$#', 'admin_logout'],
    ['GET', '#^/admin/senha$#', 'admin_password_page'],
    ['POST', '#^/admin/senha$#', 'admin_password_submit'],

    // ---- Admin: dashboard ------------------------------------------------
    ['GET', '#^/admin$#', 'admin_dashboard'],

    // ---- Admin: produtos --------------------------------------------------
    ['GET', '#^/admin/produtos/adicionar$#', 'admin_produto_form'],
    ['POST', '#^/admin/produtos/adicionar$#', 'admin_produto_create'],
    ['GET', '#^/admin/produtos/editar/(\d+)$#', 'admin_produto_form'],
    ['POST', '#^/admin/produtos/editar/(\d+)$#', 'admin_produto_update'],
    ['POST', '#^/admin/produtos/excluir/(\d+)$#', 'admin_produto_delete'],
    ['POST', '#^/admin/produtos/excluir-imagem/(\d+)$#', 'admin_produto_delete_image'],
    ['POST', '#^/admin/produtos/excluir-imagem-adicional/(\d+)/(\d+)$#', 'admin_produto_delete_gallery_image'],
    ['POST', '#^/admin/produtos/excluir-pdf/(\d+)$#', 'admin_produto_delete_pdf'],

    // ---- Admin: serviços ---------------------------------------------------
    ['GET', '#^/admin/servicos/adicionar$#', 'admin_servico_form'],
    ['POST', '#^/admin/servicos/adicionar$#', 'admin_servico_create'],
    ['GET', '#^/admin/servicos/editar/(\d+)$#', 'admin_servico_form'],
    ['POST', '#^/admin/servicos/editar/(\d+)$#', 'admin_servico_update'],
    ['POST', '#^/admin/servicos/excluir/(\d+)$#', 'admin_servico_delete'],

    // ---- Admin: conteúdo do site -------------------------------------------
    ['GET', '#^/admin/conteudo$#', 'admin_conteudo_page'],
    ['POST', '#^/admin/conteudo$#', 'admin_conteudo_save'],
    ['GET', '#^/admin/conteudo/([a-z]+)$#', 'admin_conteudo_page'],
    ['POST', '#^/admin/conteudo/([a-z]+)$#', 'admin_conteudo_save'],

    // ---- Admin: mensagens de contato --------------------------------------
    ['GET', '#^/admin/mensagens$#', 'admin_mensagens_page'],
    ['POST', '#^/admin/mensagens/marcar/(\d+)$#', 'admin_mensagem_marcar'],
    ['POST', '#^/admin/mensagens/excluir/(\d+)$#', 'admin_mensagem_excluir'],

    // ---- Admin: categorias de produtos ------------------------------------
    ['GET',  '#^/admin/categorias$#',                     'admin_categorias_page'],
    ['POST', '#^/admin/categorias/adicionar$#',           'admin_categoria_adicionar'],
    ['POST', '#^/admin/categorias/renomear/([^/]+)$#',   'admin_categoria_renomear'],
    ['POST', '#^/admin/categorias/excluir/([^/]+)$#',    'admin_categoria_excluir'],

    // ---- Admin: ajuda -----------------------------------------------------
    ['GET', '#^/admin/ajuda$#', 'admin_ajuda_page'],
];

foreach ($rotas as [$rotaMetodo, $padrao, $handler]) {
    if ($rotaMetodo !== $metodo) {
        continue;
    }
    if (preg_match($padrao, $caminho, $matches)) {
        array_shift($matches);
        $handler(...$matches);
        exit;
    }
}

// Nenhuma rota encontrada -> 404
http_response_code(404);
echo render_template('404.html', [
    'title' => 'Página não encontrada',
    'active' => '',
]);
