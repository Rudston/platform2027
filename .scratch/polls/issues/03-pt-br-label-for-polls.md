# Portuguese label for the Polls service

Status: resolved
Type: task

## Why

`services.name` for the `voting` row currently reads
`{"en": "Polls", "pt_BR": "Votações"}`.

The English was renamed when Q1 settled that **Poll** is the genus covering
elections, propositions AND rating exercises. "Votações" means votings/ballots
— the narrower sense Q1 explicitly rejected — so the two locales now describe
different scopes. "Sondagens" is the usual Portuguese for polls in the broader
sense, but this is a language judgement, not a code one.

## What it needs

A decision on the pt_BR string, then one update. Note `services.name` is
TRANSLATABLE JSON (documented in CLAUDE.md as of 7ff84a1):

- Through the model, a plain-string assignment sets only the CURRENT locale.
- Through the query builder it would replace the whole JSON and destroy the
  other locales. Do not do that.

Setting a specific locale explicitly:
`$service->setTranslation('name', 'pt_BR', '…')->save();`

## Also worth checking while in there

Whether `lang/pt/` and `lang/pt_BR/` need a `polls.php` at all — the new
`lang/en/polls.php` has ~119 keys and no translation. The fallback chain
(pt_BR → pt → en) means the UI works untranslated, so this is not blocking.

## Answer

**Keep "Votações". No change made** — the stored value was already correct.

The reasoning, which reverses the "Sondagens" suggestion this ticket was
originally written around:

- *Sondagem* is the natural Portuguese for a survey / opinion poll, and that is
  precisely what the future **Surveys** service will want to be called. Taking
  it for Polls now would recreate in Portuguese the exact Poll/Survey collision
  that CONTEXT.md goes out of its way to prevent in English. Recorded under the
  **Survey** entry in CONTEXT.md so the word stays reserved.
- *Votação* is the established term in Brazilian participatory-democracy
  contexts (participatory budgeting uses it). It covers elections and
  propositions well and strains only slightly for rating exercises — an
  acceptable cost for keeping the two services distinguishable.
- *Consultas* was the runner-up: closer to Q1's genus meaning, but less
  immediately recognisable as "here is where you vote".

## Secondary question: does pt_BR need a polls.php?

Not blocking. `lang/en/polls.php` has ~119 keys and no translation, but the
fallback to English means the UI works untranslated. Translating it is a
separate, larger job.

Note while checking, after an initial misreading on my part: the
pt_BR → pt → en chain CLAUDE.md describes **is real**. Stock Laravel resolves
only `[locale, fallback]`, but `AppServiceProvider::boot()` calls
`Lang::determineLocalesUsing()` to insert each region locale's base language
before the fallback. Verified at runtime: a key present only in `lang/pt/`
resolves under locale `pt_BR`.

So `pt` being absent from `supported_locales` is correct — it is a base LAYER,
not a selectable locale — and `lang/pt/` is not dead weight. A Portuguese
translation of Polls therefore belongs in `lang/pt/polls.php`. See issue 06.
