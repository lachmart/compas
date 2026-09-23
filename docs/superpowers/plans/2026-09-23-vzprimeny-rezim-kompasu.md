# Vzpřímený režim kompasu — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Když dítě zvedne telefon do svislé polohy ("jako když fotí"),
kompas automaticky přepne z otáčející se šipky ➤ na velké SVG pokyny
(terč / vlevo / vpravo / otoč se) podle směru zadní kamery; položením
naplocho se vrátí klasický kompas.

**Architecture:** Veškerá matematika (směr kamery z úhlů orientace,
náklon, hystereze přepínání, vyhodnocení pokynu, normalizace eventu) žije
v čistém modulu `resources/js/lib/orientation.js` testovaném Vitestem.
`useSensors` k tomu navíc vystaví absolutní `orientation` (alpha/beta/gamma).
Nová komponenta `TurnGuide.vue` jen vykresluje jeden ze 4 stavů a vibruje
při vstupu na kurz; `CompassScreen.vue` drží režim a vybírá, co zobrazit.
Spec: `docs/superpowers/specs/2026-09-23-vzprimeny-rezim-kompasu-design.md`.

**Tech Stack:** Vue 3 (`<script setup>`), Vitest (node prostředí, jen čisté
funkce), DeviceOrientation API, Vibration API, Tailwind.

## Global Constraints

- Přepnutí do svislého režimu při náklonu **> 55°**, zpět do plochého při
  **< 35°**; mezi tím zůstává aktuální režim.
- Pokyny: **±20°** → terč (`'on_course'`), odchylka **> 135°** →
  `'behind'`, jinak `'left'` / `'right'`.
- Grafika pokynů je **inline SVG** — žádné unicode znaky ani emoji v
  `TurnGuide.vue`. Hráčské UI je **bez textu**.
- Vibrace `navigator.vibrate?.(150)` **jen při přechodu** do `'on_course'`
  (ne opakovaně, dokud dítě z kurzu neuhne).
- Barevné pozadí, vzdálenost v metrech, detekce příchodu (časovač 3 s),
  pingy, mapa a přepínač mapy zůstávají **beze změny**.
- Bez absolutní orientace (`orientation === null`) se svislý režim nikdy
  nezapne (PC, starší zařízení) — zůstává dnešní ➤ / vybledlý ◎.
- Úhly ve stupních; `alpha` je absolutní (0 = horní hrana naplocho míří na
  sever, roste proti směru hodinových ručiček — konvence W3C).
- Testy: `npm run test:unit` a `php artisan test` musí na konci každého
  tasku projít; `npm run build` bez chyb.

---

### Task 1: Čisté funkce orientace (`orientation.js`)

**Files:**
- Create: `resources/js/lib/orientation.js`
- Test: `resources/js/lib/orientation.test.js`

**Interfaces:**
- Produces (Task 2 a 3 je konzumují přesně takto):
  - konstanty `UPRIGHT_ENTER_DEG = 55`, `UPRIGHT_EXIT_DEG = 35`,
    `ON_COURSE_DEG = 20`, `BEHIND_DEG = 135`
  - `cameraHeading(alpha, beta, gamma): number` — 0–360, směr zadní kamery
  - `tiltDegrees(beta, gamma): number` — 0–180, odklon osy z od svislice
  - `nextPosture(previous, beta, gamma): 'flat' | 'upright'`
  - `turnGuidance(bearing, heading): 'on_course' | 'left' | 'right' | 'behind'`
  - `absoluteOrientation(event): { alpha, beta, gamma } | null`

- [ ] **Step 1: Napiš selhávající testy**

`resources/js/lib/orientation.test.js`:

```js
import { describe, expect, test } from 'vitest';
import {
    BEHIND_DEG,
    ON_COURSE_DEG,
    UPRIGHT_ENTER_DEG,
    UPRIGHT_EXIT_DEG,
    absoluteOrientation,
    cameraHeading,
    nextPosture,
    tiltDegrees,
    turnGuidance,
} from './orientation';

describe('cameraHeading', () => {
    // Phone held upright (beta 90). With alpha 0 the screen faces south,
    // so the back camera looks north.
    test('upright facing north', () => {
        expect(cameraHeading(0, 90, 0)).toBeCloseTo(0, 5);
    });

    test('upright facing east', () => {
        expect(cameraHeading(270, 90, 0)).toBeCloseTo(90, 5);
    });

    test('upright facing south', () => {
        expect(cameraHeading(180, 90, 0)).toBeCloseTo(180, 5);
    });

    test('upright facing west', () => {
        expect(cameraHeading(90, 90, 0)).toBeCloseTo(270, 5);
    });

    test('wraps just west of north into the 350s', () => {
        expect(cameraHeading(10, 90, 0)).toBeCloseTo(350, 5);
    });

    test('gamma turns the camera direction when upright', () => {
        expect(cameraHeading(0, 90, 30)).toBeCloseTo(330, 5);
    });

    test('always returns a value in [0, 360)', () => {
        for (const alpha of [0, 45, 90, 135, 180, 225, 270, 315, 359.9]) {
            const heading = cameraHeading(alpha, 80, -10);
            expect(heading).toBeGreaterThanOrEqual(0);
            expect(heading).toBeLessThan(360);
        }
    });
});

describe('tiltDegrees', () => {
    test('flat screen-up is 0', () => {
        expect(tiltDegrees(0, 0)).toBeCloseTo(0, 5);
    });

    test('upright is 90', () => {
        expect(tiltDegrees(90, 0)).toBeCloseTo(90, 5);
    });

    test('on its side counts as upright too', () => {
        expect(tiltDegrees(0, 90)).toBeCloseTo(90, 5);
    });

    test('partial forward tilt', () => {
        expect(tiltDegrees(60, 0)).toBeCloseTo(60, 5);
    });
});

describe('nextPosture', () => {
    test('stays flat until tilt passes the enter threshold', () => {
        expect(nextPosture('flat', UPRIGHT_ENTER_DEG - 1, 0)).toBe('flat');
        expect(nextPosture('flat', UPRIGHT_ENTER_DEG + 1, 0)).toBe('upright');
    });

    test('stays upright until tilt drops below the exit threshold', () => {
        expect(nextPosture('upright', UPRIGHT_EXIT_DEG + 1, 0)).toBe('upright');
        expect(nextPosture('upright', UPRIGHT_EXIT_DEG - 1, 0)).toBe('flat');
    });

    test('keeps the previous posture inside the hysteresis band', () => {
        expect(nextPosture('flat', 45, 0)).toBe('flat');
        expect(nextPosture('upright', 45, 0)).toBe('upright');
    });
});

describe('turnGuidance', () => {
    test('on course within the tolerance on either side', () => {
        expect(turnGuidance(100, 100)).toBe('on_course');
        expect(turnGuidance(100, 100 - ON_COURSE_DEG)).toBe('on_course');
        expect(turnGuidance(100, 100 + ON_COURSE_DEG)).toBe('on_course');
    });

    test('target clockwise of the camera means turn right', () => {
        expect(turnGuidance(100, 50)).toBe('right');
    });

    test('target counter-clockwise of the camera means turn left', () => {
        expect(turnGuidance(50, 100)).toBe('left');
    });

    test('target behind means turn around', () => {
        expect(turnGuidance(0, 180)).toBe('behind');
        expect(turnGuidance(0, BEHIND_DEG + 1)).toBe('behind');
        expect(turnGuidance(0, 360 - BEHIND_DEG - 1)).toBe('behind');
    });

    test('just inside the behind threshold is still a turn', () => {
        expect(turnGuidance(0, 360 - BEHIND_DEG + 1)).toBe('right');
        expect(turnGuidance(0, BEHIND_DEG - 1)).toBe('left');
    });

    test('wraps around north', () => {
        expect(turnGuidance(350, 40)).toBe('left');
        expect(turnGuidance(40, 350)).toBe('right');
        expect(turnGuidance(355, 5)).toBe('on_course');
    });
});

describe('absoluteOrientation', () => {
    test('uses an absolute event as-is', () => {
        expect(absoluteOrientation({ absolute: true, alpha: 30, beta: 80, gamma: 5 }))
            .toEqual({ alpha: 30, beta: 80, gamma: 5 });
    });

    test('derives alpha from the iOS compass heading', () => {
        expect(absoluteOrientation({ absolute: false, alpha: 123, beta: 80, gamma: 5, webkitCompassHeading: 90 }))
            .toEqual({ alpha: 270, beta: 80, gamma: 5 });
    });

    test('maps an iOS heading of 0 to alpha 0, not 360', () => {
        expect(absoluteOrientation({ alpha: 1, beta: 0, gamma: 0, webkitCompassHeading: 0 }).alpha).toBe(0);
    });

    test('ignores relative events without a compass heading', () => {
        expect(absoluteOrientation({ absolute: false, alpha: 30, beta: 80, gamma: 5 })).toBeNull();
    });

    test('ignores events with missing angles', () => {
        expect(absoluteOrientation({ absolute: true, alpha: null, beta: null, gamma: null })).toBeNull();
    });
});
```

