# Přestavba administrace Kompas — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Jednotný vzhled Kompas v celé administraci a obrazovky postavené
kolem mapy: přehled her jako karty, editor hry a sledování jako boční
panel + velká mapa, odkaz a QR kód pro děti.

**Architecture:** Nová sada UI komponent (`resources/js/Components/UI/`) a
čisté helpery (`format.js`, `routePreview.js`, Vitest). Přestavěný
`AuthenticatedLayout` se sloty breadcrumb/tabs a režimem `fullBleed`.
Backend jen rozšiřuje Inertia props (`GameController::index`, `::edit`).
Editor: jedna mapa `GameMap.vue` pro všechna stanoviště, formulář
stanoviště v bočním panelu, poloha ↔ mapa přes v-model. Sledování sdílí
rozvržení a styl značek.
Spec: `docs/superpowers/specs/2026-09-23-prestavba-administrace-design.md` —
**je závazný pro vzhled a chování; každý task ho má číst.**

**Tech Stack:** Laravel 13, Inertia + Vue 3 (`<script setup>`), Tailwind 3
(+ `@tailwindcss/forms`), Leaflet, Vitest, Pest, nová npm závislost
`qrcode`.

## Global Constraints

- Admin UI česky; hráčské UI (`resources/js/Pages/Play`, `Components/Play`)
  se **nemění**.
- Barvy: primární `teal-600` (hover `teal-700`), akcent `orange-500`,
  neutrální `slate`; pozadí stránek `slate-50`; karty `bg-white
  rounded-2xl shadow-sm ring-1 ring-slate-900/5`. Žádné `indigo-*` v
  administraci po dokončení (ověř `grep -rn indigo resources/js`).
- Stav hry: publikováno = zelený štítek, koncept = šedý (slate),
  archivováno = amber.
- Uživatelský text do Leafletu (tooltip/popup/divIcon html) vždy jako DOM
  text node nebo escapovaný — **nikdy** surový string s přezdívkou/názvem
  (bezpečnostní oprava z dřívějška musí zůstat).
- Veškerá stávající funkčnost zůstává: správa obrázků (existing_images,
  správný obrázek, ✕), potvrzovací dialogy mazání, drag & drop pořadí se
  zámkem při otevřeném formuláři, polling/Reverb sledování, označení fotky
  jako neplatné, `correctIndexAfterRemoval`, `locationLabel`,
  `taskTypeLabel`.
- Česká množná čísla přes `pluralize()`; relativní čas přes
  `relativeTime()`.
- Responzivita: vše použitelné na šířce 390 px (bez vodorovného
  posouvání); mapové obrazovky: mobil = mapa 45vh nahoře + panel pod ní,
  `lg:` = panel vlevo 420 px + mapa vpravo na celou výšku.
- Testy: `php artisan test` a `npm run test:unit` projdou na konci každého
  tasku; `npm run build` bez chyb.

---

### Task 1: Základ vzhledu — UI komponenty a helpery

**Files:**
- Create: `resources/js/lib/format.js`, `resources/js/lib/format.test.js`
- Create: `resources/js/lib/routePreview.js`, `resources/js/lib/routePreview.test.js`
- Create: `resources/js/Components/UI/Button.vue`, `Card.vue`, `Badge.vue`,
  `FormField.vue`, `FileDrop.vue`, `EmptyState.vue`, `SegmentedTabs.vue`,
  `Spinner.vue`
- Create: `resources/js/lib/gameStatus.js` (+ test)
- Modify: `resources/js/Components/TextInput.vue`, `Checkbox.vue` (indigo → teal)
- Modify: `resources/js/Components/Admin/ConfirmDialog.vue` (použije `UI/Button`)

**Interfaces (Produces):**
- `relativeTime(iso: string|null, nowMs: number): string` —
  `null` → `''`; < 60 s → `'právě teď'`; < 60 min → `'před {n} min'`;
  < 24 h → `'před {n} h'`; < 48 h → `'včera'`; jinak `'D. M. YYYY'`
  (např. `'3. 9. 2026'`).
- `pluralize(n: number, one: string, few: string, many: string): string`
  → `'{n} {tvar}'`; 1 → one, 2–4 → few, 0 a 5+ → many.
- `projectPoints(points: [lat,lng][], width, height, padding): {x,y}[]` —
  lng → x, lat → y (sever nahoře), zachovaný poměr stran (lng škálovat
  `cos(střední lat)`), vycentrováno; 1 bod → střed; [] → [].
- `gameStatusLabel(status): string` (koncept/publikováno/archivováno),
  `gameStatusTone(status): 'green'|'slate'|'amber'`.
- `Button` props: `variant` ('primary'|'secondary'|'ghost'|'danger', default
  primary), `size` ('sm'|'md', default md), `href` (String|null → Inertia
  Link), `type` (default 'button'), `processing` (Boolean → disabled +
  Spinner), `block` (Boolean → w-full); slot default + slot `icon`.
- `Card` props: `title` (String|null), `padded` (Boolean, default true);
  sloty default, `actions`.
- `Badge` props: `tone` (default 'slate'); slot.
- `FormField` props: `label`, `hint` (nullable), `error` (nullable), `for`;
  slot.
- `FileDrop` props: `accept` (default 'image/*'), `multiple` (Boolean),
  `label` (default 'Přetáhni sem obrázky nebo klikni pro výběr'); emits
  `files` (Array<File>); vizuální stav při dragover; skrytý nativní input;
  po výběru resetuje hodnotu inputu.
- `EmptyState` props: `title`, `text`; sloty `icon`, `actions`, default.
- `SegmentedTabs` props: `tabs: {label, href, active}[]` (Inertia Link).
- `Spinner` — malý točící se kroužek (`currentColor`).

