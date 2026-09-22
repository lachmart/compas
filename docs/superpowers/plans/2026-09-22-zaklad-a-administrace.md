# Základ a administrace dozora — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Postavit základ aplikace (Laravel + Inertia/Vue) a umožnit dozorovi
zaregistrovat se, vytvořit hru, přidat do ní stanoviště všech 4 typů úkolů
(včetně nahrání obrázků), přeuspořádat je a hru publikovat.

**Architecture:** Laravel backend (auth, CRUD, validace, file storage) +
Inertia.js/Vue 3 frontend (jedna aplikace, admin sekce pod `/admin`). Toto je
Plán A ze 4: navazují na něj Plán B (herní engine hráče), Plán C (živé
sledování) a Plán D (PWA/offline). Tento plán se soustředí výhradně na
administraci — hráčská část (join, kompas, plnění úkolů) je mimo jeho rozsah.

**Tech Stack:** Laravel (nejnovější stabilní), Laravel Breeze (auth,
Inertia/Vue stack, Pest), Inertia.js + Vue 3, Leaflet + OpenStreetMap,
Tailwind CSS (součást Breeze), SQLite (dev i produkce), Pest pro testy.

## Global Constraints

- Datový model a typy úkolů dle specu: `arrival_only`, `pick_image`,
  `photo_of_something`, `selfie_doing_something` — přesně tyto 4 hodnoty,
  žádné jiné.
- `pick_image` vyžaduje 3–6 obrázků + index/ID správného.
- `arrival_radius_m` má výchozí hodnotu 15 m a je upravitelný dozorem.
- Multi-tenant: každý dozor vidí a spravuje pouze své vlastní hry (viz
  spec, oddíl 2 — "Správa dozorů").
- Zadání úkolů je čistě vizuální (obrázky), žádné textové instrukce pro
  hráče — admin formuláře proto nemají pole pro "textový popis úkolu".
- Slug hry musí být unikátní napříč celou aplikací (tvoří URL
  `/hra/{slug}` pro hráče v Plánu B).
- Známé zjednodušení v tomto plánu (zdokumentováno, ne opomenutí): úprava
  existujícího stanoviště typu `pick_image`/foto vyžaduje vždy nové
  nahrání všech obrázků (žádné částečné zachování starých souborů). Je to
  vědomý MVP kompromis kvůli jednoduchosti; případné vylepšení je mimo
  rozsah tohoto plánu.

---

### Task 1: Scaffold projektu

**Files:**
- Create: celá struktura Laravel aplikace (vygenerovaná instalátorem) v
  `/Users/lachmart/www/compas`
- Modify: `tests/Pest.php`

**Interfaces:**
- Consumes: nic (první task)
- Produces: funkční Laravel app s Breeze auth (`login`, `register`,
  `dashboard` routy), Inertia + Vue 3 přes Vite, Pest test runner s
  `RefreshDatabase` pro Feature testy, npm balíček `leaflet` dostupný pro
  import, `php artisan storage:link` vytvořený symlink pro veřejné soubory.

- [ ] **Step 1: Vytvoř Laravel projekt v aktuálním adresáři**

```bash
composer create-project laravel/laravel .
```

Pokud composer nahlásí, že adresář není prázdný (kvůli existujícímu
`.git`, `.idea`, `docs/`), potvrď pokračování — tyto soubory nekolidují se
strukturou Laravelu.

- [ ] **Step 2: Nainstaluj Breeze s Inertia/Vue/Pest stackem**

```bash
composer require laravel/breeze --dev
php artisan breeze:install vue --pest
npm install
```

- [ ] **Step 3: Nainstaluj Leaflet**

```bash
npm install leaflet
```

- [ ] **Step 4: Nastav SQLite databázi**

```bash
touch database/database.sqlite
```

Ověř, že `.env` má `DB_CONNECTION=sqlite` (výchozí u nového Laravel
projektu).

- [ ] **Step 5: Spusť migrace a vytvoř storage symlink**

```bash
php artisan migrate
php artisan storage:link
```

- [ ] **Step 6: Zajisti RefreshDatabase pro Feature testy**

Uprav `tests/Pest.php` tak, aby obsahoval:

```php
<?php

uses(
    Tests\TestCase::class,
    Illuminate\Foundation\Testing\RefreshDatabase::class,
)->in('Feature');
```

- [ ] **Step 7: Ověř, že vše běží**

```bash
php artisan test
```

Expected: výchozí testy nainstalované Breeze projdou (PASS).

```bash
npm run build
```

Expected: build proběhne bez chyb.

- [ ] **Step 8: Commit**

```bash
git add -A
git commit -m "Scaffold Laravel + Breeze (Inertia/Vue, Pest), install Leaflet"
```

---

### Task 2: Enumy, modely, migrace, factories

**Files:**
- Create: `app/Enums/GameStatus.php`
- Create: `app/Enums/TaskType.php`
- Create: `database/migrations/2026_09_22_000001_create_games_table.php`
- Create: `database/migrations/2026_09_22_000002_create_locations_table.php`
- Create: `app/Models/Game.php`
- Create: `app/Models/Location.php`
- Modify: `app/Models/User.php`
- Create: `database/factories/GameFactory.php`
- Create: `database/factories/LocationFactory.php`
- Test: `tests/Unit/Models/GameTest.php`
- Test: `tests/Unit/Models/LocationTest.php`

**Interfaces:**
- Consumes: nic nového (jen skeleton z Task 1)
- Produces: `App\Enums\GameStatus` (`Draft`, `Published`, `Archived`),
  `App\Enums\TaskType` (`ArrivalOnly`, `PickImage`, `PhotoOfSomething`,
  `SelfieDoingSomething`), `App\Models\Game` (`owner()`, `locations()`),
  `App\Models\Location` (`game()`), `User::games()`.

- [ ] **Step 1: Napiš selhávající testy pro modely**

`tests/Unit/Models/GameTest.php`:

```php
<?php

use App\Enums\GameStatus;
use App\Models\Game;
use App\Models\User;

test('game casts status to enum', function () {
    $game = Game::factory()->create(['status' => 'published']);

    expect($game->status)->toBe(GameStatus::Published);
});

test('game belongs to an owner', function () {
    $user = User::factory()->create();
    $game = Game::factory()->create(['owner_id' => $user->id]);

    expect($game->owner->id)->toBe($user->id);
});

test('user has many games', function () {
    $user = User::factory()->create();
    Game::factory()->count(2)->create(['owner_id' => $user->id]);

    expect($user->games)->toHaveCount(2);
});
```

