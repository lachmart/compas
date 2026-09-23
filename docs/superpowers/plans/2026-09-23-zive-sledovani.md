# Živé sledování (Dozor tracking) — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Dát dozorovi mapu hry, na které v reálném čase vidí všechny
přihlášené hráče (přezdívka, barva, postup "3/8", čas posledního signálu,
zašednutí při výpadku), detail hráče s časy na stanovištích a náhledy fotek
s možností fotku zpětně označit jako neplatnou, a drobné notifikace při
připojení hráče / dokončení stanoviště / dokončení hry.

**Architecture:** Navazuje na Plán A (admin, `GamePolicy`) a Plán B (hráčské
JSON endpointy `pings`/`arrive`/`answer`/`photo`/`join`). Server při každé
relevantní hráčské akci vyšle broadcast event (`PlayerPositionUpdated`,
`PlayerProgressed`) na privátní kanál `games.{gameId}.tracking` přes Laravel
Reverb. Admin stránka `/admin/games/{game}/tracking` dostane počáteční stav
jako Inertia props (jeden sdílený presenter `TrackedPlayer`), pak se přihlásí
přes Laravel Echo na kanál a stav jen inkrementálně aktualizuje čistými
funkcemi z `resources/js/lib/tracking.js`. Detail hráče a označení fotky jsou
obyčejné JSON endpointy volané přes `axios` (stejný vzor jako hráčská část
v Plánu B). Toto je Plán C ze 4: předchází Plánu D (PWA/offline).

**Tech Stack:** Laravel Reverb + broadcasting (nové), `laravel-echo` +
`pusher-js` (nové npm balíčky), Inertia.js + Vue 3, Leaflet (vzor z
`MapScreen.vue`), Pest (backend), Vitest (čisté JS funkce).

## Global Constraints

- Kanál je **privátní**: `games.{game}.tracking` (v JS
  `Echo.private('games.' + id + '.tracking')`). Autorizace kanálu =
  `GamePolicy::view` = vlastník hry. Cizí dozor se na kanál nepřihlásí (403).
- Broadcast eventy implementují `ShouldBroadcast` (**frontované**, ne
  `ShouldBroadcastNow`) — výpadek Reverbu nesmí nikdy shodit hráčský
  request (ping/arrive/answer/photo). `QUEUE_CONNECTION=database` už je
  nastavené; v testech `QUEUE_CONNECTION=sync` a `BROADCAST_CONNECTION=null`
  (už v `phpunit.xml`).
- Broadcast payload nikdy neobsahuje `correct_index` ani jiná data úkolu —
  jen stav hráče (pozice, postup, časy).
- Každá admin routa s víc než jedním modelem v URL MUSÍ být v
  `Route::scopeBindings()` (poučení z IDOR fixu v Plánu A). Scoping:
  `{game}` → `Game::playerSessions()`, `{playerSession}` →
  `PlayerSession::attempts()` (obě relace existují).
- Každá admin akce volá `$this->authorize('view', $game)` (čtení) nebo
  `$this->authorize('update', $game)` (označení fotky).
- "Výpadek" hráče = `last_seen_at` starší než **120 s**. Hráč s aktivním
  kompasem posílá ping aspoň každých **30 s** (heartbeat), i když stojí.
  `last_seen_at` se aktualizuje při **jakémkoliv** hráčském requestu
  (ne jen pingu).
- Barva hráče se počítá na serveru deterministicky z `id`
  (`PlayerSession::color()`), aby byla stejná v seznamu, na mapě i po
  refreshi.
- Admin UI je česky (dozor umí číst); hráčské UI zůstává bez textu —
  tento plán hráčské UI mění jen neviditelně (heartbeat pingů).
- Echo se inicializuje **líně** (až na admin tracking stránce) — hráčský
  telefon nesmí otevírat WebSocket, který nepotřebuje.
- Testy: `php artisan test` a `npm run test:unit` musí na konci každého
  tasku procházet.

---

### Task 1: Instalace Reverbu a autorizace tracking kanálu

**Files:**
- Modify: `composer.json`, `composer.lock` (přes `install:broadcasting`)
- Create: `config/broadcasting.php`, `config/reverb.php` (generuje installer)
- Create: `routes/channels.php` (generuje installer, pak přepsat)
- Modify: `bootstrap/app.php` (installer přidá `channels:` do `withRouting`)
- Modify: `.env.example`
- Modify: `app/Policies/GamePolicy.php`
- Test: `tests/Feature/Admin/TrackingChannelTest.php`

**Interfaces:**
- Consumes: `App\Policies\GamePolicy` (Plán A), `App\Models\Game`
- Produces: `GamePolicy::view(User, Game): bool`; privátní kanál
  `games.{game}.tracking`; routa `POST /broadcasting/auth` (Laravel).
  Task 3 na kanál vysílá, Task 6 se na něj přihlašuje.

- [ ] **Step 1: Nainstaluj broadcasting s Reverbem (bez Node části)**

```bash
php artisan install:broadcasting --reverb --without-node --no-interaction
```

Node balíčky a `echo.js` řešíme ručně v Tasku 5 (líná inicializace), proto
`--without-node`. Pokud installer přesto vytvoří `resources/js/echo.js` nebo
přidá import do `resources/js/bootstrap.js`, **smaž ho / vrať
`bootstrap.js` do původního stavu** (`git checkout resources/js/bootstrap.js`).

Ověř:

```bash
grep -n "channels" bootstrap/app.php
grep -n "REVERB" .env
composer show laravel/reverb | head -3
```

Expected: `channels: __DIR__.'/../routes/channels.php'` v `withRouting`,
v `.env` klíče `REVERB_APP_ID`, `REVERB_APP_KEY`, `REVERB_APP_SECRET`,
`REVERB_HOST`, `REVERB_PORT`, `REVERB_SCHEME`, `VITE_REVERB_*` a
`BROADCAST_CONNECTION=reverb`.

Fallback, kdyby flagy v této verzi Laravelu nefungovaly:
`composer require laravel/reverb && php artisan reverb:install` a ručně
přidat `channels: __DIR__.'/../routes/channels.php',` do `withRouting` v
`bootstrap/app.php`.

- [ ] **Step 2: Doplň `.env.example`**

Přidej do `.env.example` (hodnoty prázdné/výchozí, ne tajné klíče z `.env`)
a změň `BROADCAST_CONNECTION=log` na `BROADCAST_CONNECTION=reverb`:

```dotenv
BROADCAST_CONNECTION=reverb

REVERB_APP_ID=
REVERB_APP_KEY=
REVERB_APP_SECRET=
REVERB_HOST="localhost"
REVERB_PORT=8080
REVERB_SCHEME=http

VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
VITE_REVERB_HOST="${REVERB_HOST}"
VITE_REVERB_PORT="${REVERB_PORT}"
VITE_REVERB_SCHEME="${REVERB_SCHEME}"
```

- [ ] **Step 3: Napiš selhávající test autorizace kanálu**

`tests/Feature/Admin/TrackingChannelTest.php`:

```php
<?php

use App\Models\Game;
use App\Models\User;

beforeEach(function () {
    // phpunit.xml uses the null broadcaster, which authorizes nothing.
    // Switch to a real Pusher-protocol broadcaster with dummy credentials:
    // channel auth only signs locally, no Reverb server is contacted.
    config([
        'broadcasting.default' => 'reverb',
        'broadcasting.connections.reverb.key' => 'test-key',
        'broadcasting.connections.reverb.secret' => 'test-secret',
        'broadcasting.connections.reverb.app_id' => 'test-app',
    ]);

    // Broadcast::channel() registers on the driver that was default at boot
    // (the null one), so re-register the channels on the reverb driver.
    require base_path('routes/channels.php');
});

function authorizeTrackingChannel($test, Game $game)
{
    return $test->post('/broadcasting/auth', [
        'socket_id' => '1234.5678',
        'channel_name' => "private-games.{$game->id}.tracking",
    ]);
}

test('the game owner can subscribe to the tracking channel', function () {
    $owner = User::factory()->create();
    $game = Game::factory()->create(['owner_id' => $owner->id]);

    authorizeTrackingChannel($this->actingAs($owner), $game)
        ->assertOk()
        ->assertJsonStructure(['auth']);
});

test('another dozor cannot subscribe to the tracking channel', function () {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $game = Game::factory()->create(['owner_id' => $owner->id]);

    authorizeTrackingChannel($this->actingAs($stranger), $game)->assertForbidden();
});

test('a guest cannot subscribe to the tracking channel', function () {
    $game = Game::factory()->create();

    authorizeTrackingChannel($this, $game)->assertForbidden();
});
```