- [ ] **Step 1: Selhávající testy helperů**

`resources/js/lib/format.test.js`:

```js
import { describe, expect, test } from 'vitest';
import { pluralize, relativeTime } from './format';

const now = Date.parse('2026-09-23T12:00:00Z');

describe('relativeTime', () => {
    test('empty for null', () => expect(relativeTime(null, now)).toBe(''));
    test('just now under a minute', () => expect(relativeTime('2026-09-23T11:59:30Z', now)).toBe('právě teď'));
    test('minutes', () => expect(relativeTime('2026-09-23T11:55:00Z', now)).toBe('před 5 min'));
    test('hours', () => expect(relativeTime('2026-09-23T09:00:00Z', now)).toBe('před 3 h'));
    test('yesterday', () => expect(relativeTime('2026-09-22T09:00:00Z', now)).toBe('včera'));
    test('older shows a date', () => expect(relativeTime('2026-09-03T09:00:00Z', now)).toBe('3. 9. 2026'));
});

describe('pluralize', () => {
    const s = (n) => pluralize(n, 'stanoviště', 'stanoviště', 'stanovišť');
    const h = (n) => pluralize(n, 'hráč', 'hráči', 'hráčů');
    test('one', () => expect(h(1)).toBe('1 hráč'));
    test('few', () => { expect(h(2)).toBe('2 hráči'); expect(h(4)).toBe('4 hráči'); });
    test('many and zero', () => { expect(h(5)).toBe('5 hráčů'); expect(h(0)).toBe('0 hráčů'); expect(s(12)).toBe('12 stanovišť'); });
});
```

`resources/js/lib/routePreview.test.js`:

```js
import { describe, expect, test } from 'vitest';
import { projectPoints } from './routePreview';

describe('projectPoints', () => {
    test('empty stays empty', () => expect(projectPoints([], 160, 90, 10)).toEqual([]));

    test('a single point is centred', () => {
        expect(projectPoints([[50, 14]], 160, 90, 10)).toEqual([{ x: 80, y: 45 }]);
    });

    test('north is up and everything stays inside the padding', () => {
        const pts = projectPoints([[50.001, 14], [50, 14.001], [49.999, 14]], 160, 90, 10);
        expect(pts[0].y).toBeLessThan(pts[2].y);
        pts.forEach(({ x, y }) => {
            expect(x).toBeGreaterThanOrEqual(10); expect(x).toBeLessThanOrEqual(150);
            expect(y).toBeGreaterThanOrEqual(10); expect(y).toBeLessThanOrEqual(80);
        });
    });

    test('keeps the aspect ratio (a tall route is not stretched wide)', () => {
        const [a, b] = projectPoints([[50, 14], [50.01, 14]], 160, 90, 10);
        expect(a.x).toBeCloseTo(b.x, 5);
    });
});
```

`resources/js/lib/gameStatus.test.js`:

```js
import { expect, test } from 'vitest';
import { gameStatusLabel, gameStatusTone } from './gameStatus';

test('labels and tones', () => {
    expect(gameStatusLabel('draft')).toBe('koncept');
    expect(gameStatusLabel('published')).toBe('publikováno');
    expect(gameStatusLabel('archived')).toBe('archivováno');
    expect(gameStatusTone('published')).toBe('green');
    expect(gameStatusTone('draft')).toBe('slate');
    expect(gameStatusTone('archived')).toBe('amber');
});
```

Run: `npm run test:unit` → FAIL (moduly neexistují).

- [ ] **Step 2: Implementace helperů**

`resources/js/lib/format.js`:

```js
export function pluralize(n, one, few, many) {
    const form = n === 1 ? one : n >= 2 && n <= 4 ? few : many;

    return `${n} ${form}`;
}

export function relativeTime(iso, nowMs) {
    if (!iso) return '';

    const seconds = Math.floor((nowMs - Date.parse(iso)) / 1000);

    if (seconds < 60) return 'právě teď';
    if (seconds < 3600) return `před ${Math.floor(seconds / 60)} min`;
    if (seconds < 86400) return `před ${Math.floor(seconds / 3600)} h`;
    if (seconds < 172800) return 'včera';

    const d = new Date(iso);

    return `${d.getUTCDate()}. ${d.getUTCMonth() + 1}. ${d.getUTCFullYear()}`;
}
```

`resources/js/lib/routePreview.js`:

```js
// Projects [lat, lng] points into an SVG box (north up), keeping the
// real-world aspect ratio so a route isn't stretched.
export function projectPoints(points, width, height, padding) {
    if (points.length === 0) return [];

    const meanLat = points.reduce((sum, [lat]) => sum + lat, 0) / points.length;
    const xScale = Math.cos((meanLat * Math.PI) / 180);
    const xs = points.map(([, lng]) => lng * xScale);
    const ys = points.map(([lat]) => lat);

    const [minX, maxX] = [Math.min(...xs), Math.max(...xs)];
    const [minY, maxY] = [Math.min(...ys), Math.max(...ys)];
    const spanX = maxX - minX;
    const spanY = maxY - minY;
    const innerW = width - 2 * padding;
    const innerH = height - 2 * padding;
    const scale = spanX === 0 && spanY === 0 ? 0 : Math.min(
        spanX === 0 ? Infinity : innerW / spanX,
        spanY === 0 ? Infinity : innerH / spanY,
    );
    const offsetX = padding + (innerW - spanX * scale) / 2;
    const offsetY = padding + (innerH - spanY * scale) / 2;

    return points.map((_, i) => ({
        x: offsetX + (xs[i] - minX) * scale,
        y: offsetY + (maxY - ys[i]) * scale,
    }));
}
```

`resources/js/lib/gameStatus.js`:

