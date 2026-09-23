# Přestavba administrace Kompas — design

Datum: 2026-09-23
Stav: rozsah „velká přestavba“ schválen uživatelem; varianty vybral Claude
(uživatel: „vyber sám nejlepší variantu, žádné náhledy“).

## 1. Cíl

Administrace dozora dnes vypadá jako výchozí Laravel Breeze (fialová
tlačítka, šedé karty, textové odkazy) a nesedí k nové identitě Kompas
(tyrkysová + oranžová, přírodní styl — viz přihlašovací stránky). Editor hry
je dlouhý formulář se samostatnou malou mapou u každého stanoviště; chybí
sdílení odkazu pro děti. Cíl: jednotný vzhled a obrazovky postavené kolem
mapy, protože hra je o místech v terénu.

## 2. Klíčová rozhodnutí (a proč)

- **Mapa jako hlavní plocha editoru i sledování** (místo formulářů pod
  sebou): dozor přemýšlí v místech, ne v seznamu; jedna velká mapa
  ukazuje všechna stanoviště, trasu a poloměry najednou.
- **Boční panel (sidebar) + mapa**: na počítači panel vlevo (≈ 420 px) a
  mapa vpravo přes zbytek obrazovky; na telefonu mapa nahoře (45 vh) a
  panel pod ní. Stejné rozvržení pro editor i sledování, ať se dozor
  nemusí učit dvě obrazovky.
- **Formulář stanoviště v panelu místo seznamu** (panel přepne do detailu
  se „← Zpět na seznam“), ne modal — mapa zůstává vidět a pozice se
  nastavuje přímo v ní.
- **Odkaz a QR kód pro děti přímo v editoru**, s tlačítky Kopírovat a
  Stáhnout QR (PNG k tisku) — dnes musí dozor URL skládat ručně.
- **Přehled her jako mřížka karet** s náhledem trasy (SVG z pozic
  stanovišť, bez mapových dlaždic — rychlé) a čísly (stanoviště, hráči,
  právě hraje, dokončilo, poslední aktivita).
- **Vlastní malá sada UI komponent** (`resources/js/Components/UI/`), žádná
  nová UI knihovna; jediná nová závislost je `qrcode` (npm, MIT) pro QR.
- Barvy: primární `teal` (600/700), akcent `orange-500` (sever na logu,
  vybrané stanoviště), neutrální `slate`. Font Figtree zůstává.

## 3. Základ vzhledu (design system)

Komponenty v `resources/js/Components/UI/`:

- `Button.vue` — varianty `primary` (teal-600, bílé písmo), `secondary`
  (bílé pozadí, slate rámeček), `ghost` (bez pozadí), `danger` (red-600);
  velikosti `sm` / `md`; `href` → vykreslí Inertia `Link`, jinak `button`;
  `processing` → disabled + spinner; ikona přes slot `icon`.
- `Card.vue` — bílá karta `rounded-2xl`, jemný stín a ring; volitelný
  `title` a slot `actions` v hlavičce.
- `Badge.vue` — štítek s tónem (`green`, `slate`, `amber`, `teal`,
  `orange`, `red`); stav hry přes helper (publikováno = green, koncept =
  slate, archivováno = amber).
- `FormField.vue` — label, volitelná nápověda, slot pro vstup, chyba.
- `FileDrop.vue` — česká plocha „Přetáhni sem obrázky nebo klikni pro
  výběr“, drag & drop, `multiple`, `accept`, emituje `files` (pole
  `File`); nahrazuje nativní anglické „Choose File“.
- `EmptyState.vue` — ikona, nadpis, text, slot pro akci.
- `SegmentedTabs.vue` — přepínač záložek (Editor / Sledování).
- Úprava existujících: `TextInput` a `Checkbox` se zaměřením v teal místo
  indigo; `ConfirmDialog` přes nové `Button`; `Modal` beze změny.

