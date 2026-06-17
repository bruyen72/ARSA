<?php
/**
 * Funções utilitárias do roteador (estilo "Flask manual"):
 * url_for, render_template, redirect, flash/get_flashed_messages,
 * get_content/set_content, autenticação do admin e upload de arquivos.
 */

require_once __DIR__ . '/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Caminho base da aplicação (permite rodar tanto na raiz do site quanto
// dentro de uma subpasta, ex: /arsa).
define('BASE_URL', rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/index.php')), '/'));

/**
 * Monta uma URL absoluta (a partir da raiz do site) considerando o
 * BASE_URL do projeto. Use sempre isso em vez de escrever "/produtos"
 * direto nos templates.
 */
function url_for(string $path = '/'): string
{
    if ($path === '') {
        $path = '/';
    }
    if ($path[0] !== '/') {
        $path = '/' . $path;
    }
    return BASE_URL . $path;
}

/**
 * Escapa texto para uso seguro em HTML.
 */
function h($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/**
 * Converte um texto com parágrafos separados por linha em branco para
 * HTML com tags <p>, escapando o conteúdo.
 */
function nl2p(string $text): string
{
    $paragrafos = preg_split('/\r?\n\r?\n/', trim($text));
    $html = '';
    foreach ($paragrafos as $p) {
        $p = trim($p);
        if ($p === '') {
            continue;
        }
        $html .= '<p>' . nl2br(h($p)) . "</p>\n";
    }
    return $html;
}

/**
 * Redireciona para uma rota interna e encerra a execução.
 */
function redirect(string $path): void
{
    header('Location: ' . url_for($path));
    exit;
}

/**
 * Registra uma mensagem "flash" para ser exibida na próxima página.
 * Categorias usuais: 'success', 'error', 'info'.
 */
function flash(string $message, string $category = 'success'): void
{
    $_SESSION['_flashes'][] = ['message' => $message, 'category' => $category];
}

/**
 * Retorna (e limpa) as mensagens flash pendentes.
 */
function get_flashed_messages(): array
{
    $flashes = $_SESSION['_flashes'] ?? [];
    unset($_SESSION['_flashes']);
    return $flashes;
}

/**
 * Renderiza um template .html (em /templates ou /templates/admin),
 * disponibilizando $vars como variáveis dentro do template.
 */
function render_template(string $template, array $vars = []): string
{
    extract($vars);
    ob_start();
    include __DIR__ . '/../templates/' . $template;
    return ob_get_clean();
}

// ----------------------------------------------------------------------
// Conteúdo editável do site (tabela site_content)
// ----------------------------------------------------------------------

/**
 * Busca um texto editável pelo admin. Retorna $default se a chave não
 * existir ou estiver vazia.
 */
function get_content(string $chave, string $default = ''): string
{
    static $cache = null;

    if ($cache === null) {
        $cache = [];
        $rows = db()->query('SELECT chave, valor FROM site_content')->fetchAll();
        foreach ($rows as $row) {
            $cache[$row['chave']] = $row['valor'];
        }
    }

    if (isset($cache[$chave]) && $cache[$chave] !== '') {
        return $cache[$chave];
    }

    return $default;
}

/**
 * Salva/atualiza um texto editável pelo admin.
 */
function set_content(string $chave, string $valor): void
{
    $stmt = db()->prepare('
        INSERT INTO site_content (chave, valor, updated_at)
        VALUES (:chave, :valor, CURRENT_TIMESTAMP)
        ON CONFLICT(chave) DO UPDATE SET valor = excluded.valor, updated_at = CURRENT_TIMESTAMP
    ');
    $stmt->execute([':chave' => $chave, ':valor' => $valor]);
}

// ----------------------------------------------------------------------
// Autenticação do admin
// ----------------------------------------------------------------------

/**
 * Retorna os dados do admin logado, ou null se não houver sessão.
 */
function current_admin(): ?array
{
    static $admin = null;
    static $checked = false;

    if ($checked) {
        return $admin;
    }
    $checked = true;

    if (empty($_SESSION['admin_id'])) {
        return null;
    }

    $stmt = db()->prepare('SELECT id, username FROM admins WHERE id = ?');
    $stmt->execute([$_SESSION['admin_id']]);
    $admin = $stmt->fetch() ?: null;

    return $admin;
}

/**
 * Garante que existe um admin logado; caso contrário, redireciona para
 * a tela de login.
 */
function require_admin(): void
{
    if (!current_admin()) {
        flash('Faça login para continuar.', 'error');
        redirect('/admin/login');
    }
}

// ----------------------------------------------------------------------
// Proteção CSRF para formulários do admin
// ----------------------------------------------------------------------

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . h(csrf_token()) . '">';
}

function verify_csrf(): void
{
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'] ?? '', (string) $token)) {
        http_response_code(400);
        flash('Sessão expirada, tente novamente.', 'error');
        redirect($_SERVER['HTTP_REFERER'] ?? '/admin');
    }
}

// ----------------------------------------------------------------------
// Upload de arquivos (imagens / PDFs)
// ----------------------------------------------------------------------

/**
 * Redimensiona uma imagem no disco para no máximo $maxW x $maxH pixels,
 * mantendo proporção. Preserva transparência em PNG.
 * Silencioso: não lança exceção — se GD não estiver disponível, ignora.
 */
function resize_image_if_needed(string $path, int $maxW = 1200, int $maxH = 1200): void
{
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) return;
    if (!function_exists('imagecreatefromjpeg')) return;

    $size = @getimagesize($path);
    if (!$size) return;
    [$origW, $origH] = $size;
    if ($origW <= $maxW && $origH <= $maxH) return; // já é pequena o suficiente

    $ratio = min($maxW / $origW, $maxH / $origH);
    $newW = (int) round($origW * $ratio);
    $newH = (int) round($origH * $ratio);

    $src = match($ext) {
        'png'  => @imagecreatefrompng($path),
        'webp' => @imagecreatefromwebp($path),
        default => @imagecreatefromjpeg($path),
    };
    if (!$src) return;

    $dst = imagecreatetruecolor($newW, $newH);

    if ($ext === 'png') {
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        $transparent = imagecolorallocatealpha($dst, 0, 0, 0, 127);
        imagefilledrectangle($dst, 0, 0, $newW, $newH, $transparent);
    }

    imagecopyresampled($dst, $src, 0, 0, 0, 0, $newW, $newH, $origW, $origH);

    match($ext) {
        'png'  => imagepng($dst, $path, 8),
        'webp' => imagewebp($dst, $path, 85),
        default => imagejpeg($dst, $path, 88),
    };

    imagedestroy($src);
    imagedestroy($dst);
}

