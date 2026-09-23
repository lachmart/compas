# Hra pro děti — redesign hráčské části (spec + Implementation Plan)

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Hráčská část bez úvodní obrazovky s ▶, se vstupem přes výběr
zvířátka a s krásnými, dobře hratelnými obrazovkami (kompas, mapa, úkoly,
odměna, konec) pro děti, které neumí číst.

**Architecture:** Backend přidá hráči `avatar` (enum) a zjednoduší join
(jméno volitelné). Frontend: `Join.vue` s výběrem zvířátka, jehož klepnutí
zároveň vyžádá povolení kompasu (iOS gesto); `Show.vue` startuje rovnou do
hry (PermissionGate zmizí). Nové sdílené hráčské komponenty (HUD s tečkami
stanovišť, konfety, SVG šipka, prstenec příchodu) a helper zpětné vazby
(zvuk přes WebAudio + vibrace). Logika hry (příchod, úkoly, pingy,
vzpřímený režim) se nemění.

**Tech Stack:** Laravel 13, Inertia + Vue 3, Tailwind 3, Leaflet, Vitest,
Pest. Žádné nové závislosti.

**Schváleno uživatelem (brainstorming 2026-09-23):** odstranit úvodní
obrazovku s ▶; vstup výběrem zvířátka (8 zvířátek, emoji) + volitelné jméno
vyplněné dospělým; zbytek navrhuje Claude.

## Global Constraints

- **Hráčské UI je bez textu** (dítě neumí číst). Výjimky: pole „Jméno
  (vyplní dospělý)“ na vstupu a číslo vzdálenosti v metrech (pro dospělé /
  starší děti). Vše ostatní ikony, barvy, animace, zvuk.
- Zvířátka (klíč → emoji → český název): `fox` 🦊 Liška, `hedgehog` 🦔
  Ježek, `owl` 🦉 Sova, `rabbit` 🐰 Zajíc, `bear` 🐻 Medvěd, `frog` 🐸
  Žába, `squirrel` 🐿️ Veverka, `deer` 🦌 Jelen. Jediný zdroj: PHP enum
  `App\Enums\Avatar` a JS `resources/js/lib/avatars.js` (stejné klíče).
- Barvy Kompas: teal-600/700, orange-500; hráčská část může být sytější a
  hravější (velké zaoblení `rounded-3xl`, stíny, jemné animace).
- Dotykové cíle ≥ 56 px; vše použitelné jednou rukou na 360–430 px šířky;
  bez vodorovného posunu; `h-dvh` místo `h-screen` (mobilní lišty).
- Animace respektují `prefers-reduced-motion` (Tailwind `motion-safe:`).
- **Zachovat** logiku: `useSensors` (maximumAge Infinity, absolutní/relativní
  orientace), `usePositionPings`, arrival 3 s v okruhu `arrival_radius_m`,
  `arrive`/`answer`/`photo` requesty a jejich chybové stavy (ErrorFlash),
  vzpřímený režim (`nextPosture`, `turnGuidance` s hysterezí, `TurnGuide`,
  vibrace při vstupu na kurz), mapa (poslední známá poloha, framing hráče
  a cíle), polling/ping chování.
- Bezpečnost: uživatelský text (jméno) nikdy do Leaflet HTML; `divIcon`
  html smí obsahovat jen emoji z pevného seznamu avatarů / číslo.
- Testy: `php artisan test`, `npm run test:unit` projdou; `npm run build`
  bez chyb.

---

### Task 1: Backend — avatar hráče a jednodušší vstup

