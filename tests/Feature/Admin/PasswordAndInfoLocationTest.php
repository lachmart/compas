<?php

use App\Enums\TaskType;
use App\Models\Game;
use App\Models\Location;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
    $this->user = User::factory()->create();
    $this->game = Game::factory()->create(['owner_id' => $this->user->id]);
    $this->base = ['lat' => 50.08, 'lng' => 14.43, 'arrival_radius_m' => 15];
});

test('a dozor can add a password location with several accepted answers', function () {
    $this->actingAs($this->user)->post(route('admin.games.locations.store', $this->game), $this->base + [
        'task_type' => 'password',
        'password_answers' => " Žába \n\nRosnička\nzaba",
    ])->assertRedirect()->assertSessionHasNoErrors();

    $location = $this->game->locations()->first();
    expect($location->task_type)->toBe(TaskType::Password)
        ->and($location->passwordAnswers())->toBe(['Žába', 'Rosnička']);
});

test('a password location needs an answer', function (string $answers) {
    $this->actingAs($this->user)->post(route('admin.games.locations.store', $this->game), $this->base + [
        'task_type' => 'password',
        'password_answers' => $answers,
    ])->assertSessionHasErrors(['password_answers' => 'Napiš správné heslo.']);
})->with(['', "  \n  "]);

test('any location can carry a text board with pictures', function () {
    $this->actingAs($this->user)->post(route('admin.games.locations.store', $this->game), $this->base + [
        'task_type' => 'arrival_only',
        'info_title' => '  Starý dub ',
        'info_text' => "Tomuto dubu je přes 300 let.\n\nObvod kmene je 6 metrů.",
        'info_images' => [UploadedFile::fake()->image('dub.jpg'), UploadedFile::fake()->image('list.jpg')],
    ])->assertSessionHasNoErrors();

    $location = $this->game->locations()->first();
    expect($location->info_title)->toBe('Starý dub')
        ->and($location->info_text)->toBe("Tomuto dubu je přes 300 let.\n\nObvod kmene je 6 metrů.")
        ->and($location->infoImages())->toHaveCount(2)
        ->and($location->hasInfo())->toBeTrue();
    Storage::disk('public')->assertExists($location->infoImages());
});

test('at most six pictures go with the text', function () {
    $this->actingAs($this->user)->post(route('admin.games.locations.store', $this->game), $this->base + [
        'task_type' => 'arrival_only',
        'info_images' => array_map(fn ($i) => UploadedFile::fake()->image("i$i.jpg"), range(1, 7)),
    ])->assertSessionHasErrors('info_images');
});

test('updating keeps chosen info pictures and deletes the removed ones', function () {
    Storage::disk('public')->put('task-images/keep.jpg', 'x');
    Storage::disk('public')->put('task-images/drop.jpg', 'x');
    $location = Location::factory()->create([
        'game_id' => $this->game->id,
        'info_text' => 'Text',
        'info_images' => ['task-images/keep.jpg', 'task-images/drop.jpg'],
    ]);

    $this->actingAs($this->user)->put(route('admin.games.locations.update', [$this->game, $location]), $this->base + [
        'task_type' => 'arrival_only',
        'info_text' => 'Nový text',
        'existing_info_images' => ['task-images/keep.jpg'],
        'info_images' => [UploadedFile::fake()->image('new.jpg')],
    ])->assertSessionHasNoErrors();

    $location->refresh();
    expect($location->info_text)->toBe('Nový text')
        ->and($location->infoImages())->toHaveCount(2)
        ->and($location->infoImages()[0])->toBe('task-images/keep.jpg');
    Storage::disk('public')->assertMissing('task-images/drop.jpg');
});

test('clearing the text board removes it completely', function () {
    Storage::disk('public')->put('task-images/old.jpg', 'x');
    $location = Location::factory()->create([
        'game_id' => $this->game->id,
        'info_title' => 'Nadpis',
        'info_images' => ['task-images/old.jpg'],
    ]);

    $this->actingAs($this->user)->put(route('admin.games.locations.update', [$this->game, $location]), $this->base + [
        'task_type' => 'arrival_only',
        'info_title' => '',
    ])->assertSessionHasNoErrors();

    expect($location->fresh()->hasInfo())->toBeFalse();
    Storage::disk('public')->assertMissing('task-images/old.jpg');
});

test('only pictures of this location can be kept', function () {
    $location = Location::factory()->create(['game_id' => $this->game->id]);

    $this->actingAs($this->user)->put(route('admin.games.locations.update', [$this->game, $location]), $this->base + [
        'task_type' => 'arrival_only',
        'existing_info_images' => ['task-images/someone-elses.jpg'],
    ])->assertSessionHasErrors('existing_info_images.0');
});

test('deleting a location deletes its info pictures', function () {
    Storage::disk('public')->put('task-images/info.jpg', 'x');
    $location = Location::factory()->create(['game_id' => $this->game->id, 'info_images' => ['task-images/info.jpg']]);

    $this->actingAs($this->user)->delete(route('admin.games.locations.destroy', [$this->game, $location]))->assertRedirect();

    Storage::disk('public')->assertMissing('task-images/info.jpg');
});

test('the editor gets the answers and the text board', function () {
    Location::factory()->create([
        'game_id' => $this->game->id,
        'task_type' => 'password',
        'task_payload' => ['answers' => ['Žába', 'Rosnička']],
        'info_title' => 'Rybník',
        'info_text' => 'Text',
        'info_images' => ['task-images/a.jpg'],
    ]);

    $this->withoutVite()->actingAs($this->user)->get(route('admin.games.edit', $this->game))
        ->assertInertia(fn ($page) => $page
            ->where('locations.0.password_answers', "Žába\nRosnička")
            ->where('locations.0.info_title', 'Rybník')
            ->where('locations.0.info_text', 'Text')
            ->where('locations.0.info_images.0.path', 'task-images/a.jpg'));
});