```js
const LABELS = { draft: 'koncept', published: 'publikováno', archived: 'archivováno' };
const TONES = { draft: 'slate', published: 'green', archived: 'amber' };

export const gameStatusLabel = (status) => LABELS[status] ?? status;
export const gameStatusTone = (status) => TONES[status] ?? 'slate';
```

Run: `npm run test:unit` → PASS.

- [ ] **Step 3: UI komponenty**

Implementuj komponenty podle **Interfaces** výše a spec §3. Vzhled:
- `Button`: `inline-flex items-center justify-center gap-2 rounded-xl
  font-semibold transition focus:outline-none focus-visible:ring-2
  focus-visible:ring-teal-500 focus-visible:ring-offset-2
  disabled:opacity-50 disabled:cursor-not-allowed`; `md` = `px-4 py-2.5
  text-sm`, `sm` = `px-3 py-1.5 text-sm`; primary `bg-teal-600 text-white
  hover:bg-teal-700`; secondary `bg-white text-slate-700 ring-1
  ring-slate-300 hover:bg-slate-50`; ghost `text-slate-600
  hover:bg-slate-100`; danger `bg-red-600 text-white hover:bg-red-700`.
  Při `processing` zobraz `Spinner` před textem a nastav `disabled`.
- `Card`: viz Global Constraints; hlavička (když `title` nebo slot
  `actions`) `flex items-center justify-between px-5 pt-5`, tělo `p-5`
  (bez paddingu, když `padded=false`).
- `Badge`: `inline-flex items-center rounded-full px-2.5 py-0.5 text-xs
  font-medium`; tóny green (`bg-green-100 text-green-800`), slate, amber,
  teal, orange, red analogicky.
- `FormField`: label `text-sm font-medium text-slate-700`, hint `text-xs
  text-slate-500`, chyba `text-sm text-red-600`.
- `FileDrop`: rámeček `border-2 border-dashed rounded-xl p-6 text-center
  text-sm text-slate-600`; při dragover `border-teal-500 bg-teal-50`;
  ikona obrázku (inline SVG) nad textem.
- `EmptyState`: centrovaný, ikona v kruhu `bg-teal-50 text-teal-700`,
  nadpis `text-lg font-semibold`, text `text-slate-600`.
- `SegmentedTabs`: `inline-flex rounded-xl bg-slate-100 p-1`; aktivní
  záložka `bg-white shadow-sm text-teal-800`, ostatní `text-slate-600`.
- `TextInput` a `Checkbox`: `focus:border-teal-500 focus:ring-teal-500`,
  `rounded-lg border-slate-300` (Checkbox `text-teal-600`).
- `ConfirmDialog`: tlačítka přes `Button` (secondary „Zrušit“, danger
  confirm s `processing`); rozhraní (props/emits) beze změny.

- [ ] **Step 4: Build, testy, commit**

```bash
npm run build && npm run test:unit && php artisan test
git add resources/js/lib resources/js/Components/UI resources/js/Components/TextInput.vue resources/js/Components/Checkbox.vue resources/js/Components/Admin/ConfirmDialog.vue
git commit -m "Add Kompas UI components and formatting helpers"
```

---

### Task 2: Kostra aplikace — lišta, rozvržení, profil, nová hra

**Files:**
- Modify (přepsat): `resources/js/Layouts/AuthenticatedLayout.vue`
- Modify: `resources/js/Pages/Profile/Edit.vue`, `resources/js/Pages/Profile/Partials/*.vue`
- Modify: `resources/js/Pages/Admin/Games/Create.vue`

**Interfaces:**
- Consumes: `UI/Button`, `UI/Card`, `UI/FormField`, `UI/SegmentedTabs` (Task 1).
- Produces: `AuthenticatedLayout` — prop `fullBleed` (Boolean, default
  false); sloty `header` (nadpis stránky), `actions` (tlačítka vpravo od
  nadpisu), `breadcrumb`, `tabs`, default. V režimu `fullBleed` se
  nevykresluje hlavička stránky ani kontejner; obsah dostane výšku
  `h-[calc(100dvh-var(--kompas-bar))]` (lišta nastaví CSS proměnnou podle
  toho, zda jsou vyplněné sloty breadcrumb/tabs) — nebo jednodušeji
  `flex min-h-0 flex-1` v `flex h-dvh flex-col` obalu (preferováno).

- [ ] **Step 1: Layout**

Přestav `AuthenticatedLayout.vue` podle spec §4:
- obal `flex h-dvh flex-col bg-slate-50` (režim `fullBleed`) nebo
  `min-h-screen bg-slate-50` (běžný);
- lišta `bg-white border-b border-slate-200`: vlevo logo
  (`ApplicationLogo h-8 w-8`) + „Kompas“ (`font-bold text-teal-900`, odkaz
  `admin.games.index`), navigace „Moje hry“ (aktivní = `text-teal-800`
  s tyrkysovým podtržením), vpravo uživatelské menu (Breeze `Dropdown`:
  jméno, „Profil“, „Odhlásit se“); mobil: hamburger se stejnými položkami;
- pod hlavním řádkem (jen když jsou sloty použité) řádek s `breadcrumb`
  (vlevo, `text-sm text-slate-500`, oddělovač `/`) a `tabs` (vpravo);
- běžný režim: `max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8`, nad obsahem
  `flex items-center justify-between` s `header` (h1 `text-2xl font-bold
  text-slate-900`) a `actions`;
- `fullBleed`: `<main class="flex min-h-0 flex-1">` se slotem default.

- [ ] **Step 2: Profil a Nová hra**

- `Profile/Edit.vue`: header „Profil“; tři `Card` (Údaje profilu, Změna
  hesla, Smazání účtu) místo ručních bílých boxů; tlačítka v partials přes
  `UI/Button` (Uložit = primary, Smazat účet = danger); texty beze změny.
