# Herní engine hráče — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Umožnit hráči (dítěti) otevřít URL hry, zadat přezdívku, procházet
stanoviště pomocí kompasu/GPS s barevným "chladno/teplo" ukazatelem, plnit
úkoly všech 4 typů a dokončit hru — bez nutnosti dozora jakkoliv zasahovat.

**Architecture:** Navazuje na Plán A (Laravel + Inertia/Vue admin
autorizace). Přidává hráčskou část pod prefixem `/hra/{slug}` — bez
přihlášení, identita hráče drží Laravelí session (ne vlastní cookie).
Vysokofrekvenční akce (odeslání pozice, potvrzení příchodu, odpověď na
úkol) jdou přes obyčejné JSON endpointy volané z Vue přes `axios`
(z `resources/js/bootstrap.js`), ne přes Inertia page-visit model — Inertia
zůstává jen pro počáteční načtení stránky (join/show). Toto je Plán B ze
4: navazuje na Plán A (hotovo), předchází Plánu C (živé sledování) a Plánu
D (PWA/offline). Tento plán je záměrně **online-only** — offline fronta a
service worker jsou mimo jeho rozsah.

**Tech Stack:** Laravel (routy/kontrolery/modely), Inertia.js + Vue 3
(join/show stránky), `axios` (JSON API volání ze hráčské obrazovky),
Leaflet (mapa, znovupoužije vzor z `MapPicker.vue`), Vitest (nové —
jednotkové testy pro čisté výpočetní funkce kompasu), Pest (backend testy).

## Global Constraints

- Typy úkolů beze změny z Plánu A: `arrival_only`, `pick_image`,
  `photo_of_something`, `selfie_doing_something`.
- `pick_image` — hráči se pošlou obrázky k výběru, ale **nikdy ne
  `correct_index`** předem (zabránění triviálnímu podvádění přes
  network tab). Server vyhodnotí odpověď a vrátí jen `correct: true/false`.
- Automatické splnění fotoúkolů (žádné manuální schvalování dozorem) —
  rozhodnutí z brainstormingu, zůstává v platnosti.
- Hráč nemá heslo ani účet — identita drží Laravel session
  (`session('player_session_id')`), ne vlastní `device_token` sloupec/cookie.
  `SESSION_LIFETIME` se prodlužuje na 30 dní (43200 minut), ať hráč
  zůstane přihlášený i po zavření prohlížeče přes noc.
- Stejná přezdívka od dvou hráčů je povolená (rozlišeno interně přes ID
  session) — nekontrolovat unikátnost.
- Route pattern `{game}/{location}` (kde oba parametry jsou v URL) MUSÍ
  být zabalený v `Route::scopeBindings()` — v Plánu A jsme na tohle
  zapomněli poprvé a musel to řešit bezpečnostní fix (cross-tenant IDOR).
  Tady to jde rovnou správně: `Game::locations()` relace (`app/Models/Game.php`)
  už existuje z Plánu A a umožňuje implicitní scoping.
- Hráč smí volat `arrive`/`answer`/`photo` jen pro stanoviště, které je
  jeho aktuálním cílem (`PlayerSession::currentLocation()`) — jinak 403.
  Žádné přeskakování dopředu ani zpětné přehrávání dokončených stanovišť.
- Vzdálenost/příchod se vyhodnocuje čistě na klientovi (GPS + Haversine);
  server serverside nekontroluje vzdálenost při `arrive` volání — hráč je
  důvěryhodný (žádný adversariální kontext, ležérní dětská hra). Vědomé
  zjednodušení, ne opomenutí.
- Žádný textový popis úkolu pro hráče — zadání je vždy jen obrázek
  (`images`/`reference_image`), přesně jako v adminu z Plánu A.

---

### Task 1: Typované accessory na Location + doménový model hráče

**Files:**
- Modify: `app/Models/Location.php`
- Modify: `app/Models/Game.php`
- Create: `app/Enums/AttemptStatus.php`
- Create: `database/migrations/2026_09_22_000003_create_player_sessions_table.php`
- Create: `database/migrations/2026_09_22_000004_create_location_attempts_table.php`
- Create: `database/migrations/2026_09_22_000005_create_position_pings_table.php`
- Create: `app/Models/PlayerSession.php`
- Create: `app/Models/LocationAttempt.php`
- Create: `app/Models/PositionPing.php`
- Create: `database/factories/PlayerSessionFactory.php`
- Create: `database/factories/LocationAttemptFactory.php`
- Create: `database/factories/PositionPingFactory.php`
- Test: `tests/Unit/Models/PlayerSessionTest.php`
- Test: `tests/Unit/Models/LocationAttemptTest.php`
- Test: `tests/Unit/Models/LocationAccessorsTest.php`

**Interfaces:**
- Consumes: `App\Models\Game`, `App\Models\Location`, `App\Enums\TaskType`
  (Plán A)
- Produces: `Location::images(): array`, `Location::correctImageIndex(): ?int`,
  `Location::referenceImage(): ?string`; `App\Enums\AttemptStatus`
  (`Pending`, `Arrived`, `Completed`); `App\Models\PlayerSession`
  (`game()`, `attempts()`, `pings()`, `currentLocation(): ?Location`,
  `advance(): void`, `isFinished(): bool`); `App\Models\LocationAttempt`
  (`playerSession()`, `location()`); `App\Models\PositionPing`
  (`playerSession()`); `Game::playerSessions()`. Task 2+ konzumují
  `PlayerSession::currentLocation()`/`advance()` přímo.

- [ ] **Step 1: Napiš selhávající testy**

`tests/Unit/Models/LocationAccessorsTest.php`:

```php
<?php

use App\Models\Location;

test('images() returns the pick_image payload images', function () {
    $location = Location::factory()->create([
        'task_type' => 'pick_image',
        'task_payload' => ['images' => ['a.jpg', 'b.jpg', 'c.jpg'], 'correct_index' => 1],
    ]);

    expect($location->images())->toBe(['a.jpg', 'b.jpg', 'c.jpg']);
});

test('correctImageIndex() returns the pick_image correct index', function () {
    $location = Location::factory()->create([
        'task_type' => 'pick_image',
        'task_payload' => ['images' => ['a.jpg', 'b.jpg', 'c.jpg'], 'correct_index' => 2],
    ]);

    expect($location->correctImageIndex())->toBe(2);
});

test('referenceImage() returns the photo task reference image', function () {
    $location = Location::factory()->create([
        'task_type' => 'photo_of_something',
        'task_payload' => ['reference_image' => 'ref.jpg'],
    ]);

    expect($location->referenceImage())->toBe('ref.jpg');
});

test('accessors return safe defaults for arrival_only', function () {
    $location = Location::factory()->create([
        'task_type' => 'arrival_only',
        'task_payload' => null,
    ]);

    expect($location->images())->toBe([])
        ->and($location->correctImageIndex())->toBeNull()
        ->and($location->referenceImage())->toBeNull();
});
```

`tests/Unit/Models/PlayerSessionTest.php`:

```php
<?php

use App\Models\Game;
use App\Models\Location;
use App\Models\PlayerSession;

test('currentLocation returns the location at current_location_index', function () {
    $game = Game::factory()->create();
    $first = Location::factory()->create(['game_id' => $game->id, 'order' => 1]);
    $second = Location::factory()->create(['game_id' => $game->id, 'order' => 2]);
    $session = PlayerSession::factory()->create(['game_id' => $game->id, 'current_location_index' => 1]);

    expect($session->currentLocation()->id)->toBe($second->id);
});

test('currentLocation returns null past the last location', function () {
    $game = Game::factory()->create();
    Location::factory()->create(['game_id' => $game->id, 'order' => 1]);
    $session = PlayerSession::factory()->create(['game_id' => $game->id, 'current_location_index' => 5]);

    expect($session->currentLocation())->toBeNull();
});

test('advance moves to the next location', function () {
    $game = Game::factory()->create();
    Location::factory()->create(['game_id' => $game->id, 'order' => 1]);
    Location::factory()->create(['game_id' => $game->id, 'order' => 2]);
    $session = PlayerSession::factory()->create(['game_id' => $game->id, 'current_location_index' => 0]);

    $session->advance();

    expect($session->fresh()->current_location_index)->toBe(1)
        ->and($session->fresh()->isFinished())->toBeFalse();
});

test('advance past the last location marks the session finished', function () {
    $game = Game::factory()->create();
    Location::factory()->create(['game_id' => $game->id, 'order' => 1]);
    $session = PlayerSession::factory()->create(['game_id' => $game->id, 'current_location_index' => 0]);

    $session->advance();

    expect($session->fresh()->isFinished())->toBeTrue()
        ->and($session->fresh()->current_location_index)->toBe(0);
});
```