- [ ] **Step 2: Spusť testy, ověř že selžou**

Run: `npm run test:unit`
Expected: FAIL (`Failed to resolve import "./orientation"`).

- [ ] **Step 3: Implementace**

`resources/js/lib/orientation.js`:

```js
// Pure orientation maths for the player's compass. Angles in degrees.
// alpha is absolute: 0 = top edge of a flat phone points north, growing
// counter-clockwise (W3C Device Orientation convention).

export const UPRIGHT_ENTER_DEG = 55;
export const UPRIGHT_EXIT_DEG = 35;
export const ON_COURSE_DEG = 20;
export const BEHIND_DEG = 135;

const RAD = Math.PI / 180;

const normalize360 = (degrees) => ((degrees % 360) + 360) % 360;

// Compass direction (clockwise from north) the back camera looks along,
// i.e. the device's -z axis, from R = Rz(alpha) * Rx(beta) * Ry(gamma).
// Independent of portrait/landscape (a rotation about z doesn't move -z).
export function cameraHeading(alpha, beta, gamma) {
    const [sa, ca] = [Math.sin(alpha * RAD), Math.cos(alpha * RAD)];
    const sb = Math.sin(beta * RAD);
    const [sg, cg] = [Math.sin(gamma * RAD), Math.cos(gamma * RAD)];

    const east = -(ca * sg + sa * sb * cg);
    const north = -(sa * sg - ca * sb * cg);

    return normalize360(Math.atan2(east, north) / RAD);
}

// Angle between the device's z axis (out of the screen) and straight up:
// 0 flat screen-up, 90 upright or on its side.
export function tiltDegrees(beta, gamma) {
    const cosine = Math.cos(beta * RAD) * Math.cos(gamma * RAD);

    return Math.acos(Math.max(-1, Math.min(1, cosine))) / RAD;
}

export function nextPosture(previous, beta, gamma) {
    const tilt = tiltDegrees(beta, gamma);

    if (previous === 'flat' && tilt > UPRIGHT_ENTER_DEG) return 'upright';
    if (previous === 'upright' && tilt < UPRIGHT_EXIT_DEG) return 'flat';

    return previous;
}

// bearing: direction to the target; heading: direction the camera faces.
export function turnGuidance(bearing, heading) {
    let diff = normalize360(bearing - heading);
    if (diff > 180) diff -= 360;

    if (Math.abs(diff) <= ON_COURSE_DEG) return 'on_course';
    if (Math.abs(diff) > BEHIND_DEG) return 'behind';

    return diff > 0 ? 'right' : 'left';
}

// Normalises a deviceorientation(absolute) event into absolute angles, or
// null when the event carries no absolute heading (relative-only sources).
export function absoluteOrientation(event) {
    const { alpha, beta, gamma, absolute, webkitCompassHeading } = event;

    if (typeof beta !== 'number' || typeof gamma !== 'number') return null;

    if (typeof webkitCompassHeading === 'number') {
        return { alpha: normalize360(360 - webkitCompassHeading), beta, gamma };
    }

    if (absolute === true && typeof alpha === 'number') {
        return { alpha, beta, gamma };
    }

    return null;
}
```