Čisté helpery (Vitest): `resources/js/lib/format.js` —
`relativeTime(iso, nowMs)` („právě teď“, „před 5 min“, „před 2 h“,
„včera“, datum), `pluralize(n, one, few, many)` (česká množná čísla:
1 stanoviště / 2–4 … / 5+ …); `resources/js/lib/routePreview.js` —
`projectPoints(points, width, height, padding)` → body v SVG souřadnicích
se zachovaným poměrem stran.

## 4. Kostra aplikace (shell)

`AuthenticatedLayout.vue` přestavěný:

- Horní lišta (bílá, spodní linka): logo + „Kompas“ (odkaz na Moje hry),
  navigace „Moje hry“, vpravo uživatelské menu (jméno, Profil, Odhlásit
  se); na mobilu hamburger.
- Volitelný slot `breadcrumb` (např. „Moje hry / Lesní hra“) a slot
  `tabs` (Editor | Sledování) přímo v liště pod hlavním řádkem.
- Prop `fullBleed` (Boolean): obsah přes celou šířku a výšku pod lištou
  (`h-[calc(100vh-…)]`) pro mapové obrazovky; bez něj centrovaný obsah
  `max-w-6xl` s hlavičkou stránky (slot `header` + slot `actions`).
- Pozadí stránky `slate-50`.
- Profil (`Profile/Edit.vue`) a Nová hra (`Games/Create.vue`) jen
  přestylované do nových komponent.

## 5. Přehled her (`Games/Index.vue`)

- Hlavička „Moje hry“ + primární tlačítko „Nová hra“.
- Mřížka karet (1 / 2 / 3 sloupce podle šířky). Karta hry:
  - náhled trasy: SVG 16:9 na jemném tyrkysovém pozadí s vrstevnicemi;
    stanoviště jako očíslované tečky spojené přerušovanou čarou v pořadí;
    bez stanovišť text „Zatím žádná stanoviště“;
  - název (odkaz do editoru), štítek stavu;
  - čísla: „3 stanoviště · 12 hráčů · 2 právě hrají · 5 dokončilo“ (česká
    množná čísla, nulové položky vynechat kromě stanovišť);
  - „Naposledy aktivní před 5 min“ (nebo „Zatím nikdo nehrál“);
  - akce: Upravit (primary sm), Sledovat (secondary sm), menu „⋯“ s
    Publikovat/Skrýt a Smazat (Smazat přes ConfirmDialog).
- Prázdný stav (žádná hra): EmptyState s logem, „Vytvoř svou první hru“ a
  3 kroky (1 Pojmenuj hru · 2 Rozmísti stanoviště na mapě · 3 Pošli dětem
  odkaz) + tlačítko Nová hra.
- Backend `GameController::index` přidá ke každé hře: `locations_count`,
  `players_count`, `active_players_count` (nedokončené, `last_seen_at` v
  posledních 120 s), `finished_players_count`, `last_activity_at` (ISO |
  null; max `last_seen_at`), `route` (pole `[lat, lng]` v pořadí
  stanovišť). Bez N+1 (withCount / subquery / jeden dotaz na lokace).

## 6. Editor hry (`Games/Edit.vue`)

Rozvržení `fullBleed`: panel vlevo, mapa vpravo. Lišta: breadcrumb „Moje
hry / {název}“, záložky Editor | Sledování.

**Panel — hlavní pohled:**
1. Hlavička hry: název (velký), štítek stavu, přepínač
   „Publikováno“ (switch; publikace bez stanovišť je zakázaná s nápovědou
   „Nejdřív přidej stanoviště“).
2. Karta „Pro děti“ (jen když je hra publikovaná; jinak text „Po
   publikování tu bude odkaz a QR kód pro děti“): odkaz
   `route('play.show', slug)` v needitovatelném poli + „Kopírovat“
   (potvrzení „Zkopírováno“), QR kód (SVG, 160 px) + „Stáhnout QR“ (PNG
   1024 px, název souboru `kompas-{slug}.png`).
3. Seznam stanovišť: řádky s kulatým číslem (teal; vybrané oranžové),
   názvem, ikonou a českým typem; přetahování pro změnu pořadí (úchyt
   vlevo); klik = otevřít detail; tlačítko „+ Přidat stanoviště“.
