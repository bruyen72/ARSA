<?php
/**
 * Controladores das páginas públicas do site.
 */

require_once __DIR__ . '/../helpers.php';

function home_page(): void
{
    $services = db()->query('SELECT * FROM services ORDER BY id ASC LIMIT 3')->fetchAll();

    echo render_template('home.html', [
        'title' => 'Início',
        'active' => 'home',
        'services' => $services,
    ]);
}

function produtos_page(): void
{
    // Definição das marcas com logo, descrição e padrão de busca no banco
    $marcas = [
        'hytera' => [
            'label'    => 'Hytera',
            'logo'     => '/static/img/brands/hytera.svg',
            'search'   => 'hytera',
            'descricao'=> 'Tecnologia DMR de alta performance para comunicação corporativa e industrial. Referência global em rádios digitais com criptografia, alta durabilidade e longa vida de bateria.',
        ],
        'intelbras' => [
            'label'    => 'Intelbras',
            'logo'     => '/static/img/brands/intelbras.svg',
            'search'   => 'intelbras',
            'descricao'=> 'Marca nacional com excelente custo-benefício e suporte técnico em todo o Brasil. Ideal para empresas que buscam confiabilidade com investimento acessível.',
        ],
        'caltta' => [
            'label'    => 'Caltta',
            'logo'     => '/static/img/brands/caltta.png',
            'search'   => 'caltta',
            'descricao'=> 'Solução PoC (Push-to-Talk over Cellular) que opera via rede celular e internet, eliminando barreiras de distância e cobertura. Perfeito para operações em área ampla.',
        ],
        'motorola' => [
            'label'    => 'Motorola Solutions',
            'logo'     => '/static/img/brands/motorola-solutions.svg',
            'search'   => 'motorola',
            'descricao'=> 'Líder mundial em radiocomunicação com décadas de inovação. Equipamentos robustos, confiáveis e com ecossistema completo de acessórios e suporte.',
        ],
    ];

    $marca_key  = isset($_GET['marca']) && isset($marcas[$_GET['marca']]) ? $_GET['marca'] : null;
    $categoria  = $_GET['category'] ?? 'todos';
    $categorias = array_merge(['todos' => 'Todos os produtos'], get_categories());

    if ($categoria !== 'todos' && !isset(get_categories()[$categoria])) {
        $categoria = 'todos';
    }

    $produtos = [];
    if ($marca_key !== null) {
        $busca = '%' . $marcas[$marca_key]['search'] . '%';
        if ($categoria === 'todos') {
            $stmt = db()->prepare('SELECT * FROM products WHERE LOWER(brand) LIKE LOWER(?) ORDER BY category, name');
            $stmt->execute([$busca]);
        } else {
            $stmt = db()->prepare('SELECT * FROM products WHERE LOWER(brand) LIKE LOWER(?) AND category = ? ORDER BY name');
            $stmt->execute([$busca, $categoria]);
        }
        $produtos = $stmt->fetchAll();
    }

    foreach ($produtos as &$p) {
        $p['highlights']   = json_decode($p['highlights'] ?? '[]', true) ?: [];
        $p['image_paths']  = json_decode($p['image_paths'] ?? '[]', true) ?: [];
    }
    unset($p);

    echo render_template('produtos.html', [
        'title'            => 'Produtos',
        'active'           => 'produtos',
        'products'         => $produtos,
        'categories'       => $categorias,
        'current_category' => $categoria,
        'marcas'           => $marcas,
        'current_marca'    => $marca_key,
        'marca_info'       => $marca_key ? $marcas[$marca_key] : null,
    ]);
}

