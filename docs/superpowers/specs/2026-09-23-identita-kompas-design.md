# Identita „Kompas“ a přihlašovací stránky — design + plán

Datum: 2026-09-23
Stav: schváleno uživatelem (směr „karta na přírodním pozadí“, název „Kompas“)

## Cíl

Nahradit výchozí Laravel/Breeze vzhled vlastní identitou aplikace a udělat
z přihlášení a registrace přehlednou, přívětivou vstupní stránku, která
funguje na počítači i na telefonu.

## Rozhodnutí

- Název aplikace **Kompas** (`APP_NAME=Kompas` v `.env` i `.env.example`).
- Logo: vlastní SVG růžice kompasu — tyrkysový kruh (teal-700), světlý
  ciferník, severní hrot oranžový (orange-500), jižní tmavě tyrkysový
  (teal-900), východ/západ světle tyrkysové (teal-300), středový bod.
  Pevné barvy (nezávislé na `fill-current`), `viewBox="0 0 64 64"`.
- Favicon a ikony ze stejného SVG: `public/favicon.svg`, `public/favicon.ico`
  (16 + 32 + 48 px), `public/apple-touch-icon.png` (180 px),
  `public/icon-192.png` a `public/icon-512.png` (připraveno pro PWA v Plánu D).
  `app.blade.php` odkazuje SVG favicon, ICO fallback, apple-touch-icon a
  `<meta name="theme-color" content="#0f766e">`.
- `GuestLayout.vue` (všechny přihlašovací stránky): celostránkový přechod
  `from-emerald-100 via-teal-50 to-sky-100` + decentní SVG vzor vrstevnic
  (teal, nízká průhlednost, bez externích obrázků); nahoře logo + „Kompas“
  + věta „Hra s kompasem pro děti, které ještě neumí číst.“; bílá karta
  `max-w-md`, `rounded-2xl`, výrazný stín, na telefonu 16 px okraje.
- Každá stránka má v kartě nadpis (`h1`):
  Přihlášení dozoru / Nový účet dozoru / Zapomenuté heslo / Nové heslo /
  Potvrzení hesla / Ověření e-mailu.
- Nové komponenty `resources/js/Components/Auth/AuthButton.vue` (hlavní
  tlačítko přes celou šířku, teal-600, normální písmo, `processing` stav)
  a `AuthLinkButton.vue` (obrysové tlačítko-odkaz přes celou šířku).
  Sdílené Breeze `PrimaryButton` se nemění (používá ho administrace).
- Přihlášení: E-mail, Heslo, řádek „Zapamatovat si mě“ + vpravo
  „Zapomněli jste heslo?“, `AuthButton` „Přihlásit se“, oddělovač „nebo“,
  `AuthLinkButton` „Vytvořit účet“ → `register`.
- Registrace: `AuthButton` „Zaregistrovat se“, oddělovač, `AuthLinkButton`
  „Už mám účet – přihlásit se“ → `login`.
- Administrace: v horní liště vedle loga text „Kompas“.
- Mimo rozsah: redesign administrace, hráčské UI, e-mailové šablony (dostanou
  jen nový název přes `APP_NAME`).

## Plán

1. Logo (`ApplicationLogo.vue`) + `public/favicon.svg`; vyrenderovat PNG přes
   headless Chrome, z nich ICO/PNG ikony přes Pillow; odkazy v
   `app.blade.php`; `APP_NAME=Kompas`.
2. `AuthButton.vue`, `AuthLinkButton.vue`, nový `GuestLayout.vue`.
3. Úprava šesti přihlašovacích stránek (nadpisy, tlačítka, rozvržení
   Login/Register podle návrhu); „Kompas“ v liště administrace.
4. Ověření: `npm run build`, `php artisan test`, `npm run test:unit`,
   snímky v prohlížeči (desktop i šířka telefonu) přihlášení a registrace,
   favicon v záložce.