- [ ] **Step 4: Spusť testy**

Run: `npm run test:unit`
Expected: PASS (všechny, včetně nových).

- [ ] **Step 5: Commit**

```bash
git add resources/js/lib/orientation.js resources/js/lib/orientation.test.js
git commit -m "Add pure orientation helpers for the upright compass mode"
```

---

### Task 2: `useSensors` vystaví absolutní orientaci

**Files:**
- Modify: `resources/js/lib/useSensors.js`

**Interfaces:**
- Consumes: `absoluteOrientation(event)` (Task 1)
- Produces: `useSensors()` navíc vrací `orientation` — `Ref<{ alpha, beta, gamma } | null>`
  (absolutní úhly). Stávající `position`, `heading`, `geoError`,
  `orientationSupported`, `requestPermissions` beze změny názvů i významu.

Poznámka: Android Chrome posílá **oba** eventy — `deviceorientationabsolute`
(absolutní) i `deviceorientation` (od Chrome 50 relativní, s libovolným
počátkem). Dnešní handler plní `heading` z obou, takže relativní hodnoty
mohou přepisovat absolutní. Jakmile máme absolutní zdroj, `heading` se
bere jen z něj (pro iOS vychází `360 − alpha` = `webkitCompassHeading`,
takže iOS se nemění); relativní `alpha` zůstává jen jako fallback, dokud
žádný absolutní event nepřišel.

- [ ] **Step 1: Uprav handler**

V `resources/js/lib/useSensors.js`:

1. Přidej import na začátek souboru:

```js
import { absoluteOrientation } from '@/lib/orientation';
```

2. Pod `const orientationSupported = ref(true);` přidej:

```js
    const orientation = ref(null);
    let hasAbsoluteSource = false;
```

3. Nahraď celou funkci `handleOrientation`:

```js
    const handleOrientation = (event) => {
        const absolute = absoluteOrientation(event);

        if (absolute) {
            hasAbsoluteSource = true;
            orientation.value = absolute;
            heading.value = (360 - absolute.alpha) % 360;
        } else if (!hasAbsoluteSource && typeof event.alpha === 'number') {
            // Relative-only fallback (no absolute source seen yet).
            heading.value = (360 - event.alpha) % 360;
        }
    };
```

4. Rozšiř návratovou hodnotu:

```js
    return { position, heading, orientation, geoError, orientationSupported, requestPermissions };
```

- [ ] **Step 2: Ověř build a testy**

```bash
npm run build
npm run test:unit
php artisan test
```

Expected: build bez chyb, obě sady PASS.

- [ ] **Step 3: Commit**

```bash
git add resources/js/lib/useSensors.js
git commit -m "Expose absolute device orientation from useSensors"
```

---

### Task 3: `TurnGuide.vue` a přepínání režimu v `CompassScreen.vue`

**Files:**
- Create: `resources/js/Components/Play/TurnGuide.vue`
- Modify: `resources/js/Components/Play/CompassScreen.vue`

**Interfaces:**
- Consumes: `cameraHeading`, `nextPosture`, `turnGuidance` (Task 1);
  `orientation` z `useSensors()` (Task 2); `bearingDegrees` z
  `resources/js/lib/geo.js` (existuje).