`tests/Unit/Models/LocationTest.php`:

```php
<?php

use App\Enums\TaskType;
use App\Models\Game;
use App\Models\Location;

test('location casts task_type to enum and task_payload to array', function () {
    $location = Location::factory()->create([
        'task_type' => 'pick_image',
        'task_payload' => ['images' => ['a.jpg', 'b.jpg'], 'correct_index' => 0],
    ]);

    expect($location->task_type)->toBe(TaskType::PickImage)
        ->and($location->task_payload)->toBeArray();
});

test('locations belong to a game ordered by order column', function () {
    $game = Game::factory()->create();
    Location::factory()->create(['game_id' => $game->id, 'order' => 2]);
    Location::factory()->create(['game_id' => $game->id, 'order' => 1]);

    expect($game->locations->pluck('order')->all())->toBe([1, 2]);
});
```

- [ ] **Step 2: Spusť testy a ověř selhání**

```bash
php artisan test --filter=GameTest
php artisan test --filter=LocationTest
```

Expected: FAIL (třídy `Game`, `Location` a jejich migrace ještě
neexistují).

- [ ] **Step 3: Vytvoř enumy**

`app/Enums/GameStatus.php`:

```php
<?php

namespace App\Enums;

enum GameStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Archived = 'archived';
}
```

`app/Enums/TaskType.php`:

```php
<?php

namespace App\Enums;

enum TaskType: string
{
    case ArrivalOnly = 'arrival_only';
    case PickImage = 'pick_image';
    case PhotoOfSomething = 'photo_of_something';
    case SelfieDoingSomething = 'selfie_doing_something';
}
```

- [ ] **Step 4: Vytvoř migrace**

```bash
php artisan make:migration create_games_table
php artisan make:migration create_locations_table
```

Přejmenuj vygenerované soubory tak, aby odpovídaly názvům v sekci Files
výše (nebo ponech vygenerovaný timestamp — pořadí games → locations je
důležité kvůli cizímu klíči).

Obsah migrace `games`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('games', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('status')->default('draft');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('games');
    }
};
```

Obsah migrace `locations`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')->constrained('games')->cascadeOnDelete();
            $table->unsignedInteger('order');
            $table->decimal('lat', 10, 7);
            $table->decimal('lng', 10, 7);
            $table->unsignedInteger('arrival_radius_m')->default(15);
            $table->string('task_type');
            $table->json('task_payload')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('locations');
    }
};
```

- [ ] **Step 5: Vytvoř modely**

`app/Models/Game.php`:

```php
<?php

namespace App\Models;

use App\Enums\GameStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Game extends Model
{
    use HasFactory;

    protected $fillable = ['owner_id', 'name', 'slug', 'status'];

    protected $casts = [
        'status' => GameStatus::class,
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function locations(): HasMany
    {
        return $this->hasMany(Location::class)->orderBy('order');
    }
}
```

`app/Models/Location.php`:

```php
<?php

namespace App\Models;

use App\Enums\TaskType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Location extends Model
{
    use HasFactory;

    protected $fillable = ['game_id', 'order', 'lat', 'lng', 'arrival_radius_m', 'task_type', 'task_payload'];

    protected $casts = [
        'task_type' => TaskType::class,
        'task_payload' => 'array',
        'lat' => 'decimal:7',
        'lng' => 'decimal:7',
        'order' => 'integer',
        'arrival_radius_m' => 'integer',
    ];

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }
}
```

Uprav `app/Models/User.php` — přidej dovnitř třídy:

```php
public function games(): \Illuminate\Database\Eloquent\Relations\HasMany
{
    return $this->hasMany(Game::class, 'owner_id');
}
```

- [ ] **Step 6: Vytvoř factories**

```bash
php artisan make:factory GameFactory --model=Game
php artisan make:factory LocationFactory --model=Location
```

`database/factories/GameFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Enums\GameStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class GameFactory extends Factory
{
    public function definition(): array
    {
        $name = $this->faker->words(3, true);

        return [
            'owner_id' => User::factory(),
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::random(6),
            'status' => GameStatus::Draft,
        ];
    }
}
```

`database/factories/LocationFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Enums\TaskType;
use App\Models\Game;
use Illuminate\Database\Eloquent\Factories\Factory;

class LocationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'game_id' => Game::factory(),
            'order' => 1,
            'lat' => $this->faker->latitude(),
            'lng' => $this->faker->longitude(),
            'arrival_radius_m' => 15,
            'task_type' => TaskType::ArrivalOnly,
            'task_payload' => null,
        ];
    }
}
```

- [ ] **Step 7: Spusť migrace a testy**

```bash
php artisan migrate
php artisan test --filter=GameTest
php artisan test --filter=LocationTest
```

Expected: PASS.

- [ ] **Step 8: Commit**

```bash
git add app/Enums app/Models database/migrations database/factories tests/Unit/Models
git commit -m "Add Game and Location domain model"
```

---

### Task 3: SlugGenerator

**Files:**
- Create: `app/Support/SlugGenerator.php`
- Test: `tests/Unit/Support/SlugGeneratorTest.php`

**Interfaces:**
- Consumes: `App\Models\Game` (Task 2)
- Produces: `App\Support\SlugGenerator::unique(string $name): string` —
  použije Task 4 v `GameController@store`.

- [ ] **Step 1: Napiš selhávající test**

`tests/Unit/Support/SlugGeneratorTest.php`:

```php
<?php

use App\Models\Game;
use App\Support\SlugGenerator;

test('generates a slug from the name', function () {
    expect(SlugGenerator::unique('Lesní dobrodružství'))->toBe('lesni-dobrodruzstvi');
});

test('appends a numeric suffix when the slug is taken', function () {
    Game::factory()->create(['slug' => 'lesni-dobrodruzstvi']);

    expect(SlugGenerator::unique('Lesní dobrodružství'))->toBe('lesni-dobrodruzstvi-2');
});

test('increments the suffix past the first collision', function () {
    Game::factory()->create(['slug' => 'lesni-dobrodruzstvi']);
    Game::factory()->create(['slug' => 'lesni-dobrodruzstvi-2']);

    expect(SlugGenerator::unique('Lesní dobrodružství'))->toBe('lesni-dobrodruzstvi-3');
});
```

- [ ] **Step 2: Spusť test a ověř selhání**

```bash
php artisan test --filter=SlugGeneratorTest
```

Expected: FAIL (třída neexistuje).

