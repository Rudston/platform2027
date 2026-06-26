# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Overview

Laravel 12 application (PHP 8.2) with **Livewire 4** for the interactive front end, Tailwind CSS 4, and Vite 7. Currently early-stage: the app is close to a fresh Laravel skeleton plus a Livewire setup.

## Commands

```bash
composer dev          # Run everything at once: PHP server + queue listener + Pail logs + Vite (via concurrently)
composer setup        # First-time setup: install deps, copy .env, key:generate, migrate, npm install + build
composer test         # Clears config, then runs the full test suite

php artisan test --filter=CounterTest          # Run one test class
php artisan test --filter=test_counter_increments   # Run one test method
php artisan test tests/Feature/CounterTest.php # Run one file

vendor/bin/pint       # Format code (Laravel Pint; uses default ruleset, no pint.json)
vendor/bin/pint --test  # Check formatting without modifying

npm run dev           # Vite dev server only (HMR)
npm run build         # Production asset build (writes public/build/manifest.json)
```

`php artisan serve` alone is usually not enough — without Vite running (or a prior `npm run build`) the `@vite(...)` directive errors because there's no manifest.

## Livewire 4 conventions (important — differ from Livewire 3)

This project uses Livewire **4**, whose conventions changed from the widely-documented v3. Getting these wrong is the most common mistake here:

- **Single-file components are the default.** `php artisan make:livewire Foo` generates one Blade file at `resources/views/components/⚡foo.blade.php` (note the literal ⚡ prefix in the filename) containing an inline `new class extends Component { ... }` PHP block followed by the markup. There is **no** separate `app/Livewire/Foo.php` class unless you convert with `php artisan livewire:convert`.
- **Full-page routing uses the `Route::livewire()` macro**, since single-file components have no addressable class:
  ```php
  Route::livewire('/counter', 'counter');   // routes/web.php
  ```
  `Route::get('/counter', Counter::class)` does **not** work for single-file components.
