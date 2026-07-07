<?php

namespace App\Models;

use App\Models\Circles\Circle;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Request extends Model
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
        'token',
        'token_expires_at',
        'responded_at',
        'response_note',
        'metadata',
    ];

    protected $casts = [
        'token_expires_at' => 'datetime',
        'responded_at' => 'datetime',
        'metadata' => 'array',
    ];

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
            'type' => 'organisation_approval',
            'status' => 'pending',
            'direction' => 'external',
            'requester_id' => $requester->id,
            'circle_id' => $circle->id,
            'requestable_type' => $organisation->getMorphClass(),
            'requestable_id' => $organisation->getKey(),
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