- [ ] **Step 3: Implementuj SlugGenerator**

`app/Support/SlugGenerator.php`:

```php
<?php

namespace App\Support;

use App\Models\Game;
use Illuminate\Support\Str;

class SlugGenerator
{
    public static function unique(string $name): string
    {
        $base = Str::slug($name) ?: 'hra';
        $slug = $base;
        $suffix = 2;

        while (Game::where('slug', $slug)->exists()) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }
}
```

- [ ] **Step 4: Spusť test a ověř průchod**

```bash
php artisan test --filter=SlugGeneratorTest
```

Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Support tests/Unit/Support
git commit -m "Add unique slug generator for games"
```

---

### Task 4: GamePolicy, GameController, routy, testy

**Files:**
- Create: `app/Policies/GamePolicy.php`
- Modify: `app/Providers/AppServiceProvider.php`
- Modify: `app/Http/Controllers/Controller.php`
- Create: `app/Http/Requests/Admin/StoreGameRequest.php`
- Create: `app/Http/Requests/Admin/UpdateGameRequest.php`
- Create: `app/Http/Controllers/Admin/GameController.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/Admin/GameManagementTest.php`

**Interfaces:**
- Consumes: `App\Models\Game`, `App\Support\SlugGenerator::unique()`
  (Task 2, 3)
- Produces: routy `admin.games.index`, `admin.games.create`,
  `admin.games.store`, `admin.games.edit`, `admin.games.update`,
  `admin.games.destroy`, `admin.games.publish`, `admin.games.unpublish`,
  a `AuthorizesRequests` trait na základní `Controller` třídě (využije ji
  i `LocationController` v Task 5). Task 5 na tyto routy naváže
  vnořenými routami pro lokace (`admin.games.locations.*`), Task 6 je
  zkonzumuje z Vue stránek.

- [ ] **Step 1: Napiš selhávající feature testy**

`tests/Feature/Admin/GameManagementTest.php`:

```php
<?php

use App\Enums\GameStatus;
use App\Models\Game;
use App\Models\User;

test('guests cannot access the games dashboard', function () {
    $this->get(route('admin.games.index'))->assertRedirect(route('login'));
});

test('a dozor can create a game and gets a unique slug', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('admin.games.store'), [
        'name' => 'Lesní dobrodružství',
    ]);

    $game = Game::first();

    $response->assertRedirect(route('admin.games.edit', $game));
    expect($game->owner_id)->toBe($user->id)
        ->and($game->slug)->toBe('lesni-dobrodruzstvi')
        ->and($game->status)->toBe(GameStatus::Draft);
});

test('a dozor only sees their own games', function () {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    Game::factory()->create(['owner_id' => $owner->id]);
    Game::factory()->create(['owner_id' => $stranger->id]);

    $this->actingAs($owner)->get(route('admin.games.index'))
        ->assertInertia(fn ($page) => $page->component('Admin/Games/Index')->has('games', 1));
});

test("a dozor cannot edit another dozor's game", function () {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $game = Game::factory()->create(['owner_id' => $owner->id]);

    $this->actingAs($stranger)->get(route('admin.games.edit', $game))->assertForbidden();
    $this->actingAs($stranger)->put(route('admin.games.update', $game), [
        'name' => 'x', 'slug' => 'x',
    ])->assertForbidden();
});

test('a dozor can update the game name and slug', function () {
    $user = User::factory()->create();
    $game = Game::factory()->create(['owner_id' => $user->id]);

    $this->actingAs($user)->put(route('admin.games.update', $game), [
        'name' => 'Nový název',
        'slug' => 'novy-nazev',
    ])->assertRedirect();

    expect($game->fresh()->name)->toBe('Nový název')
        ->and($game->fresh()->slug)->toBe('novy-nazev');
});

test('a dozor can publish and unpublish a game', function () {
    $user = User::factory()->create();
    $game = Game::factory()->create(['owner_id' => $user->id, 'status' => GameStatus::Draft]);

    $this->actingAs($user)->patch(route('admin.games.publish', $game));
    expect($game->fresh()->status)->toBe(GameStatus::Published);

    $this->actingAs($user)->patch(route('admin.games.unpublish', $game));
    expect($game->fresh()->status)->toBe(GameStatus::Draft);
});

test('a dozor can delete their own game', function () {
    $user = User::factory()->create();
    $game = Game::factory()->create(['owner_id' => $user->id]);

    $this->actingAs($user)->delete(route('admin.games.destroy', $game))
        ->assertRedirect(route('admin.games.index'));

    expect(Game::find($game->id))->toBeNull();
});
```

- [ ] **Step 2: Spusť testy a ověř selhání**

```bash
php artisan test --filter=GameManagementTest
```

Expected: FAIL (routy a controller ještě neexistují).

- [ ] **Step 3: Vytvoř GamePolicy a zaregistruj ji**

`app/Policies/GamePolicy.php`:

```php
<?php

namespace App\Policies;

use App\Models\Game;
use App\Models\User;

class GamePolicy
{
    public function update(User $user, Game $game): bool
    {
        return $user->id === $game->owner_id;
    }

    public function delete(User $user, Game $game): bool
    {
        return $user->id === $game->owner_id;
    }
}
```

V `app/Providers/AppServiceProvider.php` do metody `boot()` přidej:

```php
use App\Models\Game;
use App\Policies\GamePolicy;
use Illuminate\Support\Facades\Gate;

public function boot(): void
{
    Gate::policy(Game::class, GamePolicy::class);
}
```

- [ ] **Step 4: Přidej AuthorizesRequests trait do základní Controller třídy**

Laravel 11+ generuje `app/Http/Controllers/Controller.php` jako prázdnou
abstraktní třídu bez `AuthorizesRequests` traitu, ale `$this->authorize()`
použitý v tomto i dalších tasku ho vyžaduje. Uprav soubor na:

```php
<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