- **Page layouts live in `resources/views/layouts/`**, resolved via the `layouts::app` view namespace (configured as `component_layout` in Livewire's config), **not** `resources/views/components/layouts/`. The layout renders the page component into `{{ $slot }}`.
- Livewire 4 **auto-injects** its own JS/CSS — do not add `@livewireStyles`/`@livewireScripts`. The layout only needs `@vite(['resources/css/app.css', 'resources/js/app.js'])`.

Test Livewire components with `Livewire::test('component-name')->call('method')->assertSet('prop', value)`. See `tests/Feature/CounterTest.php`.

## Architecture notes

- **Laravel 12 streamlined structure**: there is no `app/Http/Kernel.php`. Middleware, routing, and exception handling are all registered in `bootstrap/app.php` via the `Application::configure()` fluent API. Add global/route middleware in the `withMiddleware()` closure there, not in a Kernel.
- A health-check endpoint is exposed at `/up`.
- Tailwind 4 has **no `tailwind.config.js`** — theme config lives in `resources/css/app.css` via `@import 'tailwindcss'`, `@theme`, and `@source` directives. `@source '../**/*.blade.php'` already scans all Blade files (including Livewire components), so class purging is not a concern.

## Databases

The app uses **two separate MySQL connections**, both defined in `config/database.php` and served by the local **MAMP MySQL**:

| Connection | Database | Env prefix | Role |
|------------|----------|------------|------|
| `mysql` (default) | `platform2027` | `DB_*` | Primary application database |
| `vision_summit` | `vision_summit` | `DB_VISION_*` | Secondary database |

Use the secondary connection explicitly, e.g. `DB::connection('vision_summit')->...` or `protected $connection = 'vision_summit';` on a model. The default connection (`DB_CONNECTION=mysql`) backs Eloquent, sessions, cache, and queues unless overridden.

Other contexts use different drivers:
- **Tests**: `phpunit.xml` overrides to `DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:`, `SESSION_DRIVER=array` — no MySQL needed, which is why tests pass even when the browser 500s.
- **`.env.example`**: defaults to `DB_CONNECTION=sqlite` (a `database/database.sqlite` file exists), diverging from the committed `.env`'s MySQL setup.

**⚠️ Connecting from the CLI — the socket / port gotcha.** MAMP MySQL actually listens on a Unix **socket** and on **TCP 3306** (not 8889). With `DB_HOST=localhost`, PHP connects via the **socket and ignores `DB_PORT`** entirely — so the committed `DB_PORT=8889` is effectively unused and misleading (`localhost` ≠ TCP). The browser app works because MAMP's PHP knows MAMP's socket, but `php artisan` from a normal terminal uses a *different* default socket and fails with `MySQL server has gone away`. **Fix in place:** `.env` sets `DB_SOCKET=/Applications/MAMP/tmp/mysql/mysql.sock`, which points both connections at MAMP's server (a set `DB_SOCKET` overrides host/port). `.env` is gitignored, so set this locally if connecting via a fresh checkout. Alternative: `DB_HOST=127.0.0.1` + `DB_PORT=3306` to force TCP.

## Demography (South African geography data)

A geography hierarchy imported from the legacy `vision_summit` database into `platform2027`. Models live in `app/Models/Demography/` (namespace `App\Models\Demography`).

| Model | Table | Belongs to | Has many |
|-------|-------|------------|----------|
| `Country` | `countries` | — | provinces |
| `Province` | `provinces` | **country** | districtMunicipalities, localMunicipalities, cities |
| `DistrictMunicipality` | `district_municipalities` | province, **mainCity** (`main_city_id`) | cities, localMunicipalities |
| `LocalMunicipality` | `local_municipalities` | province, districtMunicipality | **mainPlaces** |
| `City` | `cities` | province, districtMunicipality | urbanPlaces, **mainPlaces** |
| `UrbanPlace` | `urban_places` | city | — |
| `MainPlace` | `main_places` | localMunicipality, city | — |
| `CoordinateData` | `coordinate_data` | province *(loose, no FK constraint)* | — |

Notes on the schema:
- **`coordinate_data` is a standalone ~12.7k-row coordinate lookup.** Its `city` is a plain string (not a relation), and `province_id` is indexed but **not** FK-constrained.
- **Circular FK** between `cities` and `district_municipalities` (a city belongs to a district; a district has a main city) is resolved by adding `main_city_id` in a *separate later migration* (`..._add_main_city_to_district_municipalities_table`) after `cities` exists. Replicate this split-migration pattern for any future circular FK.
- All FK columns are **nullable** to tolerate the legacy `0` sentinel (converted to `NULL` on import).
- The legacy `Location` table and its `ClassName` columns were deliberately **excluded**; do not reintroduce them without reason.
- **`main_places`** (~14k rows) links to `local_municipalities` and `cities` by **matching `code`** (codes are unique in both). The `local_municipality_id` / `city_id` FKs are populated by a one-off command **after** the columns migration: `php artisan demography:link-main-places` (`app/Console/Commands/LinkMainPlaces.php`, uses `UPDATE … JOIN` on `code`, idempotent). Every place matches exactly one — 13,390 local-muni, 649 city, 0 both, 0 unlinked.
- **`countries`** was imported externally (no Laravel migration). The `Country` model sets `$timestamps = false` (the table has none). `provinces.country_id` was added by migration and backfilled to **191** (South Africa) for all rows — no permanent column default, so future provinces must set it explicitly.

### Re-importing from vision_summit

The import is driven by seeders in `database/seeders/Demography/`, orchestrated by `DemographyDatabaseSeeder` (reads via `DB::connection('vision_summit')`, writes to the default connection):

```bash
php artisan migrate:fresh   # tables must be EMPTY first — see below
php artisan db:seed --class="Database\Seeders\Demography\DemographyDatabaseSeeder"
```

Critical constraints when touching this import:
- **Original IDs are preserved** (legacy `ID` → `id`) so cross-table foreign keys line up. This makes the seeders **one-shot into empty tables** — re-running over existing data collides on primary keys. Always `migrate:fresh` (or truncate) before re-seeding.
- Seeder order is **FK-safe and must be maintained**: provinces → district_municipalities → local_municipalities → cities → urban_places → coordinate_data → then a **second pass** (`DistrictMunicipalityMainCitySeeder`) fills `main_city_id` once all cities exist.
- `created_at`/`updated_at` are set to `now()` at import time (legacy `Created`/`LastEdited` are intentionally not carried over).
- Requires the MAMP MySQL (`vision_summit`) to be running. The import is **read-only** against `vision_summit`.

## Circles & Services

A **Circle** is a polymorphic, self-nesting collaborative container that wraps any *community* type (Organisation, Campaign, Course, Event, LocationCommunity, ThemeCommunity). A **Service** is a functional module (e.g. Manage Events) that Circles attach via a many-to-many pivot.

**Tables** (migrations `2026_06_19_000001`–`000003`, run & live):
- `circles` — `morphs('circleable')` owner, **`morphs('locatable')` (NOT NULL — every circle has a location)**, self-ref `parent_id`, `depth`, `path` (materialised path). The `locatable` columns were added by `2026_06_23_000003_add_locatable_to_circles_table`.
- `services` — `name`, unique `key`, `handler_class`, `is_active`
- `circle_service` — pivot with `config` (json), `is_active`, unique `(circle_id, service_id)`

**Key classes:**
| File | Role |
|------|------|
| `app/Models/Circles/Circle.php` | morphTo `circleable` + `locatable`, `parent`/`children`, `services` (m2m), `ancestors()`/`descendants()`, `isNestedIn()` |
| `app/Models/Circles/Service.php` | `circles` (m2m) |
| `app/Contracts/Circleable.php` | contract for circle-owning models (`circle`, `hasService`, `isNestedIn`, `getCircleName`, `getCircleDescription`) |
| `app/Contracts/Locatable.php` | contract for located models (`location`, `locatedIn`, `setLocation`) |
| `app/Contracts/CircleServiceContract.php` | contract for service handlers (`boot`, `getKey`, `getPermissions`) |
| `app/Traits/HasCircle.php` | implements `Circleable`; gives a model `circle()`, `hasService()`, `defaultServices()`, `isNestedIn()`, `withCirclePermissions()`, `getCircleName()`/`getCircleDescription()` |
| `app/Traits/HasLocation.php` | implements `Locatable` by **delegating to `$this->circle`** (location is stored on the circle, not the community) |
| `app/Enums/CommunityType.php` | string enum mapping each community type → its model FQCN (`->value` / `->modelClass()`) |
| `app/Enums/LocatableType.php` | string enum of place types (Country, Province, DistrictMunicipality, LocalMunicipality, MainPlace, City) → demography model FQCN; `modelClass()`, `label()` |
| `app/Services/Circles/CircleCreationService.php` | **the entry point** for creating a community + its Circle (see below) |
| `app/Models/Communities/*.php` | Organisation, Campaign, Course, Event, LocationCommunity, ThemeCommunity — each `implements Circleable, Locatable` + `use HasCircle, HasLocation` |
| `app/Services/Circles/*.php` | 9 `CircleServiceContract` handler stubs |

**Adding a new community type** requires three things in lockstep: a model in `app/Models/Communities/` (`implements Circleable, Locatable` + `use HasCircle, HasLocation`), a matching `case` in `CommunityType`, and its table migration (e.g. `Event` → `events`). Models have no `$table` override — they rely on Laravel's inferred names.

**How the hierarchy works** (`Circle::booted()`):
- On `creating`: `depth = parent->depth + 1`; and if `name` is blank, it is auto-populated from `circleable->getCircleName()` (+ `getCircleDescription()`). This is why a circle's `circleable_type` **must** be set at create time — see the gotcha below.
- On `created`: `path` is set to `parent->path . '/' . id` (or `(string) id` for a root), then `saveQuietly()`. Nesting queries rely on this path — `isNestedIn()` is `str_starts_with($this->path, $circle->path.'/')`, `descendants()` is a `path LIKE "{path}/%"` query, `ancestors()` parses the path ids.
- Also on `created`: the owner's `defaultServices()` (array of service **keys**) are attached to the new circle. **This hook is the SINGLE owner of default-service attachment** — do not also attach in callers (a duplicate `attach` would hit the `unique(circle_id, service_id)` constraint). **Currently every community returns `[]`**, so nothing auto-attaches — override `defaultServices()` per community model to change this.

**Creating circles — use `CircleCreationService::create()`:**
```php
app(CircleCreationService::class)->create(
    type: CommunityType::Event,
    data: ['name' => 'Launch Rally', 'description' => '...'],
    parentCircle: $optionalParent,
    // location is optional; omit for the default (South Africa):
    locatableType: LocatableType::Province,
    locatableId: 3,
);
```
It creates the community row, then the `Circle` (whose `booted()` hook fills name/description + attaches default services). **Two gotchas that previously broke this** (now fixed — keep them in mind):
- `Circle::$fillable` **must** include `circleable_id`, `circleable_type`, `locatable_id`, `locatable_type`. Because the model sets `$fillable`, it overrides `$guarded = []`; if a morph column is missing it's silently dropped on `Circle::create([...])` → the corresponding NOT NULL morph fails.
- Pass `$type->value` / `$locatableType->value` (the FQCN strings), **not** the enum instances, as the morph `*_type` columns — a raw enum object breaks morph resolution.

**Location (every circle has one).** The `circles.locatable` morph is **NOT NULL** — each circle is located in a demography place (`Country` / `Province` / `DistrictMunicipality` / `LocalMunicipality` / `MainPlace` / `City`, per `LocatableType`). `CircleCreationService::create()` takes `?LocatableType $locatableType` + `?int $locatableId`; when omitted it **defaults to `LocatableType::Country` id `191` (South Africa)** — see `CircleCreationService::DEFAULT_COUNTRY_ID`. Passing a non-Country type without an id throws. The location lives **only on the circle**; community models are `Locatable` via `HasLocation`, which **delegates to `$this->circle`** — so `$community->location()` returns the circle's `locatable`, and `setLocation()`/`locatedIn()` operate through the circle. For eager loading use `$community->circle->locatable`. `locatedIn()` is an **exact** place match (same type + id), not hierarchical containment.

**ThemeCommunity naming (special-cased in the service).** A `ThemeCommunity`'s name/description derive from BOTH its theme and its location, so `CircleCreationService::create()` special-cases it: it **requires `theme_id` in `$data`** (throws `InvalidArgumentException` otherwise) and sets `$data['name']`/`['description']` from `$theme->name` + the locatable's **`circleNameShort()`** (e.g. *"Education (Province of the Western Cape)"*). This is why every demography model has a `circleNameShort()` (`Country`→"National", `Province`→"Province of …", `DistrictMunicipality`→"… DM"/Metro, `LocalMunicipality`→"… Municipal Area", `City`→name). The `ThemeCommunity` model's own `circleName()`/`circleDescription()` read the locatable via **`$this->circle->locatable`** (the location lives on the circle, not the community). Note `booted()` uses `getCircleName()` (the community's own `$this->name`), so the themed name only works because the service sets `$data['name']` — nothing auto-calls the model's `circleName()`.

