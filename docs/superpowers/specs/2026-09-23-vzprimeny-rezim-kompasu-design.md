# Vzpřímený režim kompasu (otáčecí šipky) — design

Datum: 2026-09-23
Stav: schváleno v brainstormingu, čeká na review specu

## 1. Cíl a kontext

Kompas hráče (`CompassScreen.vue`) dnes funguje dobře jen s telefonem
položeným naplocho: šipka ➤ ukazuje směr k cíli podle natočení horní hrany
telefonu. Dítě ale telefon přirozeně drží svisle, "jako když fotí" — v té
poloze je heading z `deviceorientation` nespolehlivý a otáčející se šipka
pro dítě nečitelná.

Cíl: když dítě telefon zvedne do svislé polohy, kompas automaticky přepne
do režimu **otáčecích pokynů** — místo šipky velká grafika říká "otoč se
doleva / doprava / otoč se" a když dítě míří správně, ukáže **terč**.

## 2. Rozhodnutí z brainstormingu

- **Pozadí:** stejné barevné "chladno/teplo" pozadí jako plochý kompas,
  **bez živého obrazu z kamery** (žádné další oprávnění, šetří baterii).
- **Přepínání:** **automaticky podle náklonu** s hysterezí — do svislého
  režimu nad ~55°, zpět do plochého pod ~35°. Dítě nic neovládá.
- **Pokyny:** **4 stavy** — terč (±20° od správného směru), šipka vlevo,
  šipka vpravo, "otoč se" (cíl za zády, víc než 135°).
- **Grafika:** vlastní **inline SVG**, žádné unicode znaky ani emoji (ve
  stylu existujícího SVG špendlíku v `PermissionGate`/`CompassScreen`).
- **Zpětná vazba:** krátká **vibrace** (`navigator.vibrate(150)`) v okamžiku
  přechodu do stavu "na kurzu" (Android; iOS web vibraci ignoruje, vizuální
  terč funguje všude). Nevibruje opakovaně, dokud dítě z kurzu neuhne.

## 3. Chování pro hráče

- **Telefon naplocho:** beze změny — ➤ šipka, barevné pozadí, vzdálenost.
- **Zvednutí nad ~55°** → vzpřímený režim; **položení pod ~35°** → zpět
  plochý kompas. Mezi 35–55° zůstává aktuální režim (žádné blikání).
- **Vzpřímený režim:** barevné pozadí a vzdálenost v metrech jako dnes,
  uprostřed jedna velká SVG grafika:
  - **terč** (jemně pulzuje) — směr zadní kamery je do ±20° od azimutu k
    cíli; při vstupu do tohoto stavu jedna krátká vibrace,
  - **šipka vlevo / vpravo** — odchylka 20–135°, otoč se tím směrem,
  - **"otoč se"** — odchylka nad 135°, cíl je za zády.
- **Zařízení bez orientačního senzoru** (PC, starší telefony): vzpřímený
  režim se nikdy nezapne — zůstává dnešní vybledlý ◎ + vzdálenost.
- **Beze změny:** detekce příchodu na stanoviště, pingy pozice, mapa a
  přepínač mapy.

## 4. Architektura

### 4.1 Čisté funkce — `resources/js/lib/orientation.js`

Všechny úhly ve stupních, bez závislosti na DOM (testovatelné ve Vitestu).

- `cameraHeading(alpha, beta, gamma): number` — kompasový směr (0–360°,
  po směru hodinových ručiček od severu), kam míří **zadní kamera**
  (osa −z zařízení). Používá rotační matici W3C Device Orientation
  (Z-X'-Y'': `R = Rz(α)·Rx(β)·Ry(γ)`), kde `alpha` je absolutní (0 = horní
  hrana naplocho míří na sever, roste proti směru hodinových ručiček).
  Pro osu −z v zemských souřadnicích (x = východ, y = sever):
  `east = −(cosα·sinγ + sinα·sinβ·cosγ)`,
  `north = −(sinα·sinγ − cosα·sinβ·cosγ)`,
  `heading = atan2(east, north)` převedené na 0–360°.
  Kontrola: `alpha=0, beta=90, gamma=0` (telefon svisle, displej k jihu)
  → 0° (sever). Směr kamery nezávisí na portrait/landscape držení
  (rotace kolem osy z ho nemění).
