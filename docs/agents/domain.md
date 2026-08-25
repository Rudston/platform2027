# Domain Docs

How the engineering skills should consume this repo's domain documentation when
exploring the codebase.

This is a **single-context** repo: one `CONTEXT.md` and one `docs/adr/` at the
root. There is no `CONTEXT-MAP.md` and no per-package context.

## Before exploring, read these

- **`CLAUDE.md`** at the repo root. Until a `CONTEXT.md` exists, this is the
  authoritative domain reference for Platform 2027 — it defines Circles, the
  geographic hierarchy, membership rules, forums, moderation, and the
  non-negotiable rules. Read it before touching any file.
- **`CONTEXT.md`** at the repo root, if it exists: the domain glossary.
- **`docs/adr/`**: read ADRs that touch the area you're about to work in.

If `CONTEXT.md` or `docs/adr/` don't exist, **proceed silently**. Don't flag
their absence; don't suggest creating them upfront. The `/domain-modeling`
skill (reached via `/grill-with-docs` and `/improve-codebase-architecture`)
creates them lazily when terms or decisions actually get resolved.

## File structure

```
/
├── CLAUDE.md          ← authoritative project rules + domain reference
├── CONTEXT.md         ← glossary (created lazily by /domain-modeling)
├── docs/
│   ├── adr/           ← architecture decision records (created lazily)
│   │   ├── 0001-....md
│   │   └── 0002-....md
│   └── agents/        ← this directory: skill configuration
└── app/
```

## Use the glossary's vocabulary

When your output names a domain concept (in an issue title, a refactor
proposal, a hypothesis, a test name), use the term as defined in `CONTEXT.md` —
or, absent that, the term as used in `CLAUDE.md`. Don't drift to synonyms the
project explicitly avoids.

Concrete examples of this project's vocabulary: a community is a **Circle**;
its geographic anchor is a **locatable**; only `LocationLevel::Place`
(MainPlace) is **terminal**; a forum discussion's replies are **comments**
(surfaced as "posts" in forum UI only — never fork the model).

If the concept you need isn't in the glossary yet, that's a signal: either
you're inventing language the project doesn't use (reconsider) or there's a
real gap (note it for `/domain-modeling`).

## Flag ADR conflicts

If your output contradicts an existing ADR — or one of the Non-Negotiable Rules
in `CLAUDE.md` — surface it explicitly rather than silently overriding:

> _Contradicts ADR-0007 (event-sourced orders), but worth reopening because…_