/**
 * Processa um upload vindo de $_FILES[$field]. Retorna null se nenhum
 * arquivo foi enviado. Lança RuntimeException em caso de erro.
 *
 * @return array{path:string,url:string,original_name:string}|null
 */
function handle_upload(string $field, array $allowedExt, string $subdir): ?array
{
    if (empty($_FILES[$field]) || $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    $file = $_FILES[$field];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Erro ao enviar o arquivo (código ' . $file['error'] . ').');
    }

    if ($file['size'] > MAX_UPLOAD_SIZE) {
        $maxMb = round(MAX_UPLOAD_SIZE / 1024 / 1024, 1);
        throw new RuntimeException("Arquivo muito grande. O limite é {$maxMb} MB.");
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExt, true)) {
        throw new RuntimeException('Tipo de arquivo não permitido: .' . $ext);
    }

    $subdir = trim($subdir, '/');
    $dir = UPLOAD_DIR . '/' . $subdir;
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }

    $filename = date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $dest = $dir . '/' . $filename;

    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        throw new RuntimeException('Falha ao salvar o arquivo no servidor.');
    }

    // Redimensiona imagens grandes automaticamente (máx 1200x1200)
    resize_image_if_needed($dest);

    $relative = $subdir . '/' . $filename;
    save_file_metadata($file['name'], $relative);

    return [
        'path' => $relative,
        'url' => upload_url($relative),
        'original_name' => $file['name'],
    ];
}

/**
 * Registra metadados de upload em uploads/file_metadata.json
 * (nome original, nome salvo, data).
 */
