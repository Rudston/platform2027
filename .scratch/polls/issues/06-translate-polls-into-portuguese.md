# Polls has no Portuguese translation

Status: ready-for-human
Progress: placeholder in place; needs a Portuguese speaker
Type: task

## Why

`lang/en/polls.php` holds ~119 keys and there is no Portuguese counterpart, so
a pt_BR user sees the entire Polls UI in English — tab, group cards, the
compose modal, every respond form, the result panel and its explanatory notes.

Not blocking: the fallback chain resolves pt_BR → pt → en, so nothing breaks.
It is simply untranslated.

## Where the file goes

**`lang/pt/polls.php`**, not `lang/pt_BR/polls.php`.

`lang/pt/` is the shared Portuguese base and `lang/pt_BR/` holds Brazilian
overrides ONLY (CLAUDE.md, Internationalisation). The chain is real, not
aspirational — it is implemented in `AppServiceProvider::boot()` via
`Lang::determineLocalesUsing()`, which inserts each region locale's base
language before the fallback. Add a `lang/pt_BR/polls.php` only for strings
where Brazilian Portuguese genuinely differs from the shared base.

## Terminology constraints for whoever translates

Settled in CONTEXT.md and issue 03; do not re-decide them in translation:

- The service label is **Votações**, and *Sondagem* is RESERVED for the future
  Surveys service. Do not use *sondagem* anywhere in `polls.php`.
- A poll answerer is a **Respondent** — a distinct word from the forum
  "Participant" in English. Portuguese should keep them distinct too.
- The instruction above the options is a **Prompt**, not a "question".
- Never render `hide_voter_identities` as "anónimo"/"anônimo". Identity is
  stored; it is not a secret ballot. The English copy says "Hide who chose
  what" for exactly this reason.
- The instant-runoff result note explains that totals are FIRST PREFERENCES and
  the winner may not be the largest number. That explanation carries the
  meaning — a literal translation that loses it is worse than none.

## Done when

`lang/pt/polls.php` exists and a Portuguese speaker has read it against the
constraints above.

## Progress

`lang/pt/polls.php` now exists as a placeholder — a faithful copy of the English
file, 124 keys, marked `// TODO: translate to Portuguese` in the manner of the
other seven `pt` files. The terminology constraints above are repeated in its
header so a translator meets them in the file they are editing rather than
having to find this ticket.

**No `lang/pt_BR/polls.php`, deliberately.** That layer holds Brazilian
OVERRIDES only — every key currently in it is genuinely translated (13 of
communities' 63, 4 of explore's 24). A placeholder there would be English
strings that permanently outrank the `pt` layer, so a later Portuguese
translation of `pt/polls.php` would silently never appear. Add a `pt_BR` file
only for strings where Brazilian genuinely differs from the shared base.

## Still to do

Translate the 124 values in `lang/pt/polls.php`. Nothing is blocked meanwhile:
the chain resolves pt_BR → pt → en and the placeholder holds English, so the UI
works exactly as before.

## Known caveat, inherited not introduced

A placeholder copy can serve STALE English: if `en/polls.php` wording is later
revised, `pt/polls.php` keeps the old wording and wins for pt_BR users. This is
already true of the seven existing `pt` files and is a property of the
convention, not of Polls.
