# Every Poll belongs to exactly one Poll Group

`polls.poll_group_id` is NOT NULL. A Circle cannot hold a loose Poll: the
creation form names or picks a Group inline, and there is no default
"General" Group to fall into. The intent is that a Circle's Polls stay
organised by the person who knows what they are for, at the moment they are
written, rather than accumulating in an undifferentiated list.

A reader will notice there is no nullable escape and assume it was an
oversight, because the obvious design is an optional grouping. It wasn't: the
alternative was considered and rejected.

## Considered options

- **Optional membership, at most one Group.** Rejected: ungrouped becomes the
  default path, and grouping then only ever happens retroactively, which is to
  say almost never.
- **Auto-create a "General" Group per Circle.** Rejected twice over. It writes
  a row into every Circle on the platform — some 14,000 MainPlace circles —
  for a service most will never use; and a catch-all bucket defeats the point
  of requiring a Group at all.
- **Many Groups per Poll.** Rejected: that is a tag, and Polls already have
  tags through the existing `HasTags` trait.

## Consequences

Group lifecycle is coupled to its contents, so a Group is **archived, never
deleted** (`archived_at`; the FK is `restrictOnDelete`). Archiving leaves its
Polls listed and findable. Cascade deletion is specifically excluded: a
Concluded Poll carries a frozen Result and is a record of a community
decision, which must not be destroyable by tidying up a shelf.

A Poll Group is organisational only — no visibility, no status — so it never
gates the Polls inside it. Access is answered by the Poll alone. This keeps
the view axis off Groups deliberately: giving them visibility would reintroduce
the two-gate matrix (group visibility × poll eligibility) that was declined
when results-only publishing was chosen.

Poll Groups and tags coexist and must not collapse into one another. A tag
says what a Poll is about and is comparable across Circles; a Group says which
local effort it belongs to and never leaves its Circle.
