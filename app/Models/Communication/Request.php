<?php

namespace App\Models\Communication;

use App\Contracts\Stewardship\CircleStewardshipQueue;
use App\Enums\RequestType;
use App\Filament\Resources\Requests\RequestResource;
use App\Models\Circles\Circle;
use App\Models\Circles\CircleMembership;
use App\Models\Organisation;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class Request extends Model implements CircleStewardshipQueue
{
    use SoftDeletes;

    protected $fillable = [
        'ulid',
        'type',
        'status',
        'direction',
        'requester_id',
        'circle_id',
        'requestable_type',
        'requestable_id',
        'respondent_email',
        'respondent_user_id',
        'responsible_admin_id',
        'token',
        'token_expires_at',
        'responded_at',
        'response_note',
        'metadata',
    ];

    protected $casts = [
        'type' => RequestType::class,
        'token_expires_at' => 'datetime',
        'responded_at' => 'datetime',
        'metadata' => 'array',
    ];

    /*
    |--------------------------------------------------------------------------
    | CircleStewardshipQueue (surfaced on the per-circle Oversight page)
    |--------------------------------------------------------------------------
    */

    public static function queueLabel(): string
    {
        return 'Pending Requests';
    }

    public static function pendingCountForCircle(Circle $circle): int
    {
        return static::query()->where('circle_id', $circle->id)->pending()->count();
    }

    public static function oldestPendingAgeForCircle(Circle $circle): ?Carbon
    {
        return static::query()
            ->where('circle_id', $circle->id)
            ->pending()
            ->oldest()
            ->first()?->created_at;
    }

    public static function filamentUrlForCircle(Circle $circle): string
    {
        // RequestResource has no circle filter to target, so link to its index
        // unfiltered (rather than building filter-URL plumbing here).
        return RequestResource::getUrl('index', panel: 'admin');
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    /** The user who raised the request. */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    /** The circle the request concerns (optional). */
    public function circle(): BelongsTo
    {
        return $this->belongsTo(Circle::class, 'circle_id');
    }

    /** The internal user expected to respond (when direction = internal). */
    public function respondent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'respondent_user_id');
    }

    /**
     * The platform user accountable for actioning this request, resolved via
     * Circle::responsibleAdminFor at creation. Null when no admin was found.
     * Any admin/superadmin may still act — this only drives notification.
     */
    public function responsibleAdmin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_admin_id');
    }

    /** The polymorphic subject of the request (e.g. an Organisation). */
    public function requestable(): MorphTo
    {
        return $this->morphTo();
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    /** Requests whose token has passed its expiry. */
    public function scopeExpired(Builder $query): Builder
    {
        return $query->whereNotNull('token_expires_at')
            ->where('token_expires_at', '<', now());
    }

    public function scopeExternal(Builder $query): Builder
    {
        return $query->where('direction', 'external');
    }

    public function scopeInternal(Builder $query): Builder
    {
        return $query->where('direction', 'internal');
    }

    /*
    |--------------------------------------------------------------------------
    | Methods
    |--------------------------------------------------------------------------
    */

    /**
     * Create an external organisation-approval request for the given circle.
     *
     * ulid + token are auto-generated in booted(); metadata always starts with
     * an empty email_log the controller/service appends to.
     *
     * @param  array<string, mixed>  $metadata
     */
    public static function createForOrganisation(
        User $requester,
        Circle $circle,
        Organisation $organisation,
        string $respondentEmail,
        array $metadata = [],
    ): self {
        return static::create([
            'type' => RequestType::OrganisationApproval,
            'status' => 'pending',
            'direction' => 'external',
            'requester_id' => $requester->id,
            'circle_id' => $circle->id,
            'requestable_type' => $organisation->getMorphClass(),
            'requestable_id' => $organisation->getKey(),
            'respondent_email' => $respondentEmail,
            // Internal steward accountable for this request (notification only;
            // any admin/superadmin may still act). Null when none is found.
            'responsible_admin_id' => Circle::responsibleAdminFor($circle)?->id,
            'token_expires_at' => now()->addDays(7),
            'metadata' => array_merge($metadata, ['email_log' => []]),
        ]);
    }

    /**
     * Create an external organisation-member-claim request: a user has claimed
     * the 'organisation_member' internal role on $membership, and the org's
     * contact must confirm it. Token + expiry mirror createForOrganisation().
     *
     * @param  array<string, mixed>  $metadata
     */
    public static function createForMemberClaim(
        User $requester,
        Circle $circle,
        CircleMembership $membership,
        string $respondentEmail,
        array $metadata = [],
    ): self {
        return static::create([
            'type' => RequestType::OrganisationMemberClaim,
            'status' => 'pending',
            'direction' => 'external',
            'requester_id' => $requester->id,
            'circle_id' => $circle->id,
            'requestable_type' => $membership->getMorphClass(),
            'requestable_id' => $membership->getKey(),
            'respondent_email' => $respondentEmail,
            'token_expires_at' => now()->addDays(7),
            'metadata' => array_merge($metadata, ['email_log' => []]),
        ]);
    }

    /** True when a token expiry is set and has passed. */
    public function isExpired(): bool
    {
        return $this->token_expires_at !== null
            && $this->token_expires_at->isPast();
    }

    /**
     * Append an entry to the metadata.email_log array, preserving all other
     * metadata keys. Records each attempt to send an email for this request.
     */
    public function logEmail(
        string $template,
        string $recipient,
        string $status,
        ?string $error = null,
    ): void {
        $metadata = $this->metadata ?? [];

        $entry = [
            'template' => $template,
            'recipient' => $recipient,
            'sent_at' => now()->toIso8601String(),
            'status' => $status,
        ];

        if ($error !== null) {
            $entry['error'] = $error;
        }

        $metadata['email_log'] = array_merge($metadata['email_log'] ?? [], [$entry]);

        $this->update(['metadata' => $metadata]);
    }

    /*
    |--------------------------------------------------------------------------
    | Model events
    |--------------------------------------------------------------------------
    */

    protected static function booted(): void
    {
        // Auto-generate the public ulid and the approval token on creation.
        static::creating(function (self $request): void {
            if (empty($request->ulid)) {
                $request->ulid = (string) Str::ulid();
            }

            if (empty($request->token)) {
                $request->token = Str::random(64);
            }
        });
    }
}