`tests/Unit/Models/LocationAttemptTest.php`:

```php
<?php

use App\Enums\AttemptStatus;
use App\Models\LocationAttempt;

test('location attempt casts status to enum', function () {
    $attempt = LocationAttempt::factory()->create(['status' => 'arrived']);

    expect($attempt->status)->toBe(AttemptStatus::Arrived);
});
```

- [ ] **Step 2: Spusť testy a ověř selhání**

```bash
php artisan test --filter=LocationAccessorsTest
php artisan test --filter=PlayerSessionTest
php artisan test --filter=LocationAttemptTest
```

Expected: FAIL (metody/třídy/migrace ještě neexistují).

- [ ] **Step 3: Přidej typované accessory na Location**

Do `app/Models/Location.php` přidej dovnitř třídy:

```php
public function images(): array
{
    return $this->task_payload['images'] ?? [];
}

public function correctImageIndex(): ?int
{
    return $this->task_payload['correct_index'] ?? null;
}

public function referenceImage(): ?string
{
    return $this->task_payload['reference_image'] ?? null;
}
```

- [ ] **Step 4: Vytvoř AttemptStatus enum**

`app/Enums/AttemptStatus.php`:

```php
<?php

namespace App\Enums;

enum AttemptStatus: string
{
    case Pending = 'pending';
    case Arrived = 'arrived';
    case Completed = 'completed';
}
```

- [ ] **Step 5: Vytvoř migrace**

`database/migrations/2026_09_22_000003_create_player_sessions_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('player_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')->constrained('games')->cascadeOnDelete();
            $table->string('nickname');
            $table->unsignedInteger('current_location_index')->default(0);
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('player_sessions');
    }
};
```

`database/migrations/2026_09_22_000004_create_location_attempts_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('location_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('player_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('location_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('pending');
            $table->timestamp('arrived_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->string('submitted_photo_path')->nullable();
            $table->boolean('flagged_invalid')->default(false);
            $table->timestamps();
            $table->unique(['player_session_id', 'location_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('location_attempts');
    }
};
```

`database/migrations/2026_09_22_000005_create_position_pings_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('position_pings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('player_session_id')->constrained()->cascadeOnDelete();
            $table->decimal('lat', 10, 7);
            $table->decimal('lng', 10, 7);
            $table->decimal('heading', 5, 1)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('position_pings');
    }
};
```

- [ ] **Step 6: Vytvoř modely**

`app/Models/PlayerSession.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PlayerSession extends Model
{
    use HasFactory;

    protected $fillable = ['game_id', 'nickname', 'current_location_index', 'started_at', 'finished_at'];

    protected $casts = [
        'current_location_index' => 'integer',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(LocationAttempt::class);
    }

    public function pings(): HasMany
    {
        return $this->hasMany(PositionPing::class);
    }

    public function currentLocation(): ?Location
    {
        return $this->game->locations()->get()->values()->get($this->current_location_index);
    }

    public function isFinished(): bool
    {
        return $this->finished_at !== null;
    }

    public function advance(): void
    {
        $nextIndex = $this->current_location_index + 1;

        if ($nextIndex >= $this->game->locations()->count()) {
            $this->update(['finished_at' => now()]);

            return;
        }

        $this->update(['current_location_index' => $nextIndex]);
    }
}
```

`app/Models/LocationAttempt.php`:

```php
<?php

namespace App\Models;

use App\Enums\AttemptStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LocationAttempt extends Model
{
    use HasFactory;

    protected $fillable = [
        'player_session_id', 'location_id', 'status',
        'arrived_at', 'completed_at', 'submitted_photo_path', 'flagged_invalid',
    ];

    protected $casts = [
        'status' => AttemptStatus::class,
        'arrived_at' => 'datetime',
        'completed_at' => 'datetime',
        'flagged_invalid' => 'boolean',
    ];

    public function playerSession(): BelongsTo
    {
        return $this->belongsTo(PlayerSession::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }
}
```

`app/Models/PositionPing.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PositionPing extends Model
{
    use HasFactory;

    protected $fillable = ['player_session_id', 'lat', 'lng', 'heading'];

    protected $casts = [
        'lat' => 'decimal:7',
        'lng' => 'decimal:7',
        'heading' => 'decimal:1',
    ];

    public function playerSession(): BelongsTo
    {
        return $this->belongsTo(PlayerSession::class);
    }
}
```

Do `app/Models/Game.php` přidej dovnitř třídy:

```php
public function playerSessions(): \Illuminate\Database\Eloquent\Relations\HasMany
{
    return $this->hasMany(PlayerSession::class);
}
```

- [ ] **Step 7: Vytvoř factories**

```bash
php artisan make:factory PlayerSessionFactory --model=PlayerSession
php artisan make:factory LocationAttemptFactory --model=LocationAttempt
php artisan make:factory PositionPingFactory --model=PositionPing
```

`database/factories/PlayerSessionFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Models\Game;
use Illuminate\Database\Eloquent\Factories\Factory;

class PlayerSessionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'game_id' => Game::factory(),
            'nickname' => $this->faker->firstName(),
            'current_location_index' => 0,
            'started_at' => now(),
            'finished_at' => null,
        ];
    }
}
```

`database/factories/LocationAttemptFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Models\Location;
use App\Models\PlayerSession;
use Illuminate\Database\Eloquent\Factories\Factory;

class LocationAttemptFactory extends Factory
{
    public function definition(): array
    {
        return [
            'player_session_id' => PlayerSession::factory(),
            'location_id' => Location::factory(),
            'status' => 'pending',
        ];
    }
}
```

`database/factories/PositionPingFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Models\PlayerSession;
use Illuminate\Database\Eloquent\Factories\Factory;

class PositionPingFactory extends Factory
{
    public function definition(): array
    {
        return [
            'player_session_id' => PlayerSession::factory(),
            'lat' => $this->faker->latitude(),
            'lng' => $this->faker->longitude(),
            'heading' => $this->faker->numberBetween(0, 359),
        ];
    }
}
```

- [ ] **Step 8: Spusť migrace a testy**

```bash
php artisan migrate
php artisan test --filter=LocationAccessorsTest
php artisan test --filter=PlayerSessionTest
php artisan test --filter=LocationAttemptTest
```

Expected: PASS.

- [ ] **Step 9: Commit**

```bash
git add app/Models app/Enums/AttemptStatus.php database/migrations database/factories tests/Unit/Models
git commit -m "Add player domain model (PlayerSession, LocationAttempt, PositionPing) and Location typed accessors"
```

---

### Task 2: PlayController — join a herní stav

**Files:**
- Create: `app/Http/Controllers/Play/ResolvesPlayerSession.php`
- Create: `app/Http/Controllers/Play/PlayController.php`
- Create: `app/Http/Requests/Play/JoinGameRequest.php`
- Modify: `routes/web.php`
- Modify: `.env.example`
- Test: `tests/Feature/Play/PlayControllerTest.php`

**Interfaces:**
- Consumes: `App\Models\Game`, `App\Models\PlayerSession` (Task 1)
- Produces: trait `ResolvesPlayerSession::resolvePlayerSession(Game $game): PlayerSession`
  (Task 3+ ji použijí), routy `play.show` (GET `hra/{game:slug}`) a
  `play.join` (POST `hra/{game:slug}/join`). Inertia stránky
  `Play/Join` (props: `game.name`, `game.slug`) a `Play/Show` (props:
  `game`, `locations[]`, `player` — konzumuje Task 8/9/10 na frontendu).

- [ ] **Step 1: Napiš selhávající feature testy**

`tests/Feature/Play/PlayControllerTest.php`:

