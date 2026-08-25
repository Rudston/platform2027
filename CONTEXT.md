# Platform 2027

A civic platform where every community — geographic, thematic, organisational,
educational — is a **Circle**. This glossary fixes the vocabulary those
communities and their services are described in.

It is a glossary and nothing else: no schema, no implementation decisions.
Project rules and architecture live in `CLAUDE.md`; decisions live in
`docs/adr/`.

## Polls

**Poll**:
The genus term for any structured collective decision run inside a Circle —
an election, a proposition, or a rating exercise are all Polls.
_Avoid_: Vote, Ballot, Survey (as umbrella terms); Instance (a Poll is the
thing itself, not an instance of anything users can name).

**Election**:
A Poll whose options are candidates and whose outcome selects a person.
A description of a Poll's shape, not a separate kind of thing.

**Proposition**:
A Poll whose options are courses of action rather than people.
A description of a Poll's shape, not a separate kind of thing.

**Survey**:
A future, separate service: many questions per instance, free-text answers,
no forced tally. Explicitly NOT a Poll — do not use the two interchangeably.

**Eligibility**:
Who may respond to a Poll. The *same underlying concept* as forum
participation — a membership test against the Circle — but surfaced through
its own enum so the two services can diverge later.
_Avoid_: treating Eligibility and Participation as different rules; today they
resolve identically.

**Attribution**:
Whether a Poll's results show which user chose which option. Withholding
attribution is a *display* rule — the platform always records who responded —
and it is withheld from EVERYONE: ordinary members, the organiser who created
the Poll, platform admins and superadmins alike. No role reveals another
user's choice in the interface. The sole exception is a user viewing their
own response.
_Avoid_: "Anonymous" — it promises a secrecy the platform does not provide.

**Secret Ballot**:
A Poll in which a response cannot be linked to the user who cast it, even with
database access. NOT BUILT — no Poll on this platform is secret in this sense.
Never describe an unattributed Poll as secret.

**Respondent**:
A user who has submitted a response to a Poll.
_Avoid_: Participant — in this codebase that already means something else
(a forum contributor, derived from having posted). Voter is acceptable
informally for election-shaped Polls only.

**Response Shape**:
What a Respondent physically does when answering a Poll: pick one option,
rank several, or score several. Constrains which Tally Methods are legal.
_Avoid_: conflating with Tally Method — one describes the ballot, the other
the arithmetic.

**Tally Method**:
A pure computation over the responses a Poll already holds, producing its
Result — plurality, instant-runoff, average score. Several Tally Methods may
be legal for one Response Shape; the Shape decides which.
_Not_ a Tally Method: anything requiring a further round of voting. Majority
runoff spawns a second Poll rather than computing over the first, which is why
it is deferred rather than merely unimplemented.

**Published**:
The organiser has finished composing a Poll and released it. Says nothing
about whether responses are currently being accepted.

**Open**:
A Poll that is Published *and* whose current moment falls inside its response
window. Always derived from the clock, never stored — a Poll cannot be
recorded as Open while refusing responses.

**Concluded**:
A Poll an organiser ended before its scheduled close. It ran, it has a Result,
and the decision stands.

**Cancelled**:
A voided Poll. Its responses must never be tallied and it yields no Result —
distinct from a Poll that merely stopped early.
_Avoid_: Aborted; Deactivated (which means *switched off* elsewhere in this
codebase, not *finished*).

**Archived**:
A Poll filed away and hidden from ordinary listings. Independent of how the
Poll ended — archiving never overwrites the fact that it was Concluded or
Cancelled.

**Closed**:
A Poll that is not accepting responses — because its window has passed, or
because it was Concluded or Cancelled. Like Open, always derived; a Poll that
merely ran to schedule records nothing about having finished.

**Organiser**:
The user who created a Poll. One person, recorded once; a Poll has no second
"accountable" party distinct from its creator. The Organiser may Conclude or
Cancel their own Poll *while they remain a member of the owning Circle*, as may
any circle admin of that Circle unconditionally. Leaving the Circle ends an
Organiser's authority over a Poll without unmaking them its Organiser.

**Prompt**:
The sentence a Respondent reads above a Poll's options — e.g. "Select ONE
from:". A Poll has a title, a description, and a Prompt.
_Avoid_: Question. Options are grouped internally under a question record, but
that grouping is structural: users never meet the word, and a Poll never has
more than one.

**Electorate**:
The set of users entitled to respond to a Poll, drawn from Circle membership
as it stood on the Qualifying Date and fixed when the Poll is published. It is
the denominator of turnout, and it never shrinks: a member who later leaves
stays in the Electorate and keeps any response already given.

**Qualifying Date**:
The cut-off that decides a Poll's Electorate. Defaults to the moment of
publication and may be set earlier — so that joining a Circle after a Poll is
announced confers no vote — but never later. Casting a response requires being
in the Electorate *and* still being a member at that moment; both are tested
when the response is given, never when the Poll is tallied.
