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

**Status:** needs-triage

- [ ] An unslugabble suggestion cannot return an unrelated Theme
- [ ] Existing `themes` rows with an empty slug are dealt with (audit or backfill)
- [ ] The rejection lands on whoever can act on it, decided explicitly
