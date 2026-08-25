<?php

namespace App\Models\Polls;

use App\Enums\Polls\PollEligibility;
use App\Enums\Polls\PollStatus;
use App\Models\Circles\Circle;
use App\Models\Concerns\HasTags;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use LogicException;

/**
 * A structured collective decision run inside a Circle. An election, a
 * proposition and a rating exercise are all Polls — those words describe a
 * Poll's shape, and none of them is stored.
 *
 * TWO RULES GOVERN EVERYTHING HERE, and both are easy to break by accident:
 *
 *  1. `status` records WHY a Poll stopped early, never WHETHER it is open.
 *     Scheduled/Open/Closed are derived from the clock (docs/adr/0001), so
 *     never add a stored `closed` flag and never branch on status to decide
 *     whether responses are accepted.
 *  2. Eligibility is tested when a response is CAST, never when the Poll is
 *     tallied (docs/adr/0002). Nothing is ever filtered out at tally time, so
 *     a published count cannot move after the fact.
 */
class Poll extends Model
{
    use HasTags, SoftDeletes;

    protected $guarded = [];

    protected $casts = [
        'eligibility' => PollEligibility::class,
        'status' => PollStatus::class,
        'qualifying_date' => 'datetime',
        'opens_at' => 'datetime',
        'closes_at' => 'datetime',
        'archived_at' => 'datetime',
        'result_frozen_at' => 'datetime',
        'allow_response_update' => 'boolean',
        'hide_voter_identities' => 'boolean',
        'publish_results' => 'boolean',
        'result' => 'array',
        'settings' => 'array',
    ];

    // ---------------------------------------------------------------- relations

    public function circle(): BelongsTo
    {
        return $this->belongsTo(Circle::class);
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(PollGroup::class, 'poll_group_id');
    }

    /** The Organiser — the user who created this Poll. */
    public function organiser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function questions(): HasMany
    {
        return $this->hasMany(PollQuestion::class)->orderBy('position');
    }

    /**
     * The Poll's single question. "Question" is structural and never surfaces
     * in the UI — a Poll has a title, a description and a Prompt. The Polls
     * creation UI only ever produces one, at position 0; questions() exists
     * because a future Surveys service may hold several.
     */
    public function question(): HasOne
    {
        return $this->hasOne(PollQuestion::class)->oldestOfMany('position');
    }

