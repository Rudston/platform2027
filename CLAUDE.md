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

The app uses **two separate MySQL connections**, both defined in `config/database.php` and pointed at a MAMP instance on `localhost:8889` by the committed `.env`:

| Connection | Database | Env prefix | Role |
|------------|----------|------------|------|
| `mysql` (default) | `platform2027` | `DB_*` | Primary application database |
| `vision_summit` | `vision_summit` | `DB_VISION_*` | Secondary database |

Use the secondary connection explicitly, e.g. `DB::connection('vision_summit')->...` or `protected $connection = 'vision_summit';` on a model. The default connection (`DB_CONNECTION=mysql`) backs Eloquent, sessions, cache, and queues unless overridden.

Other contexts use different drivers:
- **Tests**: `phpunit.xml` overrides to `DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:`, `SESSION_DRIVER=array` — no MySQL needed, which is why tests pass even when the browser 500s.
- **`.env.example`**: defaults to `DB_CONNECTION=sqlite` (a `database/database.sqlite` file exists), diverging from the committed `.env`'s MySQL setup.

## Demography (South African geography data)

A geography hierarchy imported from the legacy `vision_summit` database into `platform2027`. Models live in `app/Models/Demography/` (namespace `App\Models\Demography`).

| Model | Table | Belongs to | Has many |
|-------|-------|------------|----------|
| `Province` | `provinces` | — | districtMunicipalities, localMunicipalities, cities |
| `DistrictMunicipality` | `district_municipalities` | province, **mainCity** (`main_city_id`) | cities, localMunicipalities |
| `LocalMunicipality` | `local_municipalities` | province, districtMunicipality | — |
| `City` | `cities` | province, districtMunicipality | urbanPlaces |
| `UrbanPlace` | `urban_places` | city | — |
| `CoordinateData` | `coordinate_data` | province *(loose, no FK constraint)* | — |

Notes on the schema:
- **`coordinate_data` is a standalone ~12.7k-row coordinate lookup.** Its `city` is a plain string (not a relation), and `province_id` is indexed but **not** FK-constrained.
- **Circular FK** between `cities` and `district_municipalities` (a city belongs to a district; a district has a main city) is resolved by adding `main_city_id` in a *separate later migration* (`..._add_main_city_to_district_municipalities_table`) after `cities` exists. Replicate this split-migration pattern for any future circular FK.
- All FK columns are **nullable** to tolerate the legacy `0` sentinel (converted to `NULL` on import).
- The legacy `Location` table and its `ClassName` columns were deliberately **excluded**; do not reintroduce them without reason.

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

A **Circle** is a polymorphic, self-nesting collaborative container that wraps any *community* type (Organisation, LocationCommunity, ThemeCommunity, Campaign, Course). A **Service** is a functional module (e.g. Manage Events) that Circles attach via a many-to-many pivot.

**Tables** (migrations `2026_06_19_000001`–`000003`, run & live):
- `circles` — `morphs('circleable')` owner, self-ref `parent_id`, `depth`, `path` (materialised path)
- `services` — `name`, unique `key`, `handler_class`, `is_active`
- `circle_service` — pivot with `config` (json), `is_active`, unique `(circle_id, service_id)`

**Key classes:**
| File | Role |
|------|------|
| `app/Models/Circles/Circle.php` | morphTo `circleable`, `parent`/`children`, `services` (m2m), `ancestors()`/`descendants()`, `isNestedIn()` |
| `app/Models/Circles/Service.php` | `circles` (m2m) |
| `app/Contracts/Circleable.php` | contract for circle-owning models |
| `app/Contracts/CircleServiceContract.php` | contract for service handlers (`boot`, `getKey`, `getPermissions`) |
| `app/Traits/HasCircle.php` | implements `Circleable`; gives a model `circle()`, `hasService()`, `defaultServices()`, `isNestedIn()` |
| `app/Models/Communities/*.php` | Organisation, Campaign, Course, LocationCommunity, ThemeCommunity — each `implements Circleable` + `use HasCircle` |
| `app/Services/Circles/*.php` | 9 `CircleServiceContract` handler stubs |

**How the hierarchy works** (`Circle::booted()`):
- On `creating`: `depth = parent->depth + 1`.
- On `created`: `path` is set to `parent->path . '/' . id` (or `(string) id` for a root), then `saveQuietly()`. Nesting queries rely on this path — `isNestedIn()` is `str_starts_with($this->path, $circle->path.'/')`, `descendants()` is a `path LIKE "{path}/%"` query, `ancestors()` parses the path ids.
- Also on `created`: the owner's `defaultServices()` (array of service **keys**) are attached to the new circle. **Currently every community returns `[]`**, so nothing auto-attaches — override `defaultServices()` per community model to change this.

**Seeding services:** `database/seeders/Circles/ServicesSeeder.php` populates all 9 services (idempotent via `updateOrCreate` on `key`; `handler_class` set via `::class`). Run with:
```bash
php artisan db:seed --class="Database\Seeders\Circles\ServicesSeeder"
```

**⚠️ Community tables are minimal and PENDING.** Migrations `2026_06_19_000004`–`000008` create `organisations`, `campaigns`, `courses`, `location_communities`, `theme_communities` with **only `id` + `timestamps`** (no `name` or relations yet) — and as of writing they have **not been run** (`migrate:status` shows them Pending). Until they're migrated, creating a `Circle` whose `circleable` is a community model **throws** (the `created` hook resolves `circleable`, hitting a missing table). The community models have no `$table` override and rely on Laravel's inferred names (`Organisation` → `organisations`, `ThemeCommunity` → `theme_communities`, etc.).

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

## Environment gotcha

Because the committed `.env` sets `SESSION_DRIVER=database` against MySQL on `localhost:8889`, if that MAMP MySQL is not running **every browser request 500s** in `StartSession` middleware before any view renders — this looks like an app bug but is purely the unreachable DB. To view pages locally without MAMP, start MySQL or set `SESSION_DRIVER=file` in `.env`.