**Files:**
- Create: `app/Enums/Avatar.php`
- Create: `database/migrations/2026_09_23_000003_add_avatar_to_player_sessions_table.php`
- Modify: `app/Models/PlayerSession.php` (fillable, cast)
- Modify: `database/factories/PlayerSessionFactory.php` (`'avatar' => 'fox'`)
- Modify: `app/Http/Requests/Play/JoinGameRequest.php`
- Modify: `app/Http/Controllers/Play/PlayController.php` (`join`, `show` props)
- Modify: `app/Support/TrackedPlayer.php` (`avatar`)
- Create: `resources/js/lib/avatars.js` (+ `avatars.test.js`)
- Test: `tests/Feature/Play/PlayControllerTest.php`, `tests/Unit/Support/TrackedPlayerTest.php`

**Interfaces (Produces):**
- `App\Enums\Avatar` (string-backed: fox, hedgehog, owl, rabbit, bear,
  frog, squirrel, deer) s metodou `label(): string` (české názvy výše).
- Sloupec `player_sessions.avatar` string(20) nullable (staří hráči null).
- Join request: `avatar` required `Rule::enum(Avatar::class)`; `nickname`
  nullable string max:50. Uložená přezdívka = trim(nickname) nebo
  `Avatar::label()`.
- `PlayController::show` props `player` navíc `avatar` (string|null);
  Join props navíc nic (seznam zvířátek je na frontendu).
- `TrackedPlayer::make()` navíc klíč `avatar` (string|null).
- `avatars.js`: `AVATARS` = pole `{ key, emoji, label }` v pořadí výše;
  `avatarEmoji(key)` → emoji nebo `'🙂'` pro null/neznámý.

- [ ] **Step 1: Selhávající testy** — do `PlayControllerTest.php`:

```php
test('a player joins by picking an animal, the name is optional', function () {
    $game = Game::factory()->create(['status' => GameStatus::Published]);

    $this->post(route('play.join', $game->slug), ['avatar' => 'owl'])->assertRedirect();

    $session = PlayerSession::first();
    expect($session->avatar)->toBe(\App\Enums\Avatar::Owl)
        ->and($session->nickname)->toBe('Sova');
});

test('an adult-typed name is kept alongside the animal', function () {
    $game = Game::factory()->create(['status' => GameStatus::Published]);

    $this->post(route('play.join', $game->slug), ['avatar' => 'fox', 'nickname' => '  Anička '])->assertRedirect();

    expect(PlayerSession::first()->nickname)->toBe('Anička');
});

test('joining requires a known animal', function () {
    $game = Game::factory()->create(['status' => GameStatus::Published]);

    $this->post(route('play.join', $game->slug), ['avatar' => 'dragon'])->assertSessionHasErrors('avatar');
    $this->post(route('play.join', $game->slug), [])->assertSessionHasErrors('avatar');
});

test('the play screen receives the player avatar', function () {
    $game = Game::factory()->create(['status' => GameStatus::Published]);
    Location::factory()->create(['game_id' => $game->id, 'order' => 1]);
    $session = PlayerSession::factory()->create(['game_id' => $game->id, 'avatar' => 'bear']);

    $this->withSession(['player_session_id' => $session->id])->get(route('play.show', $game->slug))
        ->assertInertia(fn ($page) => $page->where('player.avatar', 'bear'));
});
```

Do `TrackedPlayerTest.php` přidej aserci `'avatar'` (factory default
`'fox'`). Existující testy, které joinují jen s `nickname`, uprav tak, aby
posílaly i `avatar`. Stávající `LastSeenTest`/`TrackingBroadcastTest` join
volání také.

`resources/js/lib/avatars.test.js`:

```js
import { expect, test } from 'vitest';
import { AVATARS, avatarEmoji } from './avatars';

test('eight animals with emoji and Czech labels in a fixed order', () => {
    expect(AVATARS.map((a) => a.key)).toEqual(['fox', 'hedgehog', 'owl', 'rabbit', 'bear', 'frog', 'squirrel', 'deer']);
    expect(AVATARS.find((a) => a.key === 'owl').label).toBe('Sova');
});

test('avatarEmoji falls back for unknown or missing keys', () => {
    expect(avatarEmoji('fox')).toBe('🦊');
    expect(avatarEmoji(null)).toBe('🙂');
    expect(avatarEmoji('dragon')).toBe('🙂');
});
```