- [ ] **Step 4: Spusť test, ověř že selže**

Run: `php artisan test --filter=TrackingChannelTest`
Expected: FAIL (kanál ještě není definovaný → 403 i pro vlastníka).

- [ ] **Step 5: Přidej `GamePolicy::view` a definici kanálu**

`app/Policies/GamePolicy.php` — přidej metodu (stejná logika jako
`update`, ale samostatná, ať čtení a zápis jdou později oddělit):

```php
    public function view(User $user, Game $game): bool
    {
        return $user->id === $game->owner_id;
    }
```

`routes/channels.php` — nahraď celý obsah vygenerovaný installerem:

```php
<?php

use App\Models\Game;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('games.{game}.tracking', function (User $user, Game $game) {
    return $user->can('view', $game);
});
```

- [ ] **Step 6: Spusť testy**

Run: `php artisan test`
Expected: PASS (všechny, včetně 3 nových).

- [ ] **Step 7: Commit**

```bash
git add composer.json composer.lock config/broadcasting.php config/reverb.php routes/channels.php bootstrap/app.php .env.example app/Policies/GamePolicy.php tests/Feature/Admin/TrackingChannelTest.php
git commit -m "Install Reverb and authorize the per-game tracking channel"
```

---

### Task 2: Sledování "naposledy viděn" + presenter `TrackedPlayer` + heartbeat pingů

**Files:**
- Create: `database/migrations/2026_09_23_000001_add_last_seen_at_to_player_sessions_table.php`
- Modify: `app/Models/PlayerSession.php`
- Modify: `database/factories/PlayerSessionFactory.php`
- Modify: `app/Http/Controllers/Play/ResolvesPlayerSession.php`
- Modify: `app/Http/Controllers/Play/PlayController.php` (join)
- Create: `app/Support/TrackedPlayer.php`
- Modify: `resources/js/Components/Play/CompassScreen.vue`
- Test: `tests/Unit/Models/PlayerSessionTest.php` (doplnit)
- Test: `tests/Unit/Support/TrackedPlayerTest.php`
- Test: `tests/Feature/Play/LastSeenTest.php`

**Interfaces:**
- Consumes: `PlayerSession`, `PositionPing`, `Game::locations()` (Plán B)
- Produces:
  - sloupec `player_sessions.last_seen_at` (nullable timestamp, cast `datetime`)
  - `PlayerSession::latestPing(): HasOne` (`latestOfMany()`)
  - `PlayerSession::markSeen(): void`
  - `PlayerSession::color(): string` (hex, z palety podle `id`)
  - `App\Support\TrackedPlayer::make(PlayerSession $s): array` s klíči
    přesně: `id` (int), `nickname` (string), `color` (string),
    `current_location_index` (int), `locations_count` (int),
    `finished_at` (ISO 8601 string|null), `last_seen_at` (ISO 8601 string),
    `position` (`['lat' => float, 'lng' => float, 'heading' => float|null]`|null).
  Tasky 3, 4 a 6 tento tvar konzumují beze změny.

- [ ] **Step 1: Napiš selhávající testy**

Doplň na konec `tests/Unit/Models/PlayerSessionTest.php`:

```php
test('markSeen() stores the current time as last_seen_at', function () {
    $this->travelTo(now()->setTime(10, 15, 0));
    $session = PlayerSession::factory()->create(['last_seen_at' => null]);

    $session->markSeen();

    expect($session->fresh()->last_seen_at->format('H:i:s'))->toBe('10:15:00');
});

test('latestPing() returns the most recent ping', function () {
    $session = PlayerSession::factory()->create();
    $session->pings()->create(['lat' => 50.0, 'lng' => 14.0]);
    $session->pings()->create(['lat' => 50.1, 'lng' => 14.1]);

    expect((float) $session->latestPing->lat)->toBe(50.1);
});

test('color() is deterministic per player and a hex colour', function () {
    $session = PlayerSession::factory()->create();

    expect($session->color())->toMatch('/^#[0-9a-f]{6}$/')
        ->and($session->color())->toBe($session->fresh()->color());
});
```

Pokud soubor nemá nahoře `use App\Models\PlayerSession;`, přidej ho.

`tests/Unit/Support/TrackedPlayerTest.php`:

```php
<?php

use App\Models\Game;
use App\Models\Location;
use App\Models\PlayerSession;
use App\Support\TrackedPlayer;

test('make() describes a player with their latest position', function () {
    $game = Game::factory()->create();
    Location::factory()->count(3)->sequence(fn ($s) => ['order' => $s->index + 1])->create(['game_id' => $game->id]);
    $session = PlayerSession::factory()->create([
        'game_id' => $game->id,
        'nickname' => 'Anna',
        'current_location_index' => 1,
        'last_seen_at' => '2026-09-23 10:00:00',
    ]);
    $session->pings()->create(['lat' => 50.08, 'lng' => 14.43, 'heading' => 90]);

    $data = TrackedPlayer::make($session);

    expect($data)->toMatchArray([
        'id' => $session->id,
        'nickname' => 'Anna',
        'color' => $session->color(),
        'current_location_index' => 1,
        'locations_count' => 3,
        'finished_at' => null,
        'position' => ['lat' => 50.08, 'lng' => 14.43, 'heading' => 90.0],
    ])->and($data['last_seen_at'])->toStartWith('2026-09-23T10:00:00');
});

test('make() has a null position and falls back to started_at when never seen', function () {
    $session = PlayerSession::factory()->create([
        'started_at' => '2026-09-23 09:00:00',
        'last_seen_at' => null,
    ]);

    $data = TrackedPlayer::make($session);

    expect($data['position'])->toBeNull()
        ->and($data['last_seen_at'])->toStartWith('2026-09-23T09:00:00');
});
```

`tests/Feature/Play/LastSeenTest.php`:

```php
<?php

use App\Enums\GameStatus;
use App\Models\Game;
use App\Models\Location;
use App\Models\PlayerSession;

test('joining a game marks the player as seen', function () {
    $game = Game::factory()->create(['status' => GameStatus::Published]);

    $this->post(route('play.join', $game->slug), ['nickname' => 'Anna']);

    expect(PlayerSession::first()->last_seen_at)->not->toBeNull();
});

test('any player request refreshes last_seen_at', function () {
    $game = Game::factory()->create(['status' => GameStatus::Published]);
    Location::factory()->create(['game_id' => $game->id, 'order' => 1, 'task_type' => 'pick_image',
        'task_payload' => ['images' => ['a.jpg', 'b.jpg', 'c.jpg'], 'correct_index' => 0]]);
    $session = PlayerSession::factory()->create([
        'game_id' => $game->id,
        'last_seen_at' => now()->subHour(),
    ]);

    $this->travelTo(now()->addMinute());
    $this->withSession(['player_session_id' => $session->id])
        ->postJson(route('play.attempts.arrive', [$game->slug, $game->locations()->first()->id]))
        ->assertOk();

    expect($session->fresh()->last_seen_at->diffInSeconds(now()))->toBeLessThan(2);
});
```

- [ ] **Step 2: Spusť testy, ověř že selžou**

Run: `php artisan test --filter="PlayerSessionTest|TrackedPlayerTest|LastSeenTest"`
Expected: FAIL (`last_seen_at` sloupec, `markSeen`, `latestPing`, `color`,
`TrackedPlayer` neexistují).

- [ ] **Step 3: Migrace**

`database/migrations/2026_09_23_000001_add_last_seen_at_to_player_sessions_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('player_sessions', function (Blueprint $table) {
            $table->timestamp('last_seen_at')->nullable()->after('finished_at');
        });
    }

    public function down(): void
    {
        Schema::table('player_sessions', function (Blueprint $table) {
            $table->dropColumn('last_seen_at');
        });
    }
};
```