function produto_page(string $id): void
{
    $stmt = db()->prepare('SELECT * FROM products WHERE id = ?');
    $stmt->execute([$id]);
    $product = $stmt->fetch();

    if (!$product) {
        http_response_code(404);
        echo render_template('404.html', ['title' => 'Produto não encontrado', 'active' => 'produtos']);
        return;
    }

    $product['specs'] = json_decode($product['specs'] ?? '[]', true) ?: [];
    $product['highlights'] = json_decode($product['highlights'] ?? '[]', true) ?: [];
    $product['image_paths'] = json_decode($product['image_paths'] ?? '[]', true) ?: [];

    $stmtR = db()->prepare('
        SELECT * FROM products
        WHERE category = ? AND id != ?
        ORDER BY RANDOM()
        LIMIT 3
    ');
    $stmtR->execute([$product['category'], $product['id']]);
    $relacionados = $stmtR->fetchAll();
    foreach ($relacionados as &$r) {
        $r['highlights'] = json_decode($r['highlights'] ?? '[]', true) ?: [];
    }
    unset($r);

    echo render_template('produto.html', [
        'title' => $product['name'],
        'active' => 'produtos',
        'product' => $product,
        'related' => $relacionados,
    ]);
}

function servicos_page(): void
{
    $services = db()->query('SELECT * FROM services ORDER BY id ASC')->fetchAll();
    foreach ($services as &$s) {
        $s['features'] = json_decode($s['features'] ?? '[]', true) ?: [];
    }
    unset($s);

    echo render_template('servicos.html', [
        'title' => 'Serviços',
        'active' => 'servicos',
        'services' => $services,
    ]);
}

function quem_somos_page(): void
{
    echo render_template('quem-somos.html', [
        'title' => 'Quem Somos',
        'active' => 'quem-somos',
    ]);
}

function contato_page(): void
{
    echo render_template('contato.html', [
        'title' => 'Contato',
        'active' => 'contato',
    ]);
}

function contato_submit(): void
{
    verify_csrf();

    $nome = trim($_POST['nome'] ?? '');
    $empresa = trim($_POST['empresa'] ?? '');
    $telefone = trim($_POST['telefone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $interesse = trim($_POST['interesse'] ?? '');
    $mensagem = trim($_POST['mensagem'] ?? '');

    if ($nome === '' || $mensagem === '') {
        flash('Preencha pelo menos nome e mensagem antes de enviar.', 'error');
        redirect('/contato');
    }

    $stmt = db()->prepare('
        INSERT INTO messages (nome, empresa, telefone, email, interesse, mensagem)
        VALUES (?, ?, ?, ?, ?, ?)
    ');
    $stmt->execute([$nome, $empresa, $telefone, $email, $interesse, $mensagem]);

    if (SMTP_ENABLED) {
        require_once __DIR__ . '/../mailer.php';
        try {
            send_contact_email(compact('nome', 'empresa', 'telefone', 'email', 'interesse', 'mensagem'));
        } catch (Throwable $e) {
            // Mensagem já foi salva no banco; não bloqueia o envio do formulário.
            error_log('Falha ao enviar e-mail de contato: ' . $e->getMessage());
        }
    }

    flash('Mensagem recebida! Nossa equipe responde em instantes por aqui ou pelo WhatsApp.', 'success');
    redirect('/contato');
}

/**
 * Serve arquivos da pasta /uploads (fallback para servidores onde o
 * .htaccess não entrega o arquivo diretamente, ex: servidor embutido do PHP).
 */
function serve_upload(string $relativePath): void
{
    $relativePath = ltrim($relativePath, '/');
    $base = realpath(UPLOAD_DIR);
    $full = realpath(UPLOAD_DIR . '/' . $relativePath);

    if (!$base || !$full || strpos($full, $base) !== 0 || !is_file($full)) {
        http_response_code(404);
        echo 'Arquivo não encontrado.';
        return;
    }

    $mime = function_exists('mime_content_type') ? (mime_content_type($full) ?: 'application/octet-stream') : 'application/octet-stream';
    header('Content-Type: ' . $mime);
    header('Content-Length: ' . filesize($full));
    header('Cache-Control: public, max-age=31536000');
    readfile($full);
}
