<?php
/**
 * Conexão com o banco de dados (PDO) + criação automática do schema +
 * seed inicial (executado apenas uma vez, quando o banco está vazio).
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/seed_data.php';

/**
 * Retorna a conexão PDO (cria/abre o banco na primeira chamada).
 */
function db(): PDO
{
    static $pdo = null;

    if ($pdo !== null) {
        return $pdo;
    }

    $databaseUrl = getenv('DATABASE_URL');

    if ($databaseUrl) {
        // Permite trocar para MySQL/Postgres no futuro via variável de ambiente.
        // Formato esperado: "mysql:host=...;dbname=...|usuario|senha"
        $partes = explode('|', $databaseUrl);
        $dsn = $partes[0];
        $user = $partes[1] ?? null;
        $pass = $partes[2] ?? null;
        $pdo = new PDO($dsn, $user, $pass);
    } else {
        $dataDir = dirname(DB_PATH);
        if (!is_dir($dataDir)) {
            mkdir($dataDir, 0775, true);
        }
        $pdo = new PDO('sqlite:' . DB_PATH);
        $pdo->exec('PRAGMA foreign_keys = ON');
    }

    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    init_schema($pdo);

    return $pdo;
}

/**
 * Cria as tabelas (se não existirem) e popula dados iniciais na primeira
 * execução.
 */