- [ ] **Step 4: Model `PlayerSession`**

V `app/Models/PlayerSession.php`:

- přidej `'last_seen_at'` do `$fillable` a `'last_seen_at' => 'datetime'` do `$casts`,
- přidej `use Illuminate\Database\Eloquent\Relations\HasOne;`,
- přidej metody:

```php
    private const COLORS = [
        '#e6194b', '#3cb44b', '#4363d8', '#f58231', '#911eb4',
        '#42d4f4', '#f032e6', '#9a6324', '#800000', '#000075',
    ];

    public function latestPing(): HasOne
    {
        return $this->hasOne(PositionPing::class)->latestOfMany();
    }

    public function markSeen(): void
    {
        $this->forceFill(['last_seen_at' => now()])->save();
    }

    public function color(): string
    {
        return self::COLORS[$this->id % count(self::COLORS)];
    }
```

`database/factories/PlayerSessionFactory.php` — do `definition()` přidej
`'last_seen_at' => now(),`.

- [ ] **Step 5: Presenter `TrackedPlayer`**

`app/Support/TrackedPlayer.php`:

```php
<?php

namespace App\Support;

use App\Models\PlayerSession;

/**
 * The single shape the dozor's tracking UI knows a player by — used for the
 * initial Inertia props, the JSON detail endpoint and PlayerProgressed
 * broadcasts, so the three can never drift apart.
 */
class TrackedPlayer
{
    public static function make(PlayerSession $playerSession): array
    {
        $ping = $playerSession->latestPing;

        return [
            'id' => $playerSession->id,
            'nickname' => $playerSession->nickname,
            'color' => $playerSession->color(),
            'current_location_index' => $playerSession->current_location_index,
            'locations_count' => $playerSession->game->locations()->count(),
            'finished_at' => $playerSession->finished_at?->toIso8601String(),
            'last_seen_at' => ($playerSession->last_seen_at ?? $playerSession->started_at)->toIso8601String(),
            'position' => $ping ? [
                'lat' => (float) $ping->lat,
                'lng' => (float) $ping->lng,
                'heading' => $ping->heading === null ? null : (float) $ping->heading,
            ] : null,
        ];
    }
}
```

- [ ] **Step 6: `markSeen` ve všech hráčských requestech**

`app/Http/Controllers/Play/ResolvesPlayerSession.php` — v
`resolvePlayerSession()` těsně před `return $playerSession;`:

```php
        $playerSession->markSeen();
```

(`resolvePlayerSession` volají `arrive`, `answer`, `photo` i `pings` —
jedno místo pokryje všechny akce.)

`app/Http/Controllers/Play/PlayController.php` — v `join()` přidej do
pole pro `PlayerSession::create([...])` klíč `'last_seen_at' => now(),`.

- [ ] **Step 7: Spusť testy**

Run: `php artisan test`
Expected: PASS.

- [ ] **Step 8: Heartbeat pingů v `CompassScreen.vue`**

Dnes se ping pošle jen při posunu ≥ 5 m, takže stojící hráč by po 120 s
vypadal jako "ve výpadku". V `resources/js/Components/Play/CompassScreen.vue`
nahraď funkci `sendPingIfMoved` a proměnnou `lastPing` takto:

```js
const HEARTBEAT_MS = 30000;
let lastPing = null; // { lat, lng, at }

const sendPingIfMoved = () => {
    if (!position.value) return;

    const moved = !lastPing
        || distanceMeters(lastPing.lat, lastPing.lng, position.value.lat, position.value.lng) >= 5;
    const heartbeatDue = !lastPing || Date.now() - lastPing.at >= HEARTBEAT_MS;

    if (!moved && !heartbeatDue) return;

    lastPing = { lat: position.value.lat, lng: position.value.lng, at: Date.now() };
    window.axios
        .post(route('play.pings.store', props.gameSlug), {
            lat: position.value.lat,
            lng: position.value.lng,
            heading: heading.value,
        })
        .catch(() => {});
};
```

(Původní řádek `let lastPing = null;` u `let arrivalTimer`/`let pingTimer`
odstraň — je nahrazený výše. Interval `setInterval(sendPingIfMoved, 5000)`
zůstává, heartbeat se tedy vyhodnocuje každých 5 s.)

Run: `npm run build`
Expected: build projde bez chyb.

- [ ] **Step 9: Commit**

```bash
git add database/migrations app/Models/PlayerSession.php database/factories/PlayerSessionFactory.php app/Http/Controllers/Play app/Support/TrackedPlayer.php resources/js/Components/Play/CompassScreen.vue tests/Unit/Models/PlayerSessionTest.php tests/Unit/Support/TrackedPlayerTest.php tests/Feature/Play/LastSeenTest.php
git commit -m "Track player last-seen time, add TrackedPlayer presenter and ping heartbeat"
```

---

### Task 3: Broadcast eventy z hráčských akcí

**Files:**
- Create: `app/Events/PlayerPositionUpdated.php`
- Create: `app/Events/PlayerProgressed.php`
- Modify: `app/Http/Controllers/Play/PositionPingController.php`
- Modify: `app/Http/Controllers/Play/AttemptController.php`
- Modify: `app/Http/Controllers/Play/PlayController.php`
- Test: `tests/Feature/Play/TrackingBroadcastTest.php`

**Interfaces:**
- Consumes: `TrackedPlayer::make()`, `PlayerSession::markSeen()` (Task 2),
  kanál `games.{game}.tracking` (Task 1)
- Produces (Task 6 na to poslouchá přes Echo):
  - `App\Events\PlayerPositionUpdated(PositionPing $ping)` —
    `broadcastAs(): 'player.position'`, `broadcastWith()`:
    `{ player_session_id: int, lat: float, lng: float, heading: float|null, last_seen_at: string }`
  - `App\Events\PlayerProgressed(PlayerSession $playerSession, string $kind)` —
    `$kind` ∈ `'joined' | 'completed_location' | 'finished'`;
    `broadcastAs(): 'player.progressed'`, `broadcastWith()`:
    `{ kind: string, player: TrackedPlayer }`
  - oba vysílají na `new PrivateChannel('games.'.$gameId.'.tracking')`

- [ ] **Step 1: Napiš selhávající testy**

`tests/Feature/Play/TrackingBroadcastTest.php`:

