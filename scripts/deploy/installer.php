<?php

use Illuminate\Contracts\Console\Kernel;

// One-shot installer for hosting without SSH. deploy.sh uploads it under a
// random name next to release.zip, calls it once over HTTPS and it deletes
// itself (and the zip) whatever happens.

header('Content-Type: text/plain; charset=utf-8');
@set_time_limit(300);

$root = dirname(__DIR__);
$zipPath = $root.'/release.zip';

register_shutdown_function(function () use ($zipPath) {
    @unlink($zipPath);
    @unlink(__FILE__);
});

function removeTree(string $path): void
{
    if (is_link($path) || is_file($path)) {
        unlink($path);

        return;
    }

    if (! is_dir($path)) {
        return;
    }

    foreach (scandir($path) as $entry) {
        if ($entry !== '.' && $entry !== '..') {
            removeTree($path.'/'.$entry);
        }
    }

    rmdir($path);
}

if (! is_file($zipPath)) {
    http_response_code(500);
    exit("release.zip missing\n");
}

// Code directories are replaced wholesale so deleted files don't linger;
// storage/, .env and public/storage (uploaded photos) are kept.
foreach (['app', 'bootstrap/cache', 'config', 'database', 'lang', 'public/build', 'resources', 'routes', 'vendor'] as $dir) {
    removeTree($root.'/'.$dir);
}

$zip = new ZipArchive;

if ($zip->open($zipPath) !== true || ! $zip->extractTo($root)) {
    http_response_code(500);
    exit("extracting release.zip failed\n");
}

$zip->close();
echo "extracted\n";

require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

// migrate first: optimize:clear empties the database cache store, whose
// table doesn't exist yet on the very first deploy.
foreach ([
    ['migrate', ['--force' => true]],
    ['optimize:clear', []],
    ['storage:link', []],
    ['optimize', []],
] as [$command, $arguments]) {
    $status = $kernel->call($command, $arguments);
    echo "== {$command} (exit {$status})\n", $kernel->output();

    if ($status !== 0) {
        http_response_code(500);
        exit;
    }
}

echo "DEPLOY OK\n";
