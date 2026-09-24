<?php

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;

test('the password reset email is in Czech', function () {
    $user = User::factory()->create();

    $mail = (new ResetPassword('token'))->toMail($user);
    $html = (string) $mail->render();

    expect($mail->subject)->toBe('Obnovení hesla')
        ->and($mail->actionText)->toBe('Nastavit nové heslo')
        ->and($html)->toContain('Dobrý den,')
        ->and($html)->toContain('S pozdravem')
        ->and($html)->toContain('zkopírujte do prohlížeče')
        ->and($html)->not->toContain('Hello!');
});