    /**
     * The Electorate: users entitled to respond, SNAPSHOTTED at publish from
     * the membership log as of qualifying_date. Never derive this — see
     * docs/adr/0002 for why the membership log cannot reconstruct it.
     */
    public function electorate(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'poll_electorate')->withTimestamps();
    }

    // ------------------------------------------------------------------ scopes

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', PollStatus::Published);
    }

    public function scopeNotArchived(Builder $query): Builder
    {
        return $query->whereNull('archived_at');
    }

    // ------------------------------------------------- derived lifecycle state

    public function isDraft(): bool
    {
        return $this->status === PollStatus::Draft;
    }

    /** Published, but its window has not begun. */
    public function isScheduled(): bool
    {
        return $this->status === PollStatus::Published
            && $this->opens_at !== null
            && $this->opens_at->isFuture();
    }

    /**
     * Accepting responses right now: Published, and the present moment falls
     * inside the window. A null opens_at means "open from publication"; a null
     * closes_at means "until an Organiser ends it".
     */
    public function isOpen(): bool
    {
        if ($this->status !== PollStatus::Published) {
            return false;
        }

        if ($this->opens_at !== null && $this->opens_at->isFuture()) {
            return false;
        }

        return $this->closes_at === null || $this->closes_at->isFuture();
    }

    /**
     * Not accepting responses, having previously been able to: the window
     * passed, or an Organiser Concluded or Cancelled it. A Draft is neither
     * Open nor Closed — it has not started.
     */
    public function isClosed(): bool
    {
        if (in_array($this->status, [PollStatus::Concluded, PollStatus::Cancelled], true)) {
            return true;
        }

        return $this->status === PollStatus::Published
            && $this->closes_at !== null
            && $this->closes_at->isPast();
    }

    /**
     * The single derived state a viewer is shown, resolved once here so no two
     * views disagree about whether a poll is Scheduled, Open or Closed. Maps to
     * the `polls.state.*` lang keys.
     *
     * Note "closed" covers a poll that simply ran out its clock — which still
     * carries status `published`, because status records why a poll stopped
     * EARLY and nothing exceptional happened (docs/adr/0001).
     */
    public function stateKey(): string
    {
        return match (true) {
            $this->status === PollStatus::Cancelled => 'cancelled',
            $this->status === PollStatus::Concluded => 'concluded',
            $this->isDraft() => 'draft',
            $this->isScheduled() => 'scheduled',
            $this->isOpen() => 'open',
            default => 'closed',
        };
    }

    public function isCancelled(): bool
    {
        return $this->status === PollStatus::Cancelled;
    }

    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }

    // --------------------------------------------------------- who may respond

    public function isInElectorate(?User $user): bool
    {
        return $user !== null
            && $this->electorate()->whereKey($user->getKey())->exists();
    }

    /**
     * Entitled to respond: in the Electorate AND still an active member of the
     * Circle. The ceiling is the Qualifying Date (you had to be a member then);
     * the floor is current membership (you must still be one now). Someone who
     * leaves keeps a response already given but cannot cast a new one.
     */
    public function isEntitled(?User $user): bool
    {
        if ($user === null || ! $this->isInElectorate($user)) {
            return false;
        }

        return $this->circle?->activeMembership($user) !== null;
    }

    public function hasResponded(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        return $this->question?->responses()
            ->where('user_id', $user->getKey())
            ->exists() ?? false;
    }

    /**
     * May this user submit or change a response at this moment? Open, entitled,
     * and either not yet responded or permitted to revise. Re-check this
     * server-side on every write — it is the gate, not a UI hint.
     */
    public function canRespond(?User $user): bool
    {
        if (! $this->isOpen() || ! $this->isEntitled($user)) {
            return false;
        }

        return ! $this->hasResponded($user) || $this->allow_response_update;
    }

    // ------------------------------------------------- roster, turnout, result

    public function electorateCount(): int
    {
        return $this->electorate()->count();
    }

    /** How many of the Electorate have responded. Published live while Open. */
    public function respondentCount(): int
    {
        return $this->question?->responses()->count() ?? 0;
    }

    /**
     * Whether the Roster's NAMES may be shown. Only once the Poll has Closed:
     * a live list of who has responded is a list of who has yet to comply. A
     * Cancelled Poll never rosters — it was never counted. Also false for a
     * Poll with no question yet, which is the precondition roster() relies on.
     */
    public function rosterIsVisible(): bool
    {
        return $this->isClosed()
            && ! $this->isCancelled()
            && $this->question !== null;
    }

    /**
     * The Roster — Respondents by name, available only once the Poll has
     * Closed. Check rosterIsVisible() first; use respondentCount() for the
     * live figure while the Poll is Open.
     *
     * THROWS if the names are not yet visible, rather than returning an empty
     * collection: an empty roster is indistinguishable from "nobody has
     * responded", so a caller who forgot to check would render a plausible
     * falsehood instead of failing. Refuse loudly rather than leak quietly —
     * the same pairing as canUserJoin() before joinAsMember().
     *
     * A Poll with no question yet (a half-built Draft) is never visible, so
     * that case is caught by the same guard.
     *
     * The Roster reveals WHO responded, never WHAT they chose — that is
     * withheld from everyone (see hide_voter_identities).
     *
     * @return Collection<int, User>
     *
     * @throws LogicException when the Roster's names are not yet visible
     */
    public function roster(): Collection
    {
        if (! $this->rosterIsVisible()) {
            throw new LogicException(
                "Roster names are not visible for poll [{$this->getKey()}]: a Poll's Respondents "
                .'are named only once it has Closed. Check rosterIsVisible() first, and use '
                .'respondentCount() for the live figure while it is Open.'
            );
        }

        return User::query()
            ->whereIn('id', $this->question->responses()->select('user_id'))
            ->orderBy('name')
            ->get();
    }

    public function hasResult(): bool
    {
        return $this->result !== null;
    }

    /**
     * May this Poll's Result be seen from outside its Circle? Only a Closed
     * Poll's Result is ever published; the Poll itself is never externally
     * visible while it runs, and a Cancelled Poll has no Result at all.
     */
    public function resultIsPublic(): bool
    {
        return $this->publish_results && $this->hasResult() && ! $this->isCancelled();
    }

    // ----------------------------------------------------------- authorization

    /**
     * Who may Conclude or Cancel: the Organiser WHILE THEY REMAIN A MEMBER of
     * the Circle, or a circle manager unconditionally. Leaving the Circle ends
     * an Organiser's authority without unmaking them the Organiser — a
     * departed member must not be able to void a live election.
     *
     * Note a circle admin can end a Poll they cannot read: power over process,
     * none over content.
     */
    public function canBeEndedBy(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        if ($this->circle?->isManageableBy($user) ?? false) {
            return true;
        }

        if ($this->created_by === null || $this->created_by !== $user->getKey()) {
            return false;
        }

        return $this->circle?->activeMembership($user) !== null;
    }

    /** Composing and publishing are circle-level acts, like creating a group. */
    public function isManageableBy(?User $user): bool
    {
        return $this->circle?->isManageableBy($user) ?? false;
    }

    /** Tagging mirrors the Poll's manage rights. */
    public function canBeTaggedBy(?User $user): bool
    {
        return $this->isManageableBy($user);
    }
}
