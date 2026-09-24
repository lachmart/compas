<?php

namespace App\Providers;

use App\Mail\FsockopenSmtpStream;
use App\Models\Game;
use App\Policies\GamePolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        Gate::policy(Game::class, GamePolicy::class);

        // SMTP for hosting that disables stream_socket_client (see FsockopenSmtpStream).
        Mail::extend('smtp-fsockopen', function (array $config) {
            $port = (int) ($config['port'] ?? 465);
            $transport = new EsmtpTransport(
                $config['host'],
                $port,
                $port === 465,
                stream: new FsockopenSmtpStream,
            );
            $transport->setUsername((string) ($config['username'] ?? ''));
            $transport->setPassword((string) ($config['password'] ?? ''));

            return $transport;
        });
    }
}