abstract class Controller
{
    use AuthorizesRequests;
}
```

- [ ] **Step 5: Vytvoř Form Requesty**

`app/Http/Requests/Admin/StoreGameRequest.php`:

```php
<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreGameRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
        ];
    }
}
```

`app/Http/Requests/Admin/UpdateGameRequest.php`:

```php
<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateGameRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'required', 'string', 'max:255',
                Rule::unique('games', 'slug')->ignore($this->route('game')),
            ],
        ];
    }
}
```

- [ ] **Step 6: Vytvoř GameController**

`app/Http/Controllers/Admin/GameController.php`:

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Enums\GameStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreGameRequest;
use App\Http\Requests\Admin\UpdateGameRequest;
use App\Models\Game;
use App\Support\SlugGenerator;
use Illuminate\Http\Request;
use Inertia\Inertia;

class GameController extends Controller
{
    public function index(Request $request)
    {
        return Inertia::render('Admin/Games/Index', [
            'games' => $request->user()->games()->latest()->get(),
        ]);
    }

    public function create()
    {
        return Inertia::render('Admin/Games/Create');
    }

    public function store(StoreGameRequest $request)
    {
        $game = $request->user()->games()->create([
            'name' => $request->validated('name'),
            'slug' => SlugGenerator::unique($request->validated('name')),
            'status' => GameStatus::Draft,
        ]);

        return redirect()->route('admin.games.edit', $game);
    }

    public function edit(Game $game)
    {
        $this->authorize('update', $game);

        return Inertia::render('Admin/Games/Edit', [
            'game' => $game->load('locations'),
        ]);
    }

    public function update(UpdateGameRequest $request, Game $game)
    {
        $this->authorize('update', $game);

        $game->update($request->validated());

        return back();
    }

    public function destroy(Game $game)
    {
        $this->authorize('delete', $game);

        $game->delete();

        return redirect()->route('admin.games.index');
    }

    public function publish(Game $game)
    {
        $this->authorize('update', $game);

        $game->update(['status' => GameStatus::Published]);

        return back();
    }

    public function unpublish(Game $game)
    {
        $this->authorize('update', $game);

        $game->update(['status' => GameStatus::Draft]);

        return back();
    }
}
```

- [ ] **Step 7: Přidej routy**

Na konec `routes/web.php` (před `require __DIR__.'/auth.php';` nebo za
ním, dle existující struktury souboru) přidej:

```php
use App\Http\Controllers\Admin\GameController;

Route::middleware('auth')->prefix('admin')->name('admin.')->group(function () {
    Route::get('games', [GameController::class, 'index'])->name('games.index');
    Route::get('games/create', [GameController::class, 'create'])->name('games.create');
    Route::post('games', [GameController::class, 'store'])->name('games.store');
    Route::get('games/{game}/edit', [GameController::class, 'edit'])->name('games.edit');
    Route::put('games/{game}', [GameController::class, 'update'])->name('games.update');
    Route::delete('games/{game}', [GameController::class, 'destroy'])->name('games.destroy');
    Route::patch('games/{game}/publish', [GameController::class, 'publish'])->name('games.publish');
    Route::patch('games/{game}/unpublish', [GameController::class, 'unpublish'])->name('games.unpublish');
});
```

- [ ] **Step 8: Spusť testy a ověř průchod**

```bash
php artisan test --filter=GameManagementTest
```

Expected: PASS. (Testy renderující Inertia stránky projdou, i když Vue
komponenty ještě neexistují — Inertia testovací asserce kontrolují jen
název komponenty a props, ne skutečný render.)

- [ ] **Step 9: Commit**

```bash
git add app/Policies app/Providers app/Http/Controllers/Controller.php app/Http/Requests/Admin app/Http/Controllers/Admin/GameController.php routes/web.php tests/Feature/Admin/GameManagementTest.php
git commit -m "Add game CRUD with ownership policy and publish/unpublish"
```

---

### Task 5: LocationRequest, LocationController, routy, testy

**Files:**
- Create: `app/Http/Requests/Admin/LocationRequest.php`
- Create: `app/Http/Controllers/Admin/LocationController.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/Admin/LocationManagementTest.php`

**Interfaces:**
- Consumes: `App\Models\Game`, `App\Models\Location`, `App\Enums\TaskType`
  (Task 2), routy `admin.games.*` (Task 4)
- Produces: routy `admin.games.locations.store`,
  `admin.games.locations.update`, `admin.games.locations.destroy`,
  `admin.games.locations.reorder`. Task 9 tyto routy zkonzumuje z
  `LocationForm.vue`.

- [ ] **Step 1: Napiš selhávající feature testy**

`tests/Feature/Admin/LocationManagementTest.php`:

```php
<?php

use App\Enums\TaskType;
use App\Models\Game;
use App\Models\Location;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
});

test('a dozor can add an arrival_only location', function () {
    $user = User::factory()->create();
    $game = Game::factory()->create(['owner_id' => $user->id]);

    $this->actingAs($user)->post(route('admin.games.locations.store', $game), [
        'lat' => 50.08,
        'lng' => 14.43,
        'arrival_radius_m' => 15,
        'task_type' => 'arrival_only',
    ])->assertRedirect();

    $location = $game->locations()->first();
    expect($location->task_type)->toBe(TaskType::ArrivalOnly)
        ->and($location->task_payload)->toBeNull()
        ->and($location->order)->toBe(1);
});

test('a dozor can add a pick_image location with uploaded images', function () {
    $user = User::factory()->create();
    $game = Game::factory()->create(['owner_id' => $user->id]);

    $this->actingAs($user)->post(route('admin.games.locations.store', $game), [
        'lat' => 50.08,
        'lng' => 14.43,
        'arrival_radius_m' => 15,
        'task_type' => 'pick_image',
        'images' => [
            UploadedFile::fake()->image('a.jpg'),
            UploadedFile::fake()->image('b.jpg'),
            UploadedFile::fake()->image('c.jpg'),
        ],
        'correct_index' => 1,
    ])->assertRedirect();

    $location = $game->locations()->first();
    expect($location->task_payload['correct_index'])->toBe(1)
        ->and($location->task_payload['images'])->toHaveCount(3);
    Storage::disk('public')->assertExists($location->task_payload['images'][0]);
});

test('pick_image requires at least three images', function () {
    $user = User::factory()->create();
    $game = Game::factory()->create(['owner_id' => $user->id]);

    $this->actingAs($user)->post(route('admin.games.locations.store', $game), [
        'lat' => 50.08,
        'lng' => 14.43,
        'arrival_radius_m' => 15,
        'task_type' => 'pick_image',
        'images' => [UploadedFile::fake()->image('a.jpg')],
        'correct_index' => 0,
    ])->assertSessionHasErrors('images');
});

test('a dozor can add a photo_of_something location with a reference image', function () {
    $user = User::factory()->create();
    $game = Game::factory()->create(['owner_id' => $user->id]);

    $this->actingAs($user)->post(route('admin.games.locations.store', $game), [
        'lat' => 50.08,
        'lng' => 14.43,
        'arrival_radius_m' => 15,
        'task_type' => 'photo_of_something',
        'reference_image' => UploadedFile::fake()->image('ref.jpg'),
    ])->assertRedirect();

    $location = $game->locations()->first();
    Storage::disk('public')->assertExists($location->task_payload['reference_image']);
});

test('a dozor can reorder locations', function () {
    $user = User::factory()->create();
    $game = Game::factory()->create(['owner_id' => $user->id]);
    $first = Location::factory()->create(['game_id' => $game->id, 'order' => 1]);
    $second = Location::factory()->create(['game_id' => $game->id, 'order' => 2]);

    $this->actingAs($user)->patch(route('admin.games.locations.reorder', $game), [
        'order' => [$second->id, $first->id],
    ])->assertRedirect();

    expect($second->fresh()->order)->toBe(1)
        ->and($first->fresh()->order)->toBe(2);
});

test("a dozor cannot modify locations of another dozor's game", function () {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $game = Game::factory()->create(['owner_id' => $owner->id]);

    $this->actingAs($stranger)->post(route('admin.games.locations.store', $game), [
        'lat' => 50.08,
        'lng' => 14.43,
        'arrival_radius_m' => 15,
        'task_type' => 'arrival_only',
    ])->assertForbidden();
});

test('a dozor can delete a location', function () {
    $user = User::factory()->create();
    $game = Game::factory()->create(['owner_id' => $user->id]);
    $location = Location::factory()->create(['game_id' => $game->id]);

    $this->actingAs($user)->delete(route('admin.games.locations.destroy', [$game, $location]))
        ->assertRedirect();

    expect(Location::find($location->id))->toBeNull();
});
```

