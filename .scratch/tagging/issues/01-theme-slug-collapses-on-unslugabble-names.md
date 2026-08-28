# 01: Approving an unslugabble tag suggestion collapses onto the first one

**What to build:** Approving a theme suggestion whose name yields no slug must
not silently hand back somebody else's Theme.

`ThemeSuggestion::approve()` dedupes with

```php
Theme::firstOrCreate(['slug' => Str::slug($this->name)], ['name' => $this->name]);
```

`Str::slug` transliterates to ASCII and legitimately returns `''` for a name in
a non-Latin script (`中文名字`) or one made only of punctuation (`???`), and
`themes.slug` is UNIQUE. So:

1. User A suggests `中文名字`; an admin approves it. A Theme is created with
   `slug = ''`, name `中文名字`.
2. User B later suggests `???` on their own forum discussion. On approval
   `firstOrCreate` matches the existing row on `slug = ''` and returns **user
   A's Theme**.
3. B's entity is tagged with A's unrelated tag via `syncWithoutDetaching`, and B
   is emailed "your tag was approved".

Every punctuation-only or non-transliterable suggestion forever collapses onto
whichever one was approved first. There is no error and nothing in the UI hints
at it — the reviewer sees a successful approval.

The same empty-slug hazard on the Circles side is already solved: the rule lives
in `App\Services\Circles\Concerns\DerivesScopedSlugs`, where `slugFor()` may
return `''` and `requireSlugFor()` refuses to store it, with the friendly
rejection in each compose form (`.scratch/polls/issues/13`). Tagging does not go
through that concern, so it never got the fix.

Worth deciding while in there: where the rejection belongs. A tag suggestion is
typed by an ordinary user and approved by an admin much later, so refusing at
approval time punishes the wrong person at the wrong moment — the check probably
belongs on the SUGGESTION form, with approval keeping a guard for the rows
already in the table. Note also that `Theme` is matched by slug rather than by
name, so two genuinely different non-Latin tags cannot coexist at all until
that dedupe key is reconsidered.

**Found by:** `/code-review` while implementing `.scratch/polls/issues/13`.
Not fixed there — different subsystem, and the fix needs the decision above.

**Blocked by:** None.

**Status:** resolved

- [x] An unslugabble suggestion cannot return an unrelated Theme
- [x] Existing `themes` rows with an empty slug are dealt with (audit or backfill)
- [x] The rejection lands on whoever can act on it, decided explicitly

## Answer

### There is no rejection — and that is the decision

The ticket asked where the refusal belongs: the suggestion form, or approval.
Neither. That framing came from `.scratch/polls/issues/13`, where refusing was
right, and the two cases only look alike.

A forum or poll group's slug **IS the URL**. Nobody may be handed a generated
one they never chose, so an unslugabble name is refused in the compose form and
the person picks another — they are present, and they can fix it.

`themes.slug` appears in **no route** (verified: no reference in `routes/web.php`,
and the only reads are the model's own derivation). It is an internal dedupe
key that nobody sees or types. There is no one to refuse and nothing to explain,
and refusing would throw away a perfectly good tag: a name in a non-Latin script
is a real tag name, not a mistake. So `Theme::slugFor()` NEVER returns empty —
the exact opposite rule to `DerivesScopedSlugs::slugFor()`, deliberately, and
both docblocks now say why so neither gets "fixed" into the other.

The fallback is `t-` plus a hash of the lower-cased, trimmed name: DERIVED, so
the same tag suggested twice still dedupes, and case-insensitive, matching what
`Str::slug` already did for Latin names.

One home, two callers: `Theme::booted()` (which had its own bare `Str::slug`)
and `ThemeSuggestion::approve()`.

### A second bug, found while fixing the first

The ticket's closing note — "`Theme` is matched by slug rather than by name" —
turned out to be live, not theoretical. `themes.name` is UNIQUE as well as
`themes.slug`, so a theme whose slug was edited by hand can never be
re-suggested: `firstOrCreate` misses on slug, tries to insert a second row with
the same name, and dies on the name constraint. The admin gets a 500 out of
Approve and the suggestion can never be actioned.

This is reproducible in the dev database today: **Theme #12 "Social Justice"
carries the slug `justice-and-crime`.** Probed live (inside a rolled-back
transaction) before the fix: `UniqueConstraintViolationException`, SQLSTATE
23000. After: resolves to theme #12, override intact.

`approve()` now matches on EITHER unique key —
`where('name', …)->orWhere('slug', …)->first() ?? create(…)`. Slug keeps
`Housing`/`housing` as one tag; name catches the hand-edited case.

### Data

No backfill needed. 141 themes, **0 with an empty slug** — the collapse had not
been triggered yet, because the only suggestion ever raised
("Environmental Conservation") slugs normally. One theme's slug does not derive
from its name, and that is the deliberate override above, left alone.

### Tests

Four in `ThemeTaggingTest`, each verified red before the fix:
- two distinct unslugabble names produce two distinct Themes, and each origin
  gets its OWN tag rather than the other person's;
- the same unslugabble name still dedupes to one Theme (the fallback stays
  derived, never random);
- ordinary Latin names are untouched, `Housing`/`housing` included;
- a hand-edited slug is still found by name.

The file's hand-rolled `themes` table was also tightened to `unique()` on both
name and slug, matching the real schema — the collapse is only reproducible
against that, and the looser test table would have hidden it.

Full suite: 340 passed, 1200 assertions.