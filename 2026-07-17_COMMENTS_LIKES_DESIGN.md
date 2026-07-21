# Comments & Likes — Design Decisions (pending build)

Status: **Design finalized, NOT yet sent to Claude Code.** Waiting on
ForumGroups pass to finish first, plus three open questions below.

## Origin

Porting a Livewire 3 + Alpine.js commenting system (built for course
documents, webinars, etc.) into Platform 2027, to serve as the reply
engine for ForumDiscussions. Old system was already polymorphic
(`type`/`ref_id`) with arbitrarily deep nesting — a strong starting
point, but had one real structural duplication, found by comparing the
old schemas directly (see below).

## The collapse: three tables/concepts → two

| Old | New |
|---|---|
| `comment_sets` (type/ref_id → polymorphic parent) | **`ForumDiscussion` itself** — no new table needed, it's already the container |
| `comment_threads` + `comment_thread_messages` (near-identical columns; threads were always parentless) | **`comments`** — one table, self-nesting via `parent_id` |
| `comment_likes` (two mutually-exclusive nullable FKs: `comment_thread_id` OR `comment_thread_message_id`) | **`likes`** — polymorphic (`likeable_type`/`likeable_id`), reusable later for liking a whole Discussion, a Circle, etc. |

Reasoning: `comment_threads` had no column that wasn't either (a) also
on `comment_thread_messages`, or (b) only meaningful for root-level
items (`pinned`/`pinned_position`). A "thread" was really just "a
message with no parent." Collapsing removes duplicated moderation
columns that would otherwise need to stay in sync across two tables.

## Proposed schema

```
comments
  id
  commentable_type / commentable_id   -- renamed from type/ref_id, to
                                          match this app's existing
                                          taggable_type/id,
                                          requestable_type/id convention
  parent_id             nullable, self-FK -> comments.id, cascade
                        -- null = root comment ("thread" in old system)
                        -- non-null = reply, any depth
  user_id               FK -> users.id, cascade
  content               text
  pinned                bool, default false
                        -- only meaningful when parent_id is null;
                        -- enforce "no pinning replies" in app logic
  pinned_position       nullable int
  hidden                bool, default false
  flagged_as_offensive  bool, default false
  moderated             bool, default false
  message_to_moderator  text, nullable
  moderated_by          nullable FK -> users.id, nullOnDelete
                        -- renamed from admin_id: could be an admin,
                        -- superadmin, or (later) a circle_admin
                        -- moderating their own community's forum
  timestamps

likes
  id
  likeable_type / likeable_id
  user_id               FK -> users.id, cascade
  timestamps
  UNIQUE (likeable_type, likeable_id, user_id)
```

Possible future refinement (not blocking, noted for later): consider
collapsing `hidden` / `moderated` / `flagged_as_offensive` into one
`moderation_status` enum for consistency with `CircleStatus` /
`AdminApprovalStatus` / `ForumDiscussion.moderation_status` elsewhere in
this app — deferred because `flagged` and `hidden` may need to coexist
(user-flagged but not yet actioned), unlike the other enums which are
genuinely mutually exclusive.

## Open questions — still need answers before drafting the Claude Code prompt

1. **Scope**: build the ForumDiscussion detail page (currently a bare
   "coming soon" placeholder from the earlier Forums pass) together with
   Comments in this same task, or backend/models only for now?
   → Answered in this session: **build both together** — no page exists
   yet for comments to render on, so it has to come along.

2. **Author badge next to a comment**: old UI showed "(admin)". Options
   discussed: no badge; circle_admin/admin/superadmin badge; or also
   surface internal_role (e.g. "organisation staff") when present. **Not
   yet answered.**

3. **Flagging as offensive** — should it notify moderators by email
   (reusing the EmailServiceHandler pattern used everywhere else in this
   app), or just sit in a queue for admins/circle_admins to check
   manually? **Not yet answered.**

## Sequencing


## 1. Yes — give Claude Code the old files as raw material, but isolate them clearly

Since these come from a completely separate project, Claude Code (running in *this* repo) has no access to them unless you physically place them in this project's filesystem first. I'd do it like this:

- Create a clearly-labeled, throwaway folder at the repo root — something like `_reference/legacy-comments/` (leading underscore keeps it visually distinct from real app code, sorts to the top of a file tree).
- Inside it, mirror the old structure loosely: `models/CommentSet.php`, `models/CommentThread.php`, `models/CommentThreadMessage.php`, `models/CommentLike.php`, and whatever Livewire component(s) + Blade views rendered the screenshot you showed.
- Tell Claude Code explicitly, in the prompt itself: *this is read-only reference material from a different Livewire 3 + Alpine.js project, for understanding intent only — do not copy Livewire 3 syntax verbatim (dispatch/emit, lifecycle hooks, and Alpine `x-data` patterns all differ in Livewire 4), do not import from or leave dependencies pointing into this folder, and delete `_reference/legacy-comments/` once the port is complete.*

That last instruction matters — without it, there's a real risk the folder just lingers as dead, confusing weight in the repo, or worse, Claude Code half-references it at runtime instead of fully porting the logic into proper `app/` locations.

## 2. Keep one system; let vocabulary flex per context, not the schema

Don't rename anything at the model/table/column level per context — that would recreate exactly the duplication we just spent this whole thread collapsing out of the old schema. The underlying `Comment` model and `comments` table should stay generic and singular, used identically whether the parent is a `ForumDiscussion` or a `CourseDocument`.

Where "posts" vs "comments" *can* legitimately differ is purely presentation:

- **Blade copy / button labels** — "Add a post" vs "Add a comment," "3 posts in this discussion" vs "3 comments" — just different strings passed into the same shared Livewire component (a `noun="post"` prop, say), not different logic.
- **Relation naming, if you want the code to read naturally** — nothing stops `ForumDiscussion` from exposing a thin alias:
  ```php
  public function posts(): MorphMany
  {
      return $this->comments(); // same relation, friendlier name in forum context
  }
  ```
  `$discussion->posts` and `$discussion->comments` return the exact same rows — the alias is just for whoever's reading `ForumDiscussion`'s code later and finds "posts" more natural than "comments" in that context. Purely cosmetic, zero duplication risk.

I'd add both of these as explicit instructions in the eventual build prompt, so Claude Code doesn't quietly reach for the more literal (and wrong) interpretation of "call it Post in forums" — which would be forking the model.