```php
<?php

use App\Enums\GameStatus;
use App\Models\Game;
use App\Models\Location;
use App\Models\PlayerSession;

test('visiting an unpublished game 404s', function () {
    $game = Game::factory()->create(['status' => GameStatus::Draft]);

    $this->get(route('play.show', $game->slug))->assertNotFound();
});

test('visiting a published game without a session shows the join page', function () {
    $game = Game::factory()->create(['status' => GameStatus::Published]);

    $this->get(route('play.show', $game->slug))
        ->assertInertia(fn ($page) => $page->component('Play/Join')
            ->where('game.slug', $game->slug));
});

test('a child can join a published game with just a nickname', function () {
    $game = Game::factory()->create(['status' => GameStatus::Published]);
    Location::factory()->create(['game_id' => $game->id, 'order' => 1]);

    $response = $this->post(route('play.join', $game->slug), ['nickname' => 'Anežka']);

    $response->assertRedirect(route('play.show', $game->slug));
    $session = PlayerSession::first();
    expect($session->nickname)->toBe('Anežka')
        ->and($session->game_id)->toBe($game->id)
        ->and($session->current_location_index)->toBe(0);
});

test('joining requires a nickname', function () {
    $game = Game::factory()->create(['status' => GameStatus::Published]);

    $this->post(route('play.join', $game->slug), ['nickname' => ''])
        ->assertSessionHasErrors('nickname');
});

test('visiting with an active session shows the play screen with game state', function () {
    $game = Game::factory()->create(['status' => GameStatus::Published]);
    $first = Location::factory()->create(['game_id' => $game->id, 'order' => 1, 'task_type' => 'arrival_only']);
    Location::factory()->create(['game_id' => $game->id, 'order' => 2, 'task_type' => 'arrival_only']);
    $session = PlayerSession::factory()->create(['game_id' => $game->id]);

    $this->withSession(['player_session_id' => $session->id])
        ->get(route('play.show', $game->slug))
        ->assertInertia(fn ($page) => $page->component('Play/Show')
            ->has('locations', 2)
            ->where('player.current_location_index', 0)
            ->where('locations.0.id', $first->id));
});

test("a session for a different game does not leak into this game's play screen", function () {
    $gameA = Game::factory()->create(['status' => GameStatus::Published]);
    $gameB = Game::factory()->create(['status' => GameStatus::Published]);
    $sessionForA = PlayerSession::factory()->create(['game_id' => $gameA->id]);

    $this->withSession(['player_session_id' => $sessionForA->id])
        ->get(route('play.show', $gameB->slug))
        ->assertInertia(fn ($page) => $page->component('Play/Join'));
});
```

- [ ] **Step 2: Spusť testy a ověř selhání**

```bash
php artisan test --filter=PlayControllerTest
```

Expected: FAIL (routy a controller ještě neexistují).

- [ ] **Step 3: Prodluž SESSION_LIFETIME**

V `.env.example` najdi `SESSION_LIFETIME=120` a změň na:

```
SESSION_LIFETIME=43200
```

(30 dní — hráč zůstává "přihlášený" i po zavření prohlížeče přes noc).
Pokud `.env` v repu existuje a má stejný klíč, uprav i tam; jinak stačí
`.env.example` pro budoucí `composer setup`.

- [ ] **Step 4: Vytvoř JoinGameRequest**

`app/Http/Requests/Play/JoinGameRequest.php`:

```php
<?php

namespace App\Http\Requests\Play;

use Illuminate\Foundation\Http\FormRequest;

class JoinGameRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nickname' => ['required', 'string', 'max:50'],
        ];
    }
}
```

- [ ] **Step 5: Vytvoř ResolvesPlayerSession trait**

`app/Http/Controllers/Play/ResolvesPlayerSession.php`:

```php
<?php

namespace App\Http\Controllers\Play;

use App\Models\Game;
use App\Models\PlayerSession;

trait ResolvesPlayerSession
{
    protected function resolvePlayerSession(Game $game): PlayerSession
    {
        $id = session('player_session_id');
        abort_unless($id, 403);

        $playerSession = PlayerSession::find($id);
        abort_unless($playerSession && $playerSession->game_id === $game->id, 403);

        return $playerSession;
    }

    protected function findActivePlayerSession(Game $game): ?PlayerSession
    {
        $id = session('player_session_id');

        if (! $id) {
            return null;
        }

        $playerSession = PlayerSession::find($id);

        return ($playerSession && $playerSession->game_id === $game->id) ? $playerSession : null;
    }
}
```

- [ ] **Step 6: Vytvoř PlayController**

`app/Http/Controllers/Play/PlayController.php`:

```php
<?php

namespace App\Http\Controllers\Play;

use App\Enums\GameStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Play\JoinGameRequest;
use App\Models\Game;
use App\Models\PlayerSession;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class PlayController extends Controller
{
    use ResolvesPlayerSession;

    public function show(Game $game)
    {
        abort_unless($game->status === GameStatus::Published, 404);

        $playerSession = $this->findActivePlayerSession($game);

        if (! $playerSession) {
            return Inertia::render('Play/Join', [
                'game' => ['name' => $game->name, 'slug' => $game->slug],
            ]);
        }

        return Inertia::render('Play/Show', [
            'game' => ['name' => $game->name, 'slug' => $game->slug],
            'locations' => $game->locations()->get()->map(fn ($location) => [
                'id' => $location->id,
                'order' => $location->order,
                'lat' => (float) $location->lat,
                'lng' => (float) $location->lng,
                'arrival_radius_m' => $location->arrival_radius_m,
                'task_type' => $location->task_type->value,
            ])->values(),
            'player' => [
                'current_location_index' => $playerSession->current_location_index,
                'finished_at' => $playerSession->finished_at,
            ],
        ]);
    }

    public function join(JoinGameRequest $request, Game $game)
    {
        abort_unless($game->status === GameStatus::Published, 404);

        $playerSession = PlayerSession::create([
            'game_id' => $game->id,
            'nickname' => $request->validated('nickname'),
            'current_location_index' => 0,
            'started_at' => now(),
        ]);

        session(['player_session_id' => $playerSession->id]);

        return redirect()->route('play.show', $game->slug);
    }
}
```

- [ ] **Step 7: Přidej routy**

Na konec `routes/web.php` přidej (mimo `admin` skupinu, bez `auth`
middleware — hráč se nepřihlašuje):

```php
use App\Http\Controllers\Play\PlayController;

Route::get('hra/{game:slug}', [PlayController::class, 'show'])->name('play.show');
Route::post('hra/{game:slug}/join', [PlayController::class, 'join'])->name('play.join');
```

- [ ] **Step 8: Spusť testy a ověř průchod**

```bash
php artisan test --filter=PlayControllerTest
```

Expected: PASS.

- [ ] **Step 9: Commit**

```bash
git add app/Http/Controllers/Play app/Http/Requests/Play routes/web.php .env.example tests/Feature/Play/PlayControllerTest.php
git commit -m "Add player join flow and game-state page (session-based identity)"
```

---

### Task 3: AttemptController — arrive/answer/photo

**Files:**
- Create: `app/Http/Controllers/Play/AttemptController.php`
- Create: `app/Http/Requests/Play/SubmitAnswerRequest.php`
- Create: `app/Http/Requests/Play/SubmitPhotoRequest.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/Play/AttemptControllerTest.php`

**Interfaces:**
- Consumes: `ResolvesPlayerSession` trait, `PlayerSession::currentLocation()`/`advance()`,
  `Location::images()`/`correctImageIndex()`/`referenceImage()` (Task 1, 2)
- Produces: routy `play.attempts.arrive`, `play.attempts.answer`,
  `play.attempts.photo` (všechny `POST hra/{game:slug}/locations/{location}/...`,
  zabalené v `Route::scopeBindings()`). JSON response shape:
  `{attempt_status, task_type, images, reference_image, player: {current_location_index, finished_at}}`
  z `arrive`; `{correct, player}` z `answer`; `{player}` z `photo`.
  Task 8/9/10 na frontendu volají tyto endpointy přes `axios`.

- [ ] **Step 1: Napiš selhávající feature testy**

`tests/Feature/Play/AttemptControllerTest.php`:

```php
<?php

use App\Enums\AttemptStatus;
use App\Enums\GameStatus;
use App\Models\Game;
use App\Models\Location;
use App\Models\LocationAttempt;
use App\Models\PlayerSession;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

function startSessionAt(Game $game, Location $current): array
{
    $session = PlayerSession::factory()->create(['game_id' => $game->id, 'current_location_index' => $current->order - 1]);

    return [$session, function () use ($session) {
        return test()->withSession(['player_session_id' => $session->id]);
    }];
}

test('arriving at an arrival_only location completes it immediately and advances', function () {
    $game = Game::factory()->create(['status' => GameStatus::Published]);
    $loc = Location::factory()->create(['game_id' => $game->id, 'order' => 1, 'task_type' => 'arrival_only']);
    Location::factory()->create(['game_id' => $game->id, 'order' => 2, 'task_type' => 'arrival_only']);
    $session = PlayerSession::factory()->create(['game_id' => $game->id, 'current_location_index' => 0]);

    $response = $this->withSession(['player_session_id' => $session->id])
        ->postJson(route('play.attempts.arrive', [$game->slug, $loc->id]));

    $response->assertOk()->assertJson(['attempt_status' => 'completed']);
    expect($session->fresh()->current_location_index)->toBe(1);
});

test('arriving at a pick_image location unlocks the task without the correct answer', function () {
    $game = Game::factory()->create(['status' => GameStatus::Published]);
    $loc = Location::factory()->create([
        'game_id' => $game->id,
        'order' => 1,
        'task_type' => 'pick_image',
        'task_payload' => ['images' => ['a.jpg', 'b.jpg', 'c.jpg'], 'correct_index' => 2],
    ]);
    $session = PlayerSession::factory()->create(['game_id' => $game->id, 'current_location_index' => 0]);

    $response = $this->withSession(['player_session_id' => $session->id])
        ->postJson(route('play.attempts.arrive', [$game->slug, $loc->id]));

    $response->assertOk()
        ->assertJson(['attempt_status' => 'arrived', 'task_type' => 'pick_image'])
        ->assertJsonCount(3, 'images');
    $response->assertJsonMissingPath('correct_index');
    expect($session->fresh()->current_location_index)->toBe(0);
});

test('a correct answer completes the attempt and advances', function () {
    $game = Game::factory()->create(['status' => GameStatus::Published]);
    $loc = Location::factory()->create([
        'game_id' => $game->id,
        'order' => 1,
        'task_type' => 'pick_image',
        'task_payload' => ['images' => ['a.jpg', 'b.jpg'], 'correct_index' => 1],
    ]);
    Location::factory()->create(['game_id' => $game->id, 'order' => 2, 'task_type' => 'arrival_only']);
    $session = PlayerSession::factory()->create(['game_id' => $game->id, 'current_location_index' => 0]);
    LocationAttempt::factory()->create([
        'player_session_id' => $session->id, 'location_id' => $loc->id, 'status' => 'arrived',
    ]);

    $response = $this->withSession(['player_session_id' => $session->id])
        ->postJson(route('play.attempts.answer', [$game->slug, $loc->id]), ['selected_index' => 1]);

    $response->assertOk()->assertJson(['correct' => true]);
    expect($session->fresh()->current_location_index)->toBe(1);
});

test('a wrong answer does not advance and can be retried', function () {
    $game = Game::factory()->create(['status' => GameStatus::Published]);
    $loc = Location::factory()->create([
        'game_id' => $game->id,
        'order' => 1,
        'task_type' => 'pick_image',
        'task_payload' => ['images' => ['a.jpg', 'b.jpg'], 'correct_index' => 1],
    ]);
    $session = PlayerSession::factory()->create(['game_id' => $game->id, 'current_location_index' => 0]);
    LocationAttempt::factory()->create([
        'player_session_id' => $session->id, 'location_id' => $loc->id, 'status' => 'arrived',
    ]);

    $response = $this->withSession(['player_session_id' => $session->id])
        ->postJson(route('play.attempts.answer', [$game->slug, $loc->id]), ['selected_index' => 0]);

    $response->assertOk()->assertJson(['correct' => false]);
    expect($session->fresh()->current_location_index)->toBe(0);
});

test('submitting a photo completes a photo_of_something attempt', function () {
    Storage::fake('public');
    $game = Game::factory()->create(['status' => GameStatus::Published]);
    $loc = Location::factory()->create([
        'game_id' => $game->id, 'order' => 1, 'task_type' => 'photo_of_something',
        'task_payload' => ['reference_image' => 'ref.jpg'],
    ]);
    $session = PlayerSession::factory()->create(['game_id' => $game->id, 'current_location_index' => 0]);
    LocationAttempt::factory()->create([
        'player_session_id' => $session->id, 'location_id' => $loc->id, 'status' => 'arrived',
    ]);

    $response = $this->withSession(['player_session_id' => $session->id])
        ->post(route('play.attempts.photo', [$game->slug, $loc->id]), [
            'photo' => UploadedFile::fake()->image('kids-photo.jpg'),
        ]);

    $response->assertOk();
    $attempt = LocationAttempt::first();
    expect($attempt->status)->toBe(AttemptStatus::Completed);
    Storage::disk('public')->assertExists($attempt->submitted_photo_path);
});

test('a player cannot act on a location that is not their current target', function () {
    $game = Game::factory()->create(['status' => GameStatus::Published]);
    $current = Location::factory()->create(['game_id' => $game->id, 'order' => 1, 'task_type' => 'arrival_only']);
    $future = Location::factory()->create(['game_id' => $game->id, 'order' => 2, 'task_type' => 'arrival_only']);
    $session = PlayerSession::factory()->create(['game_id' => $game->id, 'current_location_index' => 0]);

    $this->withSession(['player_session_id' => $session->id])
        ->postJson(route('play.attempts.arrive', [$game->slug, $future->id]))
        ->assertForbidden();
});

test("a location belonging to a different game 404s even for a valid session", function () {
    $gameA = Game::factory()->create(['status' => GameStatus::Published]);
    $gameB = Game::factory()->create(['status' => GameStatus::Published]);
    $locB = Location::factory()->create(['game_id' => $gameB->id, 'order' => 1]);
    $sessionA = PlayerSession::factory()->create(['game_id' => $gameA->id, 'current_location_index' => 0]);

    $this->withSession(['player_session_id' => $sessionA->id])
        ->postJson(route('play.attempts.arrive', [$gameA->slug, $locB->id]))
        ->assertNotFound();
});
```

- [ ] **Step 2: Spusť testy a ověř selhání**

```bash
php artisan test --filter=AttemptControllerTest
```

Expected: FAIL (routy a controller ještě neexistují).

- [ ] **Step 3: Vytvoř Form Requesty**

`app/Http/Requests/Play/SubmitAnswerRequest.php`:

```php
<?php

namespace App\Http\Requests\Play;

use Illuminate\Foundation\Http\FormRequest;

class SubmitAnswerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'selected_index' => ['required', 'integer', 'min:0'],
        ];
    }
}
```

`app/Http/Requests/Play/SubmitPhotoRequest.php`:

```php
<?php

namespace App\Http\Requests\Play;

use Illuminate\Foundation\Http\FormRequest;

class SubmitPhotoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'photo' => ['required', 'image', 'max:5120'],
        ];
    }
}
```

- [ ] **Step 4: Vytvoř AttemptController**

`app/Http/Controllers/Play/AttemptController.php`:

```php
<?php

namespace App\Http\Controllers\Play;

use App\Enums\AttemptStatus;
use App\Enums\TaskType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Play\SubmitAnswerRequest;
use App\Http\Requests\Play\SubmitPhotoRequest;
use App\Models\Game;
use App\Models\Location;
use App\Models\LocationAttempt;
use App\Models\PlayerSession;
use Illuminate\Support\Facades\Storage;

class AttemptController extends Controller
{
    use ResolvesPlayerSession;

    public function arrive(Game $game, Location $location)
    {
        $playerSession = $this->resolvePlayerSession($game);
        $this->ensureCurrentTarget($playerSession, $location);

        $attempt = LocationAttempt::firstOrCreate(
            ['player_session_id' => $playerSession->id, 'location_id' => $location->id],
            ['status' => AttemptStatus::Pending],
        );

        if ($attempt->status === AttemptStatus::Pending) {
            $attempt->update(['status' => AttemptStatus::Arrived, 'arrived_at' => now()]);
        }

        if ($location->task_type === TaskType::ArrivalOnly) {
            $this->completeAttempt($attempt, $playerSession);
        }

        return response()->json([
            'attempt_status' => $attempt->fresh()->status->value,
            'task_type' => $location->task_type->value,
            'images' => $location->task_type === TaskType::PickImage
                ? collect($location->images())->map(fn ($path) => Storage::url($path))->values()->all()
                : null,
            'reference_image' => in_array($location->task_type, [TaskType::PhotoOfSomething, TaskType::SelfieDoingSomething])
                ? Storage::url($location->referenceImage())
                : null,
            'player' => $this->playerState($playerSession->fresh()),
        ]);
    }

    public function answer(SubmitAnswerRequest $request, Game $game, Location $location)
    {
        $playerSession = $this->resolvePlayerSession($game);
        $this->ensureCurrentTarget($playerSession, $location);

        abort_unless($location->task_type === TaskType::PickImage, 422);

        $attempt = LocationAttempt::where('player_session_id', $playerSession->id)
            ->where('location_id', $location->id)
            ->firstOrFail();

        $correct = $request->validated('selected_index') === $location->correctImageIndex();

        if ($correct) {
            $this->completeAttempt($attempt, $playerSession);
        }

        return response()->json([
            'correct' => $correct,
            'player' => $this->playerState($playerSession->fresh()),
        ]);
    }

    public function photo(SubmitPhotoRequest $request, Game $game, Location $location)
    {
        $playerSession = $this->resolvePlayerSession($game);
        $this->ensureCurrentTarget($playerSession, $location);

        abort_unless(
            in_array($location->task_type, [TaskType::PhotoOfSomething, TaskType::SelfieDoingSomething]),
            422,
        );

        $attempt = LocationAttempt::where('player_session_id', $playerSession->id)
            ->where('location_id', $location->id)
            ->firstOrFail();

        $path = $request->file('photo')->store('attempt-photos', 'public');
        $attempt->update(['submitted_photo_path' => $path]);

        $this->completeAttempt($attempt, $playerSession);

        return response()->json([
            'player' => $this->playerState($playerSession->fresh()),
        ]);
    }

    private function completeAttempt(LocationAttempt $attempt, PlayerSession $playerSession): void
    {
        $attempt->update(['status' => AttemptStatus::Completed, 'completed_at' => now()]);
        $playerSession->advance();
    }

    private function ensureCurrentTarget(PlayerSession $playerSession, Location $location): void
    {
        $current = $playerSession->currentLocation();
        abort_if($current === null || $current->id !== $location->id, 403);
    }

    private function playerState(PlayerSession $playerSession): array
    {
        return [
            'current_location_index' => $playerSession->current_location_index,
            'finished_at' => $playerSession->finished_at,
        ];
    }
}
```

