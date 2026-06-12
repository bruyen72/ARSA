<?php
/**
 * Script de uso único: redefine o login do admin para
 *   usuário: admin
 *   senha:   admin123
 *
 * COMO USAR:
 * 1. Acesse no navegador: http://localhost/SEU-CAMINHO/site-php/reset_admin.php
 *    (ou rode "php reset_admin.php" no terminal, dentro da pasta site-php)
 * 2. Confira a mensagem de sucesso.
 * 3. APAGUE este arquivo (reset_admin.php) por segurança.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';

$pdo = db();

$username = 'admin';
$password = 'admin123';
$hash = password_hash($password, PASSWORD_DEFAULT);

$row = $pdo->query('SELECT id FROM admins LIMIT 1')->fetch();

if ($row) {
    $stmt = $pdo->prepare('UPDATE admins SET username = ?, password_hash = ? WHERE id = ?');
    $stmt->execute([$username, $hash, $row['id']]);
} else {
    $stmt = $pdo->prepare('INSERT INTO admins (username, password_hash) VALUES (?, ?)');
    $stmt->execute([$username, $hash]);
}

header('Content-Type: text/plain; charset=utf-8');
echo "Pronto!\n\n";
echo "Login:  admin\n";
echo "Senha:  admin123\n";
echo "URL:    /admin/login\n\n";
echo "IMPORTANTE: apague o arquivo reset_admin.php agora.\n";
