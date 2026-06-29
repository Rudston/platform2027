# CLAUDE.md — Platform 2027 Project Context

## Project Overview

Platform 2027 is a South African civic platform providing persistent
collaborative spaces ("Circles") for communities across South Africa.
It facilitates citizen collaboration, pressure on the state, and
community organisation across location-based and theme-based lines.
No political parties have access.

## Tech Stack

- **Laravel 12**
- **PHP 8.2+**
- **Livewire 4**
- **Filament** (admin panels + forms)
- **Tailwind CSS 4** (via Vite — do NOT add tailwind.config.js)
- **Alpine.js** (via Livewire 4)
- **Spatie Laravel Permission** (with teams enabled)
- **MySQL**
- **wire-elements/modal** (for modals in Explore page)

## Database Connections

Single database connection: `project2027` (default)
No legacy database connections are relevant.

---

## Core Architecture

### The Circle System

Every community on the platform is wrapped in a **Circle** — a
collaborative container. Circles are hierarchical (self-referencing
via `parent_id`) and use a **materialized path** for efficient
ancestor/descendant queries.

#### circles table

```
id
name                 (required)
description          (nullable)
circleable_id        (polymorphic — points to community model)
circleable_type      (polymorphic — community model class)
locatable_id         (polymorphic — points to demography model)
locatable_type       (polymorphic — demography model class)
parent_id            (nullable FK to circles — self-referencing)
depth                (unsignedTinyInteger, default 0)
path                 (string, nullable — e.g. "1/4/12")
timestamps
```

Path and depth are auto-maintained in `Circle::booted()`.

#### circle_associations table (cross-community links)

```
circle_id               (FK to circles, cascadeOnDelete)
associated_circle_id    (FK to circles, cascadeOnDelete)
association_type        (string, default 'related')
approved                (boolean, default false)
approved_at             (timestamp, nullable)
approved_by_user_id     (FK to users, nullOnDelete, nullable)
primary key: [circle_id, associated_circle_id]
timestamps
```

---

## Enums

### CommunityType (app/Enums/CommunityType.php)

Maps community types to their model class paths.
CASE NAMES must never change — only values if class paths change.

```php
case Organisation      = 'App\Models\Communities\OrganisationCommunity';
case Campaign          = 'App\Models\Communities\Campaign';
case Course            = 'App\Models\Communities\CourseCommunity';
case LocationCommunity = 'App\Models\Communities\LocationCommunity';
case ThemeCommunity    = 'App\Models\Communities\ThemeCommunity';
```

### LocatableType (app/Enums/LocatableType.php)

Maps geographic levels to demography model class paths.
Includes `label()` and `modelClass()` methods.

```php
case Country              = 'App\Models\Demography\Country';
case Province             = 'App\Models\Demography\Province';
case DistrictMunicipality = 'App\Models\Demography\DistrictMunicipality';
case LocalMunicipality    = 'App\Models\Demography\LocalMunicipality';
case MainPlace            = 'App\Models\Demography\MainPlace';
case City                 = 'App\Models\Demography\City';
```

---

## Contracts (Interfaces)

### app/Contracts/Circleable.php
Every community model implements this.
```php
public function circle(): HasOne;
public function hasService(string $serviceKey): bool;
public function isNestedIn(Circle $circle): bool;
public function defaultServices(): array;
public function getCircleName(): string;
public function getCircleDescription(): string;
```

### app/Contracts/Locatable.php
Every community model implements this.
```php
public function location(): MorphTo;
public function locatedIn(Model $place): bool;
public function setLocation(Model $place): void;
```

### app/Contracts/CircleServiceContract.php
Every service handler implements this.
```php
public function boot(Circle $circle): void;
public function getKey(): string;
public function getPermissions(): array;
```

### app/Contracts/ProvidesCircleIdentity.php
Every demography model implements this.
```php
public function circleName(): string;
public function circleDescription(): string;
public function circleNameShort(): string;
```

---

## Traits

### app/Traits/HasCircle.php
Provides default implementations of Circleable.
Includes `withCirclePermissions(callable $callback)` helper.

### app/Traits/HasLocation.php
Provides default implementations of Locatable.

---

## Community Models (app/Models/Communities/)

All implement both `Circleable` and `Locatable`.
All use `HasCircle` and `HasLocation` traits.

### OrganisationCommunity
- Table: `organisation_communities`
- belongsTo: Organisation (via organisation_id, nullable)
- One-to-one with Organisation entity

### Campaign
- Table: `campaigns`

### CourseCommunity
- Table: `course_communities`
- belongsToMany: Course (via course_course_community pivot)

### LocationCommunity
- Table: `location_communities`
- Name/description derived from locatable's
  circleName() / circleDescription()