- `Games/Create.vue`: header „Nová hra“; `Card` s `FormField` „Název hry“
  (hint „Uvidíš ho jen ty, dětem se nezobrazuje.“), `Button` primary
  „Vytvořit a pokračovat na mapu“ s `processing`, secondary „Zpět“ na
  `admin.games.index`. Chování formuláře beze změny.

- [ ] **Step 3: Build, testy, ruční kontrola, commit**

```bash
npm run build && npm run test:unit && php artisan test
grep -rn "indigo" resources/js/Layouts resources/js/Pages/Profile resources/js/Pages/Admin/Games/Create.vue
git add resources/js/Layouts/AuthenticatedLayout.vue resources/js/Pages/Profile resources/js/Pages/Admin/Games/Create.vue
git commit -m "Rebuild the admin shell and restyle profile and new game pages"
```

(Ostatní stránky zatím používají běžný režim a starý obsah — musí dál
fungovat, i když ještě nejsou přestylované.)

---

### Task 3: Přehled her — data (backend)

**Files:**
- Modify: `app/Http/Controllers/Admin/GameController.php` (`index`)
- Test: `tests/Feature/Admin/GameManagementTest.php`

**Interfaces (Produces):** každá hra v props `games` má navíc:
`locations_count` (int), `players_count` (int), `active_players_count`
(int — `finished_at IS NULL` a `last_seen_at >= now()-120 s`),
`finished_players_count` (int), `last_activity_at` (ISO 8601 | null — max
`last_seen_at` hráčů), `route` (pole `[float lat, float lng]` v pořadí
stanovišť `order, id`). Počet dotazů nesmí růst s počtem her.

- [ ] **Step 1: Selhávající testy**

Na konec `tests/Feature/Admin/GameManagementTest.php` (importy
`App\Models\PlayerSession` doplň, pokud chybí):

```php
test('the games list includes player stats, last activity and the route', function () {
    $this->travelTo(now()->setTime(12, 0));
    $user = User::factory()->create();
    $game = Game::factory()->create(['owner_id' => $user->id]);
    Location::factory()->create(['game_id' => $game->id, 'order' => 2, 'lat' => 50.2, 'lng' => 14.2]);
    Location::factory()->create(['game_id' => $game->id, 'order' => 1, 'lat' => 50.1, 'lng' => 14.1]);
    PlayerSession::factory()->create(['game_id' => $game->id, 'last_seen_at' => now()->subSeconds(30)]);
    PlayerSession::factory()->create(['game_id' => $game->id, 'last_seen_at' => now()->subHour()]);
    PlayerSession::factory()->create(['game_id' => $game->id, 'last_seen_at' => now()->subMinutes(10), 'finished_at' => now()->subMinutes(10)]);

    $this->actingAs($user)->get(route('admin.games.index'))
        ->assertInertia(fn ($page) => $page
            ->where('games.0.locations_count', 2)
            ->where('games.0.players_count', 3)
            ->where('games.0.active_players_count', 1)
            ->where('games.0.finished_players_count', 1)
            ->where('games.0.last_activity_at', fn ($iso) => str_starts_with($iso, now()->subSeconds(30)->format('Y-m-d\TH:i:s')))
            ->where('games.0.route', [[50.1, 14.1], [50.2, 14.2]]));
});

test('a game nobody played has no last activity and an empty route', function () {
    $user = User::factory()->create();
    Game::factory()->create(['owner_id' => $user->id]);

    $this->actingAs($user)->get(route('admin.games.index'))
        ->assertInertia(fn ($page) => $page
            ->where('games.0.players_count', 0)
            ->where('games.0.last_activity_at', null)
            ->where('games.0.route', []));
});

test('the games list query count does not grow with the number of games', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $make = function () use ($user) {
        $game = Game::factory()->create(['owner_id' => $user->id]);
        Location::factory()->count(2)->create(['game_id' => $game->id]);
        PlayerSession::factory()->create(['game_id' => $game->id]);
    };
    $make();

    \DB::enableQueryLog();
    \DB::flushQueryLog();
    $this->get(route('admin.games.index'))->assertOk();
    $one = count(\DB::getQueryLog());

    $make(); $make(); $make();
    \DB::flushQueryLog();
    $this->get(route('admin.games.index'))->assertOk();
    $four = count(\DB::getQueryLog());
    \DB::disableQueryLog();

    expect($four)->toBe($one);
});
```

Run: `php artisan test --filter=GameManagementTest` → FAIL.

- [ ] **Step 2: Implementace**

`GameController::index` (import `Illuminate\Support\Facades\DB` není
nutný, použij Eloquent):

```php
    public function index(Request $request)
    {
        $activeSince = now()->subSeconds(120);

        $games = $request->user()->games()
            ->withCount([
                'locations',
                'playerSessions as players_count',
                'playerSessions as active_players_count' => fn ($q) => $q
                    ->whereNull('finished_at')->where('last_seen_at', '>=', $activeSince),
                'playerSessions as finished_players_count' => fn ($q) => $q->whereNotNull('finished_at'),
            ])
            ->withMax('playerSessions as last_activity_at', 'last_seen_at')
            ->with(['locations:id,game_id,lat,lng,order'])
            ->latest()
            ->get()
            ->map(fn (Game $game) => [
                ...$game->only(['id', 'name', 'slug', 'status', 'created_at', 'updated_at']),
                'locations_count' => $game->locations_count,
                'players_count' => $game->players_count,
                'active_players_count' => $game->active_players_count,
                'finished_players_count' => $game->finished_players_count,
                'last_activity_at' => $game->last_activity_at
                    ? \Illuminate\Support\Carbon::parse($game->last_activity_at)->toIso8601String()
                    : null,
                'route' => $game->locations->map(fn ($l) => [(float) $l->lat, (float) $l->lng])->values()->all(),
            ]);

        return Inertia::render('Admin/Games/Index', ['games' => $games]);
    }
```

