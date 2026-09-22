# Dětský geocaching — design

Datum: 2026-09-22
Stav: navrženo, čeká na review

## 1. Cíl a kontext

Webová aplikace fungující podobně jako geocaching, ale určená dětem, které
neumí číst. Hráč prochází sérii stanovišť v terénu; k navigaci mezi nimi
používá kompas a GPS svého telefonu (šipka + barevné "chladno/teplo"
znázornění vzdálenosti), na každém stanovišti plní čistě vizuální úkol
(bez textu). Hru vytváří a spravuje "dozor" (vedoucí, učitel apod.), který
zároveň může v reálném čase sledovat všechny přihlášené hráče na mapě.

Cílová platforma: mobilní prohlížeč (telefon s GPS a kompasem), appka
funguje jako instalovatelná PWA včetně offline režimu (hraje se venku, kde
může být slabý signál).

## 2. Rozhodnutí z brainstormingu

- **Validace foto úkolů:** auto-splnění. Jakmile hráč fotku odešle, úkol se
  počítá za hotový a hra pokračuje. Dozor si fotky prohlíží zpětně a může
  je označit jako neplatné (bez blokace hráče, jen pro pozdější
  vyhodnocení).
- **Offline režim:** appka musí fungovat i bez připojení — data hry se
  stáhnou předem, fotky a GPS pingy se při výpadku frontují lokálně a
  odešlou po obnovení signálu.
- **Správa dozorů:** multi-tenant — libovolný počet dozorů si zakládá
  vlastní účty a spravuje jen své vlastní hry.
- **Technologický stack:** Laravel + Inertia.js + Vue 3 jako PWA, Laravel
  Reverb pro živé sledování pozic, Leaflet + OpenStreetMap pro mapy.

## 3. Doménový model

- **User (Dozor)** — účet (Laravel Breeze auth), vlastní víc her.
- **Game (Hra)** — `owner_id`, interní název, unikátní `slug` (URL
  `/hra/{slug}`), stav `draft` / `published` / `archived`.
- **Location (Stanoviště)** — patří do `Game`, `order` (pořadí v lineární
  sekvenci), `lat`, `lng`, `arrival_radius_m` (výchozí 15 m, upravitelné),
  `task_type` (`pick_image` / `photo_of_something` / `selfie_doing_something`),
  `task_payload` (JSON dle typu — u `pick_image` sada obrázků + ID
  správného, u fotoúkolů referenční obrázek/ikona zadání).
- **PlayerSession (Hráč)** — `game_id`, přezdívka, `device_token` (uložen
  v cookie/localStorage, drží identitu bez hesla i po refreshi),
  `current_location_index`, `started_at`, `finished_at`.
- **LocationAttempt (Průběh)** — `player_session_id`, `location_id`,
  status (`pending` / `arrived` / `completed`), `arrived_at`,
  `completed_at`, `submitted_photo_path` (nullable), `flagged_invalid`
  (bool).
- **PositionPing** — `player_session_id`, `lat`, `lng`, `heading`,
  `recorded_at` — historie pro trasu na mapě hráče i pro živé sledování
  dozorem.

## 4. Flow hráče

1. Otevře `/hra/{slug}`, zadá přezdívku → vznikne `PlayerSession`, token se
   uloží lokálně (funguje jako auto-přihlášení při návratu/refreshi).
2. Appka požádá o přístup ke GPS a kompasu (na iOS vyžaduje tap na
   tlačítko kvůli `DeviceOrientationEvent.requestPermission()`).
3. Hlavní obrazovka: šipka mířící na aktuální stanoviště (azimut z GPS
   mínus natočení zařízení) + barevné pozadí podle vzdálenosti (modrá →
   žlutá → oranžová → červená pod ~20 m).
4. Přepínač na mapu (Leaflet): všechna stanoviště, aktuální cíl
   zvýrazněný, vlastní pozice a natočení, dosud ušlá trasa
   (z `PositionPing`).
5. Po vstupu do `arrival_radius_m` (potvrzeno až po pár sekundách setrvání,
   kvůli nepřesnosti GPS) se odemkne obrazovka úkolu:
   - `pick_image` — tap na jeden z nabízených obrázků, auto-vyhodnocení,
     lze zkusit znovu.
   - `photo_of_something` / `selfie_doing_something` — nativní
     fotoaparát (`<input type="file" capture>`), po vyfocení automatický
     odeslání a auto-splnění.
6. Krátká vizuální odměna → appka přepne navigaci na další stanoviště.
7. Po posledním stanovišti → obrazovka "hra dokončena".

## 5. Administrace dozora

- Dashboard s přehledem vlastních her (draft/published/archived), tlačítko
  "Nová hra".
- Editace hry: název, slug (auto-generovaný, upravitelný, kontrola
  unikátnosti), seznam stanovišť s možností přeuspořádat (drag & drop).
