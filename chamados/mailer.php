<?php

class Mailer {

    public function send(string $to, string $subj, string $html): bool {
        $port    = (int) SMTP_PORT;
        $isLocal = in_array(SMTP_HOST, ['localhost', '127.0.0.1']);

        /* ── Conexão ── */
        if ($isLocal) {
            $sock = @fsockopen(SMTP_HOST, $port, $errno, $errstr, 10);
        } else {
            $proto = ($port === 465) ? 'ssl' : 'tcp';
            $ctx   = stream_context_create([
                'ssl' => ['verify_peer' => false, 'verify_peer_name' => false],
            ]);
            $sock = @stream_socket_client(
                "{$proto}://" . SMTP_HOST . ":{$port}",
                $errno, $errstr, 15, STREAM_CLIENT_CONNECT, $ctx
            );
        }

        if (!$sock) {
            $this->log("ERRO conexão " . SMTP_HOST . ":{$port} — [{$errno}] {$errstr}");
            return false;
        }
        stream_set_timeout($sock, 15);

        /* ── Handshake ── */
        $greeting = $this->rd($sock);
        $this->log("S: " . trim($greeting));

        if (!$isLocal && $port === 587) {
            $this->cmd($sock, 'EHLO ' . SMTP_HOST);
            $r = $this->cmd($sock, 'STARTTLS');
            if (strpos($r, '220') === false) {
                $this->log("STARTTLS falhou: " . trim($r));
                @fclose($sock);
                return false;
            }
            stream_socket_enable_crypto($sock, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
        }

        $ehlo = $this->cmd($sock, 'EHLO ' . SMTP_HOST);
        $this->log("EHLO: " . trim($ehlo));

        /* ── Autenticação — apenas para servidores remotos ── */
        if (!$isLocal) {
            $authenticated = false;

            /* Tenta AUTH PLAIN */
            $plain = base64_encode("\0" . SMTP_USER . "\0" . SMTP_PASS);
            $auth  = $this->cmd($sock, "AUTH PLAIN {$plain}");
            $this->log("AUTH PLAIN: " . trim($auth));
            if (strpos($auth, '235') !== false) $authenticated = true;

            /* Tenta AUTH LOGIN como fallback */
            if (!$authenticated) {
                $this->cmd($sock, 'AUTH LOGIN');
                $this->cmd($sock, base64_encode(SMTP_USER));
                $auth = $this->cmd($sock, base64_encode(SMTP_PASS));
                $this->log("AUTH LOGIN: " . trim($auth));
                if (strpos($auth, '235') !== false) $authenticated = true;
            }

            if (!$authenticated) {
                $this->log("ERRO autenticação falhou em ambos os métodos");
                @fclose($sock);
                return false;
            }
        } else {
            $this->log("Relay local — autenticação ignorada");
        }

        /* ── Envio ── */
        $r = $this->cmd($sock, 'MAIL FROM:<' . SMTP_FROM . '>');
        $this->log("MAIL FROM: " . trim($r));

        $r = $this->cmd($sock, "RCPT TO:<{$to}>");
        $this->log("RCPT TO: " . trim($r));

        $r = $this->cmd($sock, 'DATA');
        $this->log("DATA: " . trim($r));

        $msg  = 'Date: ' . date('r') . "\r\n";
        $msg .= 'From: =?UTF-8?B?' . base64_encode(SMTP_FROM_NAME) . '?= <' . SMTP_FROM . ">\r\n";
        $msg .= "To: {$to}\r\n";
        $msg .= 'Subject: =?UTF-8?B?' . base64_encode($subj) . "?=\r\n";
        $msg .= "MIME-Version: 1.0\r\nContent-Type: text/html; charset=UTF-8\r\n";
        $msg .= "X-Mailer: FONTEC-Chamados/1.0\r\n\r\n";
        $msg .= str_replace("\n.", "\n..", $html);

        fwrite($sock, $msg . "\r\n.\r\n");
        $resp = $this->rd($sock);
        $this->log("BODY resp: " . trim($resp));

        $this->cmd($sock, 'QUIT');
        @fclose($sock);

        $ok = strpos($resp, '250') !== false;
        $this->log($ok ? "OK — enviado para {$to}" : "FALHA ao enviar para {$to}");
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
