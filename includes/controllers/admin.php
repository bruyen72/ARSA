<?php
/**
 * Controladores do painel administrativo (/admin/*).
 * Todas as rotas (exceto /admin/login) exigem require_admin().
 */

require_once __DIR__ . '/../helpers.php';

// ----------------------------------------------------------------------
// Login / logout / senha
// ----------------------------------------------------------------------

function admin_login_page(): void
{
    if (current_admin()) {
        redirect('/admin');
    }
    echo render_template('admin/login.html', [
        'title' => 'Login administrativo',
    ]);
}

function admin_login_submit(): void
{
    verify_csrf();

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = db()->prepare('SELECT * FROM admins WHERE username = ?');
    $stmt->execute([$username]);
    $admin = $stmt->fetch();

    if (!$admin || !password_verify($password, $admin['password_hash'])) {
        flash('Usuário ou senha inválidos.', 'error');
        redirect('/admin/login');
    }

    session_regenerate_id(true);
    $_SESSION['admin_id'] = $admin['id'];

    flash('Bem-vindo(a), ' . $admin['username'] . '!', 'success');
    redirect('/admin');
}

function admin_logout(): void
{
    unset($_SESSION['admin_id']);
    flash('Sessão encerrada.', 'success');
    redirect('/admin/login');
}

function admin_password_page(): void
{
    require_admin();
    echo render_template('admin/senha.html', [
        'title' => 'Alterar senha',
    ]);
}

function admin_password_submit(): void
{
    require_admin();
    verify_csrf();

    $admin = current_admin();
    $atual = $_POST['senha_atual'] ?? '';
    $nova = $_POST['nova_senha'] ?? '';
    $confirma = $_POST['confirma_senha'] ?? '';

    $stmt = db()->prepare('SELECT * FROM admins WHERE id = ?');
    $stmt->execute([$admin['id']]);
    $row = $stmt->fetch();

    if (!password_verify($atual, $row['password_hash'])) {
        flash('A senha atual está incorreta.', 'error');
        redirect('/admin/senha');
    }
    if (strlen($nova) < 8) {
        flash('A nova senha precisa ter pelo menos 8 caracteres.', 'error');
        redirect('/admin/senha');
    }
    if ($nova !== $confirma) {
        flash('A confirmação não confere com a nova senha.', 'error');
        redirect('/admin/senha');
    }

    $hash = password_hash($nova, PASSWORD_DEFAULT);
    $upd = db()->prepare('UPDATE admins SET password_hash = ? WHERE id = ?');
    $upd->execute([$hash, $admin['id']]);

    flash('Senha alterada com sucesso!', 'success');
    redirect('/admin');
}

// ----------------------------------------------------------------------
// Dashboard
// ----------------------------------------------------------------------

function admin_dashboard(): void
{
    require_admin();

    $totalProdutos = (int) db()->query('SELECT COUNT(*) FROM products')->fetchColumn();
    $totalServicos = (int) db()->query('SELECT COUNT(*) FROM services')->fetchColumn();
    $totalMensagens = (int) db()->query('SELECT COUNT(*) FROM messages')->fetchColumn();
    $mensagensNaoLidas = (int) db()->query('SELECT COUNT(*) FROM messages WHERE lida = 0')->fetchColumn();

    $produtos = db()->query('SELECT * FROM products ORDER BY created_at DESC, id DESC')->fetchAll();
    $servicos = db()->query('SELECT * FROM services ORDER BY created_at DESC, id DESC')->fetchAll();

    echo render_template('admin/dashboard.html', [
        'title' => 'Painel administrativo',
        'total_produtos' => $totalProdutos,
        'total_servicos' => $totalServicos,
        'total_mensagens' => $totalMensagens,
        'mensagens_nao_lidas' => $mensagensNaoLidas,
        'produtos' => $produtos,
        'servicos' => $servicos,
    ]);
}

// ----------------------------------------------------------------------
// Produtos (CRUD)
// ----------------------------------------------------------------------