function init_schema(PDO $pdo): void
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS products (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name VARCHAR(100) NOT NULL,
            description TEXT,
            image_path VARCHAR(200),
            pdf_path VARCHAR(200),
            category VARCHAR(50),
            specs TEXT,
            image_paths TEXT,
            highlights TEXT,
            brand VARCHAR(80),
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS services (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name VARCHAR(100) NOT NULL,
            description TEXT,
            features TEXT,
            image_path VARCHAR(200),
            category VARCHAR(50),
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS admins (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username VARCHAR(80) UNIQUE NOT NULL,
            password_hash VARCHAR(255) NOT NULL
        )
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS site_content (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            chave VARCHAR(100) UNIQUE NOT NULL,
            valor TEXT,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS messages (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            nome VARCHAR(120) NOT NULL,
            empresa VARCHAR(120),
            telefone VARCHAR(40),
            email VARCHAR(120),
            interesse VARCHAR(80),
            mensagem TEXT NOT NULL,
            lida INTEGER NOT NULL DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ");

    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_products_category_created ON products (category, created_at)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_products_created ON products (created_at)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_services_created ON services (created_at)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_messages_created ON messages (created_at)");

    seed_if_empty($pdo);
    sync_services($pdo);
}

/**
 * Gera uma senha forte e aleatória (sem caracteres ambíguos).
 */
function generate_strong_password(int $length = 14): string
{
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789@#%*+-';
    $max = strlen($chars) - 1;
    $senha = '';
    for ($i = 0; $i < $length; $i++) {
        $senha .= $chars[random_int(0, $max)];
    }
    return $senha;
}

/**
 * Garante que os textos dos serviços no banco estão atualizados.
 * Usa migrações por texto antigo → texto novo (idempotente, sem versão).
 * Roda em cada requisição mas só faz UPDATE quando ainda há texto desatualizado.
 */
function sync_services(PDO $pdo): void
{
    // Cada entrada: [campo_alvo, valor_antigo, valor_novo]
    $migracoes = [
        // Serviço 1 — descrição
        ['col' => 'description',
         'old' => 'Equipamentos portáteis, móveis e repetidoras para diária, quinzena ou mês, já programados para a sua equipe usar sem enrolação.',
         'new' => 'Soluções completas em radiocomunicação com rádios portáteis, móveis e repetidoras já configurados e preparados para entrar em operação.'],
        // Serviço 2 — descrição
        ['col' => 'description',
         'old' => 'Buscamos os rádios, avaliamos em bancada e devolvemos funcionando, com programação, testes e suporte direto.',
         'new' => 'Da coleta à entrega, garantimos um serviço completo: avaliação em bancada, programação, testes de funcionamento e suporte técnico para manter sua operação conectada.'],
        // Serviço 3 — nome
        ['col' => 'name',
         'old' => 'Rede dimensionada do jeito certo',
         'new' => 'Rede planejada e dimensionada para máxima eficiência operacional.'],
        // Serviço 3 — categoria
        ['col' => 'category',
         'old' => 'Consultoria e ANATEL',
         'new' => 'Consultoria para licenciamento junto à ANATEL'],
        // Serviço 3 — descrição (texto longo → curto)
        ['col' => 'description',
         'old' => 'Visitamos sua operação, analisamos as necessidades de comunicação, planejamos a cobertura, configuramos canais e repetidoras e acompanhamos a regularização necessária para uma operação segura, eficiente e sem interrupções.',
         'new' => 'Analisamos sua operação, planejamos a cobertura e cuidamos da configuração e regularização da comunicação.'],
        // Serviço 3 — descrição (variação gerada pelo sync anterior)
        ['col' => 'description',
         'old' => 'Visitamos sua operação, analisamos as necessidades de comunicação, planejamos a cobertura, configuramos canais e repetidoras e acompanhamos a regularização necessária para uma operação segura, eficiente e sem interrupções',
         'new' => 'Analisamos sua operação, planejamos a cobertura e cuidamos da configuração e regularização da comunicação.'],
        // Serviço 3 — descrição (texto atual no banco ao vivo)
        ['col' => 'description',
         'old' => 'Visitamos a operação, planejamos cobertura, repetição e canais, e cuidamos da regularização para a frequência operar com segurança.',
         'new' => 'Analisamos sua operação, planejamos a cobertura e cuidamos da configuração e regularização da comunicação.'],
    ];

    foreach ($migracoes as $m) {
        // PDO não permite bind de nomes de coluna — monta query diretamente (seguro: coluna vem do array interno)
        $col = $m['col'];
        $pdo->prepare("UPDATE services SET {$col} = :new WHERE {$col} = :old")
            ->execute([':new' => $m['new'], ':old' => $m['old']]);
    }
}

/**
 * Popula o banco na primeira execução (quando a tabela admins está vazia).
 */
function seed_if_empty(PDO $pdo): void
{
    $total = (int) $pdo->query('SELECT COUNT(*) FROM admins')->fetchColumn();
    if ($total > 0) {
        return;
    }

    // --- Cria o usuário admin inicial -----------------------------------
    $username = 'admin';
    $password = 'admin123';
    $hash = password_hash($password, PASSWORD_DEFAULT);

    $stmt = $pdo->prepare('INSERT INTO admins (username, password_hash) VALUES (?, ?)');
    $stmt->execute([$username, $hash]);

    // Salva as credenciais em arquivo local (fora do alcance do navegador,
    // protegido por data/.htaccess) para o admin consultar.
    $credFile = dirname(DB_PATH) . '/ADMIN_CREDENCIAIS.txt';
    file_put_contents(
        $credFile,
        "Credenciais do painel admin\n" .
        "=======================================================\n\n" .
        "URL: /admin/login\n" .
        "Usuário: {$username}\n" .
        "Senha:   {$password}\n\n" .
        "IMPORTANTE:\n" .
        "- Recomendado trocar essa senha em /admin/senha.\n"
    );

    // --- Produtos -----------------------------------------------------------
    // Catálogo começa vazio: a dona da empresa cadastra os produtos reais
    // pelo painel /admin/produtos.

    // --- Serviços -----------------------------------------------------------
    $stmtS = $pdo->prepare('
        INSERT INTO services (name, description, features, image_path, category)
        VALUES (:name, :description, :features, :image_path, :category)
    ');
    foreach (seed_services() as $s) {
        $stmtS->execute([
            ':name' => $s['name'],
            ':description' => $s['description'],
            ':features' => json_encode($s['features'], JSON_UNESCAPED_UNICODE),
            ':image_path' => $s['image_path'],
            ':category' => $s['category'],
        ]);
    }

    // --- Conteúdo do site (textos editáveis) --------------------------------
    $stmtC = $pdo->prepare('INSERT INTO site_content (chave, valor) VALUES (:chave, :valor)');
    foreach (seed_content() as $chave => $valor) {
        $stmtC->execute([':chave' => $chave, ':valor' => $valor]);
    }
}
