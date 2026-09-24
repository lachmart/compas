# Správa hráčů ve sledování — skrýt a smazat (spec + Implementation Plan)

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Dozor může ve sledování hry hráče skrýt (vratně) nebo smazat
(nevratně) — jednotlivě kdykoliv, hromadně výběrem, a jedním tlačítkem
skrýt všechny neaktivní.

**Architecture:** Sloupec `player_sessions.hidden_at`; `TrackedPlayer`
nese `hidden` (bool). Nové JSON akce v `TrackingController` (skrýt,
zobrazit, smazat, hromadně) za `GamePolicy::update` a `scopeBindings`.
Smazání odstraní i fotky hráče z disku. Skrytý hráč se automaticky znovu
zobrazí, když splní další stanoviště (ne při pouhém pingu polohy).
Frontend (`Tracking.vue`, `PlayerCard`, `PlayerDetail`): menu ⋯, režim
výběru s lištou hromadných akcí, tlačítko „Skrýt neaktivní (N)“,
sbalitelný seznam skrytých hráčů.

**Tech Stack:** Laravel 13, Pest, Inertia + Vue 3, Vitest.

**Schváleno uživatelem (2026-09-24):** skrýt (vratné) + smazat (nevratné);
mazání testovacích hráčů kdykoliv (ne až po limitu); hromadný výběr.

## Global Constraints

- Admin UI česky; každá akce autorizuje `$this->authorize('update', $game)`;
  routy s `{game}` + `{playerSession}` v `Route::scopeBindings()`;
  hromadné `ids` validované jen na hráče dané hry
  (`Rule::exists('player_sessions','id')->where('game_id', $game->id)`).
- „Neaktivní“ = hráč dokončil hru, NEBO `last_seen_at` je starší než
  **30 minut**. Jen pro tlačítko „Skrýt neaktivní“; jednotlivé i hromadně
  vybrané akce fungují pro kohokoliv kdykoliv.
- Skrytí: vratné, data zůstávají; skrytý hráč není v seznamu, na mapě ani
  ve statistikách sledování (Moje hry dál počítá všechny). Automatické
  zobrazení při splnění stanoviště (`completeAttempt`), ne při pingu.
- Smazání: potvrzovací dialog, smaže `player_sessions` řádek (kaskádou
  attempts + pings) a soubory `submitted_photo_path` z disku `public`
  (po úspěšném smazání řádků).
- Jména hráčů jen přes interpolaci / text node (bezpečnost). Český text
  s uvozovkami „“ psát jako `„`/`“` v JS.
- Testy: `php artisan test`, `npm run test:unit`, `npm run build` projdou.

---

### Task 1: Backend — skrýt, zobrazit, smazat hráče

**Files:**
- Create: `database/migrations/2026_09_24_000001_add_hidden_at_to_player_sessions_table.php`
- Modify: `app/Models/PlayerSession.php` (fillable/cast `hidden_at`)
- Modify: `app/Support/TrackedPlayer.php` (`'hidden' => $playerSession->hidden_at !== null`)
- Modify: `app/Http/Controllers/Admin/TrackingController.php` (akce `hide`, `unhide`, `destroy`, `bulk`)
- Modify: `app/Http/Controllers/Play/AttemptController.php` (`completeAttempt` → `hidden_at = null`)
- Modify: `routes/web.php`
- Test: `tests/Feature/Admin/PlayerManagementTest.php`

**Interfaces (Produces):**
- `PATCH admin/games/{game}/players/{playerSession}/hide` → `admin.games.players.hide` → JSON `{ player: TrackedPlayer }`
- `PATCH admin/games/{game}/players/{playerSession}/unhide` → `admin.games.players.unhide` → JSON `{ player: TrackedPlayer }`
- `DELETE admin/games/{game}/players/{playerSession}` → `admin.games.players.destroy` → `204`
- `POST admin/games/{game}/players/bulk` → `admin.games.players.bulk`, body
  `{ action: 'hide'|'unhide'|'delete', ids: int[] (1..500) }` → JSON
  `{ affected: int }`
- `TrackedPlayer` navíc `hidden` (bool); tracking props / players JSON
  vrací všechny hráče včetně skrytých (frontend filtruje).

- [ ] **Step 1: Selhávající testy** `tests/Feature/Admin/PlayerManagementTest.php`:

```php
<?php

use App\Enums\GameStatus;
use App\Models\Game;
use App\Models\Location;
use App\Models\LocationAttempt;
use App\Models\PlayerSession;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
    $this->owner = User::factory()->create();
    $this->game = Game::factory()->create(['owner_id' => $this->owner->id, 'status' => GameStatus::Published]);
    $this->location = Location::factory()->create(['game_id' => $this->game->id, 'order' => 1]);
    $this->player = PlayerSession::factory()->create(['game_id' => $this->game->id]);
});

test('the owner can hide and unhide a player', function () {
    $this->actingAs($this->owner)
        ->patchJson(route('admin.games.players.hide', [$this->game, $this->player]))
        ->assertOk()->assertJsonPath('player.hidden', true);
    expect($this->player->fresh()->hidden_at)->not->toBeNull();

    $this->actingAs($this->owner)
        ->patchJson(route('admin.games.players.unhide', [$this->game, $this->player]))
        ->assertOk()->assertJsonPath('player.hidden', false);
    expect($this->player->fresh()->hidden_at)->toBeNull();
});

test('hidden players are still listed for tracking, flagged as hidden', function () {
    $this->player->update(['hidden_at' => now()]);

    $this->actingAs($this->owner)->getJson(route('admin.games.tracking.players', $this->game))
        ->assertJsonPath('players.0.hidden', true);
});

test('deleting a player removes their attempts, pings and photos', function () {
    Storage::disk('public')->put('attempt-photos/p.jpg', 'x');
    $attempt = LocationAttempt::factory()->create([
        'player_session_id' => $this->player->id, 'location_id' => $this->location->id,
        'submitted_photo_path' => 'attempt-photos/p.jpg',
    ]);
    $this->player->pings()->create(['lat' => 50, 'lng' => 14]);

    $this->actingAs($this->owner)
        ->deleteJson(route('admin.games.players.destroy', [$this->game, $this->player]))
        ->assertNoContent();

    expect(PlayerSession::find($this->player->id))->toBeNull()
        ->and(LocationAttempt::find($attempt->id))->toBeNull();
    Storage::disk('public')->assertMissing('attempt-photos/p.jpg');
});

test('bulk actions hide, unhide and delete only players of this game', function () {
    $second = PlayerSession::factory()->create(['game_id' => $this->game->id]);
    $foreign = PlayerSession::factory()->create();

    $this->actingAs($this->owner)
        ->postJson(route('admin.games.players.bulk', $this->game), ['action' => 'hide', 'ids' => [$this->player->id, $second->id]])
        ->assertOk()->assertJson(['affected' => 2]);
    expect($second->fresh()->hidden_at)->not->toBeNull();

    $this->actingAs($this->owner)
        ->postJson(route('admin.games.players.bulk', $this->game), ['action' => 'delete', 'ids' => [$this->player->id, $foreign->id]])
        ->assertJsonValidationErrors('ids.1');
    expect(PlayerSession::find($foreign->id))->not->toBeNull();

    $this->actingAs($this->owner)
        ->postJson(route('admin.games.players.bulk', $this->game), ['action' => 'delete', 'ids' => [$this->player->id, $second->id]])
        ->assertOk()->assertJson(['affected' => 2]);
    expect(PlayerSession::where('game_id', $this->game->id)->count())->toBe(0);
});

test('another dozor cannot hide, delete or bulk-manage players', function () {
    $stranger = User::factory()->create();

    $this->actingAs($stranger)->patchJson(route('admin.games.players.hide', [$this->game, $this->player]))->assertForbidden();
    $this->actingAs($stranger)->deleteJson(route('admin.games.players.destroy', [$this->game, $this->player]))->assertForbidden();
    $this->actingAs($stranger)->postJson(route('admin.games.players.bulk', $this->game), ['action' => 'delete', 'ids' => [$this->player->id]])->assertForbidden();
    expect(PlayerSession::find($this->player->id))->not->toBeNull();
});

test('a player of another game cannot be targeted through this game url', function () {
    $foreign = PlayerSession::factory()->create();

    $this->actingAs($this->owner)
        ->deleteJson(route('admin.games.players.destroy', [$this->game, $foreign]))
        ->assertNotFound();
});

test('a hidden player reappears when they complete a location', function () {
    $this->player->update(['hidden_at' => now()]);

    $this->withSession(['player_session_id' => $this->player->id])
        ->postJson(route('play.attempts.arrive', [$this->game->slug, $this->location->id]))
        ->assertOk();

    expect($this->player->fresh()->hidden_at)->toBeNull();
});

test('a hidden player stays hidden while only sending position pings', function () {
    $this->player->update(['hidden_at' => now()]);

    $this->withSession(['player_session_id' => $this->player->id])
        ->postJson(route('play.pings.store', $this->game->slug), ['lat' => 50.08, 'lng' => 14.43])
        ->assertNoContent();

    expect($this->player->fresh()->hidden_at)->not->toBeNull();
});
```

(Lokace ve `beforeEach` je `arrival_only` podle factory — ověř výchozí
`task_type` factory; pokud není `arrival_only`, nastav ho v testu.)