```php
<?php

use App\Enums\GameStatus;
use App\Events\PlayerPositionUpdated;
use App\Events\PlayerProgressed;
use App\Models\Game;
use App\Models\Location;
use App\Models\PlayerSession;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    Event::fake([PlayerPositionUpdated::class, PlayerProgressed::class]);
    $this->game = Game::factory()->create(['status' => GameStatus::Published]);
});

function addLocation(Game $game, int $order, string $type = 'arrival_only'): Location
{
    return Location::factory()->create([
        'game_id' => $game->id, 'order' => $order, 'task_type' => $type,
        'task_payload' => $type === 'pick_image'
            ? ['images' => ['a.jpg', 'b.jpg', 'c.jpg'], 'correct_index' => 2]
            : [],
    ]);
}

test('a ping broadcasts the new position on the game tracking channel', function () {
    $session = PlayerSession::factory()->create(['game_id' => $this->game->id]);

    $this->withSession(['player_session_id' => $session->id])
        ->postJson(route('play.pings.store', $this->game->slug), ['lat' => 50.08, 'lng' => 14.43, 'heading' => 12]);

    Event::assertDispatched(PlayerPositionUpdated::class, function (PlayerPositionUpdated $event) use ($session) {
        $payload = $event->broadcastWith();

        return $event->broadcastOn()->name === 'private-games.'.$this->game->id.'.tracking'
            && $event->broadcastAs() === 'player.position'
            && $payload['player_session_id'] === $session->id
            && $payload['lat'] === 50.08
            && $payload['lng'] === 14.43
            && $payload['heading'] === 12.0;
    });
});

test('joining broadcasts a joined progress event', function () {
    addLocation($this->game, 1);

    $this->post(route('play.join', $this->game->slug), ['nickname' => 'Anna']);

    Event::assertDispatched(PlayerProgressed::class, fn (PlayerProgressed $e) => $e->kind === 'joined'
        && $e->broadcastWith()['player']['nickname'] === 'Anna'
        && $e->broadcastAs() === 'player.progressed');
});

test('completing a non-final location broadcasts completed_location', function () {
    $first = addLocation($this->game, 1);
    addLocation($this->game, 2);
    $session = PlayerSession::factory()->create(['game_id' => $this->game->id]);

    $this->withSession(['player_session_id' => $session->id])
        ->postJson(route('play.attempts.arrive', [$this->game->slug, $first->id]))->assertOk();

    Event::assertDispatched(PlayerProgressed::class, fn (PlayerProgressed $e) => $e->kind === 'completed_location'
        && $e->broadcastWith()['player']['current_location_index'] === 1);
});

test('completing the final location broadcasts finished', function () {
    $only = addLocation($this->game, 1);
    $session = PlayerSession::factory()->create(['game_id' => $this->game->id]);

    $this->withSession(['player_session_id' => $session->id])
        ->postJson(route('play.attempts.arrive', [$this->game->slug, $only->id]))->assertOk();

    Event::assertDispatched(PlayerProgressed::class, fn (PlayerProgressed $e) => $e->kind === 'finished'
        && $e->broadcastWith()['player']['finished_at'] !== null);
});

test('arriving at a task location without completing it broadcasts nothing', function () {
    $pick = addLocation($this->game, 1, 'pick_image');
    $session = PlayerSession::factory()->create(['game_id' => $this->game->id]);

    $this->withSession(['player_session_id' => $session->id])
        ->postJson(route('play.attempts.arrive', [$this->game->slug, $pick->id]))->assertOk();

    Event::assertNotDispatched(PlayerProgressed::class);
});

test('a wrong pick_image answer broadcasts nothing, a correct one broadcasts progress', function () {
    $pick = addLocation($this->game, 1, 'pick_image');
    addLocation($this->game, 2);
    $session = PlayerSession::factory()->create(['game_id' => $this->game->id]);
    $as = $this->withSession(['player_session_id' => $session->id]);
    $as->postJson(route('play.attempts.arrive', [$this->game->slug, $pick->id]));

    $as->postJson(route('play.attempts.answer', [$this->game->slug, $pick->id]), ['selected_index' => 0]);
    Event::assertNotDispatched(PlayerProgressed::class);

    $as->postJson(route('play.attempts.answer', [$this->game->slug, $pick->id]), ['selected_index' => 2]);
    Event::assertDispatched(PlayerProgressed::class, fn (PlayerProgressed $e) => $e->kind === 'completed_location');
});

test('broadcast payloads never leak the pick_image answer', function () {
    $pick = addLocation($this->game, 1, 'pick_image');
    $session = PlayerSession::factory()->create(['game_id' => $this->game->id]);

    $event = new PlayerProgressed($session, 'joined');

    expect(json_encode($event->broadcastWith()))->not->toContain('correct_index');
});
```

- [ ] **Step 2: Spusť testy, ověř že selžou**

Run: `php artisan test --filter=TrackingBroadcastTest`
Expected: FAIL (`App\Events\PlayerPositionUpdated` neexistuje).

- [ ] **Step 3: Eventy**

`app/Events/PlayerPositionUpdated.php`:

```php
<?php

namespace App\Events;

use App\Models\PositionPing;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PlayerPositionUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public PositionPing $ping) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('games.'.$this->ping->playerSession->game_id.'.tracking');
    }

    public function broadcastAs(): string
    {
        return 'player.position';
    }

    public function broadcastWith(): array
    {
        return [
            'player_session_id' => $this->ping->player_session_id,
            'lat' => (float) $this->ping->lat,
            'lng' => (float) $this->ping->lng,
            'heading' => $this->ping->heading === null ? null : (float) $this->ping->heading,
            'last_seen_at' => $this->ping->created_at->toIso8601String(),
        ];
    }
}
```

`app/Events/PlayerProgressed.php`:

```php
<?php

namespace App\Events;

use App\Models\PlayerSession;
use App\Support\TrackedPlayer;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PlayerProgressed implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param  'joined'|'completed_location'|'finished'  $kind
     */
    public function __construct(public PlayerSession $playerSession, public string $kind) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('games.'.$this->playerSession->game_id.'.tracking');
    }

    public function broadcastAs(): string
    {
        return 'player.progressed';
    }

    public function broadcastWith(): array
    {
        return [
            'kind' => $this->kind,
            'player' => TrackedPlayer::make($this->playerSession->fresh()),
        ];
    }
}
```

- [ ] **Step 4: Napoj eventy do kontrolerů**

`app/Http/Controllers/Play/PositionPingController.php` — nahraď tělo
`store()`:

```php
    public function store(StorePositionPingRequest $request, Game $game)
    {
        $playerSession = $this->resolvePlayerSession($game);

        $ping = $playerSession->pings()->create($request->validated());

        PlayerPositionUpdated::dispatch($ping);

        return response()->noContent();
    }
```

(+ `use App\Events\PlayerPositionUpdated;`)

`app/Http/Controllers/Play/AttemptController.php` — nahraď
`completeAttempt()`:

```php
    private function completeAttempt(LocationAttempt $attempt, PlayerSession $playerSession): void
    {
        $attempt->update(['status' => AttemptStatus::Completed, 'completed_at' => now()]);
        $playerSession->advance();

        PlayerProgressed::dispatch(
            $playerSession,
            $playerSession->isFinished() ? 'finished' : 'completed_location',
        );
    }
```

(+ `use App\Events\PlayerProgressed;`. `advance()` volá `update()` na téže
instanci, takže `isFinished()` vidí nový `finished_at`.)

`app/Http/Controllers/Play/PlayController.php` — v `join()` hned za
`PlayerSession::create([...]);`:

```php
        PlayerProgressed::dispatch($playerSession, 'joined');
```

(+ `use App\Events\PlayerProgressed;`)

- [ ] **Step 5: Spusť testy**

Run: `php artisan test`
Expected: PASS (včetně existujících `PlayThroughGameTest` a
`PositionPingControllerTest` — s `BROADCAST_CONNECTION=null` a sync
frontou se eventy tiše zahodí).

- [ ] **Step 6: Commit**

```bash
git add app/Events app/Http/Controllers/Play tests/Feature/Play/TrackingBroadcastTest.php
git commit -m "Broadcast player position and progress on the game tracking channel"
```

---

### Task 4: Admin tracking backend — stránka, detail hráče, označení fotky

**Files:**
- Create: `app/Http/Controllers/Admin/TrackingController.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/Admin/TrackingControllerTest.php`

**Interfaces:**
- Consumes: `GamePolicy::view`/`update`, `TrackedPlayer::make()`,
  `PlayerSession::latestPing`, `Game::playerSessions()`,
  `PlayerSession::attempts()`
- Produces:
  - `GET /admin/games/{game}/tracking` → `admin.games.tracking`,
    Inertia `Admin/Games/Tracking` s props:
    `game: {id, name, slug}`,
    `locations: [{id, position (1-based int), lat (float), lng (float), task_type}]`,
    `players: TrackedPlayer[]` (seřazení podle `started_at`)
  - `GET /admin/games/{game}/players/{playerSession}` →
    `admin.games.players.show`, JSON
    `{ player: TrackedPlayer, attempts: [{id, location_position, task_type, status, arrived_at, completed_at, photo_url, flagged_invalid}] }`
    (časy ISO 8601 nebo null, `photo_url` null pokud fotka není,
    seřazeno podle `location_position`)
  - `PATCH /admin/games/{game}/players/{playerSession}/attempts/{attempt}/flag`
    → `admin.games.players.attempts.flag`, body `{flagged_invalid: bool}`,
    JSON `{ flagged_invalid: bool }`; 422 pokud pokus nemá fotku

- [ ] **Step 1: Napiš selhávající testy**

`tests/Feature/Admin/TrackingControllerTest.php`:

```php
<?php

use App\Enums\AttemptStatus;
use App\Models\Game;
use App\Models\Location;
use App\Models\LocationAttempt;
use App\Models\PlayerSession;
use App\Models\User;

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->game = Game::factory()->create(['owner_id' => $this->owner->id]);
    $this->first = Location::factory()->create(['game_id' => $this->game->id, 'order' => 1, 'task_type' => 'arrival_only', 'task_payload' => []]);
    $this->second = Location::factory()->create(['game_id' => $this->game->id, 'order' => 2, 'task_type' => 'photo_of_something', 'task_payload' => ['reference_image' => 'ref.jpg']]);
});

test('the owner sees the tracking page with players and locations', function () {
    $session = PlayerSession::factory()->create(['game_id' => $this->game->id, 'nickname' => 'Anna']);
    $session->pings()->create(['lat' => 50.08, 'lng' => 14.43]);

    $this->actingAs($this->owner)->get(route('admin.games.tracking', $this->game))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Games/Tracking')
            ->where('game.id', $this->game->id)
            ->has('locations', 2)
            ->where('locations.1.position', 2)
            ->has('players', 1)
            ->where('players.0.nickname', 'Anna')
            ->where('players.0.position.lat', 50.08));
});

test('another dozor cannot see the tracking page', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('admin.games.tracking', $this->game))
        ->assertForbidden();
});

test('player detail lists attempts with times and photo urls', function () {
    $session = PlayerSession::factory()->create(['game_id' => $this->game->id, 'current_location_index' => 1]);
    LocationAttempt::factory()->create([
        'player_session_id' => $session->id, 'location_id' => $this->second->id,
        'status' => AttemptStatus::Completed, 'arrived_at' => now(), 'completed_at' => now(),
        'submitted_photo_path' => 'attempt-photos/x.jpg',
    ]);
    LocationAttempt::factory()->create([
        'player_session_id' => $session->id, 'location_id' => $this->first->id,
        'status' => AttemptStatus::Completed, 'arrived_at' => now(), 'completed_at' => now(),
    ]);

    $this->actingAs($this->owner)
        ->getJson(route('admin.games.players.show', [$this->game, $session]))
        ->assertOk()
        ->assertJsonPath('player.id', $session->id)
        ->assertJsonPath('attempts.0.location_position', 1)
        ->assertJsonPath('attempts.0.photo_url', null)
        ->assertJsonPath('attempts.1.location_position', 2)
        ->assertJsonPath('attempts.1.status', 'completed')
        ->assertJsonPath('attempts.1.flagged_invalid', false)
        ->assertJson(fn ($json) => $json->where('attempts.1.photo_url', fn ($url) => str_ends_with($url, '/storage/attempt-photos/x.jpg'))->etc());
});

test('player detail is scoped to the game in the url', function () {
    $otherGame = Game::factory()->create(['owner_id' => $this->owner->id]);
    $foreignSession = PlayerSession::factory()->create(['game_id' => $otherGame->id]);

    $this->actingAs($this->owner)
        ->getJson(route('admin.games.players.show', [$this->game, $foreignSession]))
        ->assertNotFound();
});

test('another dozor cannot read player detail', function () {
    $session = PlayerSession::factory()->create(['game_id' => $this->game->id]);

    $this->actingAs(User::factory()->create())
        ->getJson(route('admin.games.players.show', [$this->game, $session]))
        ->assertForbidden();
});

test('the owner can flag and unflag a photo as invalid', function () {
    $session = PlayerSession::factory()->create(['game_id' => $this->game->id]);
    $attempt = LocationAttempt::factory()->create([
        'player_session_id' => $session->id, 'location_id' => $this->second->id,
        'status' => AttemptStatus::Completed, 'submitted_photo_path' => 'attempt-photos/x.jpg',
    ]);
    $url = route('admin.games.players.attempts.flag', [$this->game, $session, $attempt]);

    $this->actingAs($this->owner)->patchJson($url, ['flagged_invalid' => true])
        ->assertOk()->assertJson(['flagged_invalid' => true]);
    expect($attempt->fresh()->flagged_invalid)->toBeTrue();

    $this->actingAs($this->owner)->patchJson($url, ['flagged_invalid' => false])->assertOk();
    expect($attempt->fresh()->flagged_invalid)->toBeFalse();
});

test('flagging an attempt without a photo is rejected', function () {
    $session = PlayerSession::factory()->create(['game_id' => $this->game->id]);
    $attempt = LocationAttempt::factory()->create([
        'player_session_id' => $session->id, 'location_id' => $this->first->id, 'status' => AttemptStatus::Completed,
    ]);

    $this->actingAs($this->owner)
        ->patchJson(route('admin.games.players.attempts.flag', [$this->game, $session, $attempt]), ['flagged_invalid' => true])
        ->assertStatus(422);
});

test('flagging is scoped: an attempt of another player 404s', function () {
    $session = PlayerSession::factory()->create(['game_id' => $this->game->id]);
    $other = PlayerSession::factory()->create(['game_id' => $this->game->id]);
    $attempt = LocationAttempt::factory()->create([
        'player_session_id' => $other->id, 'location_id' => $this->second->id,
        'submitted_photo_path' => 'attempt-photos/x.jpg',
    ]);

    $this->actingAs($this->owner)
        ->patchJson(route('admin.games.players.attempts.flag', [$this->game, $session, $attempt]), ['flagged_invalid' => true])
        ->assertNotFound();
});

test('another dozor cannot flag a photo', function () {
    $session = PlayerSession::factory()->create(['game_id' => $this->game->id]);
    $attempt = LocationAttempt::factory()->create([
        'player_session_id' => $session->id, 'location_id' => $this->second->id,
        'submitted_photo_path' => 'attempt-photos/x.jpg',
    ]);

    $this->actingAs(User::factory()->create())
        ->patchJson(route('admin.games.players.attempts.flag', [$this->game, $session, $attempt]), ['flagged_invalid' => true])
        ->assertForbidden();
});
```

- [ ] **Step 2: Spusť testy, ověř že selžou**

Run: `php artisan test --filter=TrackingControllerTest`
Expected: FAIL (`Route [admin.games.tracking] not defined`).

- [ ] **Step 3: Routy**

`routes/web.php` — přidej `use App\Http\Controllers\Admin\TrackingController;`
a uvnitř skupiny `Route::middleware('auth')->prefix('admin')->name('admin.')`
přidej za existující `Route::scopeBindings()` blok:

```php
    Route::get('games/{game}/tracking', [TrackingController::class, 'show'])->name('games.tracking');

    Route::scopeBindings()->group(function () {
        Route::get('games/{game}/players/{playerSession}', [TrackingController::class, 'player'])->name('games.players.show');
        Route::patch('games/{game}/players/{playerSession}/attempts/{attempt}/flag', [TrackingController::class, 'flag'])->name('games.players.attempts.flag');
    });
```

- [ ] **Step 4: Kontroler**