- [ ] **Step 5: Přidej routy**

Na konec `routes/web.php` přidej:

```php
use App\Http\Controllers\Play\AttemptController;

Route::scopeBindings()->group(function () {
    Route::post('hra/{game:slug}/locations/{location}/arrive', [AttemptController::class, 'arrive'])->name('play.attempts.arrive');
    Route::post('hra/{game:slug}/locations/{location}/answer', [AttemptController::class, 'answer'])->name('play.attempts.answer');
    Route::post('hra/{game:slug}/locations/{location}/photo', [AttemptController::class, 'photo'])->name('play.attempts.photo');
});
```

- [ ] **Step 6: Spusť testy a ověř průchod**

```bash
php artisan test --filter=AttemptControllerTest
```

Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/Play/AttemptController.php app/Http/Requests/Play routes/web.php tests/Feature/Play/AttemptControllerTest.php
git commit -m "Add attempt endpoints (arrive/answer/photo) with scoped bindings"
```

---

### Task 4: PositionPingController

**Files:**
- Create: `app/Http/Controllers/Play/PositionPingController.php`
- Create: `app/Http/Requests/Play/StorePositionPingRequest.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/Play/PositionPingControllerTest.php`

**Interfaces:**
- Consumes: `ResolvesPlayerSession` trait (Task 2)
- Produces: routa `play.pings.store` (POST `hra/{game:slug}/pings`), no-content
  204 response. Task 8 (CompassScreen.vue) tuto routu volá periodicky.

- [ ] **Step 1: Napiš selhávající feature testy**

`tests/Feature/Play/PositionPingControllerTest.php`:

```php
<?php

use App\Enums\GameStatus;
use App\Models\Game;
use App\Models\PlayerSession;
use App\Models\PositionPing;

test('a player can record a position ping', function () {
    $game = Game::factory()->create(['status' => GameStatus::Published]);
    $session = PlayerSession::factory()->create(['game_id' => $game->id]);

    $response = $this->withSession(['player_session_id' => $session->id])
        ->postJson(route('play.pings.store', $game->slug), [
            'lat' => 50.08,
            'lng' => 14.43,
            'heading' => 180.5,
        ]);

    $response->assertNoContent();
    expect(PositionPing::where('player_session_id', $session->id)->count())->toBe(1);
});

test('a ping without a valid session is rejected', function () {
    $game = Game::factory()->create(['status' => GameStatus::Published]);

    $this->postJson(route('play.pings.store', $game->slug), ['lat' => 50.08, 'lng' => 14.43])
        ->assertForbidden();
});

test('a ping requires valid coordinates', function () {
    $game = Game::factory()->create(['status' => GameStatus::Published]);
    $session = PlayerSession::factory()->create(['game_id' => $game->id]);

    $this->withSession(['player_session_id' => $session->id])
        ->postJson(route('play.pings.store', $game->slug), ['lat' => 999, 'lng' => 14.43])
        ->assertSessionHasErrors('lat');
});
```

- [ ] **Step 2: Spusť testy a ověř selhání**

```bash
php artisan test --filter=PositionPingControllerTest
```

Expected: FAIL.

- [ ] **Step 3: Vytvoř StorePositionPingRequest**

`app/Http/Requests/Play/StorePositionPingRequest.php`:

```php
<?php

namespace App\Http\Requests\Play;

use Illuminate\Foundation\Http\FormRequest;

class StorePositionPingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
            'heading' => ['nullable', 'numeric', 'between:0,360'],
        ];
    }
}
```

- [ ] **Step 4: Vytvoř PositionPingController**

`app/Http/Controllers/Play/PositionPingController.php`:

```php
<?php

namespace App\Http\Controllers\Play;

use App\Http\Controllers\Controller;
use App\Http\Requests\Play\StorePositionPingRequest;
use App\Models\Game;

class PositionPingController extends Controller
{
    use ResolvesPlayerSession;

    public function store(StorePositionPingRequest $request, Game $game)
    {
        $playerSession = $this->resolvePlayerSession($game);

        $playerSession->pings()->create($request->validated());

        return response()->noContent();
    }
}
```

- [ ] **Step 5: Přidej routu**

Na konec `routes/web.php` přidej (mimo `scopeBindings()` skupinu — tahle
routa nemá `{location}` parametr):

```php
use App\Http\Controllers\Play\PositionPingController;

Route::post('hra/{game:slug}/pings', [PositionPingController::class, 'store'])->name('play.pings.store');
```

- [ ] **Step 6: Spusť testy a ověř průchod**

```bash
php artisan test --filter=PositionPingControllerTest
```

Expected: PASS.

- [ ] **Step 7: Spusť celou testovací sadu**

```bash
php artisan test
```

Expected: PASS (všechny dosavadní testy).

- [ ] **Step 8: Commit**

```bash
git add app/Http/Controllers/Play/PositionPingController.php app/Http/Requests/Play/StorePositionPingRequest.php routes/web.php tests/Feature/Play/PositionPingControllerTest.php
git commit -m "Add position ping recording endpoint"
```

---

### Task 5: Vitest a čisté výpočetní funkce kompasu

**Files:**
- Create: `resources/js/lib/geo.js`
- Create: `resources/js/lib/geo.test.js`
- Modify: `package.json`
- Create: `vitest.config.js`

**Interfaces:**
- Consumes: nic (čisté funkce, žádné závislosti)
- Produces: `distanceMeters(lat1,lng1,lat2,lng2): number`,
  `bearingDegrees(lat1,lng1,lat2,lng2): number`,
  `arrowRotationDegrees(bearing, heading): number`,
  `proximityColor(distanceMeters): 'red'|'orange'|'yellow'|'blue'`.
  Task 7 (CompassScreen.vue) a Task 8 (MapScreen.vue) tyto funkce
  importují.

- [ ] **Step 1: Nainstaluj Vitest**

```bash
npm install -D vitest
```

Do `package.json` do `scripts` přidej:

```json
"test:unit": "vitest run"
```

- [ ] **Step 2: Vytvoř Vitest config**

`vitest.config.js`:

```js
import { defineConfig } from 'vitest/config';

export default defineConfig({
    test: {
        include: ['resources/js/**/*.test.js'],
        environment: 'node',
    },
});
```

- [ ] **Step 3: Napiš selhávající testy**

`resources/js/lib/geo.test.js`:

```js
import { describe, expect, test } from 'vitest';
import { arrowRotationDegrees, bearingDegrees, distanceMeters, proximityColor } from './geo';

describe('distanceMeters', () => {
    test('returns ~0 for identical points', () => {
        expect(distanceMeters(50, 14, 50, 14)).toBeCloseTo(0, 5);
    });

    test('computes a known distance (Praha -> Brno, ~185 km)', () => {
        const d = distanceMeters(50.0755, 14.4378, 49.1951, 16.6068);
        expect(d).toBeGreaterThan(180000);
        expect(d).toBeLessThan(190000);
    });
});

