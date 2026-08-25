# Document the Polls service in CLAUDE.md

Status: ready-for-agent
Type: task

## Why

`CLAUDE.md` is the authoritative reference for AI-assisted work on this
codebase, and it documents every other service in depth — Forums, Comment
Moderation, Circle Membership, Stewardship, Tagging. It says nothing about
Polls, which is now one of the larger subsystems in the app.

Until it does, the next session will re-derive decisions that are already
settled, or contradict them. This is the highest-value item on the list for
that reason: everything else is a feature, this is what stops the features
being unpicked.

## What it needs

A section in the same register as the Forums one, covering:

- The vocabulary, pointing at `CONTEXT.md` as authoritative: Poll (the genus),
  Respondent (never "Participant" — forums own that), Prompt (never
  "Question"), Response Shape vs Tally Method, Electorate, Qualifying Date,
  Roster, Result, Organiser, Poll Group, Attribution.
- The three ADRs and, crucially, WHY each looks like a mistake without them:
  no `closed` status (0001), a materialised electorate beside an append-only
  membership log (0002), a NOT NULL group FK with no default group (0003).
- Tables and where the code lives: `App\Enums\Polls`, `App\Models\Polls`,
  `App\Support\Polls` (the pure tally), `VotingService` as the single write
  entry point, `App\Livewire\Communities\Services\Polls`.
- The service key stays `voting` while the label is "Polls".
- The three test seams and the entries worth adding to "Common Mistakes":
  never branch on status to decide whether responses are accepted; never
  derive the electorate; never call `roster()` without `rosterIsVisible()`;
  `Mark::value` is a score while `Mark::ratingScalePointId` is what is stored.

## Done when

A reader who has never seen this session can work on Polls from CLAUDE.md
alone without contradicting CONTEXT.md or the ADRs.
