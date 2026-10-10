# Intended Use

## Status

**Product direction — governs post-MVP slices 26 onward.** Slices 1–25 were planned around a ToDo
and information-continuity system. This document records how the owner actually intends to use the
application day to day, and is the basis for the revisions in `docs/roadmap.md`.

It was written from the owner's own description of the use. Where that description was a fragment
or ambiguous, the reading taken is listed under [Assumptions](#assumptions-and-open-questions) so it
can be corrected rather than silently built.

## What the application is for

The owner takes part in conversations, events and community work on social issues and
community solidarity. The application is the place to keep what that work depends on, so that
the owner does not have to hold it all in memory. It is used in four ways.

### 1. Look up: reference cards and cheat sheets

Short reference material that is consulted rather than completed, so that the owner is **not
straining to recall**:

- **Concepts** — what a term or idea means, in a form that can be read at a glance.
- **Topic-specific dialogue patterns** — how a particular topic tends to come up in conversation,
  and the wording or approach that works for it.
- **Cheat sheets** — a selection of cards on one topic, in a useful order, for a situation such as
  an upcoming conversation or meeting.

The moment of use is often just before or during a conversation, on a phone. Retrieval speed and
legibility matter more than structure.

### 2. Remember: notes and records

- Records of **discussions** and **events**: when, who was involved, what was said, what was
  decided and what was learned.
- **Details from other discussions** that turn out to matter somewhere else, so they can be found
  from the place where they are needed and not only from where they were first recorded.

### 3. Plan: planning and strategic thinking

Cards, records and the open ToDos feed planning and strategy. The owner needs to see, for a piece
of work or an issue, what is known about it, what has been decided, what is still open, and when
the thinking should be revisited.

### 4. Organise: material, knowledge and community needs

Keeping notes, source material, knowledge and event details organised around the **social issues**
the owner is engaged with, and tracking **community solidarity needs**: what is needed, by whom or
for whom, what is being done, and what has been forgotten.

## A few concrete scenarios

These are illustrative, derived from the description above, and exist to test slice contracts
against real use.

- **Before a meeting.** Open the cheat sheet for the topic. Skim the concept cards, read the two
  dialogue patterns, check the records of the last two discussions on it.
- **In a conversation.** Type three letters of a term, read the card's summary line, put the phone
  away.
- **After an event.** Record who was there and what was learned. Note a need that was raised. Create
  the follow-up ToDos. Link the record to the cards and issue it concerns.
- **Weekly planning.** Open an issue. See its decisions, open needs and open ToDos together. Notice
  that a need has not moved in three weeks. Record a decision, with a date to revisit.

## How well the current system fits

| Need | What exists (Slices 1–22) | Gap |
| --- | --- | --- |
| Capture and act on tasks | ToDos, Inbox, tags, search, Projects, review queue, objectives | None for this use |
| Group knowledge into contexts | Notebooks, Libraries, ResearchCollections | Contents of a Notebook are ToDos; there is no reference-material record |
| Reference cards and cheat sheets | Only a ToDo's free-text `notes` | No record type that is consulted rather than completed; no quick lookup; no cheat sheets |
| Discussion and event records | Free-text ToDo notes | No dated, kind-aware record, timeline, or participants |
| Details from other discussions | ToDo-to-ToDo relationships; ResearchCollection item links | No way to link a card, record or issue to another |
| Social issues | Tags, Libraries (membership of Projects and Notebooks only) | No issue hub that gathers cards, records, needs and ToDos |
| Community solidarity needs | Cross-context requests (Project/Notebook, capability-gated) | No private record of a need and what is being done about it |
| Planning and strategy | Projects, terminal objectives, review scheduling | No decision records, no view joining knowledge to plans |
| Not forgetting what was learned | ToDo review queue only | Nothing resurfaces cards for recall |
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
- **Low-friction capture.** A record or card can be created with only a title; structure is optional.
- **Link, do not copy.** Details are connected where they are needed, not duplicated.
- **Private by default.** Everything new belongs to one owner and is shared with no one.
- **Explicit, not automatic.** Nothing creates ToDos, links or decisions on the owner's behalf
  (`development-slice-protocol.md` §37).
- **Portable.** Every new record type is part of the account export (Slice 21).

## Sensitive information

Records and needs can describe other people, sometimes in difficult circumstances, and the community
work itself may be sensitive. The existing posture (private, owner-scoped, redacted logs, no outbound
requests) is the right baseline. For the slices in this direction it becomes explicit:

- **Minimise personal data.** Participants and people in a need are free text. There are no contact
  fields, no linking to user accounts, and no requirement to use real names; the interface should
  encourage roles, initials or aliases.
- **No sharing in Phase F.** No new record type receives a capability resource type, and none can be
  made public. Sharing cheat sheets with others is a separate decision (see the open questions) and
  needs its own ADR, migration and denied-path tests under ADR 0002.
- **No agent access by default.** Slice 24 must not give agents access to records or needs unless the
  owner grants it explicitly per resource, and must treat their content as untrusted input.
- **The export is the largest disclosure risk.** It now contains this material. Denied-path tests
  outweigh happy-path tests, as in Slice 21.
- **Logs stay content-free.** New endpoints must not log titles, bodies, participants or search
  text (`SafeLogEvent` only).
- **Deletion is real.** Deleting a record, card or need removes it, behind the explicit confirmation
  pattern from Slice 17.
- **Operator responsibilities.** Disk encryption, database backups and their retention are
  deployment concerns, documented in the README, not features of the application.

## Standing requirements for every Phase F slice

A slice in `docs/roadmap.md` Phase F is not complete unless it also:

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

## Assumptions and open questions

### Assumptions

1. "so I'm straining to recall" was read as "so I'm **not** straining to recall".
2. The fragment "to the planning and strategic thinking" was read as: cards, records and ToDos should
   connect into planning and strategic thinking.
3. The owner is the main user. Collaboration stays on the existing capability model; Phase F is
   single-user.
4. Online access is acceptable. Offline use is out of scope for now; a printed cheat sheet
   (Slice 23) is the fallback.
5. Plain text is enough for cards and records. Formatting is deferred, and would need the same
   untrusted-content handling as Slice 22.

### Open questions

1. **Dialogue patterns.** Free text, or structured fields such as situation, aim, say, avoid and why?
   Phase F starts with a short summary line and free text.
2. **Sharing.** Should cheat sheets (not records or needs) eventually be shareable with named
   people in the community? Roadmap Slice 23 would then be where a published version is produced.
3. **People.** Are free-text participants enough, or is a "people" record wanted? It would be the
   most sensitive object in the system, so it is deliberately left out until asked for.
4. **Phone use.** Is slow or no signal during conversations a real constraint? If so, offline
   caching needs its own slice and threat review.
5. **Order.** Phase F is ordered by dependency, with recall practice (Slice 29) before issues and
   needs. If community needs are more urgent than recall, Slices 30 and 31 can move ahead of 29.
6. **Planning artefacts.** Is a decision log with revisit dates the right shape, or are goals,
   milestones or a regular strategic review wanted as well?
