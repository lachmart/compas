<?php

namespace App\Mail;

use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\Transport\Smtp\Stream\AbstractStream;

/**
 * SMTP socket opened with fsockopen() instead of stream_socket_client().
 *
 * The production webhosting disables stream_socket_client (which Symfony's
 * SocketStream relies on) but allows fsockopen, so this stream lets the
 * stock EsmtpTransport handle EHLO, STARTTLS, AUTH and sending as usual.
 */
class FsockopenSmtpStream extends AbstractStream
{
    private string $host = 'localhost';

    private int $port = 465;

    private bool $tls = true;

    private float $timeout = 15;

    public function setHost(string $host): static
    {
        $this->host = $host;

        return $this;
    }

    public function getHost(): string
    {
        return $this->host;
    }

    public function setPort(int $port): static
    {
        $this->port = $port;

        return $this;
    }

    public function getPort(): int
    {
        return $this->port;
    }

    public function disableTls(): static
    {
        $this->tls = false;

        return $this;
    }

    public function isTLS(): bool
    {
        return $this->tls;
    }

    public function setTimeout(float $timeout): static
    {
        $this->timeout = $timeout;

        return $this;
    }

    public function getTimeout(): float
    {
        return $this->timeout;
    }

    public function initialize(): void
    {
        $address = ($this->tls ? 'ssl://' : '').$this->host;

        set_error_handler(function ($type, $message) use ($address) {
            throw new TransportException(sprintf('Connection could not be established with host "%s:%d": %s', $address, $this->port, $message));
        });

        try {
            $stream = fsockopen($address, $this->port, $errno, $errstr, $this->timeout);
        } finally {
            restore_error_handler();
        }

        if ($stream === false) {
            throw new TransportException(sprintf('Connection could not be established with host "%s:%d": %s', $address, $this->port, $errstr));
        }

        stream_set_blocking($stream, true);
        stream_set_timeout($stream, (int) $this->timeout, (int) (($this->timeout - (int) $this->timeout) * 1000000));

        $this->stream = $stream;
        $this->in = &$this->stream;
        $this->out = &$this->stream;
    }

    public function startTLS(): bool
    {
        set_error_handler(static function ($type, $message) {
            throw new TransportException('Unable to connect with STARTTLS: '.$message);
        });

        try {
            return (bool) stream_socket_enable_crypto($this->stream, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
        } finally {
            restore_error_handler();
        }
    }

    public function terminate(): void
    {
        if ($this->stream !== null) {
            fclose($this->stream);
        }

        parent::terminate();
    }

    protected function getReadConnectionDescription(): string
    {
        return ($this->tls ? 'ssl://' : '').$this->host.':'.$this->port;
    }
}