**Seeding services:** `database/seeders/Circles/ServicesSeeder.php` populates all 9 services (idempotent via `updateOrCreate` on `key`; `handler_class` set via `::class`). Run with:
```bash
php artisan db:seed --class="Database\Seeders\Circles\ServicesSeeder"
```

**Seeding the location-community hierarchy:** `database/seeders/Circles/LocationCommunitiesSeeder.php` builds a nested tree of `LocationCommunity` circles mirroring the demography hierarchy — **Country (191, South Africa) → Provinces → DistrictMunicipalities → LocalMunicipalities**, plus **Provinces → Cities**. Produces **296** circles (1 + 9 + 52 + 226 + 8), each located in its place via `locatableType`/`locatableId`.
```bash
php artisan db:seed --class="Database\Seeders\Circles\LocationCommunitiesSeeder"
```
- **Naming pattern (important):** each circle's name/description come from the **locatable place's** `circleName()`/`circleDescription()` — methods defined on the demography models (`Country`, `Province`, `DistrictMunicipality`, `LocalMunicipality`, `City`, `MainPlace`). These are **distinct** from the Circleable contract's `getCircleName()`/`getCircleDescription()`. They are wired in by the seeder passing them into `create()`'s **`data`** arg (which names the `LocationCommunity`, which then flows to the circle via `getCircleName()`). Nothing calls the place `circleName()` automatically — if you build location circles another way, pass these yourself, or `LocationCommunity::create([])` fails (its `name` is NOT NULL).
- **Not idempotent:** it inserts fresh rows each run (no `updateOrCreate`); re-running duplicates the tree. Clear `circles` + `location_communities` first if re-seeding.

