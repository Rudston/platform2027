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
A description people use, never a stored attribute: the platform does not
record that a Poll is an Election and cannot count them. A Circle may name a
Poll Group "Elections", but that is one Circle's convention, not platform
data.

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
own response. It never conceals *that* a user responded — see Roster.
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

**Roster**:
The list of Electorate members who have responded to a Poll. Visible even when
Attribution is withheld: who took part is not a secret, only what they chose.
Its length is published as a live count while the Poll is Open; the names
themselves appear only once it Closes, so the Roster can never be read as a
list of who has yet to comply. It is what lets a member check a published
count — their own name is on it, and its length is the numerator of turnout.

**Tally**:
The act of applying a Poll's Tally Method to its responses. A verb; the thing
it produces is a Result.

**Result**:
A Poll's outcome, computed while it is Open and frozen when it Closes: the
per-option totals, the turnout, and the winning option. Once frozen it is the
decision of record — later recomputation is a way of checking the Result,
never a way of replacing it. Detail a recomputation reproduces exactly, such
as an instant-runoff elimination sequence, is not stored. A Cancelled Poll has
no Result. A Result may be published beyond the owning Circle once the Poll
Closes — a Poll itself is never visible from outside while it runs.

**Rating Scale**:
A named, ordered set of labelled points — "Strongly Disagree".."Strongly
Agree" — used to score each option in a rating Poll. Platform vocabulary, not
a Circle's: curated centrally and shared by every Circle, so that the same
label means the same thing in two Circles' results.
_Avoid_: letting a Circle mint its own; a scale that only one Poll uses makes
its results incomparable with everyone else's.

**Poll Group**:
A named set of related Polls belonging to one Circle, with its own description
— "2027 Budget Consultation". Something a user navigates *into*, not merely a
heading they scan past. Every Poll belongs to exactly one Group — there are
no loose Polls.
A Group is organisational only: it has no visibility or status of its own and
never gates the Polls inside it. Who may respond, and who may see a Result,
are answered by the Poll alone. A Group is never deleted, only archived, and
archiving it leaves its Polls listed and findable — a Concluded Poll is a
record of a community decision and cannot be lost by tidying up. There is no
default or "General" Group: a Group is named when the first Poll that needs it
is written.

**Tag**:
A Theme applied to a Poll to say what it is *about*. Drawn from the same
curated vocabulary that tags Circles and forum groups, so "Water" means the
same thing platform-wide; a Poll may carry several.
_The division_: a Tag says what a Poll concerns and travels across Circles; a
Poll Group says which local effort it belongs to and never leaves its Circle.
Reach for a Tag first — a Group earns its place only when the set itself needs
a name and a page.
