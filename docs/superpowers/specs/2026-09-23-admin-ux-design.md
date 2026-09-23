# Úpravy administrace (UX dozora) — design

Datum: 2026-09-23
Stav: schváleno v brainstormingu, čeká na review specu

## 1. Cíl a kontext

Zpětná vazba z prvního použití administrace dozorem:

- úvodní stránka má být administrace, Dashboard zatím není potřeba,
- chybí viditelná možnost registrace,
- ve výpisu her chybí tlačítka publikovat/skrýt a sledování,
- stanoviště nejde pojmenovat, typ se zobrazuje jako `arrival_only`,
- není poznat, které stanoviště se právě upravuje,
- "Smazat" je hned vedle "Upravit" a maže bez potvrzení,
- při opětovné editaci stanoviště nejsou vidět nahrané obrázky a nejde je
  spravovat (dnes je editace `pick_image` dokonce **vyžaduje nahrát
  znovu** a staré soubory zahodí — bug).

## 2. Rozhodnutí z brainstormingu

- **Název stanoviště:** volitelný, jen pro dozora (nikdy se neukazuje
  dítěti — hráčské UI zůstává bez textu). Prázdný → zobrazí se
  "Stanoviště N".
- **Editace:** formulář se otevře přímo **pod řádkem** upravovaného
  stanoviště, řádek je zvýrazněný, formulář má nadpis "Upravuješ: …".
- **Správa obrázků:** vše se mění **ve formuláři a uloží se najednou**
  (jeden request); "Zrušit" opravdu nic nezmění.

## 3. Úvodní stránka, Dashboard, registrace

- `GET /`: přihlášený dozor → redirect na `admin.games.index`; host →
  redirect na `login`. Stránka `Welcome.vue` a její route se odstraní.
- Dashboard: route `dashboard`, stránka `Dashboard.vue` a odkazy v
  `AuthenticatedLayout.vue` (desktop i responzivní menu) se odstraní. Logo
  v menu vede na `admin.games.index`.
- Všechna místa, kam Breeze přesměrovává na `route('dashboard')`
  (`AuthenticatedSessionController`, `RegisteredUserController`,
  `VerifyEmailController`, `EmailVerificationPromptController`,
  `EmailVerificationNotificationController`,
  `ConfirmablePasswordController`), přesměrují na
  `route('admin.games.index')`. Breeze testy, které očekávají `dashboard`,
  se upraví odpovídajícím způsobem.
- Přihlašovací stránka (`Auth/Login.vue`) dostane odkaz
  **"Nemáte účet? Zaregistrujte se"** na `route('register')`;
  registrační stránka odkaz zpět "Už máte účet? Přihlaste se" (Breeze ho
  obvykle už má — ověřit, případně počeštit).

## 4. Výpis her (Moje hry)

Každý řádek hry:

- název (odkaz na editaci) a stav česky: `draft` → "koncept",
  `published` → "publikováno", `archived` → "archivováno",
- tlačítka **Upravit**, **Publikovat** / **Skrýt** (podle stavu; stejné
  routy `admin.games.publish` / `admin.games.unpublish` jako editace hry;
  publikace hry bez stanovišť vrací 422 — výpis zobrazí srozumitelnou
  chybu "Hru bez stanovišť nelze publikovat"),
  **Sledovat hráče** (`admin.games.tracking`), a vpravo oddělené
  **Smazat**.
- **Smazat hru** otevře potvrzovací dialog: "Smazat hru „{název}“? Smažou
  se i všechna stanoviště, hráči a jejich fotky. Tato akce je nevratná."

## 5. Seznam stanovišť (editace hry)

### 5.1 Název a typ

- Nový sloupec `locations.name` (nullable string, max 100). Validace
  `nullable|string|max:100` v `LocationRequest`, ukládá se ve `store` i
  `update`.
- Popisek stanoviště všude v adminu: `#{pořadí} {name || "Stanoviště
  {pořadí}"}`. Pořadí = 1-based pozice v seřazeném seznamu.
- Český název typu z jednoho sdíleného mapování
  (`resources/js/lib/taskTypes.js`):
  `arrival_only` → "Jen dojít", `pick_image` → "Vybrat obrázek",
  `photo_of_something` → "Vyfotit něco",
  `selfie_doing_something` → "Vyfotit se při činnosti". Používá ho seznam
  i `<select>` ve formuláři.
- Řádek seznamu: `#3 Studánka · Vybrat obrázek`.
- Název se zobrazí také v dozorském sledování: tooltip stanoviště na mapě
  (`TrackingMap.vue`) a sloupec "#" v detailu hráče (`PlayerDetail.vue`).
  Backend: `TrackingController::show` přidá `name` do `locations`,
  `TrackingController::player` přidá `location_name` do `attempts`.

### 5.2 Editace

- Klik na **Upravit** zvýrazní řádek (např. indigo pozadí a levý okraj) a
  formulář se vykreslí **hned pod tímto řádkem** s nadpisem
  "Upravuješ: #3 Studánka". Otevřený je vždy nejvýš jeden formulář
  (přidání i editace se vylučují, jako dnes).