`app/Http/Controllers/Admin/TrackingController.php`:

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Game;
use App\Models\LocationAttempt;
use App\Models\PlayerSession;
use App\Support\TrackedPlayer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class TrackingController extends Controller
{
    public function show(Game $game)
    {
        $this->authorize('view', $game);

        return Inertia::render('Admin/Games/Tracking', [
            'game' => ['id' => $game->id, 'name' => $game->name, 'slug' => $game->slug],
            'locations' => $game->locations()->get()->values()->map(fn ($location, $index) => [
                'id' => $location->id,
                'position' => $index + 1,
                'lat' => (float) $location->lat,
                'lng' => (float) $location->lng,
                'task_type' => $location->task_type->value,
            ]),
            'players' => $game->playerSessions()
                ->with(['latestPing', 'game'])
                ->orderBy('started_at')
                ->get()
                ->map(fn (PlayerSession $playerSession) => TrackedPlayer::make($playerSession)),
        ]);
    }

    public function player(Game $game, PlayerSession $playerSession)
    {
        $this->authorize('view', $game);

        $positions = $game->locations()->pluck('id')->flip();

        $attempts = $playerSession->attempts()->with('location')->get()
            ->map(fn (LocationAttempt $attempt) => [
                'id' => $attempt->id,
                'location_position' => $positions[$attempt->location_id] + 1,
                'task_type' => $attempt->location->task_type->value,
                'status' => $attempt->status->value,
                'arrived_at' => $attempt->arrived_at?->toIso8601String(),
                'completed_at' => $attempt->completed_at?->toIso8601String(),
                'photo_url' => $attempt->submitted_photo_path
                    ? Storage::disk('public')->url($attempt->submitted_photo_path)
                    : null,
                'flagged_invalid' => $attempt->flagged_invalid,
            ])
            ->sortBy('location_position')
            ->values();

        return response()->json([
            'player' => TrackedPlayer::make($playerSession),
            'attempts' => $attempts,
        ]);
    }

    public function flag(Request $request, Game $game, PlayerSession $playerSession, LocationAttempt $attempt)
    {
        $this->authorize('update', $game);

        abort_unless($attempt->submitted_photo_path, 422);

        $validated = $request->validate(['flagged_invalid' => ['required', 'boolean']]);

        $attempt->update(['flagged_invalid' => $validated['flagged_invalid']]);

        return response()->json(['flagged_invalid' => $attempt->flagged_invalid]);
    }
}
```

Pozn.: `$positions[$attempt->location_id]` — pokud dozor stanoviště mezitím
smazal, pokus zmizel kaskádou (`cascadeOnDelete` v migraci
`location_attempts`), takže klíč vždy existuje.

- [ ] **Step 5: Spusť testy**

Run: `php artisan test`
Expected: PASS. Pokud test `the owner sees the tracking page…` padá na
chybějícím Vue souboru (Inertia `ensure_pages_exist`), vytvoř dočasně
minimální `resources/js/Pages/Admin/Games/Tracking.vue` s
`<template><div /></template>` — plnohodnotná verze přijde v Tasku 6.

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/Admin/TrackingController.php routes/web.php tests/Feature/Admin/TrackingControllerTest.php resources/js/Pages/Admin/Games/Tracking.vue
git commit -m "Add admin tracking page, player detail and photo flagging endpoints"
```

(Pokud jsi `Tracking.vue` nevytvářel, vynech ho z `git add`.)

---

### Task 5: Frontend základ — líné Echo a čisté funkce tracking stavu

**Files:**
- Modify: `package.json`, `package-lock.json`
- Create: `resources/js/lib/echo.js`
- Create: `resources/js/lib/tracking.js`
- Test: `resources/js/lib/tracking.test.js`

**Interfaces:**
- Consumes: `VITE_REVERB_*` env proměnné (Task 1), tvar `TrackedPlayer`
  (Task 2), payloady eventů (Task 3)
- Produces:
  - `getEcho(): Echo` — singleton, vytvoří se při prvním volání
  - `STALE_AFTER_MS = 120000`
  - `isStale(player, nowMs): boolean`
  - `progressLabel(player): string` — `"3/8"`, po dokončení `"✓ 8/8"`
  - `applyPosition(players, payload): players` — vrací nové pole,
    neznámého hráče ignoruje
  - `applyProgress(players, payload): players` — upsert podle `player.id`
  - `progressMessage(payload): string` — česká notifikace
  - `formatTime(iso): string` — `"HH:MM"` v lokálním čase

- [ ] **Step 1: Nainstaluj balíčky**

```bash
npm install laravel-echo pusher-js
```

- [ ] **Step 2: Napiš selhávající testy**

`resources/js/lib/tracking.test.js`:

```js
import { describe, expect, it } from 'vitest';
import {
    STALE_AFTER_MS,
    applyPosition,
    applyProgress,
    isStale,
    progressLabel,
    progressMessage,
} from './tracking';

const player = (overrides = {}) => ({
    id: 1,
    nickname: 'Anna',
    color: '#e6194b',
    current_location_index: 2,
    locations_count: 8,
    finished_at: null,
    last_seen_at: '2026-09-23T10:00:00+00:00',
    position: null,
    ...overrides,
});

describe('isStale', () => {
    const seen = Date.parse('2026-09-23T10:00:00+00:00');

    it('is fresh right up to the threshold', () => {
        expect(isStale(player(), seen + STALE_AFTER_MS)).toBe(false);
    });

    it('is stale after the threshold', () => {
        expect(isStale(player(), seen + STALE_AFTER_MS + 1)).toBe(true);
    });

    it('is never stale once finished', () => {
        expect(isStale(player({ finished_at: '2026-09-23T10:00:00+00:00' }), seen + 10 * STALE_AFTER_MS)).toBe(false);
    });
});

describe('progressLabel', () => {
    it('shows the 1-based current location out of the total', () => {
        expect(progressLabel(player())).toBe('3/8');
    });

    it('shows a check mark when finished', () => {
        expect(progressLabel(player({ finished_at: 'x' }))).toBe('✓ 8/8');
    });
});

describe('applyPosition', () => {
    it('updates position and last seen of the matching player immutably', () => {
        const players = [player(), player({ id: 2 })];

        const next = applyPosition(players, {
            player_session_id: 2, lat: 50.1, lng: 14.4, heading: 90, last_seen_at: '2026-09-23T10:05:00+00:00',
        });

        expect(next).not.toBe(players);
        expect(next[1].position).toEqual({ lat: 50.1, lng: 14.4, heading: 90 });
        expect(next[1].last_seen_at).toBe('2026-09-23T10:05:00+00:00');
        expect(next[0]).toBe(players[0]);
    });

    it('ignores unknown players', () => {
        const players = [player()];

        expect(applyPosition(players, { player_session_id: 99, lat: 1, lng: 1, heading: null, last_seen_at: 'x' })).toEqual(players);
    });
});

describe('applyProgress', () => {
    it('replaces an existing player', () => {
        const next = applyProgress([player()], { kind: 'completed_location', player: player({ current_location_index: 3 }) });

        expect(next).toHaveLength(1);
        expect(next[0].current_location_index).toBe(3);
    });

    it('appends a newly joined player', () => {
        const next = applyProgress([player()], { kind: 'joined', player: player({ id: 2, nickname: 'Bořek' }) });

        expect(next.map((p) => p.nickname)).toEqual(['Anna', 'Bořek']);
    });
});

describe('progressMessage', () => {
    it('describes each kind of progress in Czech', () => {
        expect(progressMessage({ kind: 'joined', player: player() })).toBe('Anna se připojil(a) ke hře');
        expect(progressMessage({ kind: 'completed_location', player: player({ current_location_index: 3 }) }))
            .toBe('Anna splnil(a) stanoviště 3/8');
        expect(progressMessage({ kind: 'finished', player: player() })).toBe('Anna dokončil(a) hru 🎉');
    });
});
```

(`completed_location` přichází s už posunutým indexem — hráč s
`current_location_index: 3` právě splnil 3. stanoviště, index je 0-based.)

- [ ] **Step 3: Spusť testy, ověř že selžou**

Run: `npm run test:unit`
Expected: FAIL (`./tracking` neexistuje).

- [ ] **Step 4: Implementace `tracking.js`**

`resources/js/lib/tracking.js`:

```js
export const STALE_AFTER_MS = 120000;

export function isStale(player, nowMs) {
    if (player.finished_at) return false;

    return nowMs - Date.parse(player.last_seen_at) > STALE_AFTER_MS;
}

export function progressLabel(player) {
    if (player.finished_at) return `✓ ${player.locations_count}/${player.locations_count}`;

    return `${player.current_location_index + 1}/${player.locations_count}`;
}

export function applyPosition(players, payload) {
    return players.map((player) =>
        player.id === payload.player_session_id
            ? {
                  ...player,
                  position: { lat: payload.lat, lng: payload.lng, heading: payload.heading },
                  last_seen_at: payload.last_seen_at,
              }
            : player,
    );
}

export function applyProgress(players, payload) {
    const exists = players.some((player) => player.id === payload.player.id);

    if (!exists) return [...players, payload.player];

    return players.map((player) => (player.id === payload.player.id ? payload.player : player));
}

export function progressMessage({ kind, player }) {
    if (kind === 'joined') return `${player.nickname} se připojil(a) ke hře`;
    if (kind === 'finished') return `${player.nickname} dokončil(a) hru 🎉`;

    return `${player.nickname} splnil(a) stanoviště ${player.current_location_index}/${player.locations_count}`;
}

export function formatTime(iso) {
    return new Date(iso).toLocaleTimeString('cs-CZ', { hour: '2-digit', minute: '2-digit' });
}
```

- [ ] **Step 5: Implementace `echo.js`**

