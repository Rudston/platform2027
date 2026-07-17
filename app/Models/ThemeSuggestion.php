<?php

namespace App\Models;

use App\Enums\ThemeSuggestionStatus;
use App\Services\Communication\EmailServiceHandler;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * A user's proposal for a new tag (Theme). Reviewed by admins; on approval the
 * real Theme is created (or an existing same-named one reused) and, if the
 * suggestion recorded an origin entity, the Theme is attached to it.
 */
class ThemeSuggestion extends Model
{
    protected $fillable = [
        'name',
        'description',
        'status',
        'requested_by',
        'reviewed_by',
        'reviewed_at',
        'review_note',
        'origin_taggable_type',
        'origin_taggable_id',
    ];

    protected $casts = [
        'status' => ThemeSuggestionStatus::class,
        'reviewed_at' => 'datetime',
    ];

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /** The entity being tagged when this suggestion was raised (nullable). */
    public function origin(): MorphTo
    {
        return $this->morphTo('origin', 'origin_taggable_type', 'origin_taggable_id');
    }

    /**
     * Approve: create (or reuse a same-named) Theme, mark reviewed, attach the
     * Theme to the origin entity if one was recorded, and notify the requester.
     */
    public function approve(User $reviewer, ?string $note = null): Theme
    {
        $theme = DB::transaction(function () use ($reviewer, $note): Theme {
            // Dedupe by slug rather than erroring on an existing tag.
            $theme = Theme::firstOrCreate(
                ['slug' => Str::slug($this->name)],
                ['name' => $this->name],
            );

            $this->update([
                'status' => ThemeSuggestionStatus::Approved,
                'reviewed_by' => $reviewer->getKey(),
                'reviewed_at' => now(),
                'review_note' => $note,
            ]);

            // Auto-attach to the origin entity if it's still around and taggable.
            $origin = $this->origin;
            if ($origin !== null && method_exists($origin, 'tags')) {
                $origin->tags()->syncWithoutDetaching([$theme->getKey()]);
            }

            return $theme;
        });

        $this->notifyRequester('email.theme_suggestion_approved');

        return $theme;
    }

    /** Reject with a required note; notify the requester. */
    public function reject(User $reviewer, string $note): void
    {
        $this->update([
            'status' => ThemeSuggestionStatus::Rejected,
            'reviewed_by' => $reviewer->getKey(),
            'reviewed_at' => now(),
            'review_note' => $note,
        ]);

        $this->notifyRequester('email.theme_suggestion_rejected');
    }

    /** Email the requester the outcome (best-effort — never breaks the review). */
    private function notifyRequester(string $template): void
    {
        $requester = $this->requestedBy;

        if (! $requester?->email) {
            return;
        }

        try {
            app(EmailServiceHandler::class)->sendTemplate($template, $requester->email, [
                'user_name' => (string) $requester->name,
                'tag_name' => (string) $this->name,
                'review_note' => (string) ($this->review_note ?? ''),
            ]);
        } catch (Throwable) {
            // Swallow — an email failure must not roll back the review decision.
        }
    }
}