function admin_produto_form(?string $id = null): void
{
    require_admin();

    $product = [
        'id' => null,
        'name' => '',
        'description' => '',
        'brand' => '',
        'category' => array_key_first(get_categories()),
        'specs' => [],
        'highlights' => [],
        'image_path' => null,
        'image_paths' => [],
        'pdf_path' => null,
    ];

    if ($id !== null) {
        $stmt = db()->prepare('SELECT * FROM products WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) {
            flash('Produto não encontrado.', 'error');
            redirect('/admin');
        }
        $row['specs'] = json_decode($row['specs'] ?? '[]', true) ?: [];
        $row['highlights'] = json_decode($row['highlights'] ?? '[]', true) ?: [];
        $row['image_paths'] = json_decode($row['image_paths'] ?? '[]', true) ?: [];
        $product = $row;
    }

    echo render_template('admin/produto_form.html', [
        'title' => $id !== null ? 'Editar produto' : 'Novo produto',
        'product' => $product,
        'categories' => get_categories(),
    ]);
}

function admin_produto_create(): void
{
    require_admin();
    verify_csrf();
    save_produto(null);
}

function admin_produto_update(string $id): void
{
    require_admin();
    verify_csrf();
    save_produto($id);
}

function save_produto(?string $id): void
{
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $brand = trim($_POST['brand'] ?? '');
    $category = $_POST['category'] ?? '';
    $specs = text_to_specs($_POST['specs'] ?? '');
    $highlights = lines_to_array($_POST['highlights'] ?? '');

    $voltarPara = $id !== null ? "/admin/produtos/editar/{$id}" : '/admin/produtos/adicionar';

    if ($name === '' || !isset(get_categories()[$category])) {
        flash('Preencha o nome e escolha uma categoria válida.', 'error');
        redirect($voltarPara);
    }

    try {
        $imagemPrincipal = handle_upload('imagem', ALLOWED_IMAGE_EXT, 'products');
        $pdf = handle_upload('pdf', ALLOWED_DOC_EXT, 'products');
        $galeria = handle_multiple_uploads('galeria', ALLOWED_IMAGE_EXT, 'products');
    } catch (RuntimeException $e) {
        flash($e->getMessage(), 'error');
        redirect($voltarPara);
    }

    if ($id === null) {
        $stmt = db()->prepare('
            INSERT INTO products (name, description, brand, category, specs, highlights, image_path, image_paths, pdf_path)
            VALUES (:name, :description, :brand, :category, :specs, :highlights, :image_path, :image_paths, :pdf_path)
        ');
        $stmt->execute([
            ':name' => $name,
            ':description' => $description,
            ':brand' => $brand,
            ':category' => $category,
            ':specs' => json_encode($specs, JSON_UNESCAPED_UNICODE),
            ':highlights' => json_encode($highlights, JSON_UNESCAPED_UNICODE),
            ':image_path' => $imagemPrincipal['path'] ?? null,
            ':image_paths' => json_encode(array_map(fn ($f) => $f['path'], $galeria), JSON_UNESCAPED_UNICODE),
            ':pdf_path' => $pdf['path'] ?? null,
        ]);

        flash('Produto cadastrado com sucesso.', 'success');
        redirect('/admin');
    }

    $stmtAtual = db()->prepare('SELECT image_path, image_paths, pdf_path FROM products WHERE id = ?');
    $stmtAtual->execute([$id]);
    $atual = $stmtAtual->fetch();

    if (!$atual) {
        flash('Produto não encontrado.', 'error');
        redirect('/admin');
    }

    $imagePath = $atual['image_path'];
    if ($imagemPrincipal) {
        delete_upload($imagePath);
        $imagePath = $imagemPrincipal['path'];
    }

    $pdfPath = $atual['pdf_path'];
    if ($pdf) {
        delete_upload($pdfPath);
        $pdfPath = $pdf['path'];
    }

    $imagePaths = json_decode($atual['image_paths'] ?? '[]', true) ?: [];
    foreach ($galeria as $arquivo) {
        $imagePaths[] = $arquivo['path'];
    }

    $stmt = db()->prepare('
        UPDATE products
        SET name = :name, description = :description, brand = :brand, category = :category,
            specs = :specs, highlights = :highlights, image_path = :image_path,
            image_paths = :image_paths, pdf_path = :pdf_path
        WHERE id = :id
    ');
    $stmt->execute([
        ':name' => $name,
        ':description' => $description,
        ':brand' => $brand,
        ':category' => $category,
        ':specs' => json_encode($specs, JSON_UNESCAPED_UNICODE),
        ':highlights' => json_encode($highlights, JSON_UNESCAPED_UNICODE),
        ':image_path' => $imagePath,
        ':image_paths' => json_encode($imagePaths, JSON_UNESCAPED_UNICODE),
        ':pdf_path' => $pdfPath,
        ':id' => $id,
    ]);

    flash('Produto atualizado com sucesso.', 'success');
    redirect('/admin');
}

function admin_produto_delete(string $id): void
{
    require_admin();
    verify_csrf();

    $stmt = db()->prepare('SELECT image_path, image_paths, pdf_path FROM products WHERE id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch();

    if ($row) {
        delete_upload($row['image_path']);
        delete_upload($row['pdf_path']);
        foreach (json_decode($row['image_paths'] ?? '[]', true) ?: [] as $img) {
            delete_upload($img);
        }
        $del = db()->prepare('DELETE FROM products WHERE id = ?');
        $del->execute([$id]);
        flash('Produto excluído.', 'success');
    } else {
        flash('Produto não encontrado.', 'error');
    }

    redirect('/admin');
}

function admin_produto_delete_image(string $id): void
{
    require_admin();
    verify_csrf();

    $stmt = db()->prepare('SELECT image_path FROM products WHERE id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch();

    if ($row && $row['image_path']) {
        delete_upload($row['image_path']);
        $upd = db()->prepare('UPDATE products SET image_path = NULL WHERE id = ?');
        $upd->execute([$id]);
        flash('Imagem principal removida.', 'success');
    }

    redirect("/admin/produtos/editar/{$id}");
}

function admin_produto_delete_gallery_image(string $id, string $index): void
{
    require_admin();
    verify_csrf();

    $stmt = db()->prepare('SELECT image_paths FROM products WHERE id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch();

    if ($row) {
        $imagens = json_decode($row['image_paths'] ?? '[]', true) ?: [];
        $idx = (int) $index;
        if (isset($imagens[$idx])) {
            delete_upload($imagens[$idx]);
            array_splice($imagens, $idx, 1);
            $upd = db()->prepare('UPDATE products SET image_paths = ? WHERE id = ?');
            $upd->execute([json_encode($imagens, JSON_UNESCAPED_UNICODE), $id]);
            flash('Imagem da galeria removida.', 'success');
        }
    }

    redirect("/admin/produtos/editar/{$id}");
}

function admin_produto_delete_pdf(string $id): void
{
    require_admin();
    verify_csrf();

    $stmt = db()->prepare('SELECT pdf_path FROM products WHERE id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch();

    if ($row && $row['pdf_path']) {
        delete_upload($row['pdf_path']);
        $upd = db()->prepare('UPDATE products SET pdf_path = NULL WHERE id = ?');
        $upd->execute([$id]);
        flash('Ficha técnica (PDF) removida.', 'success');
    }

    redirect("/admin/produtos/editar/{$id}");
}

// ----------------------------------------------------------------------
// Serviços (CRUD)
// ----------------------------------------------------------------------

function admin_servico_form(?string $id = null): void
{
    require_admin();

    $service = [
        'id' => null,
        'name' => '',
        'description' => '',
        'category' => '',
        'features' => [],
        'image_path' => null,
    ];

    if ($id !== null) {
        $stmt = db()->prepare('SELECT * FROM services WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) {
            flash('Serviço não encontrado.', 'error');
            redirect('/admin');
        }
        $row['features'] = json_decode($row['features'] ?? '[]', true) ?: [];
        $service = $row;
    }

    echo render_template('admin/servico_form.html', [
        'title' => $id !== null ? 'Editar serviço' : 'Novo serviço',
        'service' => $service,
    ]);
}

function admin_servico_create(): void
{
    require_admin();
    verify_csrf();
    save_servico(null);
}

function admin_servico_update(string $id): void
{
    require_admin();
    verify_csrf();
    save_servico($id);
}

function save_servico(?string $id): void
{
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $features = lines_to_array($_POST['features'] ?? '');

    $voltarPara = $id !== null ? "/admin/servicos/editar/{$id}" : '/admin/servicos/adicionar';

    if ($name === '') {
        flash('Preencha o nome do serviço.', 'error');
        redirect($voltarPara);
    }

    try {
        $imagem = handle_upload('imagem', ALLOWED_IMAGE_EXT, 'services');
    } catch (RuntimeException $e) {
        flash($e->getMessage(), 'error');
        redirect($voltarPara);
    }

    if ($id === null) {
        $stmt = db()->prepare('
            INSERT INTO services (name, description, category, features, image_path)
            VALUES (:name, :description, :category, :features, :image_path)
        ');
        $stmt->execute([
            ':name' => $name,
            ':description' => $description,
            ':category' => $category,
            ':features' => json_encode($features, JSON_UNESCAPED_UNICODE),
            ':image_path' => $imagem['path'] ?? null,
        ]);

        flash('Serviço cadastrado com sucesso.', 'success');
        redirect('/admin');
    }

    $stmtAtual = db()->prepare('SELECT image_path FROM services WHERE id = ?');
    $stmtAtual->execute([$id]);
    $atual = $stmtAtual->fetch();

    if (!$atual) {
        flash('Serviço não encontrado.', 'error');
        redirect('/admin');
    }

    $imagePath = $atual['image_path'];
    if ($imagem) {
        // Só apaga o arquivo antigo se ele tiver sido enviado pelo admin
        // (não apaga as imagens padrão do tema, em /static).
        if ($imagePath && strpos($imagePath, 'static/') !== 0) {
            delete_upload($imagePath);
        }
        $imagePath = $imagem['path'];
    }

    $stmt = db()->prepare('
        UPDATE services
        SET name = :name, description = :description, category = :category,
            features = :features, image_path = :image_path
        WHERE id = :id
    ');
    $stmt->execute([
        ':name' => $name,
        ':description' => $description,
        ':category' => $category,
        ':features' => json_encode($features, JSON_UNESCAPED_UNICODE),
        ':image_path' => $imagePath,
        ':id' => $id,
    ]);

    flash('Serviço atualizado com sucesso.', 'success');
    redirect('/admin');
}

function admin_servico_delete(string $id): void
{
    require_admin();
    verify_csrf();

    $stmt = db()->prepare('SELECT image_path FROM services WHERE id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch();

    if ($row) {
        if ($row['image_path'] && strpos($row['image_path'], 'static/') !== 0) {
            delete_upload($row['image_path']);
        }
        $del = db()->prepare('DELETE FROM services WHERE id = ?');
        $del->execute([$id]);
        flash('Serviço excluído.', 'success');
    } else {
        flash('Serviço não encontrado.', 'error');
    }

    redirect('/admin');
}

// ----------------------------------------------------------------------
// Conteúdo do site (textos editáveis)
// ----------------------------------------------------------------------

/**
 * Mapa de chave => [rótulo amigável, "input" ou "textarea"]
 */
function content_field_map(): array
{
    return [
        'hero_eyebrow' => ['Selo acima do título (Início)', 'input'],
        'hero_title_pre' => ['Título — texto principal (Início)', 'input'],
        'hero_title_destaque' => ['Título — trecho em destaque, azul (Início)', 'input'],
        'hero_lead' => ['Texto de apoio do banner (Início)', 'textarea'],
        'about_kicker' => ['Selo da seção "Quem somos" (Início)', 'input'],
        'about_title' => ['Título da seção "Quem somos" (Início)', 'input'],
        'about_text' => ['Texto 1 - "Quem somos" (Início)', 'textarea'],
        'about_text_extra' => ['Texto 2 - "Quem somos" (Início)', 'textarea'],
        'quem_somos_titulo' => ['Título - página Quem Somos', 'input'],
        'quem_somos_texto' => ['Texto completo - página Quem Somos (separe parágrafos com uma linha em branco)', 'textarea'],
        'produtos_titulo' => ['Título - página Produtos', 'input'],
        'produtos_texto' => ['Texto de apoio - página Produtos', 'textarea'],
        'warranty_years' => ['Número de anos de garantia', 'input'],
        'warranty_title' => ['Título do selo de garantia', 'input'],
        'warranty_text' => ['Texto do selo de garantia', 'textarea'],
        'contact_phone_display' => ['Telefone exibido no site', 'input'],
        'contact_whatsapp' => ['WhatsApp (somente números, com DDI+DDD, ex: 556584451909)', 'input'],
        'contact_address_line1' => ['Endereço - linha 1', 'input'],
        'contact_address_line2' => ['Endereço - linha 2', 'input'],
        'contact_email' => ['E-mail de contato exibido no site', 'input'],
        'contact_hours' => ['Horário de atendimento', 'input'],
        'social_instagram' => ['Link do Instagram', 'input'],
        'social_facebook' => ['Link do Facebook', 'input'],
        'social_linkedin' => ['Link do LinkedIn', 'input'],
        'instagram_widget_id' => ['ID do widget Elfsight (feed do Instagram na home)', 'input'],
        'footer_copyright' => ['Texto de copyright (rodapé)', 'input'],
        'footer_location' => ['Cidade / UF (rodapé)', 'input'],
    ];
}

/**
 * Seções da tela "Editar conteúdo do site". Cada seção vira uma aba própria,
 * com seus campos de texto e (quando houver) uma foto.
 */
function content_sections(): array
{
    return [
        'inicio' => [
            'label' => 'Início',
            'descricao' => 'Banner principal e seção "Quem somos" exibidos na página inicial.',
            'campos' => ['hero_eyebrow', 'hero_title_pre', 'hero_title_destaque', 'hero_lead', 'about_kicker', 'about_title', 'about_text', 'about_text_extra'],
            'imagem' => 'about_foto',
            'imagem_label' => 'Foto da seção "Quem somos" (página inicial)',
        ],
        'produtos' => [
            'label' => 'Produtos',
            'descricao' => 'Título e texto de apoio exibidos no topo da página Produtos.',
            'campos' => ['produtos_titulo', 'produtos_texto'],
        ],
        'somos' => [
            'label' => 'Quem Somos',
            'descricao' => 'Texto institucional completo, foto e selo de garantia.',
            'campos' => ['quem_somos_titulo', 'quem_somos_texto', 'warranty_years', 'warranty_title', 'warranty_text'],
            'imagem' => 'quem_somos_foto',
            'imagem_label' => 'Foto da página Quem Somos',
        ],
        'contato' => [
            'label' => 'Contato',
            'descricao' => 'Telefone, endereço, redes sociais e rodapé do site.',
            'campos' => ['contact_phone_display', 'contact_whatsapp', 'contact_email', 'contact_hours', 'contact_address_line1', 'contact_address_line2', 'social_instagram', 'social_facebook', 'social_linkedin', 'instagram_widget_id', 'footer_copyright', 'footer_location'],
        ],
    ];
}

function admin_conteudo_page(string $secao = 'inicio'): void
{
    require_admin();

    $secoes = content_sections();
    if (!isset($secoes[$secao])) {
        $secao = 'inicio';
    }

    $rows = db()->query('SELECT chave, valor FROM site_content')->fetchAll();
    // Começa com os valores padrão (seed_content) para garantir que campos
    // adicionados depois da primeira instalação apareçam preenchidos no
    // formulário, mesmo que ainda não existam na tabela site_content.
    $valores = seed_content();
    foreach ($rows as $row) {
        $valores[$row['chave']] = $row['valor'];
    }

    echo render_template('admin/conteudo.html', [
        'title' => 'Editar conteúdo do site',
        'secoes' => $secoes,
        'secao_atual' => $secao,
        'campos' => content_field_map(),
        'valores' => $valores,
    ]);
}

function admin_conteudo_save(string $secao = 'inicio'): void
{
    require_admin();
    verify_csrf();

    $secoes = content_sections();
    if (!isset($secoes[$secao])) {
        $secao = 'inicio';
    }
    $info = $secoes[$secao];
    $voltarPara = '/admin/conteudo/' . $secao;

    $campos = content_field_map();
    foreach ($info['campos'] as $chave) {
        if (isset($campos[$chave]) && isset($_POST[$chave])) {
            set_content($chave, trim((string) $_POST[$chave]));
        }
    }

    if (!empty($info['imagem'])) {
        $chaveImg = $info['imagem'];

        try {
            $novaImagem = handle_upload($chaveImg, ALLOWED_IMAGE_EXT, 'conteudo');
        } catch (RuntimeException $e) {
            flash($e->getMessage(), 'error');
            redirect($voltarPara);
        }

        if ($novaImagem) {
            $atual = get_content($chaveImg, '');
            if ($atual) {
                delete_upload($atual);
            }
            set_content($chaveImg, $novaImagem['path']);
        } elseif (!empty($_POST['remover_' . $chaveImg])) {
            $atual = get_content($chaveImg, '');
            if ($atual) {
                delete_upload($atual);
            }
            set_content($chaveImg, '');
        }
    }

    flash('Conteúdo atualizado com sucesso.', 'success');
    redirect($voltarPara);
}

// ----------------------------------------------------------------------
// Mensagens recebidas pelo formulário de Contato
// ----------------------------------------------------------------------

function admin_mensagens_page(): void
{
    require_admin();

    $mensagens = db()->query('SELECT * FROM messages ORDER BY created_at DESC')->fetchAll();

    echo render_template('admin/mensagens.html', [
        'title' => 'Mensagens recebidas',
        'mensagens' => $mensagens,
    ]);
}

function admin_mensagem_marcar(string $id): void
{
    require_admin();
    verify_csrf();

    $stmt = db()->prepare('UPDATE messages SET lida = CASE WHEN lida = 0 THEN 1 ELSE 0 END WHERE id = ?');
    $stmt->execute([$id]);

    redirect('/admin/mensagens');
}

function admin_mensagem_excluir(string $id): void
{
    require_admin();
    verify_csrf();

    $stmt = db()->prepare('DELETE FROM messages WHERE id = ?');
    $stmt->execute([$id]);

    flash('Mensagem excluída.', 'success');
    redirect('/admin/mensagens');
}

// ----------------------------------------------------------------------
// Categorias de produtos (gerenciadas no banco via site_content)
// ----------------------------------------------------------------------

function admin_categorias_page(): void
{
    require_admin();

    echo render_template('admin/categorias.html', [
        'title'      => 'Categorias de produtos',
        'categories' => get_categories(),
    ]);
}

function admin_categoria_adicionar(): void
{
    require_admin();
    verify_csrf();

    $label = trim($_POST['label'] ?? '');
    $slug  = trim($_POST['slug']  ?? '');

    // Gera slug automaticamente a partir do rótulo se não foi informado
    if ($slug === '' && $label !== '') {
        $slug = preg_replace('/[^a-z0-9]+/', '_', strtolower(iconv('UTF-8', 'ASCII//TRANSLIT', $label)));
        $slug = trim($slug, '_');
    }

    if ($label === '' || $slug === '') {
        flash('Preencha o nome da categoria.', 'error');
        redirect('/admin/categorias');
    }

    $cats = get_categories();

    if (isset($cats[$slug])) {
        flash('Já existe uma categoria com esse identificador: ' . $slug, 'error');
        redirect('/admin/categorias');
    }

    $cats[$slug] = $label;
    save_categories($cats);

    flash('Categoria "' . $label . '" adicionada.', 'success');
    redirect('/admin/categorias');
}

function admin_categoria_renomear(string $slug): void
{
    require_admin();
    verify_csrf();

    $label = trim($_POST['label'] ?? '');
    if ($label === '') {
        flash('O nome não pode ficar em branco.', 'error');
        redirect('/admin/categorias');
    }

    $cats = get_categories();
    if (!isset($cats[$slug])) {
        flash('Categoria não encontrada.', 'error');
        redirect('/admin/categorias');
    }

    $cats[$slug] = $label;
    save_categories($cats);

    flash('Categoria renomeada para "' . $label . '".', 'success');
    redirect('/admin/categorias');
}

function admin_categoria_excluir(string $slug): void
{
    require_admin();
    verify_csrf();

    // Verifica se há produtos usando esta categoria
    $stmt = db()->prepare('SELECT COUNT(*) FROM products WHERE category = ?');
    $stmt->execute([$slug]);
    $count = (int) $stmt->fetchColumn();

    if ($count > 0) {
        flash("Não é possível excluir: há {$count} produto(s) nesta categoria. Reatribua-os antes.", 'error');
        redirect('/admin/categorias');
    }

    $cats = get_categories();
    unset($cats[$slug]);
    save_categories($cats);

    flash('Categoria excluída.', 'success');
    redirect('/admin/categorias');
}

// ----------------------------------------------------------------------
// Marcas (CRUD)
// ----------------------------------------------------------------------

function admin_marcas_page(): void
{
    require_admin();

    $marcas = db()->query("
        SELECT b.*,
               (SELECT COUNT(*) FROM products
                WHERE LOWER(brand) LIKE LOWER('%' || b.search_term || '%')) AS total_produtos
        FROM brands b
        ORDER BY b.sort_order ASC, b.id ASC
    ")->fetchAll();

    echo render_template('admin/marcas.html', [
        'title'  => 'Marcas',
        'marcas' => $marcas,
    ]);
}

function admin_marca_form(?string $id = null): void
{
    require_admin();

    $marca = [
        'id' => null, 'name' => '', 'slug' => '',
        'logo_path' => '', 'description' => '',
        'search_term' => '', 'sort_order' => 0,
    ];

    if ($id !== null) {
        $stmt = db()->prepare('SELECT * FROM brands WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) {
            flash('Marca não encontrada.', 'error');
            redirect('/admin/marcas');
        }
        $marca = $row;
    }

    echo render_template('admin/marca_form.html', [
        'title' => $id !== null ? 'Editar marca' : 'Nova marca',
        'marca' => $marca,
    ]);
}

function admin_marca_create(): void
{
    require_admin();
    verify_csrf();
    save_marca(null);
}

function admin_marca_update(string $id): void
{
    require_admin();
    verify_csrf();
    save_marca($id);
}

function save_marca(?string $id): void
{
    $name        = trim($_POST['name'] ?? '');
    $slug        = strtolower(trim($_POST['slug'] ?? ''));
    $slug        = preg_replace('/[^a-z0-9\-]/', '', $slug);
    $description = trim($_POST['description'] ?? '');
    $search_term = trim($_POST['search_term'] ?? '') ?: $slug;
    $sort_order  = (int) ($_POST['sort_order'] ?? 0);

    $back = $id !== null ? "/admin/marcas/editar/{$id}" : '/admin/marcas/adicionar';

    if ($name === '' || $slug === '') {
        flash('Preencha o nome e o identificador (slug).', 'error');
        redirect($back);
    }

    // Logo: aceita jpg/png/webp/svg
    $logo_path = null;
    if (!empty($_FILES['logo']['name'])) {
        try {
            $upload = handle_upload('logo', array_merge(ALLOWED_IMAGE_EXT, ['svg']), 'brands');
            if ($upload) {
                $logo_path = '/uploads/' . $upload['path'];
            }
        } catch (RuntimeException $e) {
            flash($e->getMessage(), 'error');
            redirect($back);
        }
    }

    if ($id === null) {
        $stmt = db()->prepare('
            INSERT INTO brands (name, slug, logo_path, description, search_term, sort_order)
            VALUES (?, ?, ?, ?, ?, ?)
        ');
        $stmt->execute([$name, $slug, $logo_path, $description, $search_term, $sort_order]);
        flash('Marca cadastrada com sucesso.', 'success');
    } else {
        $atual = db()->prepare('SELECT logo_path FROM brands WHERE id = ?');
        $atual->execute([$id]);
        $row = $atual->fetch();
        $final_logo = $logo_path ?? ($row['logo_path'] ?? null);

        $stmt = db()->prepare('
            UPDATE brands SET name=?, slug=?, logo_path=?, description=?, search_term=?, sort_order=?
            WHERE id=?
        ');
        $stmt->execute([$name, $slug, $final_logo, $description, $search_term, $sort_order, $id]);
        flash('Marca atualizada com sucesso.', 'success');
    }

    redirect('/admin/marcas');
}

function admin_marca_delete(string $id): void
{
    require_admin();
    verify_csrf();

    $stmt = db()->prepare('DELETE FROM brands WHERE id = ?');
    $stmt->execute([$id]);
    flash('Marca excluída.', 'success');
    redirect('/admin/marcas');
}

// ----------------------------------------------------------------------
// Ajuda / tutorial do painel
// ----------------------------------------------------------------------

function admin_ajuda_page(): void
{
    require_admin();

    echo render_template('admin/ajuda.html', [
        'title' => 'Ajuda',
    ]);
}