`resources/js/lib/echo.js`:

```js
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

let instance = null;

// Created lazily so only the dozor's tracking page opens a WebSocket —
// players' phones never need one.
export function getEcho() {
    if (instance) return instance;

    window.Pusher = Pusher;
    instance = new Echo({
        broadcaster: 'reverb',
        key: import.meta.env.VITE_REVERB_APP_KEY,
        wsHost: import.meta.env.VITE_REVERB_HOST,
        wsPort: import.meta.env.VITE_REVERB_PORT ?? 80,
        wssPort: import.meta.env.VITE_REVERB_PORT ?? 443,
        forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
        enabledTransports: ['ws', 'wss'],
    });

    return instance;
}
```

- [ ] **Step 6: Spusť testy a build**

```bash
npm run test:unit
npm run build
```

Expected: PASS, build bez chyb.

- [ ] **Step 7: Commit**

```bash
git add package.json package-lock.json resources/js/lib/echo.js resources/js/lib/tracking.js resources/js/lib/tracking.test.js
git commit -m "Add lazy Echo client and pure tracking state helpers"
```

---

### Task 6: Admin tracking UI — mapa, seznam hráčů, detail, notifikace

**Files:**
- Create: `resources/js/Components/Admin/TrackingMap.vue`
- Create: `resources/js/Components/Admin/PlayerDetail.vue`
- Create/Replace: `resources/js/Pages/Admin/Games/Tracking.vue`
- Modify: `resources/js/Pages/Admin/Games/Edit.vue`

**Interfaces:**
- Consumes: Inertia props z `TrackingController::show` (Task 4), JSON
  endpointy `admin.games.players.show` a `admin.games.players.attempts.flag`
  (Task 4), `getEcho()` a funkce z `tracking.js` (Task 5), eventy
  `.player.position` a `.player.progressed` na
  `private-games.{id}.tracking` (Task 3)
- Produces: funkční stránku `/admin/games/{id}/tracking`, odkaz na ni z
  editace hry

- [ ] **Step 1: `TrackingMap.vue`**

`resources/js/Components/Admin/TrackingMap.vue`:

```vue
<script setup>
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import { isStale, progressLabel } from '@/lib/tracking';

const props = defineProps({
    locations: { type: Array, required: true },
    players: { type: Array, required: true },
    now: { type: Number, required: true },
});
const emit = defineEmits(['select']);

const mapEl = ref(null);
let map;
const playerMarkers = new Map();

const styleFor = (player) => {
    const stale = isStale(player, props.now);

    return {
        radius: 9,
        color: stale ? '#9ca3af' : player.color,
        fillColor: stale ? '#d1d5db' : player.color,
        fillOpacity: stale ? 0.5 : 0.9,
        weight: 2,
    };
};

const syncPlayers = () => {
    if (!map) return;

    props.players.forEach((player) => {
        if (!player.position) return;

        const latLng = [player.position.lat, player.position.lng];
        let marker = playerMarkers.get(player.id);

        if (!marker) {
            marker = L.circleMarker(latLng, styleFor(player))
                .addTo(map)
                .on('click', () => emit('select', player.id));
            marker.bindTooltip('', { permanent: true, direction: 'top', offset: [0, -8] });
            playerMarkers.set(player.id, marker);
        }

        marker.setLatLng(latLng);
        marker.setStyle(styleFor(player));
        marker.setTooltipContent(`${player.nickname} · ${progressLabel(player)}`);
    });
};

onMounted(() => {
    map = L.map(mapEl.value);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap',
    }).addTo(map);

    const bounds = [];
    props.locations.forEach((location) => {
        L.marker([location.lat, location.lng], {
            icon: L.divIcon({
                className: '',
                html: `<div class="flex h-7 w-7 items-center justify-center rounded-full bg-indigo-600 text-sm font-bold text-white shadow">${location.position}</div>`,
                iconSize: [28, 28],
            }),
        }).addTo(map);
        bounds.push([location.lat, location.lng]);
    });

    if (bounds.length) {
        map.fitBounds(bounds, { padding: [40, 40], maxZoom: 17 });
    } else {
        map.setView([49.8, 15.5], 7);
    }

    syncPlayers();
});

onBeforeUnmount(() => map?.remove());

watch(() => [props.players, props.now], syncPlayers);
</script>

<template>
    <div ref="mapEl" class="h-full w-full"></div>
</template>
```

(Stanoviště jsou `divIcon` s číslem, takže není potřeba oprava výchozí
Leaflet ikony z `MapScreen.vue`.)

- [ ] **Step 2: `PlayerDetail.vue`**

`resources/js/Components/Admin/PlayerDetail.vue`:

```vue
<script setup>
import { ref, watch } from 'vue';
import { formatTime, progressLabel } from '@/lib/tracking';

const props = defineProps({
    gameId: { type: Number, required: true },
    playerId: { type: Number, required: true },
    // Bumped by the parent whenever this player progresses, so the attempt
    // list refreshes live without polling.
    version: { type: Number, default: 0 },
});
const emit = defineEmits(['close']);

const detail = ref(null);
const loadError = ref(false);

const TASK_ICONS = {
    arrival_only: '📍',
    pick_image: '🖼️',
    photo_of_something: '📷',
    selfie_doing_something: '🤳',
};

const load = async () => {
    loadError.value = false;
    try {
        const response = await window.axios.get(route('admin.games.players.show', [props.gameId, props.playerId]));
        detail.value = response.data;
    } catch (error) {
        console.error('Failed to load player detail', error);
        loadError.value = true;
    }
};

const toggleFlag = async (attempt) => {
    try {
        const response = await window.axios.patch(
            route('admin.games.players.attempts.flag', [props.gameId, props.playerId, attempt.id]),
            { flagged_invalid: !attempt.flagged_invalid },
        );
        attempt.flagged_invalid = response.data.flagged_invalid;
    } catch (error) {
        console.error('Failed to flag photo', error);
        loadError.value = true;
    }
};

watch(() => [props.playerId, props.version], load, { immediate: true });
</script>

<template>
    <div class="space-y-4">
        <div class="flex items-center justify-between">
            <h3 v-if="detail" class="flex items-center gap-2 text-lg font-medium text-gray-800">
                <span class="inline-block h-3 w-3 rounded-full" :style="{ backgroundColor: detail.player.color }"></span>
                {{ detail.player.nickname }}
                <span class="text-sm text-gray-500">{{ progressLabel(detail.player) }}</span>
            </h3>
            <button type="button" class="text-gray-500" @click="emit('close')">✕</button>
        </div>

        <p v-if="loadError" class="text-sm text-red-600">Nepodařilo se načíst data. Zkus to znovu.</p>

        <table v-if="detail" class="w-full text-sm">
            <thead>
                <tr class="text-left text-gray-500">
                    <th class="py-1">#</th>
                    <th>Příchod</th>
                    <th>Splněno</th>
                    <th>Fotka</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="attempt in detail.attempts" :key="attempt.id" class="border-t align-top">
                    <td class="py-2">{{ TASK_ICONS[attempt.task_type] }} {{ attempt.location_position }}</td>
                    <td>{{ attempt.arrived_at ? formatTime(attempt.arrived_at) : '—' }}</td>
                    <td>{{ attempt.completed_at ? formatTime(attempt.completed_at) : '—' }}</td>
                    <td>
                        <div v-if="attempt.photo_url" class="space-y-1">
                            <a :href="attempt.photo_url" target="_blank" rel="noopener">
                                <img
                                    :src="attempt.photo_url"
                                    class="h-16 w-16 rounded object-cover"
                                    :class="{ 'opacity-40 ring-2 ring-red-500': attempt.flagged_invalid }"
                                />
                            </a>
                            <button
                                type="button"
                                class="text-xs"
                                :class="attempt.flagged_invalid ? 'text-gray-600' : 'text-red-600'"
                                @click="toggleFlag(attempt)"
                            >
                                {{ attempt.flagged_invalid ? 'Zrušit označení' : 'Označit jako neplatnou' }}
                            </button>
                        </div>
                        <span v-else class="text-gray-400">—</span>
                    </td>
                </tr>
                <tr v-if="detail.attempts.length === 0">
                    <td colspan="4" class="py-2 text-gray-500">Zatím žádné stanoviště.</td>
                </tr>
            </tbody>
        </table>
    </div>
</template>
```