(`Game::locations()` řadí podle `order`, `id`, takže eager load zachová
pořadí. `status` se serializuje jako hodnota enumu přes `only()`/Inertia —
ověř, že test `a dozor only sees their own games` a existující
`locations_count` test dál procházejí.)

Run: `php artisan test` → PASS.

- [ ] **Step 3: Commit**

```bash
git add app/Http/Controllers/Admin/GameController.php tests/Feature/Admin/GameManagementTest.php
git commit -m "Expose player stats, last activity and route on the games list"
```

---

### Task 4: Přehled her — obrazovka

**Files:**
- Create: `resources/js/Components/Admin/RoutePreview.vue`
- Create: `resources/js/Components/Admin/GameCard.vue`
- Modify (přepsat): `resources/js/Pages/Admin/Games/Index.vue`

**Interfaces:**
- Consumes: props z Task 3; `projectPoints`, `pluralize`, `relativeTime`,
  `gameStatusLabel`, `gameStatusTone`; `UI/*`; `ConfirmDialog`.
- Produces: `RoutePreview` props `route: [lat,lng][]` (SVG `viewBox="0 0
  160 90"`, jemný tyrkysový podklad s 2–3 vrstevnicemi, přerušovaná
  `teal-600` čára mezi body v pořadí, kroužky s čísly `1..n` (první bod
  oranžový), prázdné = text „Zatím žádná stanoviště“);
  `GameCard` props `game`, `now` (ms); emits `toggle-publish`, `delete`.

- [ ] **Step 1: Komponenty a stránka**

Podle spec §5:
- `Index.vue`: layout běžný režim, header „Moje hry“, actions `Button`
  primary „Nová hra“ (`href` `admin.games.create`, ikona +); mřížka
  `grid gap-6 sm:grid-cols-2 lg:grid-cols-3`; `now` ref s tikáním po 30 s
  (vyčistit v `onBeforeUnmount`); publikace/skrytí přes `router.patch`
  (`preserveScroll`), mazání přes `ConfirmDialog` se stávající logikou
  (`deleting`, zpráva `Smazat hru „{name}“?` s escapy `„`/`“`,
  stávající varování o fotkách).
- `GameCard`: `Card padded=false`; nahoře `RoutePreview` (odkaz do
  editoru); tělo: název (odkaz `admin.games.edit`) + `Badge`; řádek čísel
  (vynechat nulové hráčské položky; stanoviště vždy): `pluralize(n,
  'stanoviště','stanoviště','stanovišť')`, `pluralize(n,'hráč','hráči',
  'hráčů')`, „{n} právě hraje/hrají/hraje“ → použij `pluralize(n, 'právě
  hraje', 'právě hrají', 'právě hraje')`, `pluralize(n,'dokončil',
  'dokončili','dokončilo')`; řádek „Naposledy aktivní {relativeTime}“ nebo
  „Zatím nikdo nehrál“; patička: `Button sm` Upravit, `Button sm
  secondary` Sledovat (`admin.games.tracking`), vpravo menu „⋯“
  (Breeze `Dropdown`) s „Publikovat“/„Skrýt“ (Publikovat zakázané bez
  stanovišť s title „Nejdřív přidej stanoviště“) a „Smazat“ (červeně).
- Prázdný stav: `EmptyState` (ikona = `ApplicationLogo`), nadpis „Vytvoř
  svou první hru“, 3 očíslované kroky („Pojmenuj hru“, „Rozmísti
  stanoviště na mapě“, „Pošli dětem odkaz nebo QR kód“), `Button` „Nová
  hra“.

- [ ] **Step 2: Build, testy, commit**

```bash
npm run build && npm run test:unit && php artisan test
git add resources/js/Components/Admin/RoutePreview.vue resources/js/Components/Admin/GameCard.vue resources/js/Pages/Admin/Games/Index.vue
git commit -m "Redesign the games overview as cards with route preview and stats"
```

---

### Task 5: Editor — data a mapa (`GameMap`)

**Files:**
- Modify: `app/Http/Controllers/Admin/GameController.php` (`edit`)
- Test: `tests/Feature/Admin/GameManagementTest.php`
- Create: `resources/js/Components/Admin/GameMap.vue`
- Create: `resources/js/lib/mapMarkers.js`

**Interfaces:**
- Produces (backend): `edit` props navíc `play_url` (String, absolutní URL
  `route('play.show', $game->slug)`), `players_count` (int).
- Produces (`mapMarkers.js`): `numberedIcon(L, number, { selected })` →
  `L.divIcon` s kulatou značkou (průměr 30 px, `teal-600` / vybraná
  `orange-500`, bílé číslo, bílý okraj, stín); **HTML obsahuje jen číslo
  (integer)** — nikdy text od uživatele.