- [ ] **Step 2:** Implementuj enum, migraci, model (`'avatar' => Avatar::class`
  cast, nullable), request, controller (`join` ukládá avatar + přezdívku,
  `show` posílá `avatar` jako `?->value`), TrackedPlayer (`avatar` =
  `?->value`), avatars.js (emoji v JS psát jako `\u{…}` escape, ať je
  nepoškodí nástroj; ověř testem).
- [ ] **Step 3:** `php artisan test`, `npm run test:unit`, commit
  „Let players join by picking an animal“.

---

### Task 2: Vstup do hry bez úvodní obrazovky

**Files:**
- Modify (přepsat šablonu): `resources/js/Pages/Play/Join.vue`
- Modify: `resources/js/Pages/Play/Show.vue` (bez PermissionGate)
- Delete: `resources/js/Components/Play/PermissionGate.vue`
- Create: `resources/js/lib/orientationPermission.js` (+ test)
- Modify: `resources/js/lib/useSensors.js` (použije sdílený stav povolení)

**Interfaces:**
- `orientationPermission.js`: `needsOrientationPermission(): boolean`
  (true, když existuje `DeviceOrientationEvent.requestPermission` a ještě
  nebylo uděleno v této relaci), `requestOrientationPermission():
  Promise<'granted'|'denied'|'unsupported'>` (volat synchronně z handleru
  klepnutí; výsledek si pamatuje v `sessionStorage` klíči
  `kompas.orientation` — v try/catch), `orientationGranted(): boolean`.
  Testuj s mockovaným `globalThis.DeviceOrientationEvent` a
  `sessionStorage`.
- `useSensors.requestPermissions()` přestane sama volat
  `DeviceOrientationEvent.requestPermission()`, pokud už je uděleno
  (jen zaregistruje listenery) — a vystaví `needsTap` ref (true na iOS,
  když povolení chybí), plus `enableCompass()` (volá
  `requestOrientationPermission` z gesta a pak registruje listenery).

**Join.vue:** celoobrazovkové pozadí jako přihlášení dozora (přechod +
vrstevnice), logo Kompas; mřížka 4×2 velkých tlačítek zvířátek (emoji
~56 px v kruhu 88 px, bílý kruh, vybrané: `ring-4 ring-orange-400
scale-110` + poskočení); pod tím malé pole „Jméno (vyplní dospělý)“
(maxlength 50); velké zelené kulaté tlačítko ▶ (96 px, SVG trojúhelník),
neaktivní dokud není vybrané zvířátko (průhledné + jemná nápověda
pulzováním mřížky při klepnutí bez výběru). Klepnutí na ▶: **synchronně**
`requestOrientationPermission()` (neawaituj před odesláním formuláře —
spusť a pak `form.post`), jednorázově `navigator.geolocation
.getCurrentPosition(() => {}, () => {})` pro vyvolání dotazu na polohu,
pak `form.post(route('play.join'))` s `{ avatar, nickname }`. Chyby
validace: `avatar` → zatřesení mřížky (bez textu).

**Show.vue:** odstraň PermissionGate a `permissionsGranted`; pingy běží
hned (`pingsEnabled = !finished`). Ostatní stavová logika beze změny.

- [ ] Test orientationPermission, implementace, build, testy, commit
  „Start the game straight from the animal picker“.

---

### Task 3: Kompas — hlavní obrazovka

**Files:**
- Create: `resources/js/Components/Play/PlayerHud.vue`
- Create: `resources/js/Components/Play/CompassArrow.vue`
- Create: `resources/js/Components/Play/ArrivalRing.vue`
- Create: `resources/js/lib/proximity.js` (+ test)
- Modify: `resources/js/Components/Play/CompassScreen.vue` (šablona + prezentace)
- Modify: `resources/js/Pages/Play/Show.vue` (HUD + tlačítko mapy)