- Produces: `TurnGuide` s prop `guidance: 'on_course' | 'left' | 'right' | 'behind'`.

- [ ] **Step 1: Komponenta `TurnGuide.vue`**

`resources/js/Components/Play/TurnGuide.vue`:

```vue
<script setup>
import { watch } from 'vue';

const props = defineProps({
    guidance: {
        type: String,
        required: true,
        validator: (value) => ['on_course', 'left', 'right', 'behind'].includes(value),
    },
});

// One short buzz the moment the child turns onto course; not repeated
// while they stay on it. iOS ignores web vibration — the target still shows.
watch(
    () => props.guidance,
    (value, previous) => {
        if (value === 'on_course' && previous !== 'on_course') {
            navigator.vibrate?.(150);
        }
    },
);
</script>

<template>
    <svg
        v-if="guidance === 'on_course'"
        viewBox="0 0 24 24"
        class="h-40 w-40 animate-pulse text-gray-800"
        fill="none"
        stroke="currentColor"
        stroke-width="2"
    >
        <circle cx="12" cy="12" r="10" />
        <circle cx="12" cy="12" r="6" />
        <circle cx="12" cy="12" r="2" fill="currentColor" />
    </svg>

    <svg
        v-else-if="guidance === 'left'"
        viewBox="0 0 24 24"
        class="h-40 w-40 text-gray-800"
        fill="none"
        stroke="currentColor"
        stroke-width="3"
        stroke-linecap="round"
        stroke-linejoin="round"
    >
        <path d="M20 12H5M11 6l-6 6 6 6" />
    </svg>

    <svg
        v-else-if="guidance === 'right'"
        viewBox="0 0 24 24"
        class="h-40 w-40 text-gray-800"
        fill="none"
        stroke="currentColor"
        stroke-width="3"
        stroke-linecap="round"
        stroke-linejoin="round"
    >
        <path d="M4 12h15M13 6l6 6-6 6" />
    </svg>

    <svg
        v-else
        viewBox="0 0 24 24"
        class="h-40 w-40 text-gray-800"
        fill="none"
        stroke="currentColor"
        stroke-width="2.5"
        stroke-linecap="round"
        stroke-linejoin="round"
    >
        <path d="M8 20V10a4 4 0 0 1 8 0v5" />
        <path d="M13 12l3 3 3-3" />
    </svg>
</template>
```

- [ ] **Step 2: Napoj režim do `CompassScreen.vue`**

V `resources/js/Components/Play/CompassScreen.vue`:

1. Importy — nahraď řádek s `geo` importem a přidej nové:

```js
import { arrowRotationDegrees, bearingDegrees, distanceMeters, proximityColor } from '@/lib/geo';
import { cameraHeading, nextPosture, turnGuidance } from '@/lib/orientation';
import TurnGuide from '@/Components/Play/TurnGuide.vue';
```

2. Destrukturování `useSensors()` rozšiř o `orientation`:

```js
const { position, heading, orientation, geoError, orientationSupported, requestPermissions } = useSensors();
```

3. Nahraď celý blok `const rotation = computed(...)` tímto:

```js
const bearing = computed(() => {
    if (!position.value) return null;

    return bearingDegrees(position.value.lat, position.value.lng, props.location.lat, props.location.lng);
});

const rotation = computed(() => {
    if (bearing.value === null || !hasHeading.value) return 0;

    return arrowRotationDegrees(bearing.value, heading.value);
});

// 'flat' shows the classic arrow; 'upright' (phone held like a camera)
// shows turn guidance. nextPosture() applies the hysteresis band.
const posture = ref('flat');

watch(orientation, (value) => {
    if (value) posture.value = nextPosture(posture.value, value.beta, value.gamma);
});

const guidance = computed(() => {
    if (posture.value !== 'upright' || !orientation.value || bearing.value === null) return null;

    const { alpha, beta, gamma } = orientation.value;

    return turnGuidance(bearing.value, cameraHeading(alpha, beta, gamma));
});
```