- [ ] **Step 3: Stránka `Tracking.vue`**

`resources/js/Pages/Admin/Games/Tracking.vue` (celý obsah, přepiš případný
placeholder z Tasku 4):

```vue
<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import TrackingMap from '@/Components/Admin/TrackingMap.vue';
import PlayerDetail from '@/Components/Admin/PlayerDetail.vue';
import { getEcho } from '@/lib/echo';
import { applyPosition, applyProgress, formatTime, isStale, progressLabel, progressMessage } from '@/lib/tracking';

const props = defineProps({
    game: Object,
    locations: Array,
    players: Array,
});

const livePlayers = ref(props.players);
const now = ref(Date.now());
const selectedPlayerId = ref(null);
const detailVersion = ref(0);
const toasts = ref([]);
let toastId = 0;
let nowTimer = null;

const channelName = `games.${props.game.id}.tracking`;

const pushToast = (message) => {
    const id = ++toastId;
    toasts.value.push({ id, message });
    setTimeout(() => {
        toasts.value = toasts.value.filter((toast) => toast.id !== id);
    }, 5000);
};

onMounted(() => {
    nowTimer = setInterval(() => {
        now.value = Date.now();
    }, 15000);

    getEcho()
        .private(channelName)
        .listen('.player.position', (payload) => {
            livePlayers.value = applyPosition(livePlayers.value, payload);
        })
        .listen('.player.progressed', (payload) => {
            livePlayers.value = applyProgress(livePlayers.value, payload);
            pushToast(progressMessage(payload));
            if (payload.player.id === selectedPlayerId.value) detailVersion.value++;
        });
});

onBeforeUnmount(() => {
    clearInterval(nowTimer);
    getEcho().leave(channelName);
});
</script>

<template>
    <Head :title="`Sledování – ${game.name}`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">Sledování: {{ game.name }}</h2>
                <Link :href="route('admin.games.edit', game.id)" class="text-sm text-indigo-600">← Zpět na úpravu hry</Link>
            </div>
        </template>

        <div class="mx-auto grid max-w-7xl gap-4 p-4 lg:grid-cols-[1fr_22rem]">
            <div class="h-[70vh] overflow-hidden rounded-lg bg-white shadow-sm">
                <TrackingMap
                    :locations="locations"
                    :players="livePlayers"
                    :now="now"
                    @select="selectedPlayerId = $event"
                />
            </div>

            <div class="space-y-4">
                <div class="rounded-lg bg-white p-4 shadow-sm">
                    <h3 class="mb-2 font-medium text-gray-800">Hráči ({{ livePlayers.length }})</h3>
                    <ul class="divide-y">
                        <li v-for="player in livePlayers" :key="player.id">
                            <button
                                type="button"
                                class="flex w-full items-center justify-between py-2 text-left"
                                :class="{ 'text-gray-400': isStale(player, now) }"
                                @click="selectedPlayerId = player.id"
                            >
                                <span class="flex items-center gap-2">
                                    <span
                                        class="inline-block h-3 w-3 rounded-full"
                                        :style="{ backgroundColor: isStale(player, now) ? '#9ca3af' : player.color }"
                                    ></span>
                                    {{ player.nickname }}
                                </span>
                                <span class="text-right text-sm">
                                    {{ progressLabel(player) }}
                                    <span class="block text-xs">
                                        {{ isStale(player, now) ? 'naposledy viděn v ' : '' }}{{ formatTime(player.last_seen_at) }}
                                    </span>
                                </span>
                            </button>
                        </li>
                        <li v-if="livePlayers.length === 0" class="py-2 text-sm text-gray-500">Zatím se nikdo nepřipojil.</li>
                    </ul>
                </div>

                <div v-if="selectedPlayerId" class="rounded-lg bg-white p-4 shadow-sm">
                    <PlayerDetail
                        :game-id="game.id"
                        :player-id="selectedPlayerId"
                        :version="detailVersion"
                        @close="selectedPlayerId = null"
                    />
                </div>
            </div>
        </div>

        <div class="fixed bottom-4 right-4 z-[1000] space-y-2">
            <div
                v-for="toast in toasts"
                :key="toast.id"
                class="rounded-lg bg-gray-900 px-4 py-2 text-sm text-white shadow-lg"
            >
                {{ toast.message }}
            </div>
        </div>
    </AuthenticatedLayout>
</template>
```

- [ ] **Step 4: Odkaz z editace hry**

`resources/js/Pages/Admin/Games/Edit.vue` — přidej `Link` do importu z
`@inertiajs/vue3` (`import { Head, Link, useForm, router } from '@inertiajs/vue3';`)
a do `<div class="flex items-center gap-4">` za tlačítko publikace:

```vue
                        <Link
                            :href="route('admin.games.tracking', game.id)"
                            class="rounded bg-gray-100 px-4 py-2 text-gray-800"
                        >
                            Sledovat hráče
                        </Link>
```

- [ ] **Step 5: Build a testy**

```bash
npm run build
npm run test:unit
php artisan test
```

Expected: build bez chyb, obě sady PASS.

- [ ] **Step 6: Commit**

```bash
git add resources/js/Components/Admin/TrackingMap.vue resources/js/Components/Admin/PlayerDetail.vue resources/js/Pages/Admin/Games/Tracking.vue resources/js/Pages/Admin/Games/Edit.vue
git commit -m "Add live tracking page with player map, detail panel and progress toasts"
```

---

### Task 7: Ruční end-to-end ověření

**Files:** žádné (pouze manuální QA průchod; případné opravy + testy)

**Interfaces:**
- Consumes: celý Plán C (Task 1–6), hráčský engine z Plánu B
- Produces: potvrzení, že živé sledování funguje s běžícím Reverbem

- [ ] **Step 1: Spusť aplikaci včetně Reverbu a fronty**

```bash
composer run dev
```

Ověř ve výstupu, že běží web server, Vite, **queue worker** a **Reverb**.
Pokud `php artisan dev` Reverb nespouští, pusť ho v druhém terminálu:

```bash
php artisan reverb:start --debug
```

Bez queue workeru se broadcasty (`ShouldBroadcast`) neodešlou — pokud
worker neběží, `php artisan queue:work`.

- [ ] **Step 2: Projdi scénář**

1. Jako dozor otevři publikovanou hru (z Plánu B) → "Sledovat hráče".
   Ověř mapu s očíslovanými stanovišti a prázdný seznam hráčů. V DevTools
   → Network → WS ověř otevřené spojení a úspěšný `POST /broadcasting/auth`.
2. V anonymním okně (jiná session) otevři `/hra/{slug}` a připoj se jako
   "Anna". U dozora musí okamžitě vyskočit toast "Anna se připojil(a) ke
   hře" a Anna se objeví v seznamu (bez refreshe).
3. V hráčském okně nastav DevTools → Sensors → Location na nějakou
   souřadnici. U dozora se do ~5 s objeví barevný marker Anny s popiskem
   "Anna · 1/N"; změna souřadnice marker posune.
4. Přesuň hráče na první stanoviště (`arrival_only`) → toast "splnil(a)
   stanoviště 1/N", popisek "2/N".
5. Klikni na Annu → detail s časy příchodu/splnění. Dokonči fotoúkol →
   detail se sám obnoví a ukáže náhled fotky. "Označit jako neplatnou" →
   fotka zprůhlední s červeným rámečkem, po refreshi stránky stav drží.
6. Zavři hráčské okno a počkej > 2 min (+ až 15 s na tick) → Anna
   zešedne a ukáže "naposledy viděn v HH:MM".
7. Jako jiný dozor (jiný účet) zkus otevřít
   `/admin/games/{id}/tracking` cizí hry → 403.

- [ ] **Step 3: Spusť celou automatizovanou testovací sadu**

```bash
php artisan test
npm run test:unit
```

Expected: PASS.

- [ ] **Step 4: Commit (pokud vznikly opravy)**

Pokud ruční test odhalil bug, oprav ho, přidej/uprav odpovídající test a
commitni. Pokud ne, tento krok přeskoč — Plán C je hotový.