**Interfaces:**
- `proximity.js`: `heatLevel(distanceM): 0..5` (≥300→0, ≥150→1, ≥75→2,
  ≥35→3, ≥15→4, <15→5; null→0); `heatGradient(level): string` (Tailwind
  třídy pozadí `bg-gradient-to-b …` od studené modré (0) přes tyrkys,
  žlutou, oranžovou po červenou (5)) — test na hranice.
- `PlayerHud` props `avatar`, `total`, `completed` (počet splněných),
  `current` (index aktuálního): nahoře vlevo kruh se zvířátkem, vpravo
  řada teček (splněné = plná hvězdička ★ SVG zlatá, aktuální = pulzující
  kroužek, budoucí = prázdný kroužek); při velkém počtu (> 8) teček se
  zmenší.
- `CompassArrow` props `rotation` (deg), `dimmed` (Boolean): SVG šipka
  (velká, bílá s tmavým obrysem a stínem) v kruhovém ciferníku (průsvitný
  bílý kruh, 4 ryski); plynulá rotace přes CSS transition (pozor na přechod
  359→0: drž neomezený úhel — `unwrapAngle(prev, next)` helper v
  `proximity.js` + test).
- `ArrivalRing` props `progress` (0..1): SVG prstenec kolem ciferníku,
  vyplňuje se po dobu 3 s arrival timeru.

**CompassScreen:** pozadí `heatGradient(heatLevel(distance))` s plynulým
přechodem (dvě vrstvy s crossfade, nebo `transition-colors` na barvách
přes CSS proměnné); uprostřed `CompassArrow` (plochý režim) nebo
`TurnGuide` (vzpřímený — beze změny logiky), kolem `ArrivalRing` když
běží arrival timer (progress z `requestAnimationFrame` od startu timeru
do 3 s); pod ciferníkem „teploměr“ 5 dílků (`heatLevel`) + malé číslo
„{n} m“; když chybí heading: místo šipky pulzující ikona kompasu —
pokud `needsTap` (iOS), je to tlačítko `enableCompass()`; jinak jen
vzdálenost a barva. Chybový stav polohy (`geoError`): celá obrazovka
s přeškrtnutým špendlíkem a velkým ↻ (stávající chování, hezčí).

**Show.vue:** nad kompasem/mapou `PlayerHud` (avatar z `player.avatar`,
total `locations.length`, completed = `currentIndex`, current =
`currentIndex`); přepínač mapa/kompas jako velké kulaté tlačítko vpravo
dole (56–64 px, SVG ikona mapy/kompasu místo emoji), nad Leafletem
(`z-[1000]`).

- [ ] Testy proximity, implementace, build, testy, commit „Redesign the
  compass screen with heat colours, HUD and arrival ring“.

---

### Task 4: Úkoly, odměna a konec hry

**Files:**
- Create: `resources/js/lib/feedback.js` (+ test s mockem AudioContext/vibrate)
- Create: `resources/js/Components/Play/Confetti.vue`
- Modify: `resources/js/Components/Play/PickImageTask.vue`,
  `PhotoTask.vue`, `RewardOverlay.vue`, `FinishScreen.vue`, `ErrorFlash.vue`
- Modify: `resources/js/Pages/Play/Show.vue` (předání avataru/počtů do Finish)

**Interfaces:**
- `feedback.js`: `playSuccess()`, `playWrong()` — krátké tóny přes
  WebAudio (lazy `AudioContext`, žádné soubory; tiché selhání), plus
  `vibrate(pattern)` (`navigator.vibrate?.`); `celebrate()` = success
  zvuk + vibrace `[60, 40, 60]`.
- `Confetti` — čisté CSS/SVG konfety (≈40 kousků, náhodné barvy Kompas
  palety, pád 1.5 s), `motion-safe`.

