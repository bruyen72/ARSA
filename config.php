<?php
/**
 * Configurações gerais do site ARSA.
 *
 * IMPORTANTE: preencha os dados de SMTP abaixo com as credenciais de
 * e-mail da ARSA antes de colocar o site no ar. Enquanto SMTP_ENABLED
 * estiver "false", o formulário de contato apenas salva a mensagem no
 * banco (visível em /admin/mensagens) e o site continua direcionando o
 * cliente para o WhatsApp normalmente.
 *
 * Este arquivo NÃO deve ser versionado com senhas reais em projetos
 * públicos — em produção, prefira configurar essas constantes via
 * variáveis de ambiente do servidor.
 */

// ----------------------------------------------------------------------
// Banco de dados
// ----------------------------------------------------------------------
// Por padrão usa SQLite (arquivo data/local.db). Se quiser usar outro
// banco (MySQL/Postgres) no futuro, defina a variável de ambiente
// DATABASE_URL com a string de conexão PDO completa, ex:
//   mysql:host=localhost;dbname=arsa;charset=utf8mb4
define('DB_PATH', __DIR__ . '/data/local.db');

// ----------------------------------------------------------------------
// E-mail / SMTP (formulário de Contato)
// ----------------------------------------------------------------------
define('SMTP_ENABLED', false); // mude para true depois de preencher os dados abaixo

define('SMTP_HOST', '');           // ex: smtp.hostinger.com
define('SMTP_PORT', 587);          // 587 (TLS) ou 465 (SSL)
define('SMTP_SECURE', 'tls');      // 'tls' ou 'ssl'
define('SMTP_USER', '');           // ex: contato@arsaradio.com.br
define('SMTP_PASS', '');           // senha da caixa de e-mail
define('SMTP_FROM', '');           // geralmente igual ao SMTP_USER
define('SMTP_FROM_NAME', 'Site ARSA');
define('CONTACT_TO_EMAIL', '');    // e-mail que recebe as mensagens do site

// ----------------------------------------------------------------------
// Upload de arquivos
// ----------------------------------------------------------------------
define('UPLOAD_DIR', __DIR__ . '/uploads');
define('UPLOAD_URL_PREFIX', '/uploads');
define('ALLOWED_IMAGE_EXT', ['jpg', 'jpeg', 'png', 'webp']);
define('ALLOWED_DOC_EXT', ['pdf']);
define('MAX_UPLOAD_SIZE', 8 * 1024 * 1024); // 8 MB

// ----------------------------------------------------------------------
// Categorias de produtos
// ----------------------------------------------------------------------
define('CATEGORY_LABELS', [
    'portatil'   => 'Rádio Portátil',
    'movel'      => 'Rádio Móvel',
    'repetidora' => 'Repetidora',
]);

// ----------------------------------------------------------------------
// Outras informações fixas (fallback caso site_content esteja vazio)
// ----------------------------------------------------------------------
define('SITE_NAME', 'ARSA Radiocomunicação');
define('SITE_URL_FALLBACK', 'https://www.arsaradio.com.br');
