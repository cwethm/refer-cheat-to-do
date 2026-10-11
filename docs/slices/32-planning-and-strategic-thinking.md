# Slice 32 — Planning and strategic thinking: decision log

## Slice Contract

### Status
PROPOSED — Phase F (`docs/intended-use.md`). The owner has confirmed the decision-log shape below: a
decision as a kind of record with a status, a revisit date, an expected outcome and an outcome note.

### Goal
Join what is known to what is planned. Let the owner record decisions together with the reasoning and
the perspectives that informed them and a date on which to revisit them, and let a Project or an
issue show the cards, records, needs and open ToDos that bear on it.

### What a decision log is for
A decision is a choice the owner has made that should not be re-argued from scratch and may need
revisiting: a recommendation, a position, a strategy, a way of proceeding. The log keeps the choice,
why it was made, what was weighed, what was expected, what then happened, and when to look again.

Benefits for the intended use:

1. **Settled questions stay settled.** The owner does not re-litigate a question because they cannot
   remember how it was resolved.
2. **The reasoning survives.** Later, the question is not only what was decided but why.
3. **Recommendations are grounded.** A decision links to the needs, records, cards and people that
   informed it, so a recommendation can cite the perspectives behind it.
4. **Decisions do not fossilise.** Circumstances change; a revisit date forces the question "is this
   still right?" at a chosen time.
5. **Judgement improves.** Comparing the expected outcome with what actually happened, over time,
   shows where the owner's reasoning is reliable and where it is not.
6. **Thinking connects to action.** A decision links to the ToDos and Projects that carry it out,
   and the Planning view shows both.

It is the same discipline as `docs/adr/` for engineering decisions, applied to the owner's own
strategy. The cost is capture effort, so a decision can be recorded with only a title and the other
fields filled in as time allows.

### In scope
- A third record `kind`, `decision`, added to Slice 27 records by a deliberate migration (the `CHECK`
  vocabulary is closed). A decision uses the existing `title` (the choice or question),
  `occurred_at` (when it was made), `summary` (what was decided, one line), `details` (the reasoning)
  and `participants`, and gains these fields, which are valid only when `kind = 'decision'` and are
  `NULL` otherwise (a database `CHECK`):
  - `decision_status`: `active`, `superseded` or `withdrawn`, default `active`;
  - `revisit_on` (date): when to look again; allowed only while `active`;
  - `expected_outcome` (≤2,000 characters): what the owner expects to happen or what would show it is
    working;
  - `outcome_note` (≤2,000 characters): what actually happened, recorded at a revisit.
- What informed a decision, and what carries it out, are Slice 28 links from the decision (to needs,
  records, cards, people, issues, ToDos and Projects). Link labels are free text, so the Planning
  view lists every link of a decision and does not depend on particular label values.
- Explicit revisit actions, each recorded in activity and none automatic:
  - **Uphold**: record an `outcome_note` and either set a new `revisit_on` or clear it;
  - **Replace**: create a new decision linked to the old with the label `supersedes`; the old one
    becomes `superseded`, in the same explicit action;
  - **Withdraw**: set `withdrawn` without a replacement.
- A **decisions due for revisit** list: `active` decisions whose `revisit_on` is today or earlier,
  with the reason shown, ordered by how overdue, paginated.
- A **Planning view** for a Project and for an issue hub: its terminal objectives and results
  (Slice 8), open ToDos, linked records and decisions, needs with their factors (Slice 31) and
  linked cards, as a read-only composition of what the owner already has, each group bounded and
  paginated.
- A **Plan** client entry point reachable from the Project and Issue detail views, and the due
  decisions list on the Today view as a count that links to it.
- Account export coverage for the new fields; activity entries use the existing record calls.

### Out of scope
- Goals, OKRs, milestones, Gantt charts, timelines, or any new planning entity.
- A per-revisit history table. `outcome_note` holds the latest outcome and a new revisit overwrites
  it; if the owner wants each revisit kept, it is recorded as a discussion record linked to the
  decision. A dedicated revisit history is a separate contract if it is wanted.
- Automatic ToDo creation, automatic decisions, or any AI summary or recommendation.
- Reminders or notifications.
- Changes to Project, terminal objective, review or capability behaviour. The Planning view only reads.

### Dependencies
Slices 27 (records), 28 (links), 30 (issues), 31 (needs), 8 (objectives), 7 (review precedent),
13 and 20 (dashboard conventions and bounded queries), 12 (activity).

### Required decision
Whether a decision is a **kind of record** or a **separate table**. The leaning is a kind: it shares
fields, the timeline, tags, links and export with discussions and events, and the new fields are
nullable and tied to `kind = 'decision'` by a `CHECK`. The ADR should record that choice, and that
"decisions due for revisit" is a deterministic rule on one date, not a score. The ADR must also state
whether `decision_status` is enforced as a small state machine (`active` to either other state, no
way back except an explicit reactivate action) in the service and in the database.

### Required threat and authorization review
- The Planning view is a read-only aggregation. It must be authorised by the same checks as the
  underlying lists, never by a looser path, and must not become a second read path: for every group
  it may return only items the requester owns and could already list.
- It is bounded. Every group is paginated, the number of queries does not grow with the number of
  linked items, and traversal is one hop, not recursive.
- A due-decisions count on the Today view must use one aggregate query, consistent with the dashboard
  query budget from Slice 20, and must not hydrate records.
- Decision text is plain text, never logged, inserted with `textContent`, and exported. A decision is
  a sensitive type for bundles and offline copies (Slices 34 and 35), as records are.

### Test plan
- Migration apply, rollback and reapply, including tightening the `kind` vocabulary on rollback
  safely (decisions are removed or the rollback is blocked, and the contract states which).
- PHPUnit: the decision-only fields accepted only for decisions; `revisit_on` accepted only while
  `active`; due and not-due boundaries including today; the Uphold, Replace and Withdraw actions and
  their activity entries; ordering; Planning view contains exactly the owner's linked items and no
  other user's; the denied-path matrix for every group; query count independent of linked-item
  count; dashboard count equals the list; export.
- A regression test that the Planning view mutates nothing and grants no authority.
- Browser tests for recording a decision with a revisit date and linked considerations, finding it
  when due, upholding and replacing it, and opening the Planning view for a Project and for an
  issue, with empty and paginated groups; accessibility and overflow checks;
  `tests/js/client-security.test.mjs` passing.

### Risks
- "Planning" is the easiest label under which to add unrelated features. Anything not listed in
  scope needs its own contract.
- A view that assembles many kinds of content is exactly where an authorisation shortcut hides. The
  per-group denied-path tests are the control.
- A log nobody revisits becomes clutter. The due list is bounded and its date is the owner's, but
  the habit of looking at it is not something the application can supply.