- `tiltDegrees(beta, gamma): number` — odklon osy z zařízení od svislice:
  `acos(cosβ·cosγ)` ve stupních (0° naplocho displejem nahoru, 90° svisle).
  Zohledňuje i náklon do strany.
- `nextPosture(previous, beta, gamma): 'flat' | 'upright'` — hystereze:
  z `'flat'` na `'upright'` až při `tiltDegrees > 55`, z `'upright'` na
  `'flat'` až při `tiltDegrees < 35`, jinak vrací `previous`.
- `turnGuidance(bearing, heading): 'on_course' | 'left' | 'right' | 'behind'`
  — `diff` = normalizovaná odchylka `bearing − heading` do intervalu
  (−180, 180]; `|diff| ≤ 20` → `'on_course'`, `|diff| > 135` → `'behind'`,
  jinak `diff > 0` → `'right'`, `diff < 0` → `'left'`.

Konstanty (`UPRIGHT_ENTER_DEG = 55`, `UPRIGHT_EXIT_DEG = 35`,
`ON_COURSE_DEG = 20`, `BEHIND_DEG = 135`) exportované z modulu.

### 4.2 `resources/js/lib/useSensors.js`

- Stávající `heading` (pro plochý režim) zůstává beze změny.
- Nově vrací `orientation` ref: `{ alpha, beta, gamma } | null`, kde
  `alpha` je **absolutní**:
  - Android/Chrome: z eventu `deviceorientationabsolute` (`event.alpha`),
  - iOS Safari: `alpha = (360 − event.webkitCompassHeading) % 360`,
    `beta`/`gamma` z téhož eventu. **Riziko:** `webkitCompassHeading` je
    navržený pro telefon naplocho; správnost kombinace ve svislé poloze je
    nutné ověřit na reálném iPhonu (viz §6).
  - Pokud absolutní alpha není k dispozici (jen relativní
    `deviceorientation` bez `webkitCompassHeading`), `orientation` zůstává
    `null` → vzpřímený režim se nezapne.

### 4.3 `resources/js/Components/Play/TurnGuide.vue` (nová)

- Props: `guidance` (`'on_course' | 'left' | 'right' | 'behind'`).
- Vykreslí jednu ze čtyř inline SVG grafik (terč, šipka vlevo, šipka
  vpravo, "otoč se"); terč s CSS pulzem.
- `watch(guidance)`: při přechodu z jiného stavu do `'on_course'` zavolá
  `navigator.vibrate?.(150)`.
- Žádný text (hráčské UI je bez textu).

### 4.4 `resources/js/Components/Play/CompassScreen.vue`

- Drží `posture` ref (`'flat'` výchozí), aktualizovaný přes
  `nextPosture(posture, beta, gamma)` při změně `orientation`.
- Pokud `posture === 'upright'` a `orientation` a pozice jsou k dispozici:
  místo ➤ šipky vykreslí
  `<TurnGuide :guidance="turnGuidance(bearing, cameraHeading(...))" />`.
- Jinak beze změny (➤ šipka, případně vybledlý ◎).
- Barevné pozadí, vzdálenost a časovač příchodu beze změny.

## 5. Mimo rozsah

- Živý obraz z kamery (AR) za pokyny.
- `AbsoluteOrientationSensor` (Generic Sensor API) — jen Chrome/Android.
- Kalibrace kompasu, upozornění na magnetické rušení.
- Změny mapy, admin části a pingů.

## 6. Testování

- **Vitest** (`resources/js/lib/orientation.test.js`):
  - `cameraHeading` — telefon svisle mířící na S/V/J/Z (alpha 0/270/180/90
    při beta 90), svisle s náklonem do strany, přechod přes 0°/360°,
  - `tiltDegrees` — naplocho 0°, svisle 90°, na boku (gamma 90) 90°,
  - `nextPosture` — vstup nad 55°, setrvání v pásmu 35–55° v obou směrech,
    výstup pod 35°,
  - `turnGuidance` — hranice 20° a 135°, obě strany, wrap-around
    (bearing 350°, heading 10° → `'left'`; bearing 10°, heading 350° →
    `'right'`).
- **Desktop:** Chrome DevTools → Sensors → Orientation (alpha/beta/gamma)
  umožní ručně ověřit přepínání režimů a všechny 4 stavy.
- **Reálná zařízení (povinné před dokončením):** jeden Android a jeden
  iPhone přes HTTPS (např. ngrok) — zejména iOS kombinace
  `webkitCompassHeading` + náklon ve svislé poloze.