- Produces (`GameMap.vue`): props `locations` (pole `{id, lat, lng,
  arrival_radius_m}` v pořadí), `selectedId` (Number|null), `draft`
  (`{lat, lng, arrival_radius_m}`|null — upravované/nové stanoviště),
  `mode` ('view'|'edit'|'place'), `showRadii` (Boolean, default true);
  emits `select` (id), `update:draft` ({lat, lng}); metoda přes
  `defineExpose`: `fitAll()`, `flyTo(lat, lng)`.
  Chování: značky `numberedIcon` (číslo = pozice 1-based); přerušovaná
  polyline v pořadí (`dashArray '6 8'`, teal); kruhy poloměru (teal,
  průhledné); klik na značku → `select`; v režimu `edit`/`place`: pozice
  `draft` se vykreslí oranžovou značkou (přetahovatelnou) + kruh podle
  `draft.arrival_radius_m`, klik do mapy → `update:draft` s novými
  souřadnicemi, `dragend` značky → `update:draft`; při `edit` se původní
  značka vybraného stanoviště skryje (nahradí ji draft). Úvodní pohled
  `fitBounds` všech bodů (padding 60), bez bodů `navigator.geolocation`
  jednorázově nebo střed ČR `[49.8, 15.5]` zoom 7. Watch props → překreslit
  vrstvy (jedna `L.layerGroup` pro stanoviště, jedna pro draft).
  `onBeforeUnmount` → `map.remove()`. Mapa má `class="h-full w-full"`.

- [ ] **Step 1: Selhávající test backendu**

```php
test('the edit page provides the play url and player count', function () {
    $user = User::factory()->create();
    $game = Game::factory()->create(['owner_id' => $user->id, 'slug' => 'lesni-hra']);
    PlayerSession::factory()->count(2)->create(['game_id' => $game->id]);

    $this->actingAs($user)->get(route('admin.games.edit', $game))
        ->assertInertia(fn ($page) => $page
            ->where('play_url', route('play.show', 'lesni-hra'))
            ->where('players_count', 2));
});
```

Run → FAIL. Implementace v `GameController::edit` — do props přidej:

```php
            'play_url' => route('play.show', $game->slug),
            'players_count' => $game->playerSessions()->count(),
```

Run `php artisan test` → PASS.

- [ ] **Step 2: `mapMarkers.js` a `GameMap.vue`**

`resources/js/lib/mapMarkers.js`:

```js
// Round numbered marker. The HTML only ever contains an integer — never
// user-provided text (Leaflet would render it as HTML).
export function numberedIcon(L, number, { selected = false } = {}) {
    const n = Number.parseInt(number, 10);
    const bg = selected ? '#f97316' : '#0d9488';

    return L.divIcon({
        className: '',
        html: `<div style="background:${bg}" class="flex h-[30px] w-[30px] items-center justify-center rounded-full border-2 border-white text-sm font-bold text-white shadow-md">${n}</div>`,
        iconSize: [30, 30],
        iconAnchor: [15, 15],
    });
}
```

`GameMap.vue` implementuj podle Interfaces výše (vzor Leafletu:
`resources/js/Components/Admin/TrackingMap.vue`).

- [ ] **Step 3: Build, testy, commit**

```bash
npm run build && npm run test:unit && php artisan test
git add app/Http/Controllers/Admin/GameController.php tests/Feature/Admin/GameManagementTest.php resources/js/lib/mapMarkers.js resources/js/Components/Admin/GameMap.vue
git commit -m "Add play url to the editor and a shared GameMap component"
```

---

### Task 6: Editor hry — obrazovka

**Files:**
- Modify (přepsat): `resources/js/Pages/Admin/Games/Edit.vue`
- Create: `resources/js/Components/Admin/GameHeader.vue`
- Create: `resources/js/Components/Admin/SharePanel.vue`
- Create: `resources/js/Components/Admin/GameSettings.vue`
- Create: `resources/js/Components/Admin/TaskTypePicker.vue`
- Modify (přepsat): `resources/js/Components/Admin/LocationList.vue`
- Modify (přepsat): `resources/js/Components/Admin/LocationForm.vue`
- Modify: `resources/js/Components/Admin/TaskContentFields.vue` (FileDrop, vzhled)
- Delete: `resources/js/Components/Admin/MapPicker.vue` (už nepoužitý)
- Modify: `package.json`, `package-lock.json` (`npm install qrcode`)

**Interfaces:**
- Consumes: `play_url`, `players_count`, `locations` (tvar z admin UX
  plánu: `{id, order, name, lat, lng, arrival_radius_m, task_type,
  correct_index, images:[{path,url}], reference_image:{path,url}|null}`),
  `game`; `GameMap`, `UI/*`, `ConfirmDialog`, `locationLabel`,
  `taskTypeLabel`, `TASK_TYPE_LABELS`, `correctIndexAfterRemoval`.
- Produces: `LocationForm` — props `game`, `location` (null = nové),
  `draft` (v-model `{lat, lng, arrival_radius_m}` sdílený s mapou); emits
  `saved`, `cancel`, `delete`. `LocationList` — props `game`, `locations`,
  `selectedId`, `locked` (Boolean — zákaz přetahování); emits `select`,
  `add`. `TaskTypePicker` — v-model task type.

- [ ] **Step 1: Instalace QR**

```bash
npm install qrcode
```

- [ ] **Step 2: Stav stránky `Edit.vue`**

Stav v `Edit.vue`: `panel` ('list'|'edit'|'add'), `selectedId`, `draft`
(`{lat, lng, arrival_radius_m}`|null). Přechody:
- klik na stanoviště (v seznamu nebo na mapě) → `panel='edit'`,
  `selectedId=id`, `draft={lat,lng,arrival_radius_m}` z lokace, mapa
  `flyTo`;
- „+ Přidat stanoviště“ → `panel='add'`, `selectedId=null`,
  `draft={lat:null,lng:null,arrival_radius_m:15}`; mapa režim `place`
  (kurzor crosshair, nápověda „Klikni do mapy, kam stanoviště umístit“);
- uložení/zrušení → `panel='list'`, `draft=null`, `selectedId=null`;
- smazání z formuláře → `ConfirmDialog` (stávající text a varování pro
  publikovanou hru) → `router.delete` → seznam.
Mapa: `mode` = `panel==='list' ? 'view' : panel==='add' ? 'place' :
'edit'`. Při změně props `locations` (po uložení) zachovej panel dle
přechodů výše.