- [ ] **Step 2: Implementace** — migrace (`timestamp('hidden_at')->nullable()`
  za `last_seen_at`, reverzibilní), model, TrackedPlayer, routy (hide,
  unhide, destroy ve `scopeBindings`; bulk mimo), akce v
  `TrackingController`:
  - `hide`/`unhide`: `authorize('update')`, `forceFill(['hidden_at' => now()/null])->save()`, vrať `['player' => TrackedPlayer::make($playerSession->fresh())]`.
  - `destroy`: `authorize('update')`; posbírej `attempts()->whereNotNull('submitted_photo_path')->pluck(...)`; `$playerSession->delete()`; pak `Storage::disk('public')->delete($paths)`; `response()->noContent()`.
  - `bulk`: validace `action` in hide|unhide|delete, `ids` array 1–500, `ids.*` integer exists-in-game; hide/unhide → hromadný `update(['hidden_at' => …])` na `$game->playerSessions()->whereIn('id', $ids)`; delete → posbírej fotky, `delete()`, smaž soubory; vrať `['affected' => n]`.
  - `AttemptController::completeAttempt` (a jen tam) nastaví `hidden_at = null`, pokud bylo nastavené.
- [ ] **Step 3:** `php artisan migrate`, `php artisan test`, commit „Let the dozor hide and delete players“.

---

### Task 2: Frontend — skrýt/smazat ve sledování

**Files:**
- Create: `resources/js/lib/playerVisibility.js` (+ test)
- Modify: `resources/js/Pages/Admin/Games/Tracking.vue`
- Modify: `resources/js/Components/Admin/PlayerCard.vue`
- Modify: `resources/js/Components/Admin/PlayerDetail.vue`

**Interfaces:**
- `playerVisibility.js`: `INACTIVE_AFTER_MS = 30 * 60 * 1000`;
  `isInactive(player, nowMs)` (dokončil NEBO `now - last_seen_at > 30 min`);
  `splitPlayers(players)` → `{ visible, hidden }`;
  `inactiveIds(players, nowMs)` → id viditelných neaktivních hráčů.
  Testy na hranici 30 min, dokončené, skryté se nepočítají do inactiveIds.
- Tracking.vue: `livePlayers` obsahuje i skryté; seznam, mapa, statistiky
  a `sortPlayers` pracují jen s `visible`. Po akci aktualizuj lokálně:
  hide/unhide → nahraď hráče z odpovědi (`player`), delete → odeber;
  hromadné → hide/unhide nastav `hidden` u vybraných, delete odeber.
  Reverb/polling logika beze změny (odpovědi serveru nesou `hidden`).
- UI:
  - v hlavičce seznamu: `Button sm secondary` „Vybrat“ (přepíná režim
    výběru) a, když N > 0, `Button sm ghost` „Skrýt neaktivní (N)“ (bez
    potvrzení — je vratné);
  - PlayerCard: v režimu výběru zaškrtávátko vlevo (klik na kartu přepíná
    výběr místo otevření detailu); mimo režim menu ⋯ (Breeze `Dropdown`)
    se „Skrýt“ a „Smazat“ (červeně) — klik na ⋯ nesmí otevřít detail;
  - režim výběru: sticky lišta dole v panelu „Vybráno N“ + „Skrýt“ +
    „Smazat“ (danger) + „Zrušit“; smazání přes `ConfirmDialog`
    („Smazat N hráčů? Smaže se i jejich průběh a fotky. Tato akce je
    nevratná.“ s `pluralize`);
  - PlayerDetail: v hlavičce tlačítka „Skrýt“ / „Zobrazit“ a „Smazat“;
    po smazání návrat na seznam, po skrytí zpět na seznam;
  - dole v panelu sbalitelná sekce „Skrytí hráči (N)“ (jen když N > 0):
    kompaktní řádky (zvířátko + jméno) s `Button sm ghost` „Zobrazit“;
  - jednotlivé smazání přes `ConfirmDialog`: zpráva
    `Smazat hráče „{emoji} {jméno}“?` (uvozovky jako `„`/`“`),
    varování „Smaže se i jeho průběh a fotky. Tato akce je nevratná.“;
  - `deleting` / processing stavy jako jinde (dialog se zavře po úspěchu,
    při chybě zůstane otevřený s chybou).
- [ ] Testy helperu (TDD), implementace, build, testy, commit „Hide and
  delete players from the tracking page“.

---

### Task 3: Ruční kontrola (Claude)

Odhlášený dozor v automatizovaném prohlížeči → kontrola UI jen pokud je
dozor přihlášený; jinak ověř přes build + testy a nahlas to uživateli.