- Umístění stanoviště dvěma způsoby: kliknutím na mapu, nebo tlačítkem
  "použij moji aktuální polohu" (dozor fyzicky stojí na místě).
- Nastavitelný `arrival_radius_m` podle terénu.
- Podle `task_type` nahrání obsahu: u `pick_image` 3–6 obrázků + označení
  správného, u fotoúkolů jeden referenční obrázek ukazující zadání (bez
  textu).
- Volitelný testovací náhled hry před publikováním.
- Publikace přepne `status` na `published` a zpřístupní URL hráčům.

## 6. Živé sledování (Dozor tracking)

- Mapa hry s markerem za každého přihlášeného hráče (přezdívka, barva),
  pozice se aktualizují v reálném čase přes Laravel Reverb (privátní
  WebSocket kanál pro danou hru).
- U hráče vidí aktuální stanoviště (např. "3/8") a čas posledního signálu;
  při výpadku marker zešedne a ukáže "naposledy viděn v HH:MM".
- Klik na hráče → detail: časy na jednotlivých stanovištích, náhledy
  odeslaných fotek, možnost zpětně označit fotku jako neplatnou.
- Drobné notifikace při dokončení stanoviště/celé hry.

## 7. Technická architektura

- **Backend:** Laravel, Laravel Breeze (auth dozorů), Laravel Reverb
  (broadcasting živých pozic).
- **Frontend:** Inertia.js + Vue 3 přes Vite, jedna aplikace se dvěma
  částmi (`/admin/*` pro dozora, `/hra/{slug}` pro hráče).
- **Mapy:** Leaflet + OpenStreetMap dlaždice (zdarma, bez API klíče).
- **Úložiště fotek:** Laravel Storage (lokální disk, později lze přepnout
  na S3-kompatibilní úložiště beze změny domácí logiky).
- **Data:** SQLite/MySQL — pro očekávaný rozsah provozu SQLite dostačuje i
  v produkci.

### PWA a offline režim

- `vite-plugin-pwa` generuje service worker + manifest — appka je
  instalovatelná a běží i bez signálu.
- Při vstupu do hry se jednorázově stáhnou a uloží do IndexedDB všechna
  data hry (stanoviště, obrázky úkolů), takže navigace a zobrazení úkolu
  nepotřebuje síť.
- Odeslané fotky a GPS pingy se při výpadku ukládají do lokální fronty
  (IndexedDB) a automaticky doženou po obnovení připojení (vlastní retry
  na `online`/`offline` eventech, protože Background Sync API na iOS
  Safari nefunguje).

### Práce se senzory

- Vzdálenost a azimut k cíli: Haversine vzorec; směrová šipka = azimut
  mínus aktuální `deviceorientation`/`deviceorientationabsolute`.
- iOS vyžaduje explicitní tap + `requestPermission()`, Android obvykle
  funguje bez promptu — appka ošetří obě cesty.
- Vyžadováno HTTPS (Geolocation i kompas fungují jen na zabezpečeném
  originu) — i lokální testování na telefonu potřebuje tunel (např.
  ngrok) nebo platný certifikát.
- Throttling: přepočet UI cca 2×/s kvůli výdrži baterie; "dorazil jsem" se
  potvrdí až po pár sekundách setrvání v dosahu.
- Fallback bez kompasu (starší zařízení, desktop) → appka ukáže jen mapu a
  vzdálenost bez šipky.

## 8. Edge cases

- Zamítnutý přístup ke GPS/kameře/kompasu → vizuální obrazovka (ikona +
  tlačítko zkusit znovu), bez textu.
- Slabá přesnost GPS (les, zástavba) → appka toleruje, dozor si nastavuje
  poloměr stanoviště podle terénu.
- Stejná přezdívka od dvou hráčů → povoleno, rozlišeno interně přes
  `device_token`.
- Dlouhodobý výpadek signálu → appka funguje dál s ikonou "offline" a
  frontou k odeslání.

## 9. Testování

- Pest/PHPUnit feature testy: CRUD her a stanovišť, join hráče,
  vyhodnocení úkolů, ukládání pozic a broadcast eventů.
- Vitest jednotkové testy na čisté výpočetní funkce (vzdálenost, azimut,
  barva podle vzdálenosti).
- Playwright E2E na hlavní scénář (dozor založí hru → hráč se připojí →
  splní úkol), GPS mockované přes devtools override.
- Reálné terénní ověření na fyzickém zařízení před každým nasazením
  zůstává nutné — senzory a offline chování nejde plně nahradit
  automatizovanými testy.

## 10. Mimo rozsah (možné budoucí rozšíření)

- Větvení pořadí stanovišť podle hráče (nyní je pořadí pro všechny stejné
  a lineární).
- Bodování / žebříček / časový limit hry.
- Automatické rozpoznávání obsahu fotek (nyní čistě auto-splnění +
  zpětná ruční kontrola dozorem).
- Import/export her, sdílení šablon mezi dozory.
