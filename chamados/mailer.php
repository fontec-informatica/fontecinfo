<?php

class Mailer {

    public function send(string $to, string $subj, string $html): bool {
        $port  = (int) SMTP_PORT;
        $proto = ($port === 465) ? 'ssl' : 'tcp';
        $ctx   = stream_context_create([
            'ssl' => ['verify_peer' => false, 'verify_peer_name' => false],
        ]);

        $sock = @stream_socket_client(
            "{$proto}://" . SMTP_HOST . ":{$port}",
            $errno, $errstr, 15, STREAM_CLIENT_CONNECT, $ctx
        );

        if (!$sock) {
            $this->log("Conexão SMTP falhou: [{$errno}] {$errstr}");
            return false;
        }
        stream_set_timeout($sock, 15);

        $this->rd($sock); // saudação do servidor

        if ($port === 587) {
            $this->cmd($sock, 'EHLO ' . SMTP_HOST);
            $this->cmd($sock, 'STARTTLS');
            stream_socket_enable_crypto($sock, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
        }

        $this->cmd($sock, 'EHLO ' . SMTP_HOST);
        $this->cmd($sock, 'AUTH LOGIN');
        $this->cmd($sock, base64_encode(SMTP_USER));
        $auth = $this->cmd($sock, base64_encode(SMTP_PASS));

        if (strpos($auth, '235') === false) {
            $this->log('Autenticação SMTP falhou: ' . trim($auth));
            @fclose($sock);
            return false;
        }

        $this->cmd($sock, 'MAIL FROM:<' . SMTP_FROM . '>');
        $this->cmd($sock, "RCPT TO:<{$to}>");
        $this->cmd($sock, 'DATA');

        $msg  = 'Date: ' . date('r') . "\r\n";
        $msg .= 'From: =?UTF-8?B?' . base64_encode(SMTP_FROM_NAME) . '?= <' . SMTP_FROM . ">\r\n";
        $msg .= "To: {$to}\r\n";
        $msg .= 'Subject: =?UTF-8?B?' . base64_encode($subj) . "?=\r\n";
        $msg .= "MIME-Version: 1.0\r\nContent-Type: text/html; charset=UTF-8\r\n";
        $msg .= "X-Mailer: FONTEC-Chamados/1.0\r\n\r\n";
        $msg .= str_replace("\n.", "\n..", $html);

        fwrite($sock, $msg . "\r\n.\r\n");
        $resp = $this->rd($sock);

        $this->cmd($sock, 'QUIT');
        @fclose($sock);

        $ok = strpos($resp, '250') !== false;
        $logMsg = $ok
            ? "OK — enviado para {$to}"
            : "FALHA ao enviar para {$to}: " . trim($resp);
        $this->log($logMsg);
        return $ok;
    }

    private function rd($sock): string {
        $out = '';
        while (!feof($sock) && ($line = fgets($sock, 512)) !== false) {
            $out .= $line;
            if (strlen($line) < 4 || $line[3] === ' ') break;
        }
        return $out;
    }

    private function cmd($sock, string $c): string {
        fwrite($sock, $c . "\r\n");
        return $this->rd($sock);
    }

    private function log(string $msg): void {
        file_put_contents(
            __DIR__ . '/data/mail.log',
            date('Y-m-d H:i:s') . ' ' . $msg . "\n",
            FILE_APPEND | LOCK_EX
        );
    }
}