4. Sbalitelná sekce „Nastavení hry“: název, adresa (slug) s náhledem
   výsledné URL, Uložit; „Nebezpečná zóna“ se Smazat hru (ConfirmDialog
   s počtem hráčů).

**Panel — detail stanoviště (přidání i úprava):**
- „← Zpět na seznam“, nadpis „Nové stanoviště“ / „Upravuješ: #3 …“.
- Pozice: text „Klikni do mapy nebo přetáhni značku“ + tlačítko „Použít
  moji polohu“; souřadnice jen malým šedým textem.
- Název (nepovinné).
- Typ úkolu jako 4 velké volitelné karty s ikonou a popisem (Jen dojít /
  Vybrat obrázek / Vyfotit něco / Vyfotit se při činnosti).
- Poloměr: posuvník 5–100 m + číslo; kruh na mapě se mění živě.
- Obsah úkolu (`TaskContentFields`): stávající logika (dlaždice, správný,
  ✕), ale `FileDrop` místo nativního inputu a hezčí dlaždice.
- Spodní lišta panelu (sticky): Uložit stanoviště (primary), Zrušit,
  vpravo Smazat (ghost/danger, ConfirmDialog).

**Mapa (`GameMap.vue`, Leaflet):**
- Všechna stanoviště jako kulaté očíslované značky (divIcon), trasa v
  pořadí přerušovanou teal čarou, kolem každého kruh `arrival_radius_m`.
- Vybrané stanoviště oranžové; při úpravě je jeho značka přetahovatelná a
  klik do mapy ji přesune; při přidávání klik do mapy umístí novou značku.
- Poloha a poloměr ve formuláři ↔ mapa obousměrně (v-model).
- Úvodní zobrazení: fitBounds na všechna stanoviště; bez stanovišť
  poloha zařízení nebo střed ČR.
- Nahrazuje `MapPicker.vue` (smazat, pokud nebude použit).

**Backend `GameController::edit`** přidá `play_url` a `players_count`.

## 7. Sledování (`Games/Tracking.vue`)

Rozvržení `fullBleed` shodné s editorem; záložka Sledování aktivní.

- Panel: stav spojení („Živě“ se zelenou tečkou přes Reverb / „Obnovuje
  se každých 5 s“ při pollingu / „Spojení přerušeno“ oranžově), řádek
  statistik (Hráči · Právě hrají · Dokončili), seznam karet hráčů:
  barevná tečka, přezdívka, průběh jako lišta `current/total` (dokončení =
  zelená fajfka), „naposledy před 2 min“ (výpadek = šedě + štítek
  „bez signálu“); řazení: aktivní, pak podle průběhu, dokončení na konci.
- Klik na hráče: detail v panelu („← Zpět na hráče“) — stávající
  `PlayerDetail` přestylovaný (časová osa stanovišť s časy, náhledy fotek,
  označení neplatné).
- Mapa: stanoviště stejně jako v editoru (čísla, trasa, bez poloměrů),
  hráči jako barevné kruhy s popiskem (text node — bezpečné), klik na
  hráče vybere jeho detail.
- Toasty vpravo dole přestylované (bílá karta s barevným proužkem).
- Prázdný stav: „Zatím se nikdo nepřipojil“ + odkaz/QR pro děti.

## 8. Mimo rozsah

- Hráčské UI (dítě) — beze změny.
- Nové funkce mimo popsané (archivace, duplikace hry, statistiky v čase).
- Tmavý režim.

## 9. Testování

- Pest: nové props v `index` (počty, aktivita, trasa, bez N+1) a `edit`
  (`play_url`, `players_count`); stávající testy musí projít.
- Vitest: `format.js`, `routePreview.js`.
- Build bez chyb.
- Ruční kontrola v prohlížeči (Claude, přes přihlášenou relaci dozora na
  :8000): přehled her, editor (výběr, přidání kliknutím do mapy,
  přetažení značky, poloměr, QR), sledování, mobilní šířka.