describe('bearingDegrees', () => {
    test('due north is ~0 degrees', () => {
        expect(bearingDegrees(50, 14, 51, 14)).toBeCloseTo(0, 0);
    });

    test('due east is ~90 degrees', () => {
        expect(bearingDegrees(50, 14, 50, 15)).toBeCloseTo(90, 0);
    });

    test('due south is ~180 degrees', () => {
        expect(bearingDegrees(51, 14, 50, 14)).toBeCloseTo(180, 0);
    });
});

describe('arrowRotationDegrees', () => {
    test('facing the bearing yields zero rotation', () => {
        expect(arrowRotationDegrees(90, 90)).toBe(0);
    });

    test('wraps correctly around 360', () => {
        expect(arrowRotationDegrees(10, 350)).toBe(20);
    });
});

describe('proximityColor', () => {
    test.each([
        [10, 'red'],
        [30, 'orange'],
        [100, 'yellow'],
        [200, 'blue'],
    ])('distance %i meters -> %s', (distance, expected) => {
        expect(proximityColor(distance)).toBe(expected);
    });
});
```

- [ ] **Step 4: Spusť test a ověř selhání**

```bash
npm run test:unit
```

Expected: FAIL (`geo.js` neexistuje).

- [ ] **Step 5: Implementuj geo.js**

`resources/js/lib/geo.js`:

```js
const EARTH_RADIUS_M = 6371000;

function toRadians(degrees) {
    return (degrees * Math.PI) / 180;
}

function toDegrees(radians) {
    return (radians * 180) / Math.PI;
}

export function distanceMeters(lat1, lng1, lat2, lng2) {
    const dLat = toRadians(lat2 - lat1);
    const dLng = toRadians(lng2 - lng1);
    const a =
        Math.sin(dLat / 2) ** 2 +
        Math.cos(toRadians(lat1)) * Math.cos(toRadians(lat2)) * Math.sin(dLng / 2) ** 2;
    const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));

    return EARTH_RADIUS_M * c;
}

export function bearingDegrees(lat1, lng1, lat2, lng2) {
    const y = Math.sin(toRadians(lng2 - lng1)) * Math.cos(toRadians(lat2));
    const x =
        Math.cos(toRadians(lat1)) * Math.sin(toRadians(lat2)) -
        Math.sin(toRadians(lat1)) * Math.cos(toRadians(lat2)) * Math.cos(toRadians(lng2 - lng1));

    return (toDegrees(Math.atan2(y, x)) + 360) % 360;
}

export function arrowRotationDegrees(bearing, heading) {
    return (bearing - heading + 360) % 360;
}

export function proximityColor(distance) {
    if (distance < 20) return 'red';
    if (distance < 50) return 'orange';
    if (distance < 150) return 'yellow';

    return 'blue';
}
```

- [ ] **Step 6: Spusť test a ověř průchod**

```bash
npm run test:unit
```

Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add resources/js/lib/geo.js resources/js/lib/geo.test.js vitest.config.js package.json package-lock.json
git commit -m "Add Vitest and pure geo/compass calculation functions"
```

---

### Task 6: useSensors composable a PermissionGate

**Files:**
- Create: `resources/js/lib/useSensors.js`
- Create: `resources/js/Components/Play/PermissionGate.vue`

**Interfaces:**
- Consumes: nic (prohlížečové Geolocation/DeviceOrientation API)
- Produces: `useSensors()` composable vracející reaktivní
  `{ position, heading, geoError, orientationSupported, requestPermissions }`,
  kde `position` je `{lat, lng, accuracy} | null` a `heading` je
  `number | null` (0 = sever). `PermissionGate.vue` — ikonové tlačítko
  "start", které po kliknutí zavolá `requestPermissions()` a emituje
  `@ready`. Task 7 (CompassScreen.vue) obě konzumuje.

- [ ] **Step 1: Vytvoř useSensors.js**

`resources/js/lib/useSensors.js`:

```js
import { onUnmounted, ref } from 'vue';

export function useSensors() {
    const position = ref(null);
    const heading = ref(null);
    const geoError = ref(null);
    const orientationSupported = ref(true);

    let watchId = null;

    const handleOrientation = (event) => {
        if (typeof event.webkitCompassHeading === 'number') {
            heading.value = event.webkitCompassHeading;
        } else if (typeof event.alpha === 'number') {
            heading.value = (360 - event.alpha) % 360;
        }
    };

    const startGeolocation = () => {
        if (!navigator.geolocation) {
            geoError.value = 'unsupported';

            return;
        }

        watchId = navigator.geolocation.watchPosition(
            (pos) => {
                position.value = {
                    lat: pos.coords.latitude,
                    lng: pos.coords.longitude,
                    accuracy: pos.coords.accuracy,
                };
                geoError.value = null;
            },
            (error) => {
                geoError.value = error.code === error.PERMISSION_DENIED ? 'denied' : 'unavailable';
            },
            { enableHighAccuracy: true, maximumAge: 1000 },
        );
    };

    const requestPermissions = async () => {
        startGeolocation();

        const OrientationEvent = window.DeviceOrientationEvent;

        if (OrientationEvent && typeof OrientationEvent.requestPermission === 'function') {
            try {
                const result = await OrientationEvent.requestPermission();

                if (result === 'granted') {
                    window.addEventListener('deviceorientation', handleOrientation);
                } else {
                    orientationSupported.value = false;
                }
            } catch {
                orientationSupported.value = false;
            }
        } else if (OrientationEvent) {
            window.addEventListener('deviceorientationabsolute', handleOrientation);
            window.addEventListener('deviceorientation', handleOrientation);
        } else {
            orientationSupported.value = false;
        }
    };

    onUnmounted(() => {
        if (watchId !== null) {
            navigator.geolocation.clearWatch(watchId);
        }
        window.removeEventListener('deviceorientation', handleOrientation);
        window.removeEventListener('deviceorientationabsolute', handleOrientation);
    });

    return { position, heading, geoError, orientationSupported, requestPermissions };
}
```

- [ ] **Step 2: Vytvoř PermissionGate.vue**

`resources/js/Components/Play/PermissionGate.vue`:

```vue
<script setup>
const emit = defineEmits(['ready']);

const start = async () => {
    emit('ready');
};
</script>

<template>
    <div class="flex h-screen flex-col items-center justify-center gap-6 bg-sky-100">
        <svg viewBox="0 0 24 24" class="h-24 w-24 text-sky-600" fill="currentColor">
            <path d="M12 2a7 7 0 0 0-7 7c0 5.25 7 13 7 13s7-7.75 7-13a7 7 0 0 0-7-7Zm0 9.5a2.5 2.5 0 1 1 0-5 2.5 2.5 0 0 1 0 5Z" />
        </svg>
        <button
            type="button"
            @click="start"
            class="rounded-full bg-sky-600 px-10 py-6 text-2xl text-white shadow-lg active:scale-95"
        >
            ▶
        </button>
    </div>
</template>
```

Poznámka: samotné volání `requestPermissions()` (které potřebuje reagovat
na uživatelské gesto kvůli iOS) se volá až v Task 7 uvnitř
`@ready` handleru — `PermissionGate` jen poskytuje bezpečný "tap"
prvek bez textu.

- [ ] **Step 3: Ruční ověření**

