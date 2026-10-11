# Intended Use

## Status

**Product direction — governs Phase F and all later phases.** Earlier slices were planned around a ToDo
and information-continuity system. This document records how the owner actually intends to use the
application day to day, and is the basis for the revisions in `docs/roadmap.md`.

It was written from the owner's own description of the use, then revised twice with the owner's
confirmations and answers (see [Decisions and open questions](#decisions-and-open-questions)). Where
something is still a recommendation and not a decision, it says so, so that it can be corrected
rather than silently built.

## What the application is for

The owner takes part in conversations, events and community work on social issues and
community solidarity. The application is the place to keep what that work depends on, so that
the owner is **not straining to recall** it. It is used in six ways.

### 1. Look up: reference cards and cheat sheets

Short reference material that is consulted rather than completed:

- **Concepts** — what a term or idea means, in a form that can be read at a glance.
- **Topic-specific dialogue patterns** — how a recurring moment in a conversation on a topic tends
  to go, and what helps (defined in [the next section](#dialogue-patterns-definition-place-and-function)).
- **Cheat sheets** — a selection of cards on one topic, in a useful order, for a situation such as
  an upcoming conversation or meeting.

The moment of use is often just before or during a conversation, on a phone. Retrieval speed and
legibility matter more than structure.

### 2. Remember: notes and records

- Records of **discussions** and **events**: when, who was involved, what was said, what was
  decided and what was learned.
- **People, groups and organisations** that matter to the work: why they matter, what is known about
  them, how they are connected to one another by *defined* relationships, what each could contribute
  (strengths, abilities, assets) and in what capacity (as part of a team, as a volunteer, or as a paid
  consultant or contractor), and everything they connect to (see Slice 33 and the
  sensitive-information rules below).
- **Details from other discussions** that turn out to matter somewhere else, so they can be found
  from the place where they are needed and not only from where they were first recorded.

### 3. Plan: planning and strategic thinking

Cards, records and ToDos connect into planning and strategy. The owner needs to see, for a piece of
work or an issue, what is known about it, what perspectives bear on it, what has been decided and
why, what is still open, and when the thinking should be revisited.

### 4. Organise: material, knowledge and community needs

Keeping notes, source material, knowledge and event details organised around the **social issues**
the owner is engaged with.

Community solidarity needs are recorded mainly to **broaden the perspective behind the owner's
recommendations and contributions**: more chances to form thoughts and ideas, with access to the
detailed issues and factors that should be considered. A need record is therefore first a piece of
*perspective and context* — what is needed, in what circumstances, what factors bear on it — and
second a follow-through item, so that something in progress is not forgotten.

### 5. Carry: bundles and offline access

Selecting **resource bundles** — named sets of items of any kind — for two purposes:

- **Local access** on the owner's phone, without a connection, for material that is either
  *frequently needed* or needed only *temporarily* (a particular meeting or discussion).
- **Packaging** a planning, meeting, discussion, ongoing or topic set so it can be taken
  elsewhere for evaluation or planning refinement.

A bundle is a deliberate selection made for a purpose. It is not a topic hub (an issue, Slice 30),
which is a standing home for everything about a subject.

Offline access is **read-only**. Recording something while offline is not planned, because it would
need an offline write queue and conflict handling.

### 6. Share: cheat sheets with named people

Cheat sheets are meant to be shareable, read-only, with named people in the community who have
accounts (Slice 36, which needs Slice 19 first). It is deliberately limited to cheat sheets:
records, people and organisations, needs and decisions are never shared.

## Dialogue patterns: definition, place and function

**Definition.** A dialogue pattern is a reusable, topic-specific way of handling a recurring moment
in a conversation. It records *when* a moment tends to come up, *what you are trying to achieve*,
*what tends to work*, *what tends to go wrong*, and *why*. It is knowledge about how to talk about
something, as distinct from knowledge about the thing itself.

*Illustration only — the content is the owner's to write.* Topic: mutual aid. Situation: someone
says "isn't this just charity?". Aim: keep the conversation open rather than win the point.
Approach: ask what they have seen work, then draw the distinction in one sentence. Avoid: opening
with theory. Rationale: people engage with a question they can answer from experience before a
framework.

**Place in the system.** Three kinds of knowledge sit side by side and are linked, not merged:

| Kind | Answers | Record type | Example |
| --- | --- | --- | --- |
| Concept | What is it? | Reference card (`concept`) | A definition of "mutual aid" |
| Dialogue pattern | How do I handle this moment? | Reference card (`dialogue_pattern`) | The pattern above |
| Record | What actually happened? | Discussion or event record | Notes from Thursday's meeting |

They form a loop: **records → patterns → conversations → records.** A discussion record shows what
worked and what did not; a lesson is distilled into a pattern (or an existing one is revised); the
pattern is used in the next conversation; that conversation is recorded. Patterns lean on the
concepts they use (linked, Slice 28), belong to the issues they concern (Slice 30), and can be
practised (Slice 29).

**Function.** Its job is to reduce what the owner must recall at the moment of use. It is read
before or during a conversation, assembled into a cheat sheet for a particular meeting, kept after
a conversation so that what was learned is not lost, and revised when the next record shows it was
wrong.

**Structure — confirmed by the owner.** There were two reasonable shapes:

1. *Summary line plus free text.* Cheapest and most flexible. A phone reader has to find their way
   through unstructured text, and a cheat sheet cannot show only the part that matters.
2. *A summary line plus optional named fields:* `situation`, `aim`, `approach`, `avoid`,
   `rationale`, each short and each optional, with free text still available for anything else.
   Only a title is ever required. The client shows the populated fields as labelled blocks, so a
   pattern is readable at a glance: when it comes up, what to do, what to avoid.

Decided: shape 2. It is the shape that best serves "not straining to recall", and it costs five
optional columns that are only valid for dialogue-pattern cards. It is specified that way in
`docs/slices/26-reference-cards-and-cheat-sheets.md`.

## A few concrete scenarios

These are illustrative, derived from the description above, and exist to test slice contracts
against real use.

- **Before a meeting.** Open the cheat sheet for the topic. Skim the concept cards, read the two
  dialogue patterns, check the records of the last two discussions on it.
- **In a conversation.** Type three letters of a term, read the card's summary line, put the phone
  away.
- **After an event.** Record who was there and what was learned. Link the people. Note a need that
  was raised and the factors around it. Create the follow-up ToDos. Link the record to the cards and
  issue it concerns. Revise the dialogue pattern that did not work.
- **Forming a recommendation.** Open the issue. Read the needs and factors recorded around it, with
  how recently each was confirmed. Record the decision, link what informed it, and set a date to
  revisit it.
- **Weekly planning.** Open the issue or Project. See its decisions, needs and open ToDos together.
  See the decisions due for revisit.
- **Weak signal ahead.** The night before a meeting, make that meeting's bundle available on the
  phone: the cheat sheet, the last two records and the relevant needs. It expires after the meeting
  and the phone copy is removed.
- **Refining a plan.** Package the quarter's planning bundle — decisions, needs, records — and take
  it somewhere to evaluate and refine it.

## How well the current system fits

| Need | What exists (Slices 1–22) | Gap |
| --- | --- | --- |
| Capture and act on tasks | ToDos, Inbox, tags, search, Projects, review queue, objectives | None for this use |
| Group knowledge into contexts | Notebooks, Libraries, ResearchCollections | Contents of a Notebook are ToDos; there is no reference-material record |
| Reference cards and cheat sheets | Only a ToDo's free-text `notes` | No record type that is consulted rather than completed; no quick lookup; no cheat sheets |
| Discussion and event records | Free-text ToDo notes | No dated, kind-aware record or timeline |
| People, groups and organisations | Nothing | No record of them, of how they relate to each other, or of what they can contribute, and no way to connect one to what it relates to |
| Details from other discussions | ToDo-to-ToDo relationships; ResearchCollection item links | No way to link a card, record, person or issue to another |
| Social issues | Tags, Libraries (membership of Projects and Notebooks only) | No issue hub that gathers cards, records, needs, people and ToDos |
| Community needs as perspective | Cross-context requests (Project/Notebook, capability-gated) | No record of a need, the factors around it, and how current that knowledge is |
| Planning and strategy | Projects, terminal objectives, review scheduling | No decision log, no view joining knowledge and perspectives to plans |
| Sharing reference material | Capability grants on Projects and Notebooks (Slices 10, 11, 18); accounts created only by the operator | No way to share a cheat sheet, and no way to create a recipient's account without a shell (Slice 19) |
| Not forgetting what was learned | ToDo review queue only | Nothing resurfaces cards for recall |
| Bundles | Nothing | No purpose-built selection of mixed items |
| Offline use on a phone | Nothing, and the client rules forbid it without an ADR | No offline store, and the standing client security rules must be deliberately amended (Slice 35) |
| Printing a cheat sheet | Planned: document generation (Slice 23) | Cards do not exist yet to print |

Reference material is **not** modelled as a ToDo. A ToDo has `inbox`/`active`/`done` states, feeds
the review queue and orphan detection, and is counted by the dashboard. A card that can be "done",
nags for review as an orphan, or inflates the Today counts would be wrong in each of those places.
The new record types are therefore separate, and reuse the existing tags, ownership, activity,
export and client scaffolding.

ResearchCollections (Slice 22) hold gathered *source* material. Cards and records hold the owner's own
distilled knowledge and account of events. They are related by links (Slice 28), not merged.

## Principles for this use

- **Retrieval first.** A card is found by typing; it is not necessary to remember where it was filed.
- **Low-friction capture.** A record, card, need or person can be created with only a title or name;
  structure is optional.
- **Link, do not copy.** Details are connected where they are needed, not duplicated.
- **Perspective, not only tasks.** Needs, people and records exist to inform thinking, so they carry
  detail and a sense of how current it is, and they are not forced into a to-do shape.
- **Private by default.** Everything new belongs to one owner and is shared with no one.
- **Explicit, not automatic.** Nothing creates ToDos, links, decisions or bundles on the owner's
  behalf, and nothing is deleted automatically (`development-slice-protocol.md` §37).
- **Explicit disclosure.** Anything that leaves the server for a device or a file is something the
  owner selected, with the most sensitive kinds excluded unless the owner says otherwise.
- **Portable.** Every new record type is part of the account export (Slice 21).

## Sensitive information

Records, people and needs can describe other people, sometimes in difficult circumstances, and the
community work itself may be sensitive. The existing posture (private, owner-scoped, redacted logs,
no outbound requests) is the right baseline. For the slices in this direction it becomes explicit:

- **People and organisations are the most sensitive object in the system.** A record for them is
  wanted (Slice 33), so it is designed with safeguards and not left out: a display name that can be
  an alias, initials or a role; no contact fields unless the owner asks for them; no link to a user
  account; owner-only; real deletion. The *set of connections* is itself sensitive even when each
  record is harmless, and so are the *relationships* (who works or volunteers with whom can reveal an
  affiliation) and the *contributions* (statements about what a third party can do), so the export and
  every bundle treat all of it that way.
- **Minimise personal data.** Participants in a record and the people in a need are free text or
  party records, never contact details. The interface encourages roles, initials or aliases.
  Contributions record what a party offers, in terms of what they can do; never impairments,
  conditions or health, and never finances.
- **What not to record.** No code can enforce this, so it is guidance for the owner: avoid recording
  about an identifiable private individual their health, immigration or legal status, home address
  or precise location, political or religious affiliation, or anything that could put them at risk if
  the database were ever disclosed. Prefer a role or alias, and keep the detail in your head or on
  paper.
- **Sharing is limited to published cheat sheets (Slice 36).** The owner wants cheat sheets shareable
  with named people. This is the one person-to-person sharing exception to "private by default": it is
  read-only, for people with accounts, and requires an ADR, a migration and denied-path tests under
  ADR 0002. No other new record type receives a person-to-person capability resource type, and nothing
  can be made public. A publication contains only the selected cheat-sheet/card fields; Slice 10
  already permits cross-user Project and Notebook reads through grants, so hostile-content tests cover
  this new publication and card-field surface.
- **No agent access by default.** Slice 24 uses individual resource grants: its ADR and migration must
  represent an agent as a grant subject distinct from a user and add the needed resource kinds to ADR
  0002's closed set. An agent may read a record, need, decision or party only when a `read` grant names
  that exact resource. Neither a type-wide grant nor bundle membership authorizes those reads; content
  remains untrusted input.
- **Bundles are a disclosure unit.** A bundle exposes content to a device or file, so people and
  organisations, needs and records are excluded from an offline copy or package unless the owner
  explicitly allows it for that bundle (Slice 34).
- **An offline copy is the largest new exposure.** Once content is on a phone, the server cannot
  revoke it and the phone's own protection is what remains. Slice 35 therefore needs an ADR, a
  limited lifetime for every copy, and removal on logout and expiry.
- **The export is the largest server-side disclosure risk.** It now contains this material.
  Denied-path tests outweigh happy-path tests, as in Slice 21.
- **Logs stay content-free.** New endpoints must not log titles, bodies, names, participants or
  search text (`SafeLogEvent` only).
- **Deletion is real.** Deleting a record, card, party or need removes it, behind the explicit
  confirmation pattern from Slice 17. It does not rewrite free text elsewhere that mentions a
  deleted person or organisation; this is stated in the slice and shown in the interface.
- **Operator responsibilities.** Disk encryption, database backups and their retention are
  deployment concerns, documented in the README, not features of the application.

## Standing requirements for every Phase F slice

A slice in `docs/roadmap.md` Phase F is not complete unless it also (Slice 36 meets the same list,
with the single, reviewed exception of read-only reads by named grantees):

1. Is owner-scoped, with ownership set from the session and immutable; inaccessible and nonexistent
   ids both return `404`.
2. Adds its tables to the account export, with a denied-path test showing no other user's rows.
3. Records activity through the existing explicit post-mutation calls (ADR 0003: actor-only),
   extending the closed `activity_records_subject_type_chk` set by migration, as Slice 22 did.
4. States its delete behaviour (cascade or restrict) and its text and pagination limits
   (`page` default 1, `limit` default 20, maximum 100), stored and returned as plain text and
   inserted in the client with `textContent`.
5. Leaves the dashboard counts, review queue and orphan detection unchanged unless the slice says
   otherwise, with a regression test.
6. Ships its client view in the same slice, using the shared client record path where it fits, with
   the browser, accessibility and responsive checks from `docs/front-end-testing-policy.md`.
7. Runs the migration apply, rollback and reapply check, and `tests/js/client-security.test.mjs`.

## Decisions and open questions

### Confirmed interpretations of the original description

1. "so I'm straining to recall" means "so I'm **not** straining to recall".
2. "to the planning and strategic thinking" means cards, records and ToDos should connect into
   planning and strategic thinking.

### Assumptions still in force

1. The owner is the main user. Collaboration stays on the existing capability model. Phase F is
   single-user until Slice 36, which adds read-only sharing of published cheat sheets with named
   people and nothing else.
2. Plain text is enough for cards, records, people and organisations, needs and decisions.
   Formatting is deferred, and would need the same untrusted-content handling as Slice 22.
3. "Assets" in a contribution means a resource a party could put toward the work (a vehicle, a venue,
   equipment, a network), not their finances. Corrected below if that is wrong.

### Decisions recorded from the owner's answers

| # | Question | Outcome |
| --- | --- | --- |
| 1 | Structure of dialogue patterns | **Confirmed**: a summary plus optional `situation`, `aim`, `approach`, `avoid` and `rationale`. Slice 26 |
| 2 | Is a person record needed? | **Yes**, and **groups and organisations too**. Person-to-person (and person-to-organisation) connections use **defined relationships**. Each party records contributions (strengths, abilities, assets) by engagement: **team, volunteer, or paid consultant or contractor**. Slice 33 |
| 3 | Is offline access a real need? | **Yes**, as bundles for local access (frequent or temporary) and for packaging sets for evaluation or planning refinement. **Read-only is enough**; recording while offline is not planned. Slices 34 and 35 |
| 4 | Needs before recall practice? | Owner's stated aim is perspective and factors for recommendations, which favours needs first. **Applied**: Slices 30 and 31 precede 29. Reversible, because slice numbers do not encode order |
| 5 | Decision log with revisit dates | **Confirmed**: Slice 32 |
| 6 | Order of the tail | **Confirmed**: 33, 34, 35, then 29. Slice 19 and then Slice 36 follow (see 7); either can be pulled ahead of 29 without changing a dependency |
| 7 | Share cheat sheets with named people? | **Yes, definitely.** New Slice 36, which needs Slice 19 (accounts) and an ADR first. Only cheat sheets |
| 8 | Needs reframing (Slice 31) | Owner asked what it involves; explained below. **Confirmation pending** |

### Implications of the ordering choice (question 4)

Slice 29 (recall practice) needs Slice 26 for its core; its optional issue-scoped sessions also need
Slice 30. Slices 30 and 31 (issues and needs) need Slice 28's links as well. Whichever comes first,
the dependency chain 28 → 30 → 31 is unchanged.

**Recall practice first (29 before 30 and 31)**
- Delivers earlier: the lookup-and-retain use is complete soon after the cards exist, and the
  scheduling reuse is proven first, which makes the later explainable-reasons extraction cheaper.
- Costs: it only pays off once there are many cards, and the cards have nothing to attach to yet (no
  issue to scope practice to). The perspective use, which is the owner's stated priority for
  community needs, arrives later.
- Risk: practice may be built, used briefly, and found less valuable than fast lookup, which Slice 26
  already provides.

**Issues and needs first (30 and 31 before 29), as applied**
- Delivers earlier: the structured space for broader perspective, detailed issues and the factors to
  weigh (Slices 30 and 31), which is the main use the owner described for community needs. Practice
  is then built later with an anchor: a card set can be scoped to an issue or a cheat sheet.
- Costs: recall practice waits, and the first two consumers of the "explainable reasons" rules are
  the review queue and needs, so the shared extraction happens when practice arrives as the third.
  The sensitive-information rules (owner-only records, plain text, real deletion) apply to
  community material earlier, which is a reason to move carefully, not a reason to avoid it.
- Risk: needs without recall practice leave cards as lookup-only for longer.

The owner's aim is to enrich recommendations and contributions with broader perspective and detail,
which fits the second order, so it is the one applied. It is reversible: slice numbers do not encode
order, but moving Slice 29 ahead of Slice 30 requires deferring its optional issue-scoped sessions
until Slice 30 is available.

### What the needs reframing involves

The first draft of Slice 31 treated a need as a *follow-through item*: a title, a status, a due date,
and a "needs attention" list for needs with no movement. The reframing follows from the owner's stated
aim, which is to enrich recommendations and contributions with broader perspective and detail. A need is
now first a piece of *perspective and context*, and second a follow-through item:

- **Two new fields.** `factors` (the circumstances, constraints, trade-offs and other considerations
  around the need, who else is affected, what has been tried) and `last_confirmed_on` (when the owner
  last checked that what is recorded is still true).
- **A new main view, Perspectives.** The needs on an issue or tag with their descriptions, factors and
  how recently each was confirmed. Needs that are `met` or `closed` stay in it, because what happened
  is also perspective.
- **Changed attention rules.** A need that is out of date misleads the thinking it informs, so
  `not_confirmed_recently` is a rule. An `open` need no longer has to have a follow-up ToDo (the owner
  may record it purely as perspective); `no_follow_up` applies only to `in_progress` needs.
- **Unchanged.** Needs are private, are not ToDos, and never enter the ToDo review queue or the
  dashboard counts.

To confirm: that this emphasis is right, and that `factors` and `last_confirmed_on` are the right two
fields to add.

### Open questions

1. **"Evaluation": for what purpose?** The earlier question asked *who* evaluates a packaged bundle.
   The purpose decides the who, and it decides what has to be built:

   | Purpose | Who evaluates | What it needs | New exposure |
   | --- | --- | --- | --- |
   | Check readiness before a meeting, or review after an event | The owner, later | The bundle opened in the app or offline (Slices 34 and 35) | None |
   | Refine a plan: find gaps, test assumptions, compare expected with actual outcomes | The owner later, a trusted person, or an AI assistant | A rendered, downloadable or printable package (Slice 23), or agent access to the bundle (Slice 24) | A file leaves the system, or data goes to a model and its provider |
   | Get another person's view, or brief someone on a topic | Another person | For reference material, a published cheat sheet (Slice 36); for anything else, a new and narrower kind of sharing that does not exist | Another user sees the content |

   Which of these do you intend? **Applied meanwhile:** Slice 34 ships no packaging format, so nothing
   leaves the system except an offline copy you ask for (Slice 35). Documents (Slice 23) and agents
   (Slice 24) wait for this answer.
2. **Offline device.** Which phone and browser do you use, and is a screen lock in place? They constrain
   the at-rest design in Slice 35.
3. **Needs reframing.** Confirm the reframing above.
4. **People and organisations.** Confirm that "assets" means resources and not finances, and that no
   contact details are wanted. The starter list of relationship types (for example "works with",
   "volunteers with", "employs / works for", "contracts / contracted by", "introduced by") is
   illustrative and editable, so it can be corrected in use.
5. **Sharing.**
   - Will the people you share with be willing to have an account on this installation (an invitation
     under Slice 19), or would a printed or downloaded copy be enough for some of them?
   - Should a shared sheet be a **published copy** that you republish to update (the leaning: you
     review exactly what is shared, and a later edit reaches nobody by accident), or **live**, always
     showing your current cards (always current, but every edit, including a mistake, is visible at
     once)?
   - Dialogue-pattern fields (`avoid`, `rationale`) are the likeliest to mention other people. Should
     they be shareable, left out by default, or chosen per card?
   - Slice 19 and then Slice 36 currently follow recall practice (29). Slice 36 does not depend on 29,
     so it can move ahead of it. Do you want sharing sooner?
