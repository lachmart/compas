<?php

use App\Mail\FsockopenSmtpStream;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;

test('it talks to an SMTP server over fsockopen', function () {
    $server = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
    [, $port] = explode(':', stream_socket_get_name($server, false));

    $stream = (new FsockopenSmtpStream)->setHost('127.0.0.1')->setPort((int) $port)->disableTls();
    $stream->initialize();

    $connection = stream_socket_accept($server, 2);
    fwrite($connection, "220 test ESMTP\r\n");
    expect($stream->readLine())->toBe("220 test ESMTP\r\n");

    $stream->write("EHLO kompas\r\n");
    expect(fgets($connection))->toBe("EHLO kompas\r\n");

    $stream->terminate();
    fclose($connection);
    fclose($server);
});

test('it connects over implicit TLS by default', function () {
    $stream = (new FsockopenSmtpStream)->setHost('mail.example.test')->setPort(465);

    expect($stream->isTLS())->toBeTrue()
        ->and($stream->getHost())->toBe('mail.example.test')
        ->and($stream->getPort())->toBe(465);
});

test('the hosting mailer is an esmtp transport over the fsockopen stream', function () {
    config([
        'mail.mailers.hosting' => [
            'transport' => 'smtp-fsockopen',
            'host' => 'mail.example.test',
            'port' => 465,
            'username' => 'kompas@example.test',
            'password' => 'secret',
        ],
    ]);

    $transport = Mail::mailer('hosting')->getSymfonyTransport();

    expect($transport)->toBeInstanceOf(EsmtpTransport::class)
        ->and($transport->getStream())->toBeInstanceOf(FsockopenSmtpStream::class)
        ->and($transport->getStream()->getHost())->toBe('mail.example.test')
        ->and($transport->getUsername())->toBe('kompas@example.test');
});
