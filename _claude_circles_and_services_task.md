I need you to implement two new entities: Circle and Service,
based on the following architecture. Do ONE step at a time,
stop for my review before proceeding.

═══════════════════════════════════════════
OVERVIEW
═══════════════════════════════════════════

A Circle is a collaborative container that wraps any community
type (Organisation, Location Community, Theme Community,
Campaign, Course). Any type of Circle can be nested inside
another Circle (e.g. an organisation inside a location
community, a campaign inside a theme community).

A Service is a functional module that any Circle can use
some or all of (Store Assets, Manage Events, Manage Voting
etc.). Circles and Services have a many-to-many relationship.

═══════════════════════════════════════════
STEP 1: MIGRATIONS
═══════════════════════════════════════════

Create migrations in this order:

1. create_circles_table
    - id
    - circleable_id (unsignedBigInteger) + circleable_type (string)
      — polymorphic, use morphs('circleable')
    - parent_id (nullable, foreignId constrained to circles —
      self-referencing for nesting)
    - depth (unsignedTinyInteger, default 0)
    - path (string, nullable — materialized path e.g. "1/4/12"
      representing ancestor trail)
    - timestamps

2. create_services_table
    - id
    - name (string)             e.g. "Manage Events"
    - key (string, unique)      e.g. "manage_events"
    - description (nullable string)
    - handler_class (string)    e.g. App\Services\Circles\ManageEventsService
    - is_active (boolean, default true)
    - timestamps

3. create_circle_service_table (pivot)
    - circle_id (foreignId, constrained)
    - service_id (foreignId, constrained)
    - config (json, nullable)   — per-circle service configuration
    - is_active (boolean, default true)
    - timestamps

═══════════════════════════════════════════
STEP 2: CONTRACTS (INTERFACES)
═══════════════════════════════════════════

Create app/Contracts/Circleable.php:

interface Circleable
{
public function circle(): HasOne;
public function hasService(string $serviceKey): bool;
public function isNestedIn(Circle $circle): bool;
}

Create app/Contracts/CircleServiceContract.php:

interface CircleServiceContract
{
public function boot(Circle $circle): void;
public function getKey(): string;
public function getPermissions(): array;
}

═══════════════════════════════════════════
STEP 3: TRAIT
═══════════════════════════════════════════

Create app/Traits/HasCircle.php implementing Circleable:

trait HasCircle
{
public function circle(): HasOne
{
return $this->hasOne(Circle::class, 'circleable_id')
->where('circleable_type', static::class);
}

public function hasService(string $serviceKey): bool
{
    return $this->circle->services()
                ->where('key', $serviceKey)
                ->exists();
}

public function defaultServices(): array
{
    return [];
}

public function isNestedIn(Circle $circle): bool
{
    return $this->circle->isNestedIn($circle);
}
}

═══════════════════════════════════════════
STEP 4: CIRCLE MODEL
═══════════════════════════════════════════

Create app/Models/Circles/Circle.php:

Relationships:
- circleable(): MorphTo
- services(): BelongsToMany (Service) with pivot: config, is_active
- parent(): BelongsTo (Circle, 'parent_id')
- children(): HasMany (Circle, 'parent_id')
- ancestors(): returns Collection — resolves from materialized path
- descendants(): returns Collection — queries path LIKE "$this->path/%"

Methods:
- isNestedIn(Circle $circle): bool
  → return str_starts_with($this->path, $circle->path . '/');

Auto-maintain path and depth in booted():
- On creating: if parent exists, set depth = parent->depth + 1
- On created: set path = parent exists
  ? parent->path . '/' . $this->id
  : (string) $this->id
  then saveQuietly()

Note in this model have a boot method like this:

// app/Models/Circles/Circle.php

protected static function booted(): void
{
// existing path/depth logic...

static::created(function (Circle $circle) {
$owner = $circle->circleable;

if ($owner && method_exists($owner, 'defaultServices')) {
    $serviceIds = Service::whereIn('key', $owner->defaultServices())
                         ->pluck('id');
    $circle->services()->attach($serviceIds, ['is_active' => true]);
}
});
}

═══════════════════════════════════════════
STEP 5: SERVICE MODEL
═══════════════════════════════════════════

Create app/Models/Circles/Service.php:

Relationships:
- circles(): BelongsToMany (Circle) with pivot: config, is_active

═══════════════════════════════════════════
STEP 6: COMMUNITY MODELS
═══════════════════════════════════════════

Create these models, each implementing Circleable and using
HasCircle trait, in the folder app/Models/Communities/:

- Organisation.php
- Campaign.php
- Course.php
- LocationCommunity.php
- ThemeCommunity.php

═══════════════════════════════════════════
STEP 7: SERVICE HANDLER STUBS
═══════════════════════════════════════════

Create stub handler classes in app/Services/Circles/,
each implementing CircleServiceContract, for these services
from the platform spec:

- StoreAssetsService          (key: store_assets)
- NotificationsService        (key: notifications)
- ManageInteractionService    (key: manage_interaction)
- ManageMediaService          (key: manage_media)
- ManageUsersService          (key: manage_users)
- ManageEventsService         (key: manage_events)
- ManageVotingService         (key: manage_voting)
- ManageLearningService       (key: manage_learning)
- ManageSocialMediaService    (key: manage_social_media)

═══════════════════════════════════════════
STEP 8: SEEDER
═══════════════════════════════════════════

Create a DatabaseSeeder or dedicated ServicesSeeder that
populates the services table with all 9 services above,
using their names, keys, and handler_class values.

═══════════════════════════════════════════
FOLDER STRUCTURE SUMMARY
═══════════════════════════════════════════

app/
Contracts/
Circleable.php
CircleServiceContract.php
Traits/
HasCircle.php
Models/
Circles/
Circle.php
Service.php
Communities/
Organisation.php
Campaign.php
Course.php
LocationCommunity.php
ThemeCommunity.php
Demography/           ← already implemented
Province.php
...
Services/
Circles/
StoreAssetsService.php
NotificationsService.php
ManageInteractionService.php
ManageMediaService.php
ManageUsersService.php
ManageEventsService.php
ManageVotingService.php
ManageLearningService.php
ManageSocialMediaService.php
