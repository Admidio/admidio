<?php

namespace Admidio\Tests\Unit\Infrastructure\Support;

use PHPMailer\PHPMailer\SMTP;

/**
 * An SMTP connection without a server behind it. It answers like a server that offers XOAUTH2 and
 * accepts every mail, and records what PHPMailer sent. The login itself runs through PHPMailer's own
 * SMTP::authenticate().
 */
class SmtpConnectionDouble extends SMTP
{
    /** @var array<int,string> Every line PHPMailer sent, in order. */
    public array $sent = array();
    public int $connections = 0;
    private bool $open = false;

    public function connect($host, $port = null, $timeout = 30, $options = array())
    {
        ++$this->connections;
        $this->open = true;

        return true;
    }

    public function connected()
    {
        return $this->open;
    }

    public function close()
    {
        $this->open = false;
        $this->server_caps = null;
    }

    public function startTLS()
    {
        return true;
    }

    public function hello($host = '')
    {
        $this->server_caps = array('EHLO' => 'smtp.example.org', 'AUTH' => array('XOAUTH2'));

        return true;
    }

    /**
     * Records the line and writes the debug output exactly as SMTP::client_send() does for every
     * command that is not a LOGIN, PLAIN or CRAM-MD5 credential.
     */
    public function client_send($data, $command = '')
    {
        $this->sent[] = $data;
        $this->edebug('CLIENT -> SERVER: ' . $data, self::DEBUG_CLIENT);

        return strlen($data);
    }

    protected function get_lines()
    {
        $command = rtrim((string)end($this->sent), "\r\n");

        return match (true) {
            $command === 'AUTH XOAUTH2' => "334 \r\n",
            $command === 'DATA' => "354 Go ahead\r\n",
            $command === 'QUIT' => "221 Bye\r\n",
            $command === '.', $command === 'RSET', str_starts_with($command, 'MAIL FROM'), str_starts_with($command, 'RCPT TO') => "250 OK\r\n",
            default => "235 2.7.0 Authentication successful\r\n"
        };
    }
}