4. V šabloně nahraď dvojici `<div v-if="hasHeading" ...>➤</div>` a
`<div v-else ...>◎</div>` tímto:

```vue
        <TurnGuide v-if="guidance" :guidance="guidance" />
        <div
            v-else-if="hasHeading"
            class="text-6xl"
            :style="{ transform: `rotate(${rotation}deg)` }"
        >➤</div>
        <div v-else class="text-6xl text-gray-400 opacity-30">◎</div>
```

(Chybová obrazovka `geoError`, `ErrorFlash`, barevné pozadí, vzdálenost a
časovač příchodu zůstávají beze změny.)

- [ ] **Step 3: Build a testy**

```bash
npm run build
npm run test:unit
php artisan test
```

Expected: build bez chyb, obě sady PASS.

- [ ] **Step 4: Ověření v desktopovém Chrome**

`composer run dev`, otevři `/hra/{slug}` publikované hry, projdi
PermissionGate. DevTools → ⋮ → More tools → **Sensors**:
- **Location:** souřadnice kousek od aktuálního stanoviště (vzdálenost
  se zobrazí).
- **Orientation:** nastav `beta` = 90, `gamma` = 0 → musí se objevit jedna
  ze 4 SVG grafik místo ➤. Měň `alpha`, dokud se neobjeví terč (pulzuje);
  otáčením `alpha` na obě strany ověř šipku vlevo/vpravo a při ~180° od
  terče ikonu "otoč se".
- Sniž `beta` na 45 → grafika zůstane (hystereze); na 20 → vrátí se ➤.

Pokud DevTools emulace neposílá absolutní orientaci (`orientation`
zůstane `null` a svislý režim se nezapne), zapiš to do reportu — ověření
pak proběhne až na reálném telefonu (Task 4).

- [ ] **Step 5: Commit**

```bash
git add resources/js/Components/Play/TurnGuide.vue resources/js/Components/Play/CompassScreen.vue
git commit -m "Show turn guidance when the player holds the phone upright"
```

---

### Task 4: Ověření na reálných zařízeních

**Files:** žádné (manuální QA; případné opravy + testy)

**Interfaces:**
- Consumes: celý plán (Task 1–3)
- Produces: potvrzení na Androidu i iPhonu

- [ ] **Step 1: HTTPS přístup pro telefon**

```bash
composer run dev
ngrok http 8000
```

Na telefonu otevři `https://…ngrok…/hra/{slug}`.

- [ ] **Step 2: Android (Chrome)**

1. Telefon naplocho → ➤ míří k cíli jako dosud.
2. Zvedni telefon svisle (jako k focení) → objeví se šipka / terč.
3. Otáčej se na místě: šipka vlevo/vpravo odpovídá kratšímu směru k cíli;
   při míření na cíl terč + krátká vibrace (jen jednou při vstupu).
4. Otoč se zády k cíli → ikona "otoč se".
5. Polož telefon → zpět ➤. Mezi ~35–55° nebliká.
6. Vyzkoušej i landscape držení — směr musí sedět stejně.

- [ ] **Step 3: iPhone (Safari)**

Stejné kroky 1–6. **Kritické:** ve svislé poloze ověř, že terč se ukáže,
když zadní kamera skutečně míří na stanoviště (porovnej s mapou). Pokud se
směr ve svislé poloze na iOS rozchází (kombinace `webkitCompassHeading` +
náklon), zapiš naměřenou odchylku a zastav — řešení se probere zvlášť.

- [ ] **Step 4: Automatické testy**

```bash
npm run test:unit
php artisan test
```

Expected: PASS.

- [ ] **Step 5: Commit (pokud vznikly opravy)**

Pokud QA odhalilo bug, oprav ho, přidej/uprav test a commitni. Pokud ne,
tento krok přeskoč.