function save_file_metadata(string $originalName, string $savedPath): void
{
    $metaFile = UPLOAD_DIR . '/file_metadata.json';
    $meta = [];
    if (is_file($metaFile)) {
        $conteudo = file_get_contents($metaFile);
        $meta = json_decode($conteudo, true);
        if (!is_array($meta)) {
            $meta = [];
        }
    }
    $meta[] = [
        'original_name' => $originalName,
        'saved_path' => $savedPath,
        'uploaded_at' => date('c'),
    ];
    file_put_contents($metaFile, json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

/**
 * Remove um arquivo enviado anteriormente (ignora se não existir).
 */
function delete_upload(?string $relativePath): void
{
    if (!$relativePath) {
        return;
    }
    $full = UPLOAD_DIR . '/' . ltrim($relativePath, '/');
    if (is_file($full)) {
        @unlink($full);
    }
}

/**
 * Monta a URL pública de um arquivo enviado (relativo a /uploads).
 */
function upload_url(?string $relativePath): ?string
{
    if (!$relativePath) {
        return null;
    }
    return url_for(UPLOAD_URL_PREFIX . '/' . ltrim($relativePath, '/'));
}

/**
 * Processa múltiplos arquivos enviados pelo mesmo campo (ex: galeria[]).
 * Reaproveita handle_upload() para cada arquivo individualmente.
 *
 * @return array<int, array{path:string,url:string,original_name:string}>
 */
function handle_multiple_uploads(string $field, array $allowedExt, string $subdir): array
{
    if (empty($_FILES[$field]) || !is_array($_FILES[$field]['name'] ?? null)) {
        return [];
    }

    $resultados = [];
    $total = count($_FILES[$field]['name']);

    for ($i = 0; $i < $total; $i++) {
        if ($_FILES[$field]['error'][$i] === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        $_FILES['__upload_unico'] = [
            'name' => $_FILES[$field]['name'][$i],
            'type' => $_FILES[$field]['type'][$i],
            'tmp_name' => $_FILES[$field]['tmp_name'][$i],
            'error' => $_FILES[$field]['error'][$i],
            'size' => $_FILES[$field]['size'][$i],
        ];
        $resultado = handle_upload('__upload_unico', $allowedExt, $subdir);
        if ($resultado) {
            $resultados[] = $resultado;
        }
    }

    unset($_FILES['__upload_unico']);

    return $resultados;
}

/**
 * Resolve a URL de uma imagem de produto/serviço, esteja ela em
 * /static (imagens padrão do tema) ou em /uploads (enviadas pelo admin).
 */
function media_url(?string $path): ?string
{
    if (!$path) {
        return null;
    }
    if (strpos($path, 'static/') === 0) {
        return url_for('/' . $path);
    }
    return upload_url($path);
}

// ----------------------------------------------------------------------
// Conversões texto <-> listas/specs (usadas nos formulários do admin)
// ----------------------------------------------------------------------

/**
 * Converte um array associativo de especificações em texto, uma por
 * linha, no formato "Chave: Valor".
 */
function specs_to_text(array $specs): string
{
    $linhas = [];
    foreach ($specs as $chave => $valor) {
        $linhas[] = $chave . ': ' . $valor;
    }
    return implode("\n", $linhas);
}

/**
 * Converte texto no formato "Chave: Valor" (uma por linha) em array
 * associativo de especificações.
 */
function text_to_specs(string $texto): array
{
    $specs = [];
    foreach (preg_split('/\r?\n/', $texto) as $linha) {
        $linha = trim($linha);
        if ($linha === '') {
            continue;
        }
        $partes = explode(':', $linha, 2);
        if (count($partes) === 2) {
            $specs[trim($partes[0])] = trim($partes[1]);
        }
    }
    return $specs;
}

/**
 * Converte uma lista (array) em texto, um item por linha.
 */
function array_to_lines(array $itens): string
{
    return implode("\n", $itens);
}

/**
 * Converte texto (um item por linha) em array, ignorando linhas vazias.
 */
function lines_to_array(string $texto): array
{
    $itens = [];
    foreach (preg_split('/\r?\n/', $texto) as $linha) {
        $linha = trim($linha);
        if ($linha !== '') {
            $itens[] = $linha;
        }
    }
    return $itens;
}

// ----------------------------------------------------------------------
// Diversos
// ----------------------------------------------------------------------

/**
 * Retorna as categorias de produtos salvas no banco (site_content).
 * Fallback para CATEGORY_LABELS do config.php se ainda não houver no banco.
 *
 * @return array<string,string>  chave => rótulo
 */
function get_categories(): array
{
    $json = get_content('product_categories', '');
    if ($json !== '') {
        $arr = json_decode($json, true);
        if (is_array($arr) && count($arr) > 0) {
            return $arr;
        }
    }
    return defined('CATEGORY_LABELS') ? CATEGORY_LABELS : [];
}

/**
 * Salva as categorias de volta no banco.
 *
 * @param array<string,string> $cats
 */
function save_categories(array $cats): void
{
    set_content('product_categories', json_encode($cats, JSON_UNESCAPED_UNICODE));
}

/**
 * Nome legível da categoria de produto.
 */
function category_label(string $cat): string
{
    return get_categories()[$cat] ?? ucfirst($cat);
}

/**
 * Monta um link do WhatsApp com mensagem pré-preenchida, usando o número
 * cadastrado em /admin/conteudo (ou o padrão da ARSA).
 */
function whatsapp_link(string $mensagem): string
{
    $numero = get_content('contact_whatsapp', '556584451909');
    return 'https://wa.me/' . $numero . '?text=' . rawurlencode($mensagem);
}