- [ ] **Step 2: Spusť testy a ověř selhání**

```bash
php artisan test --filter=LocationManagementTest
```

Expected: FAIL (routy a controller ještě neexistují).

- [ ] **Step 3: Vytvoř LocationRequest**

`app/Http/Requests/Admin/LocationRequest.php`:

```php
<?php

namespace App\Http\Requests\Admin;

use App\Enums\TaskType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LocationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
            'arrival_radius_m' => ['required', 'integer', 'min:5', 'max:200'],
            'task_type' => ['required', Rule::enum(TaskType::class)],
        ];

        return match ($this->input('task_type')) {
            TaskType::PickImage->value => $rules + [
                'images' => ['required', 'array', 'min:3', 'max:6'],
                'images.*' => ['image', 'max:5120'],
                'correct_index' => ['required', 'integer', 'min:0', function ($attribute, $value, $fail) {
                    $count = count($this->file('images', []));
                    if ($value >= $count) {
                        $fail('correct_index must reference one of the uploaded images.');
                    }
                }],
            ],
            TaskType::PhotoOfSomething->value, TaskType::SelfieDoingSomething->value => $rules + [
                'reference_image' => ['required', 'image', 'max:5120'],
            ],
            default => $rules,
        };
    }
}
```

- [ ] **Step 4: Vytvoř LocationController**

`app/Http/Controllers/Admin/LocationController.php`:

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Enums\TaskType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\LocationRequest;
use App\Models\Game;
use App\Models\Location;
use Illuminate\Http\Request;

class LocationController extends Controller
{
    public function store(LocationRequest $request, Game $game)
    {
        $this->authorize('update', $game);

        $game->locations()->create([
            'lat' => $request->validated('lat'),
            'lng' => $request->validated('lng'),
            'arrival_radius_m' => $request->validated('arrival_radius_m'),
            'task_type' => $request->validated('task_type'),
            'task_payload' => $this->buildTaskPayload($request),
            'order' => $game->locations()->max('order') + 1,
        ]);

        return back();
    }

    public function update(LocationRequest $request, Game $game, Location $location)
    {
        $this->authorize('update', $game);

        $location->update([
            'lat' => $request->validated('lat'),
            'lng' => $request->validated('lng'),
            'arrival_radius_m' => $request->validated('arrival_radius_m'),
            'task_type' => $request->validated('task_type'),
            'task_payload' => $this->buildTaskPayload($request),
        ]);

        return back();
    }

    public function destroy(Game $game, Location $location)
    {
        $this->authorize('update', $game);

        $location->delete();

        return back();
    }

    public function reorder(Request $request, Game $game)
    {
        $this->authorize('update', $game);

        $request->validate([
            'order' => ['required', 'array'],
            'order.*' => ['integer', 'exists:locations,id'],
        ]);

        foreach ($request->input('order') as $index => $locationId) {
            Location::where('id', $locationId)
                ->where('game_id', $game->id)
                ->update(['order' => $index + 1]);
        }

        return back();
    }

    private function buildTaskPayload(LocationRequest $request): ?array
    {
        return match (TaskType::from($request->validated('task_type'))) {
            TaskType::ArrivalOnly => null,
            TaskType::PickImage => [
                'images' => collect($request->file('images'))
                    ->map(fn ($file) => $file->store('task-images', 'public'))
                    ->values()
                    ->all(),
                'correct_index' => (int) $request->validated('correct_index'),
            ],
            TaskType::PhotoOfSomething, TaskType::SelfieDoingSomething => [
                'reference_image' => $request->file('reference_image')->store('task-images', 'public'),
            ],
        };
    }
}
```

- [ ] **Step 5: Přidej routy**

Do stejné skupiny v `routes/web.php` z Task 4 (uvnitř
`prefix('admin')->name('admin.')`) přidej:

```php
use App\Http\Controllers\Admin\LocationController;

Route::post('games/{game}/locations', [LocationController::class, 'store'])->name('games.locations.store');
Route::patch('games/{game}/locations/reorder', [LocationController::class, 'reorder'])->name('games.locations.reorder');