- [ ] **Step 3: Rozvržení a panely**

Podle spec §6: `AuthenticatedLayout fullBleed`, breadcrumb „Moje hry /
{name}“, tabs Editor (aktivní) | Sledování; `<div class="flex min-h-0
flex-1 flex-col lg:flex-row">`: panel `lg:w-[420px] shrink-0 overflow-y-auto
border-r border-slate-200 bg-white` (na mobilu pod mapou), mapa `h-[45vh]
lg:h-auto lg:flex-1` (na mobilu `order-first`).
- `GameHeader`: název `text-xl font-bold`, `Badge` stavu, přepínač
  (switch) „Publikováno“ → `admin.games.publish`/`unpublish` (zakázaný
  bez stanovišť, text nápovědy).
- `SharePanel` (props `playUrl`, `slug`, `published`): když publikováno —
  read-only input s URL + `Button sm` „Kopírovat“
  (`navigator.clipboard.writeText`, 2 s „Zkopírováno ✓“), QR jako SVG
  (`QRCode.toString(url, { type: 'svg', margin: 1, color: { dark:
  '#134e4a' } })` vložené přes `v-html` — obsah je generovaný knihovnou z
  naší URL, ne uživatelský text), `Button sm secondary` „Stáhnout QR“
  (`QRCode.toDataURL(url, { width: 1024, margin: 2 })` → `<a download
  ="kompas-{slug}.png">`); jinak šedý text „Po publikování tu bude odkaz a
  QR kód pro děti.“
- `LocationList` (sidebar varianta): řádky `flex items-center gap-3
  rounded-xl px-3 py-2.5 hover:bg-slate-50`, úchyt (⋮⋮ ikona, jen když
  není `locked`), kulaté číslo (teal; vybrané orange), název
  (`locationLabel`), pod ním `taskTypeLabel` + ikona typu; drag & drop
  pořadí se stávajícími pojistkami (drop jen když není `locked`, reset
  `draggedId`, `@drop.prevent`); na konci `Button secondary block` „+
  Přidat stanoviště“. Prázdné: „Zatím žádná stanoviště. Přidej první a
  umísti ho kliknutím do mapy.“
- `GameSettings` (sbalitelné, výchozí zavřené): název, adresa (slug) s
  náhledem `…/hra/{slug}`, Uložit (`admin.games.update`, stávající
  validace/chyby); „Nebezpečná zóna“: `Button danger sm` „Smazat hru“ →
  `ConfirmDialog` (zpráva `Smazat hru „{name}“?` s `„`/`“`;
  varování: když `players_count > 0` → „Smažou se i všechna stanoviště,
  {pluralize(players_count, 'hráč', 'hráči', 'hráčů')} a jejich fotky.
  Tato akce je nevratná.“, jinak „Smažou se i všechna stanoviště. Tato
  akce je nevratná.“) → `router.delete(route('admin.games.destroy'))`;
  redirect na Moje hry řeší backend.
- `LocationForm` (panel detail): „← Zpět na seznam“, nadpis („Nové
  stanoviště“ / `Upravuješ: {locationLabel}`); blok Pozice (nápověda,
  `Button sm secondary` „Použít moji polohu“ → geolocation → `draft`,
  souřadnice malým šedým textem, chyba lat/lng); `FormField` Název;
  `TaskTypePicker` (4 karty 2×2, ikona + název + krátký popis: Jen dojít
  „Stačí dojít na místo“, Vybrat obrázek „Dítě vybere správný z 3–6
  obrázků“, Vyfotit něco „Dítě vyfotí, co ukazuje obrázek“, Vyfotit se
  při činnosti „Selfie podle předlohy“; vybraná karta `ring-2
  ring-teal-500 bg-teal-50`); Poloměr: `input type=range` 5–100 + číslo
  „{n} m“ (v-model `draft.arrival_radius_m`); `TaskContentFields`;
  sticky patička `sticky bottom-0 bg-white border-t p-4`: `Button`
  „Uložit stanoviště“ (`processing`), `Button ghost` „Zrušit“, u úpravy
  vpravo `Button ghost` červený „Smazat“ (emit `delete`). Odeslání:
  stávající logika (`forceFormData`, `_method: put`, pole `name`, `lat`,
  `lng` z `draft`, `arrival_radius_m` z `draft`, `task_type`,
  `existing_images`, `images`, `correct_index`, `reference_image`).
- `TaskContentFields`: logika beze změny; nativní inputy nahraď
  `FileDrop` (pick_image: `multiple`, emit připojí soubory; foto: jeden
  soubor); dlaždice obrázků `h-24 w-24 rounded-xl`, vybraný správný
  `ring-2 ring-green-500` + štítek „správný“; odebrat = kulaté tlačítko ✕.

- [ ] **Step 4: Úklid, build, testy, commit**

Ověř, že `MapPicker.vue` už nikde není importovaný, pak ho smaž.

```bash
grep -rn "MapPicker" resources/js || git rm resources/js/Components/Admin/MapPicker.vue
npm run build && npm run test:unit && php artisan test
git add -A resources/js package.json package-lock.json
git commit -m "Rebuild the game editor around a full-size map with share link and QR"
```

---

### Task 7: Sledování — obrazovka

**Files:**
- Modify (přepsat šablonu): `resources/js/Pages/Admin/Games/Tracking.vue`
- Modify: `resources/js/Components/Admin/TrackingMap.vue`
- Modify: `resources/js/Components/Admin/PlayerDetail.vue`
- Create: `resources/js/Components/Admin/PlayerCard.vue`
- Create: `resources/js/lib/playerOrder.js` (+ test)