### ThemeCommunity
- Table: `theme_communities`
- belongsTo: Theme (via theme_id)
- circleName(): "{theme->name} ({locatable->circleNameShort()})"
- circleDescription(): auto-generated from theme + location

---

## Entity Models (plain — do NOT implement Circleable)

### app/Models/Organisation
- Table: `organisations`
- Fields: name, description, website (nullable),
  contact_person, contact_email
- hasOne: OrganisationCommunity
- hasCommunity(): bool helper

### app/Models/Course
- Table: `courses`
- Fields: name, description, website (nullable),
  contact_person (nullable), contact_email (nullable)
- belongsToMany: CourseCommunity (via course_course_community)

---

## Demography Models (app/Models/Demography/)

All implement ProvidesCircleIdentity.
Hierarchy: Country → Province → DistrictMunicipality
→ LocalMunicipality / City → MainPlace

### Tables
- countries
- provinces
- district_municipalities
- local_municipalities
- cities (metros and large cities)
- main_places (suburbs/areas — Stats SA 2011 data)
- urban_places

### Key relationships
- Province belongsTo Country
- DistrictMunicipality belongsTo Province
- LocalMunicipality belongsTo DistrictMunicipality
- City belongsTo Province (metros belong directly to province)
- MainPlace belongsTo LocalMunicipality OR City

### circleNameShort() implementations
- Country:              "South Africa"
- Province:             "{name}"
- DistrictMunicipality: "{name} DM"
- LocalMunicipality:    "{name}"
- City:                 "{name}"

---

## Services (app/Models/Circles/)

### Service model
- Table: `services`
- Fields: name, key (unique), description, handler_class, is_active
- belongsToMany: Circle (via circle_service pivot)

### circle_service pivot
- circle_id, service_id, config (json, nullable), is_active, timestamps

### 9 Service handlers (app/Services/Circles/)
Keys: store_assets, notifications, manage_interaction,
manage_media, manage_users, manage_events,
manage_voting, manage_learning, manage_social_media

---

## CircleCreationService (app/Services/Circles/CircleCreationService.php)

Central service for creating any circle community.

```php
public function create(
    CommunityType $type,
    array $data,
    ?Circle $parentCircle = null,
    ?LocatableType $locatableType = null,
    ?int $locatableId = null,
    ?Organisation $organisation = null,  // for OrganisationCommunity
    array $courseIds = [],               // for CourseCommunity
): Circle
```

- Defaults location to Country (South Africa) if not specified
- For LocationCommunity: auto-derives name/description from locatable
- For ThemeCommunity: derives name/description from theme + locatable
- Attaches defaultServices() automatically after circle creation
- Wraps everything in DB::transaction()
- DEFAULT_COUNTRY_ID constant for South Africa

---

## Roles and Permissions (Spatie)