Route::scopeBindings()->group(function () {
    Route::put('games/{game}/locations/{location}', [LocationController::class, 'update'])->name('games.locations.update');
    Route::delete('games/{game}/locations/{location}', [LocationController::class, 'destroy'])->name('games.locations.destroy');
});
```

`update`/`destroy` musí být v `Route::scopeBindings()` — jinak
`$this->authorize('update', $game)` ověří jen vlastnictví hry, ne že
`{location}` do ní skutečně patří (cross-tenant IDOR: dozor by mohl
mazat/přepisovat cizí stanoviště přes cizí ID v URL vlastní hry).
`scopeBindings()` využije existující `Game::locations()` relaci a
vrátí 404, pokud stanoviště nepatří k dané hře.

- [ ] **Step 6: Spusť testy a ověř průchod**

```bash
php artisan test --filter=LocationManagementTest
```

Expected: PASS.

- [ ] **Step 7: Spusť celou testovací sadu**

```bash
php artisan test
```

Expected: PASS (všechny dosavadní testy).

- [ ] **Step 8: Commit**

```bash
git add app/Http/Requests/Admin/LocationRequest.php app/Http/Controllers/Admin/LocationController.php routes/web.php tests/Feature/Admin/LocationManagementTest.php
git commit -m "Add location CRUD with per-task-type validation and reordering"
```

---

### Task 6: Vue stránky pro hry (seznam, vytvoření, editace)

**Files:**
- Create: `resources/js/Pages/Admin/Games/Index.vue`
- Create: `resources/js/Pages/Admin/Games/Create.vue`
- Create: `resources/js/Pages/Admin/Games/Edit.vue`

**Interfaces:**
- Consumes: routy `admin.games.*` (Task 4), Breeze komponenta
  `@/Layouts/AuthenticatedLayout.vue`
- Produces: funkční admin obrazovky pro správu her. Task 9 rozšíří
  `Edit.vue` o `<LocationList>`.

- [ ] **Step 1: Vytvoř Index.vue**

`resources/js/Pages/Admin/Games/Index.vue`:

```vue
<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    games: Array,
});
</script>

<template>
    <Head title="Moje hry" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">Moje hry</h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-4xl sm:px-6 lg:px-8">
                <Link :href="route('admin.games.create')" class="mb-4 inline-block rounded bg-indigo-600 px-4 py-2 text-white">
                    Nová hra
                </Link>

                <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                    <ul>
                        <li v-for="game in games" :key="game.id" class="flex items-center justify-between border-b p-4 last:border-0">
                            <div>
                                <Link :href="route('admin.games.edit', game.id)" class="font-medium text-indigo-600">
                                    {{ game.name }}
                                </Link>
                                <span class="ml-2 text-sm text-gray-500">({{ game.status }})</span>
                            </div>
                        </li>
                        <li v-if="games.length === 0" class="p-4 text-gray-500">Zatím žádné hry.</li>
                    </ul>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
```

- [ ] **Step 2: Vytvoř Create.vue**

`resources/js/Pages/Admin/Games/Create.vue`:

```vue
<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';

const form = useForm({ name: '' });

const submit = () => form.post(route('admin.games.store'));
</script>

<template>
    <Head title="Nová hra" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">Nová hra</h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-xl sm:px-6 lg:px-8">
                <form @submit.prevent="submit" class="space-y-4 bg-white p-6 shadow-sm sm:rounded-lg">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Název hry</label>
                        <input v-model="form.name" type="text" class="mt-1 block w-full rounded border-gray-300" />
                        <p v-if="form.errors.name" class="mt-1 text-sm text-red-600">{{ form.errors.name }}</p>
                    </div>

                    <button type="submit" :disabled="form.processing" class="rounded bg-indigo-600 px-4 py-2 text-white">
                        Vytvořit
                    </button>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
```

- [ ] **Step 3: Vytvoř Edit.vue**

`resources/js/Pages/Admin/Games/Edit.vue`:

```vue
<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, router } from '@inertiajs/vue3';

const props = defineProps({
    game: Object,
});

const form = useForm({
    name: props.game.name,
    slug: props.game.slug,
});

const submit = () => form.put(route('admin.games.update', props.game.id));

const togglePublish = () => {
    const routeName = props.game.status === 'published' ? 'admin.games.unpublish' : 'admin.games.publish';
    router.patch(route(routeName, props.game.id));
};
</script>

<template>
    <Head :title="game.name" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">{{ game.name }}</h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-4xl space-y-6 sm:px-6 lg:px-8">
                <form @submit.prevent="submit" class="space-y-4 bg-white p-6 shadow-sm sm:rounded-lg">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Název hry</label>
                        <input v-model="form.name" type="text" class="mt-1 block w-full rounded border-gray-300" />
                        <p v-if="form.errors.name" class="mt-1 text-sm text-red-600">{{ form.errors.name }}</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">URL (slug)</label>
                        <input v-model="form.slug" type="text" class="mt-1 block w-full rounded border-gray-300" />
                        <p v-if="form.errors.slug" class="mt-1 text-sm text-red-600">{{ form.errors.slug }}</p>
                    </div>

                    <div class="flex items-center gap-4">
                        <button type="submit" :disabled="form.processing" class="rounded bg-indigo-600 px-4 py-2 text-white">
                            Uložit
                        </button>
                        <button
                            type="button"
                            @click="togglePublish"
                            class="rounded px-4 py-2"
                            :class="game.status === 'published' ? 'bg-gray-200' : 'bg-green-600 text-white'"
                        >
                            {{ game.status === 'published' ? 'Skrýt hru' : 'Publikovat' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
```

- [ ] **Step 4: Ruční ověření v prohlížeči**

```bash
composer run dev
```

(Nebo `php artisan serve` a `npm run dev` ve dvou terminálech, pokud
composer.json skript `dev` neexistuje.)

V prohlížeči: zaregistruj dozora, přejdi na `/admin/games`, vytvoř hru
"Lesní stezka", ověř přesměrování na editační stránku, změň název a slug,
ulož, přepni publikováno/skryto.

- [ ] **Step 5: Commit**

```bash
git add resources/js/Pages/Admin/Games
git commit -m "Add admin Vue pages for game list, create and edit"
```

---

### Task 7: MapPicker komponenta

**Files:**
- Create: `resources/js/Components/Admin/MapPicker.vue`

**Interfaces:**
- Consumes: npm balíček `leaflet` (Task 1)
- Produces: komponenta `MapPicker` s `v-model` (`{ lat, lng }`
  přes `modelValue`/`update:modelValue`), tlačítkem "Použít moji
  aktuální polohu" a klikatelnou mapou. Použije ji Task 9 v
  `LocationForm.vue`.

- [ ] **Step 1: Vytvoř komponentu**

`resources/js/Components/Admin/MapPicker.vue`:

```vue
<script setup>
import { onMounted, onBeforeUnmount, ref } from 'vue';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

const props = defineProps({
    modelValue: { type: Object, default: () => ({ lat: null, lng: null }) },
});
const emit = defineEmits(['update:modelValue']);

const mapEl = ref(null);
let map;
let marker;

const setPosition = (lat, lng) => {
    emit('update:modelValue', { lat, lng });

    if (marker) {
        marker.setLatLng([lat, lng]);
    } else {
        marker = L.marker([lat, lng]).addTo(map);
    }
};

onMounted(() => {
    const start = props.modelValue.lat
        ? [props.modelValue.lat, props.modelValue.lng]
        : [50.0755, 14.4378];

    map = L.map(mapEl.value).setView(start, 16);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap',
    }).addTo(map);

    if (props.modelValue.lat) {
        setPosition(props.modelValue.lat, props.modelValue.lng);
    }

    map.on('click', (e) => setPosition(e.latlng.lat, e.latlng.lng));
});