- Mapa ve formuláři (`MapPicker`) se vycentruje na upravované stanoviště.
- Po uložení / zrušení se formulář zavře a zvýraznění zmizí.

### 5.3 Mazání

- **Smazat** se přesune na pravý okraj řádku, vizuálně oddělené od
  Upravit (ztlumená červená).
- Klik otevře potvrzovací dialog (Breeze `Modal.vue` + `DangerButton`):
  "Smazat stanoviště #3 Studánka? Tato akce je nevratná." U publikované
  hry navíc: "Hráči, kteří už toto stanoviště plní, přijdou o svůj
  průběh na něm."
- Potvrzení provede dnešní `DELETE` route; zrušení nic nedělá.
- Stejná komponenta dialogu (`ConfirmDialog.vue`) slouží i pro mazání
  hry (§4).

## 6. Správa obrázků při editaci stanoviště

### 6.1 Vybrat obrázek (`pick_image`)

Formulář:

- Jedna řada náhledů: **stávající obrázky** (URL z
  `Storage::disk('public')->url`) následované **nově vybranými** soubory.
- U každého náhledu **radio "správný"** a tlačítko **✕ odebrat**.
- Tlačítko/input **"Přidat obrázky"** připojí další soubory (neruší
  vybrané).
- Počítadlo "N / 3–6 obrázků"; uložit jde jen s 3–6 obrázky a vybraným
  správným (klient to naznačí, server vynucuje).
- Odebrání obrázku, který byl označený jako správný, zruší výběr
  správného (musí se vybrat znovu).

Request (multipart, jako dnes):

- `existing_images[]` — cesty stávajících obrázků, které zůstávají, v
  pořadí zobrazení,
- `images[]` — nové soubory, v pořadí zobrazení,
- `correct_index` — index do **spojeného** seznamu
  `existing_images ++ images`.

Server (`LocationRequest` + `LocationController`):

- `existing_images` smí obsahovat jen cesty, které jsou aktuálně v
  `task_payload.images` **tohoto** stanoviště (při `store` musí být
  prázdné) — jinak 422. Zabraňuje odkazování na cizí soubory.
- Celkový počet `existing_images + images` 3–6; `correct_index` v
  rozsahu spojeného seznamu.
- Nový payload: `images` = zachované cesty ++ nově uložené soubory,
  `correct_index` beze změny významu.
- Po úspěšném uložení se ze storage smažou obrázky, které byly v
  původním payloadu a v novém nejsou.

### 6.2 Fotoúkoly (`photo_of_something`, `selfie_doing_something`)

- Formulář zobrazí náhled **aktuálního referenčního obrázku**; výběr
  nového souboru ho nahradí (náhled nového).
- `reference_image` je **povinný jen** při vytvoření stanoviště nebo když
  se typ mění na fotoúkol (původní stanoviště referenční obrázek nemá);
  jinak volitelný — bez nového souboru se zachová stávající.
- Při nahrazení se starý soubor po úspěšném uložení smaže.

### 6.3 Změna typu

- Změní-li se `task_type`, soubory předchozího typu, které nový payload
  nepoužívá, se po úspěšném uložení smažou (např. `pick_image` →
  `arrival_only` smaže všechny obrázky).

### 6.4 Rozběhnutá hra

- Hráči uvidí nové obrázky při příštím příchodu na stanoviště (payload se
  čte při `arrive`). Dříve odeslané odpovědi a fotky zůstávají platné.
- Fotky hráčů (`attempt-photos/`) se úpravou stanoviště nikdy nemažou.

## 7. Mimo rozsah

- Dashboard se statistikami (odloženo, "zatím není potřeba").
- Změna layoutu výpisu her na mřížku karet — zůstává seznam, jen s
  tlačítky.
- Obrázky nahrávané okamžitě (bez uložení formuláře).
- Archivace her (stav `archived` se jen zobrazuje, nový workflow ne).

## 8. Testování

- **Pest feature testy:**
  - `/` → redirect na admin (přihlášený) / login (host); po přihlášení a
    registraci redirect na `admin.games.index`; `dashboard` route už
    neexistuje (upravené Breeze testy).
  - Název stanoviště: uložení při store/update, validace délky, prázdný
    povolen; `name`/`location_name` v tracking props/JSON.
  - `pick_image` update: zachovat vše beze změny (bez nových souborů),
    odebrat jeden + přidat jeden, jen přidat, správný index přes spojený
    seznam, odkaz na obrázek jiného stanoviště → 422, méně než 3 / víc
    než 6 → 422, smazání odebraných souborů ze storage (`Storage::fake`).
  - Fotoúkol update: bez nového souboru se zachová, s novým se nahradí a
    starý smaže; změna typu na fotoúkol bez souboru → 422.
  - Změna typu `pick_image` → `arrival_only` smaže soubory.
  - Výpis her: publikace/skrytí z výpisu (existující routy), publikace hry
    bez stanovišť → 422 zůstává.
- **Vitest:** `taskTypes.js` mapování (všechny 4 typy + neznámý typ).
- **Ruční kontrola v prohlížeči:** zvýraznění a formulář pod řádkem,
  potvrzovací dialogy, správa obrázků (přidat/odebrat/správný), odkaz na
  registraci.