Teams enabled. team_foreign_key = circle_id.
circle_id is NULLABLE on model_has_roles and model_has_permissions
(custom migration applied to fix Spatie's NOT NULL default).

### Global roles (no team context)
new_user, full_member, curator, trainer, admin, superadmin

### Circle-level roles (scoped per circle)
circle_admin, circle_full_member, circle_visitor

### CircleMembershipService (app/Services/Circles/CircleMembershipService.php)
Handles circle role assignment with team context.
Always resets team context to null after assignment.

---

## Explore Communities Page (Livewire 4)

Public page at GET /explore

### Component structure (app/Livewire/Explore/)
- ExploreCommunities.php  — parent, holds all state
- CommunityTypeFilter.php — pill/tab bar for type selection
- Breadcrumb.php          — geographic navigation trail
- ColumnBrowser.php       — three-panel file-browser style navigation
- CommunityCard.php       — single community display card
- CommunityDetail.php     — modal with full community info
- SearchOverlay.php       — live search across circle names
- MapView.php             — SVG map (Phase 2, not yet built)

### State (in ExploreCommunities)
- selectedType: ?string    — CommunityType enum value, null = all
- selectedCircleId: ?int   — current geographic circle, null = national
- viewMode: string         — 'browse' | 'map'
- breadcrumb: array        — [{id, name}] trail

### Key behaviour
- Switching community TYPE preserves geographic selection
- Breadcrumb always starts with South Africa (null id)
- Three empty states: communities exist / none at level / none anywhere
- Associated circles (via circle_associations) merged into results
  with 'also_here' flag for UI badging
- Map view disabled pending SVG map sourcing (show "coming soon")

### Views (resources/views/livewire/explore/)
explore-communities, community-type-filter, breadcrumb,
column-browser, community-card, community-detail,
search-overlay, map-view

### Blade components (resources/views/components/explore/)
- empty-state.blade.php

---

## Authentication (Manual — NO Breeze/Jetstream)

Built manually with Livewire 4. Tailwind 4 compatible.

### Livewire components (app/Livewire/Auth/)
- Login.php
- Register.php (assigns 'new_user' role on creation)
- ForgotPassword.php
- ResetPassword.php

### Controller
- app/Http/Controllers/Auth/LogoutController.php

### Layouts
- resources/views/layouts/guest.blade.php
  (for unauthenticated pages)
- resources/views/layouts/authenticated.blade.php
  (for dashboard etc. — nav bar with Explore link)
- resources/views/layouts/app.blade.php
  (existing — used by Explore page — DO NOT MODIFY)

### Routes
- GET  /login              → Login (guest middleware)
- GET  /register           → Register (guest middleware)
- GET  /forgot-password    → ForgotPassword (guest middleware)
- GET  /reset-password/{token} → ResetPassword (guest middleware)
- GET  /dashboard          → dashboard view (auth middleware)
- POST /logout             → LogoutController
- GET  /explore            → ExploreCommunities (public)

---

## Folder Structure Summary

```
app/
  Contracts/
    Circleable.php
    CircleServiceContract.php
    Locatable.php
    ProvidesCircleIdentity.php
  Enums/
    CommunityType.php
    LocatableType.php
  Http/Controllers/Auth/
    LogoutController.php
  Livewire/
    Auth/
      Login.php
      Register.php
      ForgotPassword.php
      ResetPassword.php
    Explore/
      ExploreCommunities.php
      CommunityTypeFilter.php
      Breadcrumb.php
      ColumnBrowser.php
      CommunityCard.php
      CommunityDetail.php
      SearchOverlay.php
      MapView.php
  Models/
    Circles/
      Circle.php
      Service.php
    Communities/
      OrganisationCommunity.php
      Campaign.php
      CourseCommunity.php
      LocationCommunity.php
      ThemeCommunity.php
    Demography/
      Country.php
      Province.php
      DistrictMunicipality.php
      LocalMunicipality.php
      City.php
      MainPlace.php
      UrbanPlace.php
    Organisation.php
    Course.php
    User.php
  Services/
    Circles/
      CircleCreationService.php
      CircleMembershipService.php
      StoreAssetsService.php
      NotificationsService.php
      ManageInteractionService.php
      ManageMediaService.php
      ManageUsersService.php
      ManageEventsService.php
      ManageVotingService.php
      ManageLearningService.php
      ManageSocialMediaService.php
  Traits/
    HasCircle.php
    HasLocation.php

database/
  migrations/
    (all standard Laravel + project migrations)
  seeders/
    LocationCommunitiesSeeder.php
    ThemeCommunitiesSeeder.php
    ServicesSeeder.php
    RolesAndPermissionsSeeder.php
    MainPlacesSeeder.php

resources/
  views/
    layouts/
      app.blade.php          ← existing Explore layout
      guest.blade.php        ← auth pages
      authenticated.blade.php ← dashboard etc.
    livewire/
      auth/
        login.blade.php
        register.blade.php
        forgot-password.blade.php
        reset-password.blade.php
      explore/
        (all explore components)
    components/explore/
      empty-state.blade.php
    welcome.blade.php
    dashboard.blade.php
```

---

## Key Decisions Log

1. **No base class for communities** — interface + trait pattern
   used instead to avoid single inheritance lockout.

2. **Materialized path on circles** — avoids recursive CTEs for
   ancestor/descendant queries. Auto-maintained in booted().

3. **Locatable is mandatory** — every circle has at least a
   Country-level location. morphs() not nullableMorphs().

4. **circle_id nullable on Spatie pivots** — custom migration
   applied so global roles (no team context) work alongside
   circle-scoped roles.

5. **Breeze rejected** — incompatible with Tailwind 4 and
   Livewire 4. Auth built manually.

6. **OrganisationCommunity ≠ Organisation** — the community
   (circle wrapper) is separate from the entity. One-to-one.

7. **CourseCommunity ↔ Course is many-to-many** — a course
   community can feature multiple courses; a course can appear
   in multiple communities.

8. **circle_associations for cross-community links** — preserves
   single parent hierarchy while allowing optional associations.
   Approval workflow fields included from the start.

9. **No map view yet** — SVG map sourcing in progress.
   amCharts SA provinces SVG recommended as starting point.

10. **Stats SA main places data** — 2011 census data seeded using
    authoritative MN_SA_2011.dbf crosswalk from Adrian Frith's
    census data. Prefix → MDB code mapping via MN_CODE field.

---

## What Is NOT Yet Built

- Full membership system (circle_user pivot + approval workflow)
- Campaign model details (fields beyond name/description)
- Filament admin panels (structure planned, not built)
- Map view for Explore page
- User profile pages
- Notification system (service stub exists)
- Voting/polls system (service stub exists)
- Social media integration (service stub exists)
- Learning management (service stub exists)
- Payment/subscription system
- API endpoints