onBeforeUnmount(() => map?.remove());

const useMyLocation = () => {
    navigator.geolocation.getCurrentPosition((position) => {
        const { latitude, longitude } = position.coords;
        map.setView([latitude, longitude], 18);
        setPosition(latitude, longitude);
    });
};
</script>

<template>
    <div>
        <div ref="mapEl" class="h-64 w-full rounded border"></div>
        <button type="button" @click="useMyLocation" class="mt-2 rounded bg-gray-200 px-3 py-1 text-sm">
            Použít moji aktuální polohu
        </button>
    </div>
</template>
```

- [ ] **Step 2: Ruční ověření**

Leaflet potřebuje reálný DOM/canvas, takže automatizovaný unit test by
byl křehký — ověř ručně: dočasně vlož `<MapPicker v-model="pos" />` do
`Edit.vue` (nebo počkej na Task 9, kde se zapojí natrvalo), spusť dev
server, klikni na mapu, potvrď že se zobrazí marker.

- [ ] **Step 3: Commit**

```bash
git add resources/js/Components/Admin/MapPicker.vue
git commit -m "Add Leaflet-based map picker component"
```

---

### Task 8: TaskContentFields a LocationForm

**Files:**
- Create: `resources/js/Components/Admin/TaskContentFields.vue`
- Create: `resources/js/Components/Admin/LocationForm.vue`

**Interfaces:**
- Consumes: `MapPicker` (Task 7), routy `admin.games.locations.store` /
  `admin.games.locations.update` (Task 5)
- Produces: komponenta `LocationForm` s props `game` (Object) a
  `location` (Object|null, `null` = nové stanoviště), eventy `saved` a
  `cancel`. Použije ji Task 9 v `LocationList.vue`.

- [ ] **Step 1: Vytvoř TaskContentFields.vue**

`resources/js/Components/Admin/TaskContentFields.vue`:

```vue
<script setup>
const props = defineProps({
    taskType: String,
    form: Object,
});

const onImagesChange = (event) => {
    props.form.images = Array.from(event.target.files);
};

const onReferenceImageChange = (event) => {
    props.form.reference_image = event.target.files[0];
};
</script>

<template>
    <div v-if="taskType === 'pick_image'" class="space-y-2">
        <label class="block text-sm font-medium text-gray-700">Obrázky (3–6), vyber správný</label>
        <input type="file" accept="image/*" multiple @change="onImagesChange" />
        <div v-if="form.images?.length" class="flex flex-wrap gap-2">
            <label v-for="(image, index) in form.images" :key="index" class="flex flex-col items-center gap-1">
                <img :src="URL.createObjectURL(image)" class="h-20 w-20 rounded object-cover" />
                <input type="radio" :value="index" v-model.number="form.correct_index" />
            </label>
        </div>
        <p v-if="form.errors.images" class="text-sm text-red-600">{{ form.errors.images }}</p>
        <p v-if="form.errors.correct_index" class="text-sm text-red-600">{{ form.errors.correct_index }}</p>
    </div>

    <div v-else-if="taskType === 'photo_of_something' || taskType === 'selfie_doing_something'" class="space-y-2">
        <label class="block text-sm font-medium text-gray-700">Referenční obrázek (ukazuje, co má dítě udělat)</label>
        <input type="file" accept="image/*" @change="onReferenceImageChange" />
        <p v-if="form.errors.reference_image" class="text-sm text-red-600">{{ form.errors.reference_image }}</p>
    </div>

    <p v-else class="text-sm text-gray-500">Tento typ stanoviště nevyžaduje žádný obsah — splní se pouhým příchodem.</p>
</template>
```

- [ ] **Step 2: Vytvoř LocationForm.vue**

`resources/js/Components/Admin/LocationForm.vue`:

```vue
<script setup>
import { computed } from 'vue';
import { useForm } from '@inertiajs/vue3';
import MapPicker from '@/Components/Admin/MapPicker.vue';
import TaskContentFields from '@/Components/Admin/TaskContentFields.vue';

const props = defineProps({
    game: Object,
    location: { type: Object, default: null },
});
const emit = defineEmits(['saved', 'cancel']);

const form = useForm({
    lat: props.location?.lat ?? null,
    lng: props.location?.lng ?? null,
    arrival_radius_m: props.location?.arrival_radius_m ?? 15,
    task_type: props.location?.task_type ?? 'arrival_only',
    images: [],
    correct_index: props.location?.task_payload?.correct_index ?? 0,
    reference_image: null,
});

const position = computed({
    get: () => ({ lat: form.lat, lng: form.lng }),
    set: ({ lat, lng }) => {
        form.lat = lat;
        form.lng = lng;
    },
});

const submit = () => {
    const options = { forceFormData: true, onSuccess: () => emit('saved') };

    if (props.location) {
        form.transform((data) => ({ ...data, _method: 'put' })).post(
            route('admin.games.locations.update', [props.game.id, props.location.id]),
            options,
        );
    } else {
        form.post(route('admin.games.locations.store', props.game.id), options);
    }
};
</script>

<template>
    <form @submit.prevent="submit" class="space-y-4 rounded border p-4">
        <MapPicker v-model="position" />
        <p v-if="form.errors.lat" class="text-sm text-red-600">{{ form.errors.lat }}</p>

        <div>
            <label class="block text-sm font-medium text-gray-700">Poloměr "dorazil jsem" (m)</label>
            <input v-model.number="form.arrival_radius_m" type="number" min="5" max="200" class="mt-1 block w-32 rounded border-gray-300" />
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700">Typ úkolu</label>
            <select v-model="form.task_type" class="mt-1 block w-full rounded border-gray-300">
                <option value="arrival_only">Jen dojít</option>
                <option value="pick_image">Vybrat obrázek</option>
                <option value="photo_of_something">Vyfotit něco</option>
                <option value="selfie_doing_something">Vyfotit se při činnosti</option>
            </select>
        </div>

        <TaskContentFields :task-type="form.task_type" :form="form" />

        <div class="flex gap-2">
            <button type="submit" :disabled="form.processing" class="rounded bg-indigo-600 px-4 py-2 text-white">
                Uložit stanoviště
            </button>
            <button type="button" @click="emit('cancel')" class="rounded bg-gray-200 px-4 py-2">Zrušit</button>
        </div>
    </form>