**Seeding theme communities:** `database/seeders/ThemeCommunitiesSeeder.php` attaches `ThemeCommunity` circles under specific `LocationCommunity` parent circles (a `parentCircleId => [themeIds]` plan — the parent circle ids are environment-specific to that run, don't reuse them elsewhere). For each it derives `locatableType`/`locatableId` from the parent circle so the theme community shares its parent's location. It **stops** if a `theme_id` is missing and **skips** duplicate theme+location combos (safe to re-run). The created circles nest correctly as children of their parent (parent_id/path/depth). Run with:
```bash
php artisan db:seed --class=ThemeCommunitiesSeeder
```

**Community tables** (`organisations`, `campaigns`, `courses`, `location_communities`, `theme_communities`, `events`) each hold `id`, `name`, `description` (nullable), `timestamps` — minimal. **`theme_communities` additionally has a `theme_id`** (→ `themes`; the `ThemeCommunity` model `belongsTo` Theme). The original five (`2026_06_19_000004`–`000008`) are migrated; `events` (`2026_06_22_000001`) is migrated too.

**⚠️ Migration hold:** the user has asked that **Claude not run community-table migrations** on their behalf — they run them manually. When invoking `php artisan migrate`, scope it (e.g. `--path=`) so any pending community migration is not applied as a side effect.

## Roles & Permissions (spatie/laravel-permission, teams mode)

Uses **`spatie/laravel-permission` with teams enabled**, where the **team key is `circle_id`** — i.e. a role can be assigned to a user *globally* or *scoped to a specific Circle*. Config in `config/permission.php`: `'teams' => true`, `'column_names.team_foreign_key' => 'circle_id'`. `App\Models\User` uses the `HasRoles` trait.

**Roles** (seeded by `database/seeders/RolesAndPermissionsSeeder.php`, all defined with `circle_id = null` so they're reusable in any context):
- **Global** (assigned with no team context): `new_user, full_member, curator, trainer, admin, superadmin`
- **Circle** (assigned within a circle's team context): `circle_admin, circle_full_member, circle_visitor`

Run the seeder with:
```bash
php artisan db:seed --class="Database\Seeders\RolesAndPermissionsSeeder"
```

**The team-context rule — this is the easy thing to get wrong:**
- A **global** assignment needs **no** team context: `$user->assignRole('admin')` (with `setPermissionsTeamId(null)`, which is the default).
- A **circle-scoped** assignment needs the team id set first: `setPermissionsTeamId($circle->id)` → `$user->assignRole('circle_admin')` → **reset with `setPermissionsTeamId(null)`**.
- Always reset the team context after a scoped operation, or later global calls silently inherit the stale circle id.

**Two helpers exist so you rarely call `setPermissionsTeamId()` by hand:**
- `App\Services\Circles\CircleMembershipService::assignCircleRole(User $user, Circle $circle, string $role)` — sets team context, clears any existing circle role for that circle, assigns the new one, resets context.
- `HasCircle::withCirclePermissions(callable $cb)` — runs a callback inside the model's circle context and resets afterward: `$organisation->withCirclePermissions(fn () => $user->assignRole('circle_admin'))`.

**⚠️ Custom pivot schema — do not re-run the stock spatie migration over this.** Spatie's teams migration makes `circle_id` **`NOT NULL`** on `model_has_roles` / `model_has_permissions`, which **breaks global assignment** (`assignRole('admin')` with a null team id fails the constraint). A follow-up migration (`2026_06_20_140000_make_circle_id_nullable_on_permission_pivots`) fixes this by making `circle_id` **nullable** on both pivots. Because a MySQL `PRIMARY KEY` cannot contain a nullable column, it **drops the composite PKs and rebuilds them as UNIQUE indexes** (`model_has_roles_role_model_type_unique`, `model_has_permissions_permission_model_type_unique`) over the same columns. Side effect: MySQL treats `NULL`s as distinct in a unique index, so the DB won't block a duplicate *global* assignment — spatie prevents that at the application layer.

## Explore Communities UI (Livewire 4)

Public page at **`GET /explore`** (`routes/web.php` → `App\Livewire\Explore\ExploreCommunities::class`, a full-page Livewire 4 class component). Lets users browse the Circle tree by geography and (later) by community type. **No auth** — fully public. Built in Phase 1 (see scope note below).

**Components** (`app/Livewire/Explore/`, multi-file class-based; views in `resources/views/livewire/explore/`):
| Component | Type | Role |
|-----------|------|------|
| `ExploreCommunities` | Livewire (full-page) | Parent: holds all state + computeds + actions |
| `CommunityTypeFilter` | Livewire | Pill bar; `#[Reactive] selectedType`; pills call `$parent.selectType(@js(value))` |
| `Breadcrumb` | Livewire | Location trail + type label; `$parent.navigateToBreadcrumb` |
| `CommunityCard` | Livewire | One card per non-location community; "View" → opens modal |
| `CommunityDetail` | Livewire (`extends ModalComponent`) | Detail modal (services, placeholder join) |
| `SearchOverlay` | Livewire | Live `name LIKE` search; `#[On('open-search')]`; result → `navigate-to-circle` + `openModal` |
| `column-browser` | **Blade component** (`resources/views/components/explore/`) | Renders the `communities` Collection (list or cards) |
| `empty-state` | **Blade component** | 3-state empty UI |

**Why two Blade components, not Livewire:** `column-browser` receives the `communities` **Collection** and `empty-state` is pure presentation. Passing a Collection between Livewire components serializes/re-queries it every request — so these are Blade components rendered in the parent's view, where `wire:click="selectCircle(id)"` / `"startCommunity"` call the parent directly (no `$parent`). Reserve nested Livewire components for stateful islands.

**Parent state & key behaviours:**
- `selectedType` = a **`CommunityType` enum value (FQCN string)** or `null` (= All/Locations). `selectedCircleId` = current circle or `null` (= national). `viewMode` (`browse`/`map`). `breadcrumb` = array of `['id'=>?int,'name'=>string]`, starts at `['id'=>null,'name'=>'South Africa']`.
- **National level shows provinces** (the country's children), not the single Country circle.
- **Breadcrumbs use place names** — `selectCircle`/`navigateToCircle` store `$circle->locatable->name` ("Gauteng"), not the verbose circle name.
- Computeds (`#[Computed]`): `communities` (location-tree children, or non-location circles by exact `locatable` match), `communitiesCountBelow` (path-descendant count, drives the empty-state "in sub-regions"), `currentLevel`, `selectedType{Label,Singular,Icon}`.
- `Event` is included as a pill (the enum has it) even though the task's list omitted it.

**Modal:** uses **`wire-elements/modal`** (the one added dependency; Livewire-4 compatible). The host tag is **`<livewire:wire-elements-modal />`** (not `<livewire:modal />`). Open via `$dispatch('openModal', { component: 'explore.community-detail', arguments: { circleId } })`; close via the `ModalComponent::closeModal()` method.

**Conventions:**
- Reuses `layouts/app.blade.php` (Vite + Tailwind 4); **no** Tailwind CDN, **no** `@livewireStyles/@livewireScripts` (Livewire 4 auto-injects).
- Livewire **views** carry a top `@php /** @var ... */ @endphp` docblock so PhpStorm resolves the injected public properties (it can't infer them). Don't add `@props` to a Livewire view — that's Blade-component-only and would clobber the injected property.

**⚠️ Phase 1 scope (current state):**
- **Map (Step 8) not built** — the `🗺 Map` toggle is visible but **disabled** with a "Coming soon" tooltip.
- Only **LocationCommunity** circles are seeded (~296), so non-location types render **empty states**; the card→modal path has no live data yet (each piece renders in isolation).
- **Placeholders:** member counts are `0`; "Join Community" / "Start a …" dispatch events only (no membership system yet).

## Environment gotcha

Because the committed `.env` sets `SESSION_DRIVER=database` against the MAMP MySQL, if that server is not running (or unreachable — see the **socket / port gotcha** under Databases) **every browser request 500s** in `StartSession` middleware before any view renders — this looks like an app bug but is purely the unreachable DB. To view pages locally without MAMP, start MySQL or set `SESSION_DRIVER=file` in `.env`.