Chování (logika requestů beze změny):
- **PickImageTask:** HUD zůstává nahoře (Show), obrázky ve velkých
  dlaždicích (2 sloupce, čtverce, `rounded-3xl`, bílý rámeček, stín);
  klepnutí → dlaždice se zmenší (feedback), při `correct` zelený rámeček
  + velká fajfka přes obrázek + `celebrate()` a pak `completed`; při
  špatné odpovědi zatřesení + červený rámeček 600 ms + `playWrong()`,
  lze zkusit znovu.
- **PhotoTask:** předloha velká (max 70 % šířky) v „polaroid“ rámečku,
  pod ní obrovské kulaté tlačítko fotoaparátu (SVG) 96 px; po výběru
  náhled vyfocené fotky + točící se kolečko během odesílání; úspěch →
  `celebrate()`.
- **RewardOverlay:** konfety + velká zlatá hvězda, která doletí/odletí
  nahoru k HUD (CSS animace), `celebrate()`; trvání 1.6 s (místo 1.2 s).
- **FinishScreen:** props `avatar`, `stars` (počet stanovišť): pohár (SVG
  nebo emoji 🏆 velký), zvířátko dítěte, řada všech hvězd, konfety
  opakovaně (jednou za pár sekund), `celebrate()` při zobrazení.
- **ErrorFlash:** hezčí (kulatý badge s ikonou vykřičníku, jemné
  zatřesení).

- [ ] Testy feedback, implementace, build, testy, commit „Make tasks,
  rewards and the finish screen playful“.

---

### Task 5: Mapa pro dítě

**Files:**
- Modify: `resources/js/Components/Play/MapScreen.vue`
- Modify: `resources/js/Pages/Play/Show.vue` (props do mapy)

- Hráč na mapě = `divIcon` s emoji zvířátka (z `avatarEmoji`, jen pevný
  seznam) v bílém kruhu se stínem; stopa (polyline) zůstává.
- Stanoviště: splněná (index < currentIndex) = zelený kruh s fajfkou
  (SVG), aktuální cíl = velká oranžová pulzující značka, budoucí = malé
  šedé kroužky bez čísel.
- Zachovat: poslední známá poloha (`maximumAge: Infinity` v useSensors),
  jednorázový framing hráče + cíle, chybový odznak polohy.
- Props `MapScreen`: navíc `avatar`, `currentIndex`.

- [ ] Build, testy, commit „Show the child's animal and progress on the map“.

---

### Task 6: Zvířátka ve sledování dozora

**Files:**
- Modify: `resources/js/Components/Admin/PlayerCard.vue`,
  `PlayerDetail.vue`, `TrackingMap.vue`
- Modify: `resources/js/lib/tracking.js` (`progressMessage` s emoji)
- Test: `resources/js/lib/tracking.test.js`

- PlayerCard a PlayerDetail: emoji zvířátka (`avatarEmoji`) před jménem
  (text interpolací).
- TrackingMap: hráčský kruh doplnit tooltipem s emoji + jménem jako text
  node (bezpečně); volitelně marker jako divIcon s emoji (pevný seznam)
  obarvený okrajem barvy hráče.
- `progressMessage` začíná emoji zvířátka („🦉 Sova se připojil(a) ke
  hře“); uprav testy.

- [ ] Testy, build, commit „Show players' animals on the tracking page“.

---

### Task 7: Ruční kontrola (Claude v prohlížeči)

Přes hráčskou relaci na `localhost:8000/hra/{slug}` (join nevyžaduje
heslo; použít testovací jméno „QA“ a po kontrole to uvést v reportu):
vstup výběrem zvířátka, kompas se simulovanou polohou/orientací (JS
stuby jako dříve), prstenec příchodu, úkol s obrázky (špatně/správně),
fotoúkol (jen UI bez skutečné fotky), odměna, mapa, konec hry; šířka
390 px; žádné chyby v konzoli.
