<?php

namespace App\Providers;

use App\Mail\FsockopenSmtpStream;
use App\Models\Game;
use App\Policies\GamePolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
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

        $this->configurePlayRateLimits();

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

    /**
     * Limits for the players' endpoints, which need no login. Each browser
     * session gets a tight limit well above normal play (a ping every 5 s at
     * most); the per-IP limit is loose because a whole class often shares
     * one Wi-Fi or mobile network address.
     */
    private function configurePlayRateLimits(): void
    {
        $limits = [
            // [per session, per IP] requests a minute
            'play-join' => [10, 120],
            'play-pings' => [30, 1500],
            'play-actions' => [30, 600],
            'play-photo' => [6, 120],
            // Viewing own photos in the journal (thumbnails + full size).
            'play-media' => [120, 3000],
        ];

        foreach ($limits as $name => [$perSession, $perIp]) {
            RateLimiter::for($name, function (Request $request) use ($name, $perSession, $perIp) {
                // A joined player is keyed by their session; before joining, by
                // the browser session.
                $player = $request->session()->get('player_session_id');
                $who = $player !== null ? 'p:'.$player : 's:'.$request->session()->getId();

                return [
                    Limit::perMinute($perSession)->by($name.'|'.$who),
                    Limit::perMinute($perIp)->by($name.'|ip:'.$request->ip()),
                ];
            });
        }
    }
}