Prohlížečové senzory nejdou smysluplně jednotkově otestovat. Ověř ručně
(nebo počkej na Task 9's manuální E2E test): spusť `npm run build`,
potvrď že soubory nemají syntax chyby.

```bash
npm run build
```

Expected: build proběhne bez chyb.

- [ ] **Step 4: Commit**

```bash
git add resources/js/lib/useSensors.js resources/js/Components/Play/PermissionGate.vue
git commit -m "Add sensor composable and permission gate component"
```

---

### Task 7: CompassScreen.vue

**Files:**
- Create: `resources/js/Components/Play/CompassScreen.vue`

**Interfaces:**
- Consumes: `useSensors()` (Task 6), `distanceMeters`/`bearingDegrees`/`arrowRotationDegrees`/`proximityColor`
  (Task 5), routa `play.attempts.arrive` (Task 3), routa `play.pings.store`
  (Task 4)
- Produces: komponenta `CompassScreen` s props `location` (aktuální cíl:
  `{id, lat, lng, arrival_radius_m}`) a `gameSlug` (string), emitem
  `@arrived` (payload = JSON response z `arrive` endpointu). Task 9
  (Play/Show.vue) ji vykresluje jako hlavní obrazovku, dokud
  `viewMode === 'compass'`.

- [ ] **Step 1: Vytvoř CompassScreen.vue**

`resources/js/Components/Play/CompassScreen.vue`:

```vue
<script setup>
import { computed, onUnmounted, ref, watch } from 'vue';
import { arrowRotationDegrees, bearingDegrees, distanceMeters, proximityColor } from '@/lib/geo';
import { useSensors } from '@/lib/useSensors';

const props = defineProps({
    location: { type: Object, required: true },
    gameSlug: { type: String, required: true },
});
const emit = defineEmits(['arrived']);

const { position, heading, requestPermissions } = useSensors();
requestPermissions();

const distance = computed(() => {
    if (!position.value) return null;

    return distanceMeters(position.value.lat, position.value.lng, props.location.lat, props.location.lng);
});

const rotation = computed(() => {
    if (!position.value || heading.value === null) return 0;

    const bearing = bearingDegrees(position.value.lat, position.value.lng, props.location.lat, props.location.lng);

    return arrowRotationDegrees(bearing, heading.value);
});

const backgroundColor = computed(() => {
    if (distance.value === null) return 'bg-gray-200';

    return {
        red: 'bg-red-400',
        orange: 'bg-orange-400',
        yellow: 'bg-yellow-300',
        blue: 'bg-blue-300',
    }[proximityColor(distance.value)];
});

let arrivalTimer = null;
let pingTimer = null;
let lastPing = null;

const sendPingIfMoved = () => {
    if (!position.value) return;

    if (lastPing && distanceMeters(lastPing.lat, lastPing.lng, position.value.lat, position.value.lng) < 5) {
        return;
    }

    lastPing = { lat: position.value.lat, lng: position.value.lng };
    window.axios.post(route('play.pings.store', props.gameSlug), {
        lat: position.value.lat,
        lng: position.value.lng,
        heading: heading.value,
    });
};

const confirmArrival = async () => {
    const response = await window.axios.post(route('play.attempts.arrive', [props.gameSlug, props.location.id]));
    emit('arrived', response.data);
};

watch(distance, (value) => {
    sendPingIfMoved();

    if (value !== null && value <= props.location.arrival_radius_m) {
        if (!arrivalTimer) {
            arrivalTimer = setTimeout(confirmArrival, 3000);
        }
    } else if (arrivalTimer) {
        clearTimeout(arrivalTimer);
        arrivalTimer = null;
    }
});

pingTimer = setInterval(sendPingIfMoved, 5000);

onUnmounted(() => {
    if (arrivalTimer) clearTimeout(arrivalTimer);
    if (pingTimer) clearInterval(pingTimer);
});
</script>

<template>
    <div class="flex h-screen flex-col items-center justify-center gap-8 transition-colors duration-500" :class="backgroundColor">
        <div class="text-6xl" :style="{ transform: `rotate(${rotation}deg)` }">➤</div>
        <p v-if="distance !== null" class="text-xl font-semibold text-gray-700">{{ Math.round(distance) }} m</p>
    </div>
</template>
```

- [ ] **Step 2: Spusť build a ověř, že komponenta kompiluje**

```bash
npm run build
```

Expected: build proběhne bez chyb (komponenta zatím nikde není
importovaná, takže se do bundlu nedostane — to je v pořádku, ověří to
Task 9).

- [ ] **Step 3: Commit**

```bash
git add resources/js/Components/Play/CompassScreen.vue
git commit -m "Add compass screen with distance/bearing/color and arrival detection"
```

---

### Task 8: MapScreen.vue

**Files:**
- Create: `resources/js/Components/Play/MapScreen.vue`

**Interfaces:**
- Consumes: `useSensors()` (Task 6), `leaflet` npm balíček (Plán A, Task 1)
- Produces: komponenta `MapScreen` s props `locations` (celý seznam
  stanovišť hry: `{id, order, lat, lng}[]`) a `currentLocationId` (number).
  Vykresluje všechna stanoviště, zvýrazní aktuální cíl, vlastní pozici a
  ušlou trasu. Task 9 (Play/Show.vue) ji vykresluje, dokud
  `viewMode === 'map'`.

- [ ] **Step 1: Vytvoř MapScreen.vue**

`resources/js/Components/Play/MapScreen.vue`:

```vue
<script setup>
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import markerIcon2x from 'leaflet/dist/images/marker-icon-2x.png';
import markerIcon from 'leaflet/dist/images/marker-icon.png';
import markerShadow from 'leaflet/dist/images/marker-shadow.png';
import { useSensors } from '@/lib/useSensors';

delete L.Icon.Default.prototype._getIconUrl;
L.Icon.Default.mergeOptions({ iconRetinaUrl: markerIcon2x, iconUrl: markerIcon, shadowUrl: markerShadow });

const props = defineProps({
    locations: { type: Array, required: true },
    currentLocationId: { type: Number, required: true },
});

const { position } = useSensors();

const mapEl = ref(null);
let map;
let playerMarker;
let trail = [];
let trailLine;

const targetIcon = L.divIcon({ className: 'text-2xl', html: '📍', iconSize: [30, 30] });

onMounted(() => {
    const first = props.locations[0];
    map = L.map(mapEl.value).setView([first.lat, first.lng], 16);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap',
    }).addTo(map);

    props.locations.forEach((location) => {
        const isCurrent = location.id === props.currentLocationId;
        L.marker([location.lat, location.lng], isCurrent ? { icon: targetIcon } : {}).addTo(map);
    });

    trailLine = L.polyline([], { color: 'blue' }).addTo(map);
});

onBeforeUnmount(() => map?.remove());

watch(position, (value) => {
    if (!value || !map) return;

    if (playerMarker) {
        playerMarker.setLatLng([value.lat, value.lng]);
    } else {
        playerMarker = L.circleMarker([value.lat, value.lng], { radius: 8, color: 'green' }).addTo(map);
    }

    trail.push([value.lat, value.lng]);
    trailLine.setLatLngs(trail);
});
</script>

<template>
    <div ref="mapEl" class="h-screen w-full"></div>
</template>
```

- [ ] **Step 2: Spusť build**

```bash
npm run build
```

Expected: build proběhne bez chyb.

- [ ] **Step 3: Commit**

```bash
git add resources/js/Components/Play/MapScreen.vue
git commit -m "Add map screen with stations, current target, own position and trail"
```

---

### Task 9: Task komponenty, Play/Join.vue, Play/Show.vue

**Files:**
- Create: `resources/js/Components/Play/PickImageTask.vue`
- Create: `resources/js/Components/Play/PhotoTask.vue`
- Create: `resources/js/Components/Play/RewardOverlay.vue`
- Create: `resources/js/Components/Play/FinishScreen.vue`
- Create: `resources/js/Pages/Play/Join.vue`
- Create: `resources/js/Pages/Play/Show.vue`

**Interfaces:**
- Consumes: `PermissionGate` (Task 6), `CompassScreen` (Task 7),
  `MapScreen` (Task 8), routy `play.join`/`play.attempts.answer`/`play.attempts.photo`
  (Task 2, 3)
- Produces: kompletní hráčský flow od vstupu na `/hra/{slug}` po
  dokončení hry. Toto je poslední task před ručním E2E testem (Task 10).

- [ ] **Step 1: Vytvoř PickImageTask.vue**

`resources/js/Components/Play/PickImageTask.vue`:

```vue
<script setup>
const props = defineProps({
    images: { type: Array, required: true },
    gameSlug: { type: String, required: true },
    locationId: { type: Number, required: true },
});
const emit = defineEmits(['completed']);

const pick = async (index) => {
    const response = await window.axios.post(
        route('play.attempts.answer', [props.gameSlug, props.locationId]),
        { selected_index: index },
    );

    if (response.data.correct) {
        emit('completed', response.data.player);
    }
};
</script>

<template>
    <div class="flex h-screen flex-col items-center justify-center gap-6 bg-white p-6">
        <div class="grid grid-cols-2 gap-4">
            <button v-for="(image, index) in images" :key="index" type="button" @click="pick(index)" class="rounded-2xl border-4 border-transparent active:border-sky-500">
                <img :src="image" class="h-40 w-40 rounded-xl object-cover" />
            </button>
        </div>
    </div>
</template>
```

- [ ] **Step 2: Vytvoř PhotoTask.vue**

`resources/js/Components/Play/PhotoTask.vue`:

```vue
<script setup>
const props = defineProps({
    referenceImage: { type: String, required: true },
    taskType: { type: String, required: true },
    gameSlug: { type: String, required: true },
    locationId: { type: Number, required: true },
});
const emit = defineEmits(['completed']);

const capture = props.taskType === 'selfie_doing_something' ? 'user' : 'environment';

const onCapture = async (event) => {
    const file = event.target.files[0];
    if (!file) return;

    const formData = new FormData();
    formData.append('photo', file);

    const response = await window.axios.post(
        route('play.attempts.photo', [props.gameSlug, props.locationId]),
        formData,
    );

    emit('completed', response.data.player);
};
</script>

<template>
    <div class="flex h-screen flex-col items-center justify-center gap-6 bg-white p-6">
        <img :src="referenceImage" class="h-56 w-56 rounded-xl object-cover" />
        <label class="rounded-full bg-sky-600 px-8 py-4 text-3xl text-white active:scale-95">
            📷
            <input type="file" accept="image/*" :capture="capture" class="hidden" @change="onCapture" />
        </label>
    </div>
</template>
```

- [ ] **Step 3: Vytvoř RewardOverlay.vue a FinishScreen.vue**

`resources/js/Components/Play/RewardOverlay.vue`:

```vue
<script setup>
import { onMounted } from 'vue';

const emit = defineEmits(['done']);

onMounted(() => {
    setTimeout(() => emit('done'), 1200);
});
</script>

<template>
    <div class="flex h-screen flex-col items-center justify-center bg-green-100">
        <div class="animate-bounce text-8xl">🎉</div>
    </div>
</template>
```

`resources/js/Components/Play/FinishScreen.vue`:

```vue
<script setup></script>

<template>
    <div class="flex h-screen flex-col items-center justify-center gap-4 bg-yellow-100">
        <div class="text-9xl">🏆</div>
    </div>
</template>
```

- [ ] **Step 4: Vytvoř Play/Join.vue**

`resources/js/Pages/Play/Join.vue`:

```vue
<script setup>
import { Head, useForm } from '@inertiajs/vue3';

const props = defineProps({
    game: Object,
});

const form = useForm({ nickname: '' });

const submit = () => form.post(route('play.join', props.game.slug));
</script>

<template>
    <Head :title="game.name" />

    <div class="flex h-screen flex-col items-center justify-center gap-6 bg-sky-50 p-6">
        <form @submit.prevent="submit" class="flex flex-col items-center gap-4">
            <label class="text-lg font-semibold text-gray-700">Zadej přezdívku</label>
            <input v-model="form.nickname" type="text" class="rounded-lg border-2 border-sky-300 px-4 py-3 text-center text-xl" autofocus />
            <p v-if="form.errors.nickname" class="text-sm text-red-600">{{ form.errors.nickname }}</p>
            <button type="submit" :disabled="form.processing" class="rounded-full bg-sky-600 px-10 py-4 text-xl text-white active:scale-95">
                Hrát ▶
            </button>
        </form>
    </div>
</template>
```

- [ ] **Step 5: Vytvoř Play/Show.vue**

`resources/js/Pages/Play/Show.vue`:

```vue
<script setup>
import { computed, ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import PermissionGate from '@/Components/Play/PermissionGate.vue';
import CompassScreen from '@/Components/Play/CompassScreen.vue';
import MapScreen from '@/Components/Play/MapScreen.vue';
import PickImageTask from '@/Components/Play/PickImageTask.vue';
import PhotoTask from '@/Components/Play/PhotoTask.vue';
import RewardOverlay from '@/Components/Play/RewardOverlay.vue';
import FinishScreen from '@/Components/Play/FinishScreen.vue';

const props = defineProps({
    game: Object,
    locations: Array,
    player: Object,
});

const permissionsGranted = ref(false);
const viewMode = ref('compass');
const currentIndex = ref(props.player.current_location_index);
const finished = ref(props.player.finished_at !== null);
const activeTask = ref(null); // { task_type, images, reference_image } once arrived
const showingReward = ref(false);

const currentLocation = computed(() => props.locations[currentIndex.value] ?? null);

const onArrived = (data) => {
    if (data.player.finished_at) {
        finished.value = true;

        return;
    }

    if (data.attempt_status === 'completed') {
        currentIndex.value = data.player.current_location_index;
        showingReward.value = true;

        return;
    }

    activeTask.value = data;
};

const onTaskCompleted = (playerState) => {
    activeTask.value = null;

    if (playerState.finished_at) {
        finished.value = true;

        return;
    }

    currentIndex.value = playerState.current_location_index;
    showingReward.value = true;
};

const onRewardDone = () => {
    showingReward.value = false;
};

const toggleView = () => {
    viewMode.value = viewMode.value === 'compass' ? 'map' : 'compass';
};
</script>

<template>
    <Head :title="game.name" />

    <PermissionGate v-if="!permissionsGranted" @ready="permissionsGranted = true" />

    <FinishScreen v-else-if="finished" />

    <RewardOverlay v-else-if="showingReward" @done="onRewardDone" />

    <PickImageTask
        v-else-if="activeTask && activeTask.task_type === 'pick_image'"
        :images="activeTask.images"
        :game-slug="game.slug"
        :location-id="currentLocation.id"
        @completed="onTaskCompleted"
    />

    <PhotoTask
        v-else-if="activeTask && ['photo_of_something', 'selfie_doing_something'].includes(activeTask.task_type)"
        :reference-image="activeTask.reference_image"
        :task-type="activeTask.task_type"
        :game-slug="game.slug"
        :location-id="currentLocation.id"
        @completed="onTaskCompleted"
    />

    <div v-else class="relative h-screen">
        <CompassScreen
            v-if="viewMode === 'compass'"
            :location="currentLocation"
            :game-slug="game.slug"
            @arrived="onArrived"
        />
        <MapScreen
            v-else
            :locations="locations"
            :current-location-id="currentLocation.id"
        />

        <button
            type="button"
            @click="toggleView"
            class="absolute bottom-6 right-6 rounded-full bg-white p-4 text-2xl shadow-lg"
        >
            {{ viewMode === 'compass' ? '🗺️' : '🧭' }}
        </button>
    </div>
</template>
```

- [ ] **Step 6: Spusť build**

```bash
npm run build
```

Expected: build proběhne bez chyb.

- [ ] **Step 7: Spusť celou testovací sadu**

```bash
php artisan test
npm run test:unit
```

Expected: PASS (obě sady).

- [ ] **Step 8: Commit**

```bash
git add resources/js/Components/Play resources/js/Pages/Play
git commit -m "Wire up player Vue pages: join, compass/map toggle, task screens, finish"
```

---

### Task 10: Ruční end-to-end ověření

**Files:** žádné (pouze manuální QA průchod)

**Interfaces:**
- Consumes: celý Plán B (Task 1–9) na existující hře z Plánu A
- Produces: potvrzení, že hráčský engine je funkčně kompletní

- [ ] **Step 1: Připrav testovací hru**

Přes admin (Plán A) vytvoř a publikuj hru s alespoň 4 stanovišti (po
jednom od každého typu), umístěnými blízko sebe (v dosahu procházky nebo
v dosahu GPS mock souřadnic v devtools).

- [ ] **Step 2: Spusť aplikaci a projdi scénář**

```bash
composer run dev
```

V prohlížeči (Chrome DevTools → Sensors → Location override pro simulaci
GPS, nebo reálně venku s telefonem):

1. Otevři `/hra/{slug}`, zadej přezdívku, potvrď že se zobrazí
   PermissionGate a po kliknutí kompas.
2. Nastav souřadnice devtools na pozici prvního stanoviště (typ
   `arrival_only`) — počkej ~3 s, ověř automatický přechod na odměnu a
   pak na další stanoviště.
3. U stanoviště `pick_image` ověř, že se po příchodu zobrazí obrázky bez
   prozrazení správné odpovědi, špatná volba nepokračuje, správná ano.
4. U fotoúkolů ověř zobrazení referenčního obrázku a že tlačítko
   fotoaparátu spustí file picker (skutečné vyfocení kamerou nejde v
   headless prohlížeči plně ověřit — stačí potvrdit, že se soubor po
   výběru odešle a úkol se dokončí).
5. Přepni na mapu, ověř zobrazení všech stanovišť, zvýrazněný cíl,
   vlastní pozici a rostoucí trasu.
6. Po posledním stanovišti ověř zobrazení `FinishScreen`.
7. Zavři a znovu otevři prohlížeč na stejné URL — ověř, že session
   (Laravel cookie) hráče vrátí zpět do rozehrané hry, ne na join
   obrazovku.

- [ ] **Step 3: Spusť celou automatizovanou testovací sadu**

```bash
php artisan test
npm run test:unit
```

Expected: PASS.

- [ ] **Step 4: Commit (pokud vznikly opravy)**

Pokud ruční test odhalil bug, oprav ho, přidej/uprav odpovídající test a
commitni. Pokud ne, tento krok přeskoč — Plán B je hotový.