**Interfaces:**
- Consumes: `isStale`, `progressLabel`, `formatTime`, `applyPosition`,
  `applyProgress`, `diffProgress`, `progressMessage`, `getEcho`,
  `relativeTime`, `pluralize`, `numberedIcon`.
- Produces: `sortPlayers(players, nowMs)` — pořadí: aktivní (ne stale, ne
  dokončení) podle `current_location_index` sestupně, pak stale podle
  `last_seen_at` sestupně, nakonec dokončení podle `finished_at`
  sestupně; vrací nové pole.

- [ ] **Step 1: `playerOrder.js` TDD**

`resources/js/lib/playerOrder.test.js`:

```js
import { expect, test } from 'vitest';
import { sortPlayers } from './playerOrder';

const now = Date.parse('2026-09-23T12:00:00Z');
const p = (id, o) => ({ id, current_location_index: 0, locations_count: 3, finished_at: null, last_seen_at: '2026-09-23T11:59:30Z', ...o });

test('active by progress, then stale by recency, then finished', () => {
    const players = [
        p(1, { finished_at: '2026-09-23T11:00:00Z' }),
        p(2, { last_seen_at: '2026-09-23T11:00:00Z' }),
        p(3, { current_location_index: 1 }),
        p(4, { current_location_index: 2 }),
        p(5, { last_seen_at: '2026-09-23T11:30:00Z' }),
    ];

    expect(sortPlayers(players, now).map((x) => x.id)).toEqual([4, 3, 5, 2, 1]);
    expect(players.map((x) => x.id)).toEqual([1, 2, 3, 4, 5]);
});
```

`resources/js/lib/playerOrder.js`:

```js
import { isStale } from './tracking';

const group = (player, nowMs) => (player.finished_at ? 2 : isStale(player, nowMs) ? 1 : 0);

export function sortPlayers(players, nowMs) {
    return [...players].sort((a, b) => {
        const ga = group(a, nowMs);
        const gb = group(b, nowMs);
        if (ga !== gb) return ga - gb;
        if (ga === 0) return b.current_location_index - a.current_location_index;
        if (ga === 1) return Date.parse(b.last_seen_at) - Date.parse(a.last_seen_at);

        return Date.parse(b.finished_at) - Date.parse(a.finished_at);
    });
}
```

Run `npm run test:unit` → PASS.

- [ ] **Step 2: Obrazovka**

Podle spec §7; **skriptová logika (Reverb/polling, toasty, detailVersion)
zůstává** — měníš šablonu a přidáváš jen `connectionState` computed
(`'live'` když je echo, `'polling'` když polling bez chyby,
`'error'` když `pollFailed`):
- `AuthenticatedLayout fullBleed`, breadcrumb „Moje hry / {name}“, tabs
  Editor | Sledování (aktivní);
- panel: řádek stavu spojení (zelená pulzující tečka „Živě“ / „Obnovuje
  se každých 5 s“ / oranžová „Spojení přerušeno — zkouším znovu…“),
  statistiky (3 malé dlaždice: Hráči, Právě hrají, Dokončili), seznam
  `PlayerCard` seřazený `sortPlayers`; klik → detail v panelu („← Zpět na
  hráče“ + `PlayerDetail`); prázdný stav „Zatím se nikdo nepřipojil“ +
  text „Pošli dětem odkaz z editoru.“ + `Button sm secondary` „Otevřít
  editor“;
- `PlayerCard` (props `player`, `now`, `selected`): barevná tečka
  (`player.color`, šedá když stale), přezdívka (text interpolace), lišta
  průběhu (`current_location_index / locations_count`, dokončení = plná
  zelená + „✓ dokončil(a)“), „naposledy {relativeTime}“, štítek „bez
  signálu“ (amber) když stale;
- `PlayerDetail`: přestylovat do panelu (časová osa stanovišť: kulaté
  číslo + název/typ + časy, náhled fotky `rounded-xl`, tlačítko označení
  jako `Button sm ghost`); logika beze změny;
- `TrackingMap`: značky stanovišť přes `numberedIcon` + přerušovaná trasa
  (jako `GameMap`), tooltip názvu stanoviště dál jako text node; hráči
  beze změny logiky (text node tooltipy!);
- toasty: bílá karta `rounded-xl shadow-lg ring-1` s levým proužkem
  `border-l-4 border-teal-500`.

- [ ] **Step 3: Build, testy, commit**

```bash
npm run build && npm run test:unit && php artisan test
grep -rn "indigo" resources/js/Pages/Admin resources/js/Components/Admin resources/js/Layouts
git add resources/js/lib/playerOrder.js resources/js/lib/playerOrder.test.js resources/js/Pages/Admin/Games/Tracking.vue resources/js/Components/Admin
git commit -m "Redesign live tracking with the shared map layout and player cards"
```

---

### Task 8: Ruční kontrola v prohlížeči

**Files:** žádné (QA; opravy + testy případně)

Přes přihlášenou relaci dozora na `http://localhost:8000` (Claude nesmí
zadávat hesla):
- [ ] Moje hry: karty, náhled trasy, čísla, menu ⋯, mazání (dialog → Zrušit).
- [ ] Editor: klik na stanoviště v seznamu i na mapě, přetažení značky,
  posuvník poloměru mění kruh, přidání kliknutím do mapy (neukládat, nebo
  uložit a zase smazat testovací stanoviště), typové karty, FileDrop,
  sdílení (Kopírovat, QR, Stáhnout), nastavení hry (sbalit/rozbalit).
- [ ] Sledování: stav spojení, statistiky, karty hráčů, detail, mapa.
- [ ] Mobil (390 px): editor a sledování (mapa nahoře, panel pod ní).
- [ ] `grep -rn indigo resources/js` → žádný výskyt v administraci.