</template>
```

- [ ] **Step 3: Commit**

```bash
git add resources/js/Components/Admin/TaskContentFields.vue resources/js/Components/Admin/LocationForm.vue
git commit -m "Add task content fields and location form component"
```

---

### Task 9: LocationList a zapojení do Edit.vue

**Files:**
- Create: `resources/js/Components/Admin/LocationList.vue`
- Modify: `resources/js/Pages/Admin/Games/Edit.vue`

**Interfaces:**
- Consumes: `LocationForm` (Task 8), routy
  `admin.games.locations.destroy` / `admin.games.locations.reorder`
  (Task 5), prop `game.locations` (Task 4's `edit()` už dělá
  `$game->load('locations')`)
- Produces: kompletní admin UI pro správu stanovišť v rámci hry — konec
  Plánu A.

- [ ] **Step 1: Vytvoř LocationList.vue**

`resources/js/Components/Admin/LocationList.vue`:

```vue
<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import LocationForm from '@/Components/Admin/LocationForm.vue';

const props = defineProps({
    game: Object,
    locations: Array,
});

const editingLocation = ref(null);
const isAdding = ref(false);
const draggedId = ref(null);

const remove = (location) => {
    router.delete(route('admin.games.locations.destroy', [props.game.id, location.id]));
};

const onDragStart = (location) => { draggedId.value = location.id; };
const onDrop = (targetLocation) => {
    const ids = props.locations.map((l) => l.id);
    const from = ids.indexOf(draggedId.value);
    const to = ids.indexOf(targetLocation.id);
    ids.splice(to, 0, ids.splice(from, 1)[0]);

    router.patch(route('admin.games.locations.reorder', props.game.id), { order: ids });
};

const closeForm = () => {
    editingLocation.value = null;
    isAdding.value = false;
};
</script>

<template>
    <div class="space-y-4">
        <ul class="divide-y rounded border bg-white">
            <li
                v-for="location in locations"
                :key="location.id"
                draggable="true"
                @dragstart="onDragStart(location)"
                @dragover.prevent
                @drop="onDrop(location)"
                class="flex items-center justify-between p-3"
            >
                <span>#{{ location.order }} — {{ location.task_type }}</span>
                <span class="space-x-2">
                    <button type="button" class="text-indigo-600" @click="editingLocation = location">Upravit</button>
                    <button type="button" class="text-red-600" @click="remove(location)">Smazat</button>
                </span>
            </li>
            <li v-if="locations.length === 0" class="p-3 text-gray-500">Zatím žádná stanoviště.</li>
        </ul>

        <button v-if="!isAdding && !editingLocation" type="button" class="rounded bg-indigo-600 px-4 py-2 text-white" @click="isAdding = true">
            Přidat stanoviště
        </button>

        <LocationForm v-if="isAdding" :game="game" @saved="closeForm" @cancel="closeForm" />
        <LocationForm v-if="editingLocation" :game="game" :location="editingLocation" @saved="closeForm" @cancel="closeForm" />
    </div>
</template>
```

- [ ] **Step 2: Zapoj do Edit.vue**

V `resources/js/Pages/Admin/Games/Edit.vue` přidej import a komponentu
za formulář s názvem/slugem hry:

```vue
<script setup>
import LocationList from '@/Components/Admin/LocationList.vue';
// ...zbytek stávajících importů beze změny
</script>
```

```vue
<div class="mx-auto max-w-4xl space-y-6 sm:px-6 lg:px-8">
    <form @submit.prevent="submit" class="space-y-4 bg-white p-6 shadow-sm sm:rounded-lg">
        <!-- ...stávající obsah formuláře beze změny... -->
    </form>

    <div class="bg-white p-6 shadow-sm sm:rounded-lg">
        <h3 class="mb-4 text-lg font-medium text-gray-800">Stanoviště</h3>
        <LocationList :game="game" :locations="game.locations" />
    </div>
</div>
```

- [ ] **Step 3: Commit**

```bash
git add resources/js/Components/Admin/LocationList.vue resources/js/Pages/Admin/Games/Edit.vue
git commit -m "Add location list with drag reorder to game edit page"
```

---

### Task 10: Ruční end-to-end ověření

**Files:** žádné (pouze manuální QA průchod)

**Interfaces:**
- Consumes: celou aplikaci vytvořenou v Task 1–9
- Produces: potvrzení, že Plán A je funkčně kompletní a připravený jako
  základ pro Plán B (herní engine hráče)

- [ ] **Step 1: Spusť aplikaci**

```bash
composer run dev
```

- [ ] **Step 2: Projdi scénář v prohlížeči**

1. Zaregistruj nový dozor účet, přihlas se.
2. Na `/admin/games` vytvoř hru "Lesní stezka".
3. Přidej stanoviště typu "Jen dojít" — klikni na mapu, ulož.
4. Přidej stanoviště typu "Vybrat obrázek" — nahraj 3 libovolné obrázky,
   označ jeden jako správný, ulož.
5. Přidej stanoviště typu "Vyfotit něco" — nahraj referenční obrázek,
   ulož.
6. Přidej stanoviště typu "Vyfotit se při činnosti" — nahraj referenční
   obrázek, ulož.
7. Přetáhni pořadí stanovišť (drag & drop) a ověř, že se pořadí uloží po
   refreshi stránky.
8. Smaž jedno stanoviště, ověř že zmizelo.
9. Klikni "Publikovat", ověř že se tlačítko/text změnil na "Skrýt hru".
10. Zkus se odhlásit, zaregistrovat druhý dozor účet a ověř, že tento
    účet hru "Lesní stezka" na `/admin/games` nevidí.

Expected: všechny kroky projdou bez chyb v konzoli prohlížeče a bez
serverových 500 chyb.

- [ ] **Step 3: Spusť celou automatizovanou testovací sadu**

```bash
php artisan test
```

Expected: PASS (všechny testy z Task 2–5).

- [ ] **Step 4: Commit (pokud při ručním testování vznikly opravy)**

Pokud ruční test odhalil bug, oprav ho, přidej/uprav odpovídající Pest
test, a commitni:

```bash
git add -A
git commit -m "Fix issues found during manual end-to-end verification"
```

Pokud žádné opravy nebyly potřeba, tento krok přeskoč — Plán A je hotový.
