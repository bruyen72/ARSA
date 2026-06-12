<?php
/**
 * Envio de e-mail via SMTP (sem dependências externas).
 *
 * Só é usado quando SMTP_ENABLED = true em config.php. Enquanto estiver
 * desativado, as mensagens do formulário de contato continuam sendo
 * salvas no banco (visíveis em /admin/mensagens).
 */

/**
 * Monta e envia o e-mail de notificação de um novo contato do site.
 */
function send_contact_email(array $dados): void
{
    if (!SMTP_ENABLED || !SMTP_HOST || !SMTP_USER || !CONTACT_TO_EMAIL) {
        throw new RuntimeException('SMTP não está configurado em config.php.');
    }

    $assunto = 'Novo contato pelo site - ' . $dados['nome'];

    $corpo = "Nova mensagem recebida pelo site da ARSA:\n\n"
        . "Nome: {$dados['nome']}\n"
        . "Empresa: {$dados['empresa']}\n"
        . "Telefone: {$dados['telefone']}\n"
        . "E-mail: {$dados['email']}\n"
        . "Interesse: {$dados['interesse']}\n\n"
        . "Mensagem:\n{$dados['mensagem']}\n";

    smtp_send_mail(CONTACT_TO_EMAIL, $assunto, $corpo, $dados['email'] !== '' ? $dados['email'] : null);
}

/**
 * Cliente SMTP mínimo, com suporte a STARTTLS (porta 587) ou SSL direto
 * (porta 465) e autenticação AUTH LOGIN.
 */
function smtp_send_mail(string $to, string $subject, string $body, ?string $replyTo = null): void
{
    $host = SMTP_HOST;
    $port = (int) SMTP_PORT;
    $secure = strtolower(SMTP_SECURE);

    $remote = ($secure === 'ssl' ? 'ssl://' : '') . $host;
    $socket = @fsockopen($remote, $port, $errno, $errstr, 15);
    if (!$socket) {
        throw new RuntimeException("Não foi possível conectar ao servidor SMTP ({$host}:{$port}): {$errstr} ({$errno})");
    }
    stream_set_timeout($socket, 15);

    $readResponse = function () use ($socket): string {
        $data = '';
        while (($line = fgets($socket, 515)) !== false) {
            $data .= $line;
            // Última linha de uma resposta multi-linha tem um espaço na 4ª posição (ex: "250 OK")
            if (strlen($line) < 4 || $line[3] === ' ') {
                break;
            }
        }
        return $data;
    };

    $sendCommand = function (string $cmd, string $expectedCode) use ($socket, $readResponse): string {
        if ($cmd !== '') {
            fwrite($socket, $cmd . "\r\n");
        }
        $resp = $readResponse();
        if (substr($resp, 0, 3) !== $expectedCode) {
            fclose($socket);
            throw new RuntimeException("Servidor SMTP respondeu de forma inesperada: {$resp} (esperado {$expectedCode} para o comando \"{$cmd}\")");
        }
        return $resp;
    };

    $hostname = $_SERVER['SERVER_NAME'] ?? 'localhost';

    try {
        $sendCommand('', '220');
        $sendCommand('EHLO ' . $hostname, '250');

        if ($secure === 'tls') {
            $sendCommand('STARTTLS', '220');
            if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new RuntimeException('Falha ao iniciar conexão segura (STARTTLS) com o servidor SMTP.');
            }
            $sendCommand('EHLO ' . $hostname, '250');
        }

        $sendCommand('AUTH LOGIN', '334');
        $sendCommand(base64_encode(SMTP_USER), '334');
        $sendCommand(base64_encode(SMTP_PASS), '235');

        $from = SMTP_FROM !== '' ? SMTP_FROM : SMTP_USER;
        $sendCommand('MAIL FROM:<' . $from . '>', '250');
        $sendCommand('RCPT TO:<' . $to . '>', '250');
        $sendCommand('DATA', '354');

        $headers = [];
        $headers[] = 'From: ' . mime_encode_header(SMTP_FROM_NAME) . ' <' . $from . '>';
        $headers[] = 'To: <' . $to . '>';
        if ($replyTo) {
            $headers[] = 'Reply-To: <' . $replyTo . '>';
        }
        $headers[] = 'Subject: ' . mime_encode_header($subject);
        $headers[] = 'MIME-Version: 1.0';
        $headers[] = 'Content-Type: text/plain; charset=UTF-8';
        $headers[] = 'Content-Transfer-Encoding: 8bit';
        $headers[] = 'Date: ' . date('r');

        // "Dot-stuffing": linhas que começam com "." precisam virar ".."
        $bodySeguro = preg_replace('/^\./m', '..', $body);

        $mensagem = implode("\r\n", $headers) . "\r\n\r\n" . $bodySeguro . "\r\n.";
        $sendCommand($mensagem, '250');

        fwrite($socket, "QUIT\r\n");
    } finally {
        fclose($socket);
    }
}

function mime_encode_header(string $text): string
{
    if (preg_match('/^[\x20-\x7E]*$/', $text)) {
        return $text;
    }
    return '=?UTF-8?B?' . base64_encode($text) . '?=';
}
