<?php

namespace App\Support\Moderation;

use App\Models\Forums\ForumDiscussion;
use Illuminate\Database\Eloquent\Model;

/**
 * Resolves display/audit facts about whatever a comment is attached to (its
 * `commentable`). Only ForumDiscussion is a commentable type today; adding a
 * second is "add a case" here, not hunting through record-creation code.
 */
class CommentableTypeLabeler
{
    /** Human label for a commentable morph type (class name) — NOT the raw class. */
    public static function label(?string $type): string
    {
        return match ($type) {
            ForumDiscussion::class => 'Forum Discussion',
            default => 'Comment',
        };
    }

    /** The owning circle's id for a commentable, or null if not resolvable. */
    public static function circleIdFor(?Model $commentable): ?int
    {
        return match (true) {
            $commentable instanceof ForumDiscussion => $commentable->group?->circle_id,
            default => null,
        };
    }

    /** Front-end URL to the commentable, or null if it can't be built. */
    public static function urlFor(?Model $commentable): ?string
    {
        return match (true) {
            $commentable instanceof ForumDiscussion => static::forumDiscussionUrl($commentable),
            default => null,
        };
    }

    private static function forumDiscussionUrl(ForumDiscussion $discussion): ?string
    {
        $group = $discussion->group;

        // The circle route param binds by id — pass the id (no need to load the
        // Circle model just to generate a URL).
        if ($group === null || $group->circle_id === null || $group->slug === null || $discussion->slug === null) {
            return null;
        }

        return route('communities.forums.discussions.show', [
            'circle' => $group->circle_id,
            'forumGroup' => $group->slug,
            'forumDiscussion' => $discussion->slug,
        ]);
    }
}
