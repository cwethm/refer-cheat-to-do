# Slice 32 — Planning and strategic thinking

## Slice Contract

### Status
PROPOSED — seventh and last slice of Phase F (`docs/intended-use.md`)

### Goal
Join what is known to what is planned: let a Project or an issue show the cards, records, needs and
open ToDos that bear on it, and let the owner record decisions and strategic reasoning with a date on
which to revisit them.

### In scope
- A third record `kind`, `decision`, added to Slice 27 records by a deliberate migration (the `CHECK`
  vocabulary is closed). A decision uses the existing `title`, `occurred_at` (when it was made),
  `summary` (what was decided) and `details` (the reasoning), and gains an optional `revisit_on`
  date, valid only for decisions.
- A **decisions due for revisit** list: decisions whose `revisit_on` is today or earlier, with the
  reason shown, ordered by how overdue, paginated.
- A **Planning view** for a Project and for an issue hub: its terminal objectives and results
  (Slice 8), open ToDos, linked records and decisions, open needs and linked cards, as a read-only
  composition of what the owner already has, each group bounded and paginated.
- A **Plan** client entry point reachable from the Project and Issue detail views, and the due
  decisions list on the Today view as a count that links to it.
- Account export coverage for the new field; activity entries use the existing record calls.

### Out of scope
- Goals, OKRs, milestones, Gantt charts, timelines, or any new planning entity.
- Automatic ToDo creation, automatic decisions, or any AI summary or recommendation.
- Reminders or notifications.
- Changes to Project, terminal objective, review or capability behaviour. The Planning view only reads.

### Dependencies
Slices 27 (records), 28 (links), 30 (issues), 31 (needs), 8 (objectives), 7 (review precedent),
13 and 20 (dashboard conventions and bounded queries), 12 (activity).

### Required decision
Whether a decision is a **kind of record** or a **separate table**. The leaning is a kind: it shares
fields, the timeline, tags, links and export with discussions and events, and the single new field
is a nullable date with a `CHECK` tying it to `kind = 'decision'`. The ADR should record that
choice, and that "decisions due for revisit" is a deterministic rule on one date, not a score.

### Required threat and authorization review
- The Planning view is a read-only aggregation. It must be authorised by the same checks as the
  underlying lists, never by a looser path, and must not become a second read path: for every group
  it may return only items the requester owns and could already list.
- It is bounded. Every group is paginated, the number of queries does not grow with the number of
  linked items, and traversal is one hop, not recursive.
- A due-decisions count on the Today view must use one aggregate query, consistent with the dashboard
  query budget from Slice 20, and must not hydrate records.
- Decision text is plain text, never logged, inserted with `textContent`, and exported.

### Test plan
- Migration apply, rollback and reapply, including tightening the `kind` vocabulary on rollback
  safely (decisions are removed or the rollback is blocked, and the contract states which).
- PHPUnit: `revisit_on` accepted only for decisions; due and not-due boundaries including today;
  ordering; Planning view contains exactly the owner's linked items and no other user's; the
  denied-path matrix for every group; query count independent of linked-item count; dashboard count
  equals the list; export.
- A regression test that the Planning view mutates nothing and grants no authority.
- Browser tests for recording a decision with a revisit date, finding it when due, and opening the
  Planning view for a Project and for an issue, with empty and paginated groups; accessibility and
  overflow checks; `tests/js/client-security.test.mjs` passing.

### Risks
- "Planning" is the easiest label under which to add unrelated features. Anything not listed in
  scope needs its own contract.
- A view that assembles many kinds of content is exactly where an authorisation shortcut hides. The
  per-group denied-path tests are the control.
